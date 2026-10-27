<!-- reportOliTime-2 -->
<?php
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  $pageType = 'Edit'; // Add, Edit, Copy, Delete
  $hasErrors = false;

  $scheduleId = isset($_GET['frm']) ? (int)$_GET['frm'] : 0;
  if ($scheduleId <= 0) {
      die("No payroll schedule specified");
  }

  include('../../setting/main-top-files.php');

  $month = $_GET['month'] ?? date('n'); // 1-12
  $year = $_GET['year'] ?? date('Y');

  $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
  $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
  $endDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . $daysInMonth;

  //get pay period dates
  $periodRow = cms_db_fetch_one(
    "SELECT id, hmrcyear, period, datefrom, dateto, name
     FROM oli_payeschedule
     WHERE id = :id
     LIMIT 1",
    [':id' => $scheduleId]
  );

  if (!$periodRow) {
    die("Invalid payroll schedule selected: ".$scheduleId);
  }

  $periodNumber = $periodRow['period'];
  $periodYear   = $periodRow['hmrcyear'];
  $startDate    = $periodRow['datefrom'];
  $endDate      = $periodRow['dateto'];
  $periodLabel  = $periodRow['name']; // e.g. 2025|7

  $dateIter = new DatePeriod(
      new DateTime($startDate),
      new DateInterval('P1D'),
      (new DateTime($endDate))->modify('+1 day')
  );
  $dates = iterator_to_array($dateIter);
  $dateKeys = [];
  foreach ($dates as $index => $d) {
      $dateKeys[$index + 1] = $d->format('Y-m-d');
  }
  $dateIndexByKey = array_flip($dateKeys);
  $daysInMonth = count($dateKeys);

  $staff = [];
  $staffRows = cms_db_fetch_all("SELECT id, name, surname FROM oli_staff WHERE archived = 0 ORDER BY surname, name");
  foreach ($staffRows as $row) {

    $days = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $days[$d] = [1=>0, 2=>0, 3=>0, 4=>0]; // type buckets
    }

    $staff[$row['id']] = [
        'name'   => $row['name'] . ' ' . $row['surname'],
        'days'   => $days,
        'totals' => [1=>0, 2=>0, 3=>0, 4=>0],
        'hours_total' => 0, // sum of types 1,3,4
        'nights_total'=> 0, // count of type 2 entries
    ];
    
  }






  

  $timeRows = cms_db_fetch_all(
      "SELECT `name`, `timefrom`, `timeto`, `type`
        FROM `oli_times` 
          WHERE DATE(timefrom) BETWEEN :start_date AND :end_date
          AND `showonweb` = 'Yes'
          AND `archived` = 0
          ORDER BY DATE(`timefrom`) ASC, `timefrom` ASC, `name` ASC, `type` ASC",
      [
        ':start_date' => $startDate,
        ':end_date' => $endDate,
      ]
  );

  foreach ($timeRows as $row) {
    $staffId = $row['name']; // staff ID is stored in oli_times.name
    if (!isset($staff[$staffId])) {
        continue;
    }

    $dayKey  = date('Y-m-d', strtotime($row['timefrom']));
    $day = $dateIndexByKey[$dayKey] ?? null;
    if ($day === null) {
        continue;
    }

    $seconds = strtotime($row['timeto']) - strtotime($row['timefrom']);

    if ($seconds <= 0) {
        $staff[$staffId]['days'][$day][$row['type']] = 'ERR';
        $hasErrors = true;
        continue;
    }
    
    if ($row['type'] == 2) {
        // Night shift = always 1
        $staff[$staffId]['days'][$day][$row['type']] += 1;
        $staff[$staffId]['totals'][2] += 1;
        $staff[$staffId]['nights_total'] += 1;
    } else {
        $hours = $seconds / 3600;
        $hours = round($hours * 4) / 4;
    
        $staff[$staffId]['days'][$day][$row['type']] += $hours;
        $staff[$staffId]['totals'][$row['type']] += $hours;
        $staff[$staffId]['hours_total'] += $hours;
    }
    

    
  }

?>

<html lang="en">
<head>  <?php include("../../include/header-code.php"); ?>
  <style>
    .header-title {
      font-size: 1.4rem;
      font-weight: 600;
    }
    .day-header {
      background-color: #f2f2f2; 
      font-weight:200;
    }
    .day-header tr {
      font-weight:400;
      background-color: #ddd;
    }
    .day-header tr th {
      font-style:normal;
    }
    .table :not(caption) * * {
      background-color: transparent;
    }
    tr .day-value {
      min-height: 45px;
      border-bottom:blueviolet solid 1px;
    }
    tr .day-value td {
      min-height: 45px;
      border:transparent;
    }
    .time-cell {
      background-color: #fff;
      min-height: 45px;
      font-weight: bold;
      vertical-align: middle !important;
    }
    .totals-box {
      background-color: #f9f9f9;
      padding: 8px;
      border: 1px solid #ddd;
      margin-bottom: 5px;
    }
  </style>
