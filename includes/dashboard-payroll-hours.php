<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$payrollNow = new DateTimeImmutable('now');
$payrollToday = new DateTimeImmutable('today');
$payrollUserId = (int) ($CMS_USER['id'] ?? 0);
$payrollRole = cms_db_fetch_one(
  'SELECT u.userrole, r.name AS role_name, r.level
   FROM cms_users u
   LEFT JOIN cms_userrole r ON r.id = u.userrole
   WHERE u.id = :id
   LIMIT 1',
  [':id' => $payrollUserId]
);
$payrollRoleName = strtolower((string) ($payrollRole['role_name'] ?? $CMS_USER['role'] ?? ''));
$payrollRoleLevel = (int) ($payrollRole['level'] ?? 0);
$payrollUserRoleId = (int) ($payrollRole['userrole'] ?? 0);
$payrollIsAdmin = $payrollRoleLevel >= 20 || in_array($payrollRoleName, ['admin', 'tech'], true);
$payrollAmendMinUserRole = 0;
$payrollAmendVerifiedMinRoleLevel = 20;
$payrollCanAmendHours = $payrollUserRoleId > $payrollAmendMinUserRole;
$payrollCanAmendVerifiedHours = $payrollRoleLevel >= $payrollAmendVerifiedMinRoleLevel || $payrollIsAdmin;

function payroll_hours_h($value): string {
  return cms_h((string) $value);
}

function payroll_hours_period_cutoff(array $period): DateTimeImmutable {
  return new DateTimeImmutable((string) $period['datereport'] . ' 16:00:00');
}

function payroll_hours_period_has_ended(array $period, DateTimeImmutable $now): bool {
  $periodEnd = new DateTimeImmutable((string) $period['dateto'] . ' 23:59:59');
  return $now > $periodEnd;
}

function payroll_hours_duration_minutes(?string $from, ?string $to): int {
  if (!$from || !$to) {
    return 0;
  }
  try {
    $fromTime = new DateTimeImmutable($from);
    $toTime = new DateTimeImmutable($to);
  } catch (Throwable $e) {
    return 0;
  }
  $seconds = $toTime->getTimestamp() - $fromTime->getTimestamp();
  return max(0, (int) floor($seconds / 60));
}

function payroll_hours_format_minutes(int $minutes): string {
  $hours = intdiv($minutes, 60);
  $mins = $minutes % 60;
  return sprintf('%d:%02d', $hours, $mins);
}

