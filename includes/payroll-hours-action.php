<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/db.php';
require_once dirname(__DIR__) . '/logrecord.php';

$payrollHoursJsonRequest = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
  || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
  || (string) ($_POST['response'] ?? '') === 'json';
if (!cms_is_logged_in()) {
  if ($payrollHoursJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Please log in again.', 'data' => ['type' => 'warning']], JSON_UNESCAPED_SLASHES);
    exit;
  }
  cms_require_login();
}

function payroll_hours_redirect(string $type, string $message): void {
  $_SESSION['payroll_hours_notice'] = [
    'type' => $type,
    'message' => $message,
  ];
  header('Location: ' . cms_base_url('/wccms/dashboard.php?tab=payroll-hours'));
  exit;
}

function payroll_hours_wants_json(): bool {
  $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
  $requested = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  global $payrollHoursJsonRequest;
  return (bool) $payrollHoursJsonRequest || str_contains($accept, 'application/json') || $requested === 'xmlhttprequest' || (string) ($_POST['response'] ?? '') === 'json';
}

function payroll_hours_json(bool $ok, string $message, array $data = []): void {
  header('Content-Type: application/json');
  echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES);
  exit;
}

function payroll_hours_fail(string $type, string $message): void {
  if (payroll_hours_wants_json()) {
    payroll_hours_json(false, $message, ['type' => $type]);
  }
  payroll_hours_redirect($type, $message);
}

function payroll_hours_success(string $message, array $data = []): void {
  if (payroll_hours_wants_json()) {
    payroll_hours_json(true, $message, $data);
  }
  payroll_hours_redirect('success', $message);
}

function payroll_hours_amend_min_role_level(): int {
  return 0;
}

function payroll_hours_amend_verified_min_role_level(): int {
  return 20;
}

function payroll_hours_user_role_level(int $userId): int {
  $role = cms_db_fetch_one(
    "SELECT COALESCE(r.level, 0) AS level
     FROM cms_users u
     LEFT JOIN cms_userrole r ON r.id = u.userrole
     WHERE u.id = :id
     LIMIT 1",
    [':id' => $userId]
  );
  return (int) ($role['level'] ?? 0);
}

function payroll_hours_normalize_datetime(string $value): ?string {
  $value = trim($value);
  if ($value === '') {
    return null;
  }
  $value = str_replace('T', ' ', $value);
  $ts = strtotime($value);
  if ($ts === false) {
    return null;
  }
  $step = 15 * 60;
  $snapped = (int) round($ts / $step) * $step;
  return date('Y-m-d H:i:s', $snapped);
}

