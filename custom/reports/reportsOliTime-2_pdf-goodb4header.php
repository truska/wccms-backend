<?php
ob_start(); // suppress accidental output

require_once('../../tcpdf/tcpdf.php');
include('../../setting/main-top-files.php');

// --- Get pay period from oli_payeschedule ---
$periodId = $_GET['period'] ?? null;
if (!$periodId) {
    die("No period specified");
}

// --- Get optional staff ID ---
$staffIdFilter = isset($_GET['id']) ? intval($_GET['id']) : 0;

$periodRow = cms_db_fetch_one(
    "SELECT id, hmrcyear, period, datefrom, dateto, name
     FROM oli_payeschedule
     WHERE id = :id
     LIMIT 1",
    [':id' => (int) $periodId]
);
if (!$periodRow) {
    die("Invalid pay period selected.");
}
$periodYear  = $periodRow['hmrcyear'];
$periodNum   = $periodRow['period'];
$startDate   = $periodRow['datefrom'];
$endDate     = $periodRow['dateto'];

// --- Load staff ---
$staff = [];
$sql = "SELECT id, name, surname
        FROM oli_staff 
        WHERE archived = 0";
$params = [];
if ($staffIdFilter > 0) {
    $sql .= " AND id = :staff_id";
    $params[':staff_id'] = $staffIdFilter;
}
$sql .= " ORDER BY surname, name";

foreach (cms_db_fetch_all($sql, $params) as $row) {
    $days = [];
    $dateIter = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        (new DateTime($endDate))->modify('+1 day')
    );
    foreach ($dateIter as $d) {
        $days[$d->format('Y-m-d')] = [1=>0, 2=>0, 3=>0, 4=>0];
    }

    $staff[$row['id']] = [
        'name'   => $row['name'] . ' ' . $row['surname'],
        'days'   => $days,
        'totals' => [1=>0, 2=>0, 3=>0, 4=>0],
        'hours_total'  => 0,
        'nights_total' => 0,
    ];
}

// --- Load times ---
$timeRows = cms_db_fetch_all(
    "SELECT name, timefrom, timeto, type
     FROM oli_times
     WHERE DATE(timefrom) BETWEEN :start_date AND :end_date
     AND archived = 0
     AND showonweb = 'Yes'",
    [
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]
);

foreach ($timeRows as $row) {
    $staffId = $row['name']; // staff ID in oli_times.name
    if (!isset($staff[$staffId])) continue;

    $dayKey = date('Y-m-d', strtotime($row['timefrom']));
    $seconds = strtotime($row['timeto']) - strtotime($row['timefrom']);

    if ($seconds <= 0) {
        $staff[$staffId]['days'][$dayKey][$row['type']] = 'ERR';
        continue;
    }

    if ($row['type'] == 2) {
        // Night shift = 1 per record
        $staff[$staffId]['days'][$dayKey][2] += 1;
        $staff[$staffId]['totals'][2] += 1;
        $staff[$staffId]['nights_total'] += 1;
    } else {
        $hours = $seconds / 3600;
        $hours = round($hours * 4) / 4;
        $staff[$staffId]['days'][$dayKey][$row['type']] += $hours;
        $staff[$staffId]['totals'][$row['type']] += $hours;
        $staff[$staffId]['hours_total'] += $hours;
    }
}

    // --- Setup PDF ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('helvetica', '', 10);

    // --- Build report for each staff ---
    $typeLabels = [1=>'D-', 2=>'N-', 3=>'H-', 4=>'S-'];