function payroll_hours_datetime_input(?string $value): string {
  $ts = strtotime((string) $value);
  return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

function payroll_hours_minutes_json(): string {
  return htmlspecialchars(json_encode(['00', '15', '30', '45']), ENT_QUOTES, 'UTF-8');
}
function payroll_hours_type_label($type): string {
  $labels = [
    1 => 'Day',
    2 => 'Night',
    3 => 'Holiday',
    4 => 'Sick',
  ];
  $key = (int) $type;
  return $labels[$key] ?? (string) $type;
}

function payroll_hours_type_timeline_class($type): string {
  $classes = [
    1 => 'payroll-time-day',
    2 => 'payroll-time-night',
    3 => 'payroll-time-holiday',
    4 => 'payroll-time-sick',
  ];
  return $classes[(int) $type] ?? 'payroll-time-day';
}

function payroll_hours_timeline_records(array $period): array {
  $periodStart = (new DateTimeImmutable((string) $period['datefrom']))->setTime(0, 0)->format('Y-m-d H:i:s');
  $periodEnd = (new DateTimeImmutable((string) $period['dateto']))->modify('+1 day')->setTime(0, 0)->format('Y-m-d H:i:s');
  return cms_db_fetch_all(
    'SELECT id, type, timefrom, timeto
     FROM oli_times
     WHERE archived = 0
       AND showonweb = "Yes"
       AND timefrom < :period_end
       AND timeto > :period_start
     ORDER BY timefrom ASC, timeto ASC, id ASC',
    [
      ':period_start' => $periodStart,
      ':period_end' => $periodEnd,
    ]
  );
}

function payroll_hours_timeline_days(array $period, array $records): array {
  $days = [];
  $start = (new DateTimeImmutable((string) $period['datefrom']))->setTime(0, 0);
  $end = (new DateTimeImmutable((string) $period['dateto']))->setTime(0, 0);
  for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
    $key = $day->format('Y-m-d');
    $days[$key] = [
      'date' => $key,
      'label' => $day->format('D j M'),
      'lanes' => [],
      'laneEnds' => [],
      'minutes' => 0,
    ];
  }

  $periodStart = $start;
  $periodEnd = $end->modify('+1 day');
  foreach ($records as $record) {
    try {
      $from = new DateTimeImmutable((string) $record['timefrom']);
      $to = new DateTimeImmutable((string) $record['timeto']);
    } catch (Throwable $e) {
      continue;
    }
    if ($to <= $from) {
      continue;
    }
    if ($from < $periodStart) {
      $from = $periodStart;
    }
    if ($to > $periodEnd) {
      $to = $periodEnd;
    }
    while ($from < $to) {
      $dayStart = $from->setTime(0, 0);
      $dayEnd = $dayStart->modify('+1 day');
      $chunkEnd = $to < $dayEnd ? $to : $dayEnd;
      $key = $dayStart->format('Y-m-d');
      if (!isset($days[$key])) {
        $from = $chunkEnd;
        continue;
      }
      $startMinute = max(0, (int) floor(($from->getTimestamp() - $dayStart->getTimestamp()) / 60));
      $endMinute = min(1440, (int) ceil(($chunkEnd->getTimestamp() - $dayStart->getTimestamp()) / 60));
      $startSegment = max(0, min(95, (int) floor($startMinute / 15)));
      $endSegment = max($startSegment + 1, min(96, (int) ceil($endMinute / 15)));
      $bar = [
        'start' => $startSegment,
        'span' => $endSegment - $startSegment,
        'class' => payroll_hours_type_timeline_class($record['type'] ?? 1),
        'label' => $from->format('H:i') . '-' . $chunkEnd->format('H:i') . ' ' . payroll_hours_type_label($record['type'] ?? 1),
      ];
      $laneIndex = null;
      foreach ($days[$key]['laneEnds'] as $index => $laneEnd) {
        if ($laneEnd <= $startSegment) {
          $laneIndex = $index;
          break;
        }
      }
      if ($laneIndex === null) {
        $laneIndex = count($days[$key]['lanes']);
        $days[$key]['lanes'][$laneIndex] = [];
      }
      $days[$key]['lanes'][$laneIndex][] = $bar;
      $days[$key]['laneEnds'][$laneIndex] = $endSegment;
      $days[$key]['minutes'] += max(0, $endMinute - $startMinute);
      $from = $chunkEnd;
    }
  }

  foreach ($days as &$day) {
    unset($day['laneEnds']);
  }
  unset($day);
  return $days;
}
function payroll_hours_staff_period_rows(int $staffId, array $period): array {
  return cms_db_fetch_all(
    'SELECT id, name, type, timefrom, timeto, comment, notes, verified
     FROM oli_times
     WHERE name = :staff_id
       AND archived = 0
       AND showonweb = "Yes"
       AND DATE(timefrom) BETWEEN :datefrom AND :dateto
     ORDER BY timefrom ASC, timeto ASC, type ASC, id ASC',
    [
      ':staff_id' => $staffId,
      ':datefrom' => $period['datefrom'],
      ':dateto' => $period['dateto'],
    ]
  );
}

function payroll_hours_period_summary(array $rows): array {
  $total = count($rows);
  $verified = 0;
  $typeTotals = [];
  foreach ($rows as $row) {
    if ((int) ($row['verified'] ?? 0) === 1) {
      $verified++;
    }
    $label = payroll_hours_type_label($row['type'] ?? '');
    $typeTotals[$label] = ($typeTotals[$label] ?? 0) + payroll_hours_duration_minutes($row['timefrom'] ?? null, $row['timeto'] ?? null);
  }
  return [
    'total' => $total,
    'verified' => $verified,
    'unverified' => max(0, $total - $verified),
    'percent' => $total > 0 ? (int) round(($verified / $total) * 100) : 0,
    'typeTotals' => $typeTotals,
  ];
}

$payrollNotice = $_SESSION['payroll_hours_notice'] ?? null;
unset($_SESSION['payroll_hours_notice']);

$staff = null;
if ($payrollUserId > 0) {
  $staff = cms_db_fetch_one(
    'SELECT id, name, surname, email
     FROM oli_staff
     WHERE user = :user_id
       AND archived = 0
       AND showonweb = "Yes"
     LIMIT 1',
    [':user_id' => $payrollUserId]
  );
}

$currentPeriod = cms_db_fetch_one(
  'SELECT *
   FROM oli_payeschedule
   WHERE archived = 0
     AND showonweb = "Yes"
     AND payroll_submitted = 0
     AND CURDATE() BETWEEN datefrom AND dateto
   ORDER BY datefrom DESC
   LIMIT 1'
);
$previousPeriod = cms_db_fetch_one(
  'SELECT *
   FROM oli_payeschedule
   WHERE archived = 0
     AND showonweb = "Yes"
     AND payroll_submitted = 0
     AND dateto < CURDATE()
   ORDER BY dateto DESC
   LIMIT 1'
);
$visiblePeriods = [];
foreach ([$previousPeriod, $currentPeriod] as $period) {
  if ($period && !isset($visiblePeriods[(int) $period['id']])) {
    $visiblePeriods[(int) $period['id']] = $period;
  }
}