function payroll_hours_format_minutes(int $minutes): string {
  $hours = intdiv($minutes, 60);
  $mins = $minutes % 60;
  return sprintf('%d:%02d', $hours, $mins);
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
function payroll_hours_format_record(array $record): array {
  if (!$record) {
    return [];
  }
  $fromTs = strtotime((string) $record['timefrom']);
  $toTs = strtotime((string) $record['timeto']);
  $minutes = max(0, (int) floor(($toTs - $fromTs) / 60));
  return [
    'id' => (int) $record['id'],
    'date' => date('D j M Y', $fromTs),
    'from' => date('H:i', $fromTs),
    'to' => date('H:i', $toTs),
    'type' => payroll_hours_type_label($record['type'] ?? ''),
    'total' => payroll_hours_format_minutes($minutes),
    'verified' => (int) ($record['verified'] ?? 0),
  ];
}
function payroll_hours_current_user_id(): int {
  return (int) ($_SESSION['cms_user']['id'] ?? 0);
}

function payroll_hours_username(): string {
  return (string) ($_SESSION['cms_user']['email'] ?? $_SESSION['cms_user']['username'] ?? '');
}

function payroll_hours_is_admin(int $userId): bool {
  $role = cms_db_fetch_one(
    "SELECT r.name AS role_name, r.level
     FROM cms_users u
     LEFT JOIN cms_userrole r ON r.id = u.userrole
     WHERE u.id = :id
     LIMIT 1",
    [':id' => $userId]
  );
  $roleName = strtolower((string) ($role['role_name'] ?? ''));
  $roleLevel = (int) ($role['level'] ?? 0);
  return $roleLevel >= 20 || in_array($roleName, ['admin', 'tech'], true);
}

function payroll_hours_staff_for_user(int $userId): ?array {
  return cms_db_fetch_one(
    "SELECT id, name, surname
     FROM oli_staff
     WHERE user = :user_id
       AND archived = 0
       AND showonweb = 'Yes'
     LIMIT 1",
    [':user_id' => $userId]
  );
}

function payroll_hours_cutoff(array $period): DateTimeImmutable {
  return new DateTimeImmutable((string) $period['datereport'] . ' 16:00:00');
}

function payroll_hours_period_ended(array $period, DateTimeImmutable $now): bool {
  return $now > new DateTimeImmutable((string) $period['dateto'] . ' 23:59:59');
}

function payroll_hours_log(string $action, int $contentId, string $notes, string $query = ''): void {
  if (function_exists('saveLogV2')) {
    saveLogV2(payroll_hours_username(), $action, $query, 'Payroll Hours', 'SUCCESS', $notes, $contentId);
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  payroll_hours_redirect('warning', 'Invalid request.');
}

$userId = payroll_hours_current_user_id();
if ($userId <= 0) {
  payroll_hours_redirect('warning', 'Please log in again.');
}

$action = (string) ($_POST['action'] ?? '');
$now = new DateTimeImmutable('now');
$isAdmin = payroll_hours_is_admin($userId);
$staff = payroll_hours_staff_for_user($userId);

try {
  if ($action === 'amend_time') {
    $timeId = (int) ($_POST['time_id'] ?? 0);
    if ($timeId <= 0) {
      payroll_hours_fail('warning', 'No time record was selected.');
    }

    $roleLevel = payroll_hours_user_role_level($userId);
    if ($roleLevel < payroll_hours_amend_min_role_level()) {
      payroll_hours_fail('danger', 'You do not have permission to amend time records.');
    }

    $record = cms_db_fetch_one(
      "SELECT t.id, t.name AS staff_id, t.type, t.timefrom, t.timeto, t.comment, t.notes, t.verified,
              p.id AS period_id, p.period, p.datefrom, p.dateto, p.payroll_submitted
       FROM oli_times t
       INNER JOIN oli_payeschedule p ON DATE(t.timefrom) BETWEEN p.datefrom AND p.dateto
       WHERE t.id = :id
         AND t.archived = 0
         AND t.showonweb = 'Yes'
         AND p.archived = 0
         AND p.showonweb = 'Yes'
       ORDER BY p.datefrom DESC
       LIMIT 1",
      [':id' => $timeId]
    );

    if (!$record) {
      payroll_hours_fail('warning', 'The selected time record could not be found.');
    }
    if (!$isAdmin && (!$staff || (int) $record['staff_id'] !== (int) $staff['id'])) {
      payroll_hours_fail('danger', 'You cannot amend another staff member\'s time record.');
    }
    if ((int) $record['payroll_submitted'] === 1) {
      payroll_hours_fail('warning', 'This payroll period has already been submitted.');
    }
    if ((int) $record['verified'] === 1 && $roleLevel < payroll_hours_amend_verified_min_role_level()) {
      payroll_hours_fail('warning', 'Verified time records cannot be amended. Please ask admin to make this change.');
    }

    $type = (int) ($_POST['type'] ?? 0);
    if (!in_array($type, [1, 2, 3, 4], true)) {
      payroll_hours_fail('warning', 'Please choose a valid time type.');
    }

    $timefrom = payroll_hours_normalize_datetime((string) ($_POST['timefrom'] ?? ''));
    $timeto = payroll_hours_normalize_datetime((string) ($_POST['timeto'] ?? ''));
    if (!$timefrom || !$timeto) {
      payroll_hours_fail('warning', 'Please enter valid Start Time and Finish Time values.');
    }
    if (strtotime($timeto) <= strtotime($timefrom)) {
      payroll_hours_fail('warning', 'Finish Time must be after Start Time. Please correct the times before saving.');
    }

    $comment = trim((string) ($_POST['comment'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    cms_db_execute(
      "UPDATE oli_times
       SET type = :type,
           timefrom = :timefrom,
           timeto = :timeto,
           comment = :comment,
           notes = :notes,
           verified = 0,
           modified = NOW()
       WHERE id = :id",
      [
        ':type' => $type,
        ':timefrom' => $timefrom,
        ':timeto' => $timeto,
        ':comment' => $comment,
        ':notes' => $notes,
        ':id' => $timeId,
      ]
    );

    $updated = cms_db_fetch_one('SELECT id, type, timefrom, timeto, verified FROM oli_times WHERE id = :id LIMIT 1', [':id' => $timeId]);
    payroll_hours_log('AMEND_TIME', $timeId, 'Amended time record from dashboard; marked not verified.', 'UPDATE oli_times SET verified = 0 WHERE id = ' . $timeId);
    payroll_hours_success('Time record amended and marked as not verified.', ['record' => payroll_hours_format_record($updated ?: [])]);
  }
  if ($action === 'verify_time') {
    $timeId = (int) ($_POST['time_id'] ?? 0);
    if ($timeId <= 0) {
      payroll_hours_redirect('warning', 'No time record was selected.');
    }

    $record = cms_db_fetch_one(
      "SELECT t.id, t.name AS staff_id, t.verified, t.timefrom, p.id AS period_id, p.period,
              p.datefrom, p.dateto, p.datereport, p.payroll_submitted
       FROM oli_times t
       INNER JOIN oli_payeschedule p ON DATE(t.timefrom) BETWEEN p.datefrom AND p.dateto
       WHERE t.id = :id
         AND t.archived = 0
         AND t.showonweb = 'Yes'
         AND p.archived = 0
         AND p.showonweb = 'Yes'
       ORDER BY p.datefrom DESC
       LIMIT 1",
      [':id' => $timeId]
    );

    if (!$record) {
      payroll_hours_redirect('warning', 'The selected time record could not be found.');
    }
    if (!$isAdmin && (!$staff || (int) $record['staff_id'] !== (int) $staff['id'])) {
      payroll_hours_redirect('danger', 'You cannot verify another staff member\'s time record.');
    }
    if ((int) $record['payroll_submitted'] === 1) {
      payroll_hours_redirect('warning', 'This payroll period has already been submitted.');
    }
    if ($now > payroll_hours_cutoff($record)) {
      payroll_hours_redirect('warning', 'The verification cutoff has passed for this period.');
    }
    if ((int) $record['verified'] === 1) {
      payroll_hours_redirect('info', 'That time record is already verified.');
    }

    cms_db_execute(
      "UPDATE oli_times
       SET verified = 1, modified = NOW()
       WHERE id = :id AND verified = 0",
      [':id' => $timeId]
    );
    payroll_hours_log('VERIFY_TIME', $timeId, 'Verified time record from dashboard.', 'UPDATE oli_times SET verified = 1 WHERE id = ' . $timeId);
    payroll_hours_redirect('success', 'Time record verified.');
  }

  if ($action === 'verify_all') {
    if (!$staff) {
      payroll_hours_redirect('warning', 'No linked staff record was found for your CMS user.');
    }
    $periodId = (int) ($_POST['period_id'] ?? 0);
    if ($periodId <= 0) {
      payroll_hours_redirect('warning', 'No payroll period was selected.');
    }

    $period = cms_db_fetch_one(
      "SELECT *
       FROM oli_payeschedule
       WHERE id = :id
         AND archived = 0
         AND showonweb = 'Yes'
       LIMIT 1",
      [':id' => $periodId]
    );
    if (!$period) {
      payroll_hours_redirect('warning', 'The selected payroll period could not be found.');
    }
    if ((int) $period['payroll_submitted'] === 1) {
      payroll_hours_redirect('warning', 'This payroll period has already been submitted.');
    }
    if (!payroll_hours_period_ended($period, $now)) {
      payroll_hours_redirect('warning', 'Verify All is only available after the period has ended.');
    }
    if ($now > payroll_hours_cutoff($period)) {
      payroll_hours_redirect('warning', 'The verification cutoff has passed for this period.');
    }

    $stmt = cms_db_query(
      "UPDATE oli_times
       SET verified = 1, modified = NOW()
       WHERE name = :staff_id
         AND archived = 0
         AND showonweb = 'Yes'
         AND verified = 0
         AND DATE(timefrom) BETWEEN :datefrom AND :dateto",
      [
        ':staff_id' => (int) $staff['id'],
        ':datefrom' => $period['datefrom'],
        ':dateto' => $period['dateto'],
      ]
    );
    $count = $stmt->rowCount();
    payroll_hours_log('VERIFY_ALL_TIME', $periodId, 'Verified ' . $count . ' time records for staff ID ' . (int) $staff['id'] . '.', 'UPDATE oli_times SET verified = 1 WHERE staff/period matched');
    payroll_hours_redirect('success', $count . ' time record(s) verified.');
  }

  if ($action === 'submit_period') {
    if (!$isAdmin) {
      payroll_hours_redirect('danger', 'Only admin users can submit payroll periods.');
    }
    $periodId = (int) ($_POST['period_id'] ?? 0);
    if ($periodId <= 0) {
      payroll_hours_redirect('warning', 'No payroll period was selected.');
    }

    $period = cms_db_fetch_one(
      "SELECT *
       FROM oli_payeschedule
       WHERE id = :id
         AND archived = 0
         AND showonweb = 'Yes'
       LIMIT 1",
      [':id' => $periodId]
    );
    if (!$period) {
      payroll_hours_redirect('warning', 'The selected payroll period could not be found.');
    }
    if ((int) $period['payroll_submitted'] === 1) {
      payroll_hours_redirect('info', 'This payroll period has already been submitted.');
    }
    if (!payroll_hours_period_ended($period, $now)) {
      payroll_hours_redirect('warning', 'Payroll can only be submitted after the period has ended.');
    }

    $unverified = (int) cms_db_fetch_column(
      "SELECT COUNT(*)
       FROM oli_times
       WHERE archived = 0
         AND showonweb = 'Yes'
         AND verified = 0
         AND DATE(timefrom) BETWEEN :datefrom AND :dateto",
      [
        ':datefrom' => $period['datefrom'],
        ':dateto' => $period['dateto'],
      ]
    );

    cms_db_execute(
      "UPDATE oli_payeschedule
       SET payroll_submitted = 1,
           payroll_submitted_at = NOW(),
           payroll_submitted_by = :user_id,
           payroll_submit_notes = :notes,
           modified = NOW()
       WHERE id = :id
         AND payroll_submitted = 0",
      [
        ':user_id' => $userId,
        ':notes' => 'Submitted from dashboard. Unverified records at submission: ' . $unverified,
        ':id' => $periodId,
      ]
    );
    payroll_hours_log('SUBMIT_PAYROLL', $periodId, 'Submitted payroll period. Unverified records: ' . $unverified, 'UPDATE oli_payeschedule SET payroll_submitted = 1 WHERE id = ' . $periodId);

    if ($unverified > 0) {
      payroll_hours_redirect('warning', 'Payroll submitted with ' . $unverified . ' unverified time record(s).');
    }
    payroll_hours_redirect('success', 'Payroll period submitted.');
  }

  payroll_hours_fail('warning', 'Unknown payroll action.');
} catch (Throwable $e) {
  error_log('Payroll hours action failed: ' . $e->getMessage());
  payroll_hours_fail('danger', 'The payroll action could not be completed.');
}