foreach ($staff as $sid => $rec) {
    if ($rec['hours_total'] <= 0 && $rec['nights_total'] <= 0) continue;

    $pdf->AddPage();

    // Header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'EMPLOYEE TIMESHEET', 0, 1, 'C');
    $pdf->Image('wccms/img/wite-canvas-logo-sq-no-tag-150.jpg', 160, 10, 30);

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Employee Name: ' . $rec['name'] . " ($sid)", 0, 1);
    $pdf->Cell(0, 6, 'Period: ' . $periodNum . " ($periodYear)", 0, 1);
    $pdf->Cell(0, 6, 'Dates: ' . date('d M', strtotime($startDate)) . " – " . date('d M Y', strtotime($endDate)), 0, 1);

    $pdf->Ln(4);

    // Grid: loop 7 days per row
    $dateIter = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        (new DateTime($endDate))->modify('+1 day')
    );
    $dates = iterator_to_array($dateIter);

    for ($i = 0; $i < count($dates); $i += 7) {
        // Header row with day numbers
        foreach (array_slice($dates, $i, 7) as $d) {
            $day = $d->format('jS');
            $pdf->Cell(25, 7, $day, 1, 0, 'C');
        }
        $pdf->Ln();

        // Data row
        foreach (array_slice($dates, $i, 7) as $d) {
            $dayKey = $d->format('Y-m-d');
            $cellData = [];

            foreach ($typeLabels as $t => $prefix) {
                $h = $rec['days'][$dayKey][$t] ?? 0;
                if ($h === 'ERR') {
                    $cellData[] = $prefix.'ERR';
                } elseif ($h > 0) {
                    if ($t == 2) {
                        $cellData[] = $prefix . intval($h);
                    } else {
                        $mins = round($h * 60);
                        $hrs  = floor($mins / 60);
                        $rem  = $mins % 60;
                        $cellData[] = $prefix . sprintf("%d:%02d", $hrs, $rem);
                    }
                }
            }

            // Group into rows of 2
            $lines = [];
            for ($idx = 0; $idx < count($cellData); $idx += 2) {
                $chunk = array_slice($cellData, $idx, 2);
                $lines[] = implode("  ", $chunk);
            }
            $text = implode("\n", $lines);

            // MultiCell ensures proper line breaks
            $pdf->MultiCell(25, 10, $text, 1, 'C', false, 0);
        }
        $pdf->Ln();
    }

    // Totals
    $pdf->Cell(80, 8, 'Total Worked (D): ' . sprintf("%d:%02d",
        floor($rec['totals'][1]), fmod($rec['totals'][1]*60, 60)), 0, 1);
    $pdf->Cell(80, 8, 'Total Holiday (H): ' . sprintf("%d:%02d",
        floor($rec['totals'][3]), fmod($rec['totals'][3]*60, 60)), 0, 1);
    $pdf->Cell(80, 8, 'Total Sickness (S): ' . sprintf("%d:%02d",
        floor($rec['totals'][4]), fmod($rec['totals'][4]*60, 60)), 0, 1);
    $pdf->Cell(80, 8, 'Hours Total (D+H+S): ' . sprintf("%d:%02d",
        floor($rec['hours_total']), fmod($rec['hours_total']*60, 60)), 0, 1);
    $pdf->Cell(80, 8, 'Nights (N): ' . $rec['nights_total'], 0, 1);

    // --- Signature & Notes ---
    $pdf->Ln(10);

    // Get usable page width
    $margins = $pdf->getMargins();
    $pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $colWidth  = $pageWidth / 2;

    // Line 1: Signatures
    $pdf->Cell($colWidth, 6, 'Employee Signature ____________________________', 0, 0, 'L');
    $pdf->Cell($colWidth, 6, 'Employer Signature ____________________________', 0, 1, 'R');

    // Line 2: Dates (aligned under each signature)
    $pdf->Cell($colWidth, 6, 'Date __________________', 0, 0, 'L');
    $pdf->Cell($colWidth, 6, 'Date __________________', 0, 1, 'R');


    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, "Employers Only: Please return to:\nHealth Your Way CIC, Payroll, PO Box 240, Wirral CH31 9FQ\nor email to: payroll@healthyourway.co.uk", 0, 'L');
}

ob_end_clean();
$pdf->Output('employee-timesheet.pdf', 'I');