$payrollTimeTypes = cms_db_fetch_all(
  'SELECT id, name FROM oli_timetype WHERE archived = 0 AND showonweb = "Yes" ORDER BY id ASC'
);
if (!$payrollTimeTypes) {
  $payrollTimeTypes = [
    ['id' => 1, 'name' => 'Day'],
    ['id' => 2, 'name' => 'Night'],
    ['id' => 3, 'name' => 'Holiday'],
    ['id' => 4, 'name' => 'Sick'],
  ];
}

$adminPeriods = [];
if ($payrollIsAdmin) {
  $adminPeriods = cms_db_fetch_all(
    'SELECT *
     FROM oli_payeschedule
     WHERE archived = 0
       AND showonweb = "Yes"
       AND payroll_submitted = 0
       AND dateto <= CURDATE()
     ORDER BY dateto DESC
     LIMIT 4'
  );
}

$payrollTimelinePeriod = null;
$payrollTimelineDays = [];
if ($payrollIsAdmin) {
  $payrollTimelinePeriod = $currentPeriod ?: ($adminPeriods[0] ?? null);
  if ($payrollTimelinePeriod) {
    $payrollTimelineDays = payroll_hours_timeline_days($payrollTimelinePeriod, payroll_hours_timeline_records($payrollTimelinePeriod));
  }
}
?>