</head>
<body>
  <section id="container">
    <?php 
      include("../../include/header.php"); 
      include("../../include/sidebar.php"); 
    ?>
    <section id="main-content">
      <section class="wrapper site-min-height">


        <?php if ($hasErrors): ?>
          <div class="alert alert-danger">
              ⚠️ One or more time records contain errors (e.g. <em>timefrom</em> is after <em>timeto</em>).  
              Please review and correct them.
          </div>
        <?php endif; ?>

<?php
        // Colour configuration (easy to tweak later)
$typeColours = [
    'D' => '#2E8B57', // Green - Day
    'H' => '#007BFF', // Blue - Holiday
    'S' => '#FF8C00', // Orange - Sick
    'N' => '#CC0000', // Red - Night
];
?>
        <?php
          function formatHoursMinutes($hoursDecimal) {
              $totalMinutes = (int)round($hoursDecimal * 60);
              $h = floor($totalMinutes / 60);
              $m = $totalMinutes % 60;
              return sprintf("%d:%02d", $h, $m);
          }
        ?>


        <section class="card" style="width:100%; margin-left:-10px;">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-4">
              
              <h2>
                Time Sheet – Period: <?= htmlspecialchars($periodNumber) ?> (<?= $periodYear ?>) <br>
                <?= date('d M', strtotime($startDate)) ?> – <?= date('d M Y', strtotime($endDate)) ?>
              </h2>
              <!--<h2>Time Sheet - <?= date('F Y', strtotime($startDate)) ?> | Period: </h2>-->

              <a href="reportsOliTime-2_pdf.php?period=<?= (int)$scheduleId ?>" class="btn btn-primary" target="_blank">Print All to PDF</a>
              <!--<a href="reportsOliTime-2_pdf.php?period=<?= htmlspecialchars($periodNumber) ?>" class="btn btn-primary" target="_blank">Print All to PDF</a> -->
            </div>

            <?php foreach ($staff as $staffId => $record): ?>
            <?php 
              //$grandTotal = array_sum($record['totals']);
              //if ($grandTotal <= 0) continue; // skip this staff completely

              if ($record['hours_total'] <= 0 && $record['nights_total'] <= 0) continue;

            ?>
            <div class="mb-5 p-3 border border-dark">
              <!-- Header -->
              <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="header-title">
                  <span style="font-weight: 200;">Employee Name:</span> <?= htmlspecialchars($record['name']) ?> <span style="font-weight: 200;">(<?= $staffId ?>)</span><br>
                  <span style="font-weight: 200;">Period:</span> <?= htmlspecialchars($periodNumber) ?> (<?= $periodYear ?>)&nbsp;&nbsp;|&nbsp;&nbsp;
                  <?= date('d M', strtotime($startDate)) ?> – <?= date('d M Y', strtotime($endDate)) ?>
                </div>

                <a href="reportsOliTime-2_pdf.php?period=<?= $scheduleId ?>&id=<?= (int)$staffId ?>" class="btn btn-outline-primary btn-sm" target="_blank">Print This Employee</a>
                <!-- <a href="reportsOliTime-2_pdf.php?period=<?= htmlspecialchars($periodNumber) ?>&id=<?= $staffId ?>" class="btn btn-outline-primary btn-sm" target="_blank">Print This Employee</a> -->
              </div>

              <!-- Weekly Time Grid (7 columns per row) -->
              <div class="table-responsive">
                <table class="table table-bordered table-sm text-center align-middle">
                  <?php
                    $typeLabels = [1 => 'D', 2 => 'N', 3 => 'H', 4 => 'S']; // Day, Night, Holiday, Sick

                    for ($i = 1; $i <= $daysInMonth; $i += 7):
                      // Header row with day numbers
                      echo "<tr class='day-header'>";
                        for ($j = 0; $j < 7; $j++) {
                            $day = $i + $j;
                            if ($day <= $daysInMonth) {
                                $suffix = 'th';
                                if (!in_array(($day % 100), [11, 12, 13])) {
                                    switch ($day % 10) {
                                        case 1: $suffix = 'st'; break;
                                        case 2: $suffix = 'nd'; break;
                                        case 3: $suffix = 'rd'; break;
                                    }
                                }

$actualDate = isset($dateKeys[$day]) ? strtotime($dateKeys[$day]) : null;

if ($actualDate) {
    $dayShort = date('D', $actualDate); // e.g. Mon
    $dayNum = date('j', $actualDate);
    $monthShort = date('M', $actualDate); // e.g. Sep
    echo "<th style='font-weight:400; white-space:nowrap;'>
            <span style='font-size:0.65rem; color:#777;'>{$dayShort}</span>
            &nbsp;<span style='font-size:1.05rem; font-weight:700; color:#000;'>{$dayNum}</span>
            &nbsp;<span style='font-size:0.65rem; color:#777;'>{$monthShort}</span>
          </th>";
} else {
   // echo "<th>{$day}{$suffix}</th>";
    echo "<th>{$day}</th>";
}








                               // echo "<th>{$day}{$suffix}</th>";
                            } else {
                                echo "<th>&nbsp;</th>";
                            }
                        }
                      echo "</tr><tr class='day-value'>";

                        // Row with hours
                        for ($j = 0; $j < 7; $j++) {
                            $day = $i + $j;
                            if ($day <= $daysInMonth) {
                                $cellData = [];



                                
                                foreach ($typeLabels as $t => $prefix) {
                                  $h = $record['days'][$day][$t] ?? 0;



if ($h === 'ERR') {
    $cellData[] = "<span style='color:#CC0000; font-weight:bold;'>{$prefix}ERR</span>";
} elseif ($h > 0) {
    $colour = $typeColours[$prefix] ?? '#000'; // fallback black
    if ($t == 2) {
        // Night shifts: always numeric count (N1, N2...)
        $cellData[] = "<span style='color:{$colour}; font-weight:600;'>{$prefix}" . intval($h) . "</span>";
    } else {
        $cellData[] = "<span style='color:{$colour}; font-weight:600;'>{$prefix}" . formatHoursMinutes($h) . "</span>";
    }
}








                              }
                              
                              
                                echo "<td class='time-cell'>" . (!empty($cellData) ? implode('<br>', $cellData) : '') . "</td>";
                            } else {
                                echo "<td class='time-cell'>&nbsp;</td>";
                            }
                        }
                      echo "</tr>";
                    endfor;
                  ?>

                </table>
              </div>

              <!-- Totals -->

              <div class="mt-3">
  <table class="table table-sm table-borderless" style="width:auto;">
    <tr>
      <td class="text-end">
        Total Worked (<span style="color:<?= $typeColours['D'] ?>">D</span>):
      </td>
      <td class="text-end">
        <strong><?= formatHoursMinutes($record['totals'][1]) ?></strong>
      </td>
    </tr>
    <tr>
      <td class="text-end">
        Total Holiday (<span style="color:<?= $typeColours['H'] ?>">H</span>):
      </td>
      <td class="text-end">
        <strong><?= formatHoursMinutes($record['totals'][3]) ?></strong>
      </td>
    </tr>
    <tr>
      <td class="text-end">
        Total Sickness (<span style="color:<?= $typeColours['S'] ?>">S</span>):
      </td>
      <td class="text-end">
        <strong><?= formatHoursMinutes($record['totals'][4]) ?></strong>
      </td>
    </tr>
    <tr>
      <td class="text-end">
        Hours Total (<span style="color:<?= $typeColours['D'] ?>">D</span>+
        <span style="color:<?= $typeColours['H'] ?>">H</span>+
        <span style="color:<?= $typeColours['S'] ?>">S</span>):
      </td>
      <td class="text-end">
        <strong><?= formatHoursMinutes($record['hours_total']) ?></strong>
      </td>
    </tr>
    <tr>
      <td class="text-end">
        Nights (<span style="color:<?= $typeColours['N'] ?>">N</span>):
      </td>
      <td class="text-end">
        <strong><?= $record['nights_total'] ?></strong>
      </td>
    </tr>
  </table>
</div>






              <hr>
            
            </div>
            <?php endforeach; ?>

            <div class="mt-4 text-end"> 
            <a href="reportsOliTime-2_pdf.php?period=<?= (int)$scheduleId ?>" class="btn btn-primary" target="_blank">Print All to PDF</a>
              <!-- <a href="reportsOliTime-2_pdf.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-primary">Print All to PDF</a> -->
            </div>
              
            </div>
        </section>
      </section>
    </section>
  </section>
<?php include("../../include/footer-code.php"); ?>
</body>
</html>