<?php if ($payrollNotice): ?>
  <div class="alert alert-<?php echo payroll_hours_h($payrollNotice['type'] ?? 'info'); ?>" role="alert">
    <?php echo payroll_hours_h($payrollNotice['message'] ?? ''); ?>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-12<?php echo $payrollIsAdmin ? ' col-xl-8' : ''; ?>">
    <div class="cms-card mb-4">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
          <h2 class="h4 mb-1">My Hours</h2>
          <?php if ($staff): ?>
            <p class="text-muted mb-0"><?php echo payroll_hours_h(trim(($staff['name'] ?? '') . ' ' . ($staff['surname'] ?? ''))); ?></p>
          <?php else: ?>
            <p class="text-muted mb-0">No linked staff record was found for this CMS user.</p>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$staff): ?>
        <p class="mb-0">Ask admin to link your CMS user to a staff record before hours can be shown here.</p>
      <?php elseif (!$visiblePeriods): ?>
        <p class="mb-0">There are no open payroll periods to verify.</p>
      <?php else: ?>
        <?php foreach ($visiblePeriods as $period): ?>
          <?php
            $rows = payroll_hours_staff_period_rows((int) $staff['id'], $period);
            $summary = payroll_hours_period_summary($rows);
            $cutoff = payroll_hours_period_cutoff($period);
            $periodEnded = payroll_hours_period_has_ended($period, $payrollNow);
            $cutoffPassed = $payrollNow > $cutoff;
            $canVerifyLine = !$cutoffPassed;
            $canVerifyAll = $periodEnded && !$cutoffPassed && $summary['unverified'] > 0;
          ?>
          <section class="border-top pt-4 mt-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
              <div>
                <h3 class="h5 mb-1">Period <?php echo payroll_hours_h($period['period']); ?> (<?php echo payroll_hours_h(date('Y', strtotime((string) $period['datefrom']))); ?>)</h3>
                <div class="text-muted small">
                  <?php echo payroll_hours_h(date('j M Y', strtotime((string) $period['datefrom']))); ?> -
                  <?php echo payroll_hours_h(date('j M Y', strtotime((string) $period['dateto']))); ?>,
                  cutoff <?php echo payroll_hours_h($cutoff->format('j M Y H:i')); ?>
                </div>
              </div>
              <div class="text-end">
                <div class="fw-semibold"><?php echo (int) $summary['verified']; ?> of <?php echo (int) $summary['total']; ?> verified</div>
                <div class="progress" style="width: 220px; height: 10px;">
                  <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int) $summary['percent']; ?>%;" aria-valuenow="<?php echo (int) $summary['percent']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>
            </div>


            <?php if (!$rows): ?>
              <p class="mb-0 text-muted">No hours have been recorded for this period.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>From</th>
                      <th>To</th>
                      <th>Type</th>
                      <th>Total</th>
                      <th>Status</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $row): ?>
                      <?php
                        $verified = (int) ($row['verified'] ?? 0) === 1;
                        $minutes = payroll_hours_duration_minutes($row['timefrom'] ?? null, $row['timeto'] ?? null);
                      ?>
                      <tr data-time-row="<?php echo (int) $row['id']; ?>">
                        <td data-label="Date" data-time-cell="date"><?php echo payroll_hours_h(date('D j M Y', strtotime((string) $row['timefrom']))); ?></td>
                        <td data-label="From" data-time-cell="from"><?php echo payroll_hours_h(date('H:i', strtotime((string) $row['timefrom']))); ?></td>
                        <td data-label="To" data-time-cell="to"><?php echo payroll_hours_h(date('H:i', strtotime((string) $row['timeto']))); ?></td>
                        <td data-label="Type" data-time-cell="type"><?php echo payroll_hours_h(payroll_hours_type_label($row['type'] ?? '')); ?></td>
                        <td data-label="Total" data-time-cell="total"><?php echo payroll_hours_h(payroll_hours_format_minutes($minutes)); ?></td>
                        <td data-label="Status" data-time-cell="status">
                          <?php if ($verified): ?>
                            <span class="badge text-bg-success">Verified</span>
                          <?php else: ?>
                            <span class="badge text-bg-danger">Not verified</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end" data-label="Action">
                          <?php
                            $canAmendThisRow = $payrollCanAmendHours && (!$verified || $payrollCanAmendVerifiedHours);
                          ?>
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary payroll-amend-btn"
                            <?php echo $canAmendThisRow ? '' : 'disabled'; ?>
                            data-time-id="<?php echo (int) $row['id']; ?>"
                            data-type="<?php echo payroll_hours_h((string) ($row['type'] ?? '')); ?>"
                            data-timefrom="<?php echo payroll_hours_h(payroll_hours_datetime_input($row['timefrom'] ?? '')); ?>"
                            data-timeto="<?php echo payroll_hours_h(payroll_hours_datetime_input($row['timeto'] ?? '')); ?>"
                            data-comment="<?php echo payroll_hours_h((string) ($row['comment'] ?? '')); ?>"
                            data-notes="<?php echo payroll_hours_h((string) ($row['notes'] ?? '')); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#payrollAmendModal"
                          >Amend</button>
                          <?php if ($verified): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Verified</button>
                          <?php elseif (!$canVerifyLine): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Cutoff passed</button>
                          <?php else: ?>
                            <form method="post" action="dashboard.php?tab=payroll-hours" class="d-inline">
                              <input type="hidden" name="action" value="verify_time">
                              <input type="hidden" name="time_id" value="<?php echo (int) $row['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-success">Verify</button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <p class="small text-muted mb-3">Admin Message to go here</p>

              <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                <?php if ($summary['typeTotals']): ?>
                  <div class="table-responsive" style="min-width: 260px;">
                    <table class="table table-sm mb-0 align-middle">
                      <thead>
                        <tr>
                          <th>Type</th>
                          <th class="text-end">Total hours</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($summary['typeTotals'] as $typeLabel => $minutes): ?>
                          <tr>
                            <td><?php echo payroll_hours_h($typeLabel); ?></td>
                            <td class="text-end" data-label="Action"><?php echo payroll_hours_h(payroll_hours_format_minutes((int) $minutes)); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>

                <div class="ms-auto">
                  <?php if ($canVerifyAll): ?>
                    <form method="post" action="dashboard.php?tab=payroll-hours">
                      <input type="hidden" name="action" value="verify_all">
                      <input type="hidden" name="period_id" value="<?php echo (int) $period['id']; ?>">
                      <button type="submit" class="btn btn-success">Verify All</button>
                    </form>
                  <?php elseif (!$periodEnded): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>Verify All available after period end</button>
                  <?php elseif ($cutoffPassed): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>Verification cutoff passed</button>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>All records verified</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($payrollIsAdmin): ?>
    <div class="col-12 col-xl-4">
      <div class="cms-card">
        <h2 class="h4 mb-3">Payroll Checklist</h2>
        <?php if (!$adminPeriods): ?>
          <p class="mb-0 text-muted">No ended payroll periods are waiting to be submitted.</p>
        <?php else: ?>
          <?php foreach ($adminPeriods as $period): ?>
            <?php
              $staffSummaries = cms_db_fetch_all(
                'SELECT s.id, s.name, s.surname,
                        COUNT(t.id) AS total_rows,
                        SUM(CASE WHEN t.verified = 1 THEN 1 ELSE 0 END) AS verified_rows
                 FROM oli_times t
                 INNER JOIN oli_staff s ON s.id = t.name
                 WHERE t.archived = 0
                   AND t.showonweb = "Yes"
                   AND DATE(t.timefrom) BETWEEN :datefrom AND :dateto
                 GROUP BY s.id, s.name, s.surname
                 ORDER BY s.surname ASC, s.name ASC',
                [
                  ':datefrom' => $period['datefrom'],
                  ':dateto' => $period['dateto'],
                ]
              );
              $periodTotal = 0;
              $periodVerified = 0;
              foreach ($staffSummaries as $staffSummary) {
                $periodTotal += (int) $staffSummary['total_rows'];
                $periodVerified += (int) $staffSummary['verified_rows'];
              }
              $periodUnverified = max(0, $periodTotal - $periodVerified);
            ?>
            <section class="border-top pt-3 mt-3">
              <div class="d-flex justify-content-between gap-3">
                <div>
                  <h3 class="h6 mb-1">Period <?php echo payroll_hours_h($period['period']); ?></h3>
                  <div class="text-muted small"><?php echo payroll_hours_h(date('j M', strtotime((string) $period['datefrom']))); ?> - <?php echo payroll_hours_h(date('j M Y', strtotime((string) $period['dateto']))); ?></div>
                </div>
                <span class="badge <?php echo $periodUnverified === 0 ? 'text-bg-success' : 'text-bg-warning'; ?> align-self-start">
                  <?php echo (int) $periodUnverified; ?> unverified
                </span>
              </div>

              <?php if (!$staffSummaries): ?>
                <p class="text-muted small mb-2 mt-2">No hours recorded.</p>
              <?php else: ?>
                <div class="table-responsive mt-2">
                  <table class="table table-sm mb-2">
                    <tbody>
                      <?php foreach ($staffSummaries as $staffSummary): ?>
                        <?php $staffUnverified = (int) $staffSummary['total_rows'] - (int) $staffSummary['verified_rows']; ?>
                        <tr>
                          <td><?php echo payroll_hours_h(trim(($staffSummary['name'] ?? '') . ' ' . ($staffSummary['surname'] ?? ''))); ?></td>
                          <td class="text-end" data-label="Action"><?php echo (int) $staffSummary['verified_rows']; ?>/<?php echo (int) $staffSummary['total_rows']; ?></td>
                          <td class="text-end" data-label="Action">
                            <span class="badge <?php echo $staffUnverified === 0 ? 'text-bg-success' : 'text-bg-danger'; ?>"><?php echo $staffUnverified === 0 ? 'OK' : (int) $staffUnverified; ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

              <form method="post" action="dashboard.php?tab=payroll-hours" class="mt-2">
                <input type="hidden" name="action" value="submit_period">
                <input type="hidden" name="period_id" value="<?php echo (int) $period['id']; ?>">
                <button type="submit" class="btn btn-sm <?php echo $periodUnverified === 0 ? 'btn-success' : 'btn-warning'; ?> w-100">
                  Submit Payroll<?php echo $periodUnverified > 0 ? ' With Warning' : ''; ?>
                </button>
              </form>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($payrollIsAdmin && $payrollTimelinePeriod): ?>
  <div class="cms-card mt-4 payroll-timeline-card">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div>
        <h2 class="h4 mb-1">Worked Hours Timeline</h2>
        <div class="text-muted small">
          Period <?php echo payroll_hours_h($payrollTimelinePeriod['period'] ?? ''); ?>,
          <?php echo payroll_hours_h(date('j M Y', strtotime((string) $payrollTimelinePeriod['datefrom']))); ?> -
          <?php echo payroll_hours_h(date('j M Y', strtotime((string) $payrollTimelinePeriod['dateto']))); ?>
        </div>
      </div>
      <div class="payroll-timeline-key" aria-label="Timeline key">
        <span><i class="payroll-time-day"></i>Day</span>
        <span><i class="payroll-time-holiday"></i>Holiday</span>
        <span><i class="payroll-time-sick"></i>Sick</span>
        <span><i class="payroll-time-night"></i>Night</span>
      </div>
    </div>

    <div class="payroll-timeline-scroll">
      <div class="payroll-timeline-inner">
        <div class="payroll-timeline-scale" aria-hidden="true">
          <div class="payroll-timeline-day-label"></div>
          <div class="payroll-timeline-scale-rail">
            <span style="left: 0%;">00</span>
            <span style="left: 25%;">06</span>
            <span style="left: 50%;">12</span>
            <span style="left: 75%;">18</span>
            <span style="left: 100%;">24</span>
          </div>
          <div class="payroll-timeline-total-label">Hours</div>
        </div>
        <?php foreach ($payrollTimelineDays as $timelineDay): ?>
          <div class="payroll-timeline-row">
            <div class="payroll-timeline-day-label"><?php echo payroll_hours_h($timelineDay['label']); ?></div>
            <div class="payroll-timeline-rail" role="img" aria-label="<?php echo payroll_hours_h($timelineDay['label'] . ' worked hours'); ?>">
              <?php if (!$timelineDay['lanes']): ?>
                <div class="payroll-timeline-empty">No hours</div>
              <?php else: ?>
                <?php foreach ($timelineDay['lanes'] as $lane): ?>
                  <div class="payroll-timeline-lane">
                    <?php foreach ($lane as $bar): ?>
                      <span
                        class="payroll-timeline-bar <?php echo payroll_hours_h($bar['class']); ?>"
                        style="--start: <?php echo (int) $bar['start']; ?>; --span: <?php echo (int) $bar['span']; ?>;"
                        title="<?php echo payroll_hours_h($bar['label']); ?>"
                      ></span>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="payroll-timeline-total-label"><?php echo payroll_hours_h(payroll_hours_format_minutes((int) $timelineDay['minutes'])); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="modal fade" id="payrollAmendModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" id="payrollAmendForm" method="post" action="dashboard.php?tab=payroll-hours">
      <div class="modal-header">
        <h5 class="modal-title">Amend Time Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" data-amend-error></div>
        <input type="hidden" name="response" value="json">
        <input type="hidden" name="action" value="amend_time">
        <input type="hidden" name="time_id" value="">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="payroll-amend-type">Type</label>
            <select class="form-select" id="payroll-amend-type" name="type" required>
              <?php foreach ($payrollTimeTypes as $timeType): ?>
                <option value="<?php echo (int) $timeType['id']; ?>"><?php echo payroll_hours_h($timeType['name'] ?? $timeType['id']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" for="payroll-amend-timefrom">Start</label>
            <input id="payroll-amend-timefrom" name="timefrom" type="hidden" required>
            <div class="payroll-amend-datetime" data-payroll-datetime="timefrom"></div>
          </div>
          <div class="col-12">
            <label class="form-label" for="payroll-amend-timeto">Finish</label>
            <input id="payroll-amend-timeto" name="timeto" type="hidden" required>
            <div class="payroll-amend-datetime" data-payroll-datetime="timeto"></div>
          </div>
          <div class="col-12">
            <label class="form-label" for="payroll-amend-comment">Comments on Care</label>
            <textarea class="form-control" id="payroll-amend-comment" name="comment" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="payroll-amend-notes">Notes relating Work</label>
            <textarea class="form-control" id="payroll-amend-notes" name="notes" rows="3"></textarea>
          </div>
        </div>
        <p class="small text-muted mb-0 mt-3">Saving an amendment marks this time record as not verified.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Amendment</button>
      </div>
    </form>
  </div>
</div>
<style>
.payroll-timeline-card {
  overflow: hidden;
}

.payroll-timeline-key {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 0.9rem;
  font-size: 0.875rem;
}

.payroll-timeline-key span {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  white-space: nowrap;
}

.payroll-timeline-key i {
  display: inline-block;
  width: 0.8rem;
  height: 0.8rem;
  border-radius: 2px;
}

.payroll-timeline-scroll {
  overflow-x: auto;
  padding-bottom: 0.25rem;
}

.payroll-timeline-inner {
  min-width: 860px;
}

.payroll-timeline-scale,
.payroll-timeline-row {
  display: grid;
  grid-template-columns: 6.5rem minmax(0, 1fr) 4rem;
  gap: 0.75rem;
  align-items: center;
}

.payroll-timeline-scale {
  color: #6c757d;
  font-size: 0.75rem;
  margin-bottom: 0.35rem;
}

.payroll-timeline-scale-rail {
  position: relative;
  height: 1rem;
}

.payroll-timeline-scale-rail span {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
}

.payroll-timeline-scale-rail span:first-child {
  transform: translateX(0);
}

.payroll-timeline-scale-rail span:last-child {
  transform: translateX(-100%);
}

.payroll-timeline-row {
  min-height: 2rem;
  padding: 0.3rem 0;
  border-top: 1px solid #eef1f4;
}

.payroll-timeline-day-label,
.payroll-timeline-total-label {
  font-size: 0.82rem;
  white-space: nowrap;
}

.payroll-timeline-total-label {
  text-align: right;
  color: #495057;
}

.payroll-timeline-rail {
  min-height: 1.45rem;
  padding: 0.18rem 0;
  background:
    linear-gradient(to right, rgba(33, 37, 41, 0.14) 1px, transparent 1px) 0 0 / 25% 100%,
    repeating-linear-gradient(to right, #f8f9fa 0, #f8f9fa calc(100% / 96 - 1px), #e1e7ee calc(100% / 96 - 1px), #e1e7ee calc(100% / 96));
  border: 1px solid #d8dee6;
  border-radius: 4px;
}

.payroll-timeline-lane {
  position: relative;
  height: 0.8rem;
  margin: 0.12rem 0;
}

.payroll-timeline-bar {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(var(--start) * 100% / 96);
  width: calc(var(--span) * 100% / 96);
  min-width: 3px;
  border-radius: 2px;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.payroll-timeline-empty {
  color: #8a929b;
  font-size: 0.78rem;
  line-height: 1rem;
  padding-left: 0.35rem;
}

.payroll-time-day { background: #198754; }
.payroll-time-holiday { background: #0d6efd; }
.payroll-time-sick { background: #fd7e14; }
.payroll-time-night { background: #dc3545; }

.payroll-amend-datetime {
  display: grid;
  grid-template-columns: minmax(10rem, 13rem) 4.5rem 4.5rem;
  gap: 0.5rem;
  align-items: center;
  max-width: 23rem;
}

.payroll-amend-datetime select {
  text-align: center;
  padding-left: 0.5rem;
  padding-right: 1.75rem;
}
@media (max-width: 991.98px) {
  .payroll-timeline-card {
    display: none;
  }
}


@media (max-width: 575.98px) {
  #payroll-hours .cms-card {
    padding: 0.85rem;
  }

  #payroll-hours .table-responsive {
    overflow: visible;
  }

  #payroll-hours table.table {
    border-collapse: separate;
    border-spacing: 0 0.75rem;
  }

  #payroll-hours table.table thead {
    display: none;
  }

  #payroll-hours table.table tbody,
  #payroll-hours table.table tr,
  #payroll-hours table.table td {
    display: block;
    width: 100%;
  }

  #payroll-hours table.table tr {
    border: 1px solid #e0e6ee;
    border-radius: 8px;
    padding: 0.55rem 0.7rem;
    background: #fff;
  }

  #payroll-hours table.table td {
    border: 0;
    padding: 0.28rem 0;
    text-align: left !important;
    white-space: normal;
  }

  #payroll-hours table.table td[data-label] {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1fr);
    gap: 0.75rem;
    align-items: center;
  }

  #payroll-hours table.table td[data-label]::before {
    content: attr(data-label);
    color: #7a8796;
    font-size: 0.78rem;
    font-weight: 600;
  }

  #payroll-hours td[data-label="Action"] {
    display: flex !important;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding-top: 0.5rem !important;
  }

  #payroll-hours td[data-label="Action"]::before {
    display: none;
  }

  #payroll-hours td[data-label="Action"] .btn,
  #payroll-hours td[data-label="Action"] form {
    flex: 1 1 7.5rem;
  }

  #payroll-hours td[data-label="Action"] .btn {
    width: 100%;
  }
}

@media (max-width: 520px) {
  .payroll-amend-datetime {
    grid-template-columns: minmax(8.5rem, 1fr) 4.25rem 4.25rem;
  }
}
</style>
<script>
(function () {
  const modal = document.getElementById('payrollAmendModal');
  const form = document.getElementById('payrollAmendForm');
  if (!modal || !form) {
    return;
  }
  const errorBox = form.querySelector('[data-amend-error]');
  const minuteChoices = ['00', '15', '30', '45'];
  const datetimeControls = {};
  let activeRow = null;

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function parseDatetime(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/);
    if (match) {
      return { date: match[1], hour: match[2], minute: match[3] };
    }
    const now = new Date();
    return {
      date: now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()),
      hour: pad(now.getHours()),
      minute: '00'
    };
  }

  function buildSelect(values) {
    const select = document.createElement('select');
    select.className = 'form-select';
    values.forEach(function (value) {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
    return select;
  }

  function setDatetime(name, value) {
    const control = datetimeControls[name];
    if (!control) {
      return;
    }
    const parts = parseDatetime(value);
    if (!minuteChoices.includes(parts.minute)) {
      const rounded = Math.round(parseInt(parts.minute, 10) / 15) * 15 % 60;
      parts.minute = pad(rounded);
    }
    control.date.value = parts.date;
    control.hour.value = parts.hour;
    control.minute.value = parts.minute;
    control.sync();
  }

  function initDatetimeControls() {
    form.querySelectorAll('[data-payroll-datetime]').forEach(function (wrap) {
      const name = wrap.dataset.payrollDatetime;
      const hidden = form.elements[name];
      const date = document.createElement('input');
      date.type = 'date';
      date.className = 'form-control';
      const hour = buildSelect(Array.from({ length: 24 }, function (_, index) { return pad(index); }));
      const minute = buildSelect(minuteChoices);
      wrap.appendChild(date);
      wrap.appendChild(hour);
      wrap.appendChild(minute);
      function sync() {
        hidden.value = date.value + 'T' + hour.value + ':' + minute.value;
      }
      date.addEventListener('change', sync);
      hour.addEventListener('change', sync);
      minute.addEventListener('change', sync);
      datetimeControls[name] = { date: date, hour: hour, minute: minute, sync: sync };
    });
  }

  initDatetimeControls();

  modal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) {
      return;
    }
    activeRow = document.querySelector('[data-time-row="' + button.dataset.timeId + '"]');
    errorBox.classList.add('d-none');
    errorBox.textContent = '';
    form.elements.time_id.value = button.dataset.timeId || '';
    form.elements.type.value = button.dataset.type || '';
    setDatetime('timefrom', button.dataset.timefrom || '');
    setDatetime('timeto', button.dataset.timeto || '');
    form.elements.comment.value = button.dataset.comment || '';
    form.elements.notes.value = button.dataset.notes || '';
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.classList.add('d-none');
    errorBox.textContent = '';
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;

    var submitUrl = form.getAttribute('action') || window.location.href;
    fetch(submitUrl, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (response) {
      return response.text().then(function (text) {
        const contentType = response.headers.get('content-type') || 'unknown';
        try {
          return JSON.parse(text);
        } catch (error) {
          const plain = text.replace(/<script[\s\S]*?<\/script>/gi, ' ')
            .replace(/<style[\s\S]*?<\/style>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
          const detail = [
            'HTTP ' + response.status + ' ' + response.statusText,
            'URL: ' + response.url,
            'Content-Type: ' + contentType,
            'Body: ' + (plain ? plain.slice(0, 500) : text.slice(0, 500))
          ].join('
');
          throw new Error('Expected JSON but received a different response.
' + detail);
        }
      });
    }).then(function (payload) {
      if (!payload.ok) {
        throw new Error(payload.message || 'The time record could not be amended.');
      }
      const record = payload.data && payload.data.record ? payload.data.record : null;
      if (record && activeRow) {
        activeRow.querySelector('[data-time-cell="date"]').textContent = record.date;
        activeRow.querySelector('[data-time-cell="from"]').textContent = record.from;
        activeRow.querySelector('[data-time-cell="to"]').textContent = record.to;
        activeRow.querySelector('[data-time-cell="type"]').textContent = record.type;
        activeRow.querySelector('[data-time-cell="total"]').textContent = record.total;
        activeRow.querySelector('[data-time-cell="status"]').innerHTML = '<span class="badge text-bg-danger">Not verified</span>';
        const amendButton = activeRow.querySelector('.payroll-amend-btn');
        if (amendButton) {
          amendButton.dataset.type = form.elements.type.value;
          amendButton.dataset.timefrom = form.elements.timefrom.value;
          amendButton.dataset.timeto = form.elements.timeto.value;
          amendButton.dataset.comment = form.elements.comment.value;
          amendButton.dataset.notes = form.elements.notes.value;
        }
        const actionCell = activeRow.querySelector('td.text-end');
        const verifiedButton = actionCell ? Array.from(actionCell.querySelectorAll('button[disabled]')).find(function (button) {
          return button.textContent.trim() === 'Verified';
        }) : null;
        if (verifiedButton) {
          const verifyForm = document.createElement('form');
          verifyForm.method = 'post';
          verifyForm.setAttribute('action', submitUrl);
          verifyForm.className = 'd-inline';
          verifyForm.innerHTML = '<input type="hidden" name="action" value="verify_time"><input type="hidden" name="time_id" value="' + form.elements.time_id.value + '"><button type="submit" class="btn btn-sm btn-success">Verify</button>';
          verifiedButton.replaceWith(verifyForm);
        }
      }
      bootstrap.Modal.getOrCreateInstance(modal).hide();
    }).catch(function (error) {
      errorBox.innerHTML = '';
      const pre = document.createElement('pre');
      pre.className = 'mb-0 small text-wrap';
      pre.style.whiteSpace = 'pre-wrap';
      pre.textContent = error.message;
      errorBox.appendChild(pre);
      errorBox.classList.remove('d-none');
    }).finally(function () {
      submit.disabled = false;
    });
  });
})();
</script>