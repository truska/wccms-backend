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

/*
    echo "<pre>";
    echo "periodId = " . $periodId . "\n";
    echo "periodNum = " . $periodNum . "\n";
    echo "periodYear = " . $periodYear . "\n";
    echo "startDate = " . $startDate . "\n";
    echo "endDate = " . $endDate . "\n";
    echo "</pre>";
    exit;
*/

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

// ------------------------------------------




    // --- Setup PDF ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('helvetica', '', 10);

    // --- Build report for each staff ---
    $typeLabels = [1=>'D-', 2=>'N-', 3=>'H-', 4=>'S-'];



    function printTotalRow1($pdf, $label, $value, $labelW, $valueW) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell($labelW, 8, $label, 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($valueW, 8, $value, 0, 1, 'R');
    }






foreach ($staff as $sid => $rec) {
    if ($rec['hours_total'] <= 0 && $rec['nights_total'] <= 0) continue;

    $pdf->AddPage();

    // Header
// --- HEADER AREA ---

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Employee Timesheet', 0, 1, 'L');
//$pdf->Image('wccms/img/wite-canvas-logo-sq-no-tag-150.jpg', 165, 10, 30);

$pdf->Ln(4);
$pdf->SetFont('helvetica', '', 10);

// Use full printable width for dynamic sizing
$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
$colW1 = $pageWidth * 0.21;  // label left
$colW2 = $pageWidth * 0.34;  // value left
$colW3 = $pageWidth * 0.21;  // label right
$colW4 = $pageWidth * 0.24;  // value right

// --- Row 1: Employer name + Payroll no. ---
$pdf->SetFont('', 'B');
$pdf->Cell($colW1, 8, 'EMPLOYER NAME', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->SetTextColor(192, 0, 0); // #C00000
$pdf->SetFont('', 'B');
$pdf->Cell($colW2, 8, 'Julie Cunningham', 1, 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('', 'B');
$pdf->Cell($colW3, 8, 'PAYROLL NO.', 1, 0, 'L');
$pdf->SetFont('', 'B');
$pdf->SetTextColor(192, 0, 0);
$pdf->Cell($colW4, 8, $sid, 1, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// --- Row 2: Client Reference (spans right side) ---
$clientHTML = '
<b><span style="color:#C00000;">OLI-CUN</span></b><br>
<span style="color:#333333;">(Name of the person who the PHB is for if different from the named Employer)</span>
';
$pdf->SetFont('', 'B');
$pdf->Cell($colW1, 12, 'CLIENT REFERENCE', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->writeHTMLCell($colW2 + $colW3 + $colW4, 12, '', '', $clientHTML, 1, 1, false, true, 'L', true);

// --- Row 3: Month/Year + Employee Name ---
$periodLine = "Period: <b><span style='color:#C00000;'>$periodNum ($periodYear)</span></b>";
$dateLine   = "Dates: <b><span style='color:#C00000;'>" . date('d M', strtotime($startDate)) . " – " . date('d M Y', strtotime($endDate)) . "</span></b>";
$monthYearHTML = $periodLine . '<br>' . $dateLine;

$pdf->SetFont('', 'B');
$pdf->Cell($colW1, 10, 'MONTH/ YEAR', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->writeHTMLCell($colW2, 10, '', '', $monthYearHTML, 1, 0, false, true, 'L', true);

$pdf->SetFont('', 'B');
$pdf->Cell($colW3, 10, 'EMPLOYEE NAME', 1, 0, 'L');
$pdf->SetFont('', 'B');
$pdf->SetTextColor(192, 0, 0);
$pdf->Cell($colW4, 10, $rec['name'], 1, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(6);




    // Grid: loop 7 days per row
    $dateIter = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        (new DateTime($endDate))->modify('+1 day')
    );
    $dates = iterator_to_array($dateIter);


// make sure base font is NOT bold before writing HTML
$pdf->SetFont('helvetica', '', 9); // family, style '', size (size here is just a base)


for ($i = 0; $i < count($dates); $i += 7) {

    // Header row with small weekday/month + larger bold day, one line
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(240, 240, 240); // light grey
    $cellH = 8; // tighter than 12

    foreach (array_slice($dates, $i, 7) as $d) {
        $weekday = $d->format('D');
        $dayNum  = $d->format('j');
        $month   = $d->format('M');

        // HTML text using <font> tags for size
        $dayHTML = 
            '<font size="7">' . $weekday . '&nbsp;</font>' .
            '<b><font size="10">' . $dayNum . '</font></b>&nbsp;' .
            '<font size="7">' . $month . '</font>';

        // Write filled cell — TCPDF will centre vertically with autopadding
        $pdf->writeHTMLCell(
            25,                // width
            $cellH,            // height (smaller)
            '', '',            // x,y auto
            $dayHTML,          // content
            1,                 // border
            0,                 // no line break
            true,              // fill (use SetFillColor)
            true,              // reset height
            'C',               // centre align
            true               // autopadding for vertical centring
        );
    }

    $pdf->Ln($cellH); // move to next row






        $pdf->SetFont('helvetica', 'b', 9); // family, style '', size (size here is just a base) 

        // Data row
        foreach (array_slice($dates, $i, 7) as $d) {
            $dayKey = $d->format('Y-m-d');
            $cellData = [];

            foreach ($typeLabels as $t => $prefix) {
                $h = $rec['days'][$dayKey][$t] ?? 0;



if ($h === 'ERR') {
    $cellData[] = $prefix . 'ERR';
} elseif ($h > 0) {
    if ($t == 2) {
        // Nights (count) – keep as integer
        $cellData[] = $prefix . intval($h);
    } else {
        // Convert to hours with 0.25, 0.5, 0.75 increments
        $decimal = round($h * 4) / 4; // already quarter-hour rounded
        $cellData[] = $prefix . number_format($decimal, 2);
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
// --- Totals (two aligned columns) ---
$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 10);

// Define column widths based on usable page width
$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
$labelW = $pageWidth * 0.15;  // left label
$valueW = $pageWidth * 0.15;  // right-aligned value



// Generate values with correct formatting
$worked = sprintf("%d:%02d", floor($rec['totals'][1]), fmod($rec['totals'][1]*60, 60));
$holiday = sprintf("%d:%02d", floor($rec['totals'][3]), fmod($rec['totals'][3]*60, 60));
$sick = sprintf("%d:%02d", floor($rec['totals'][4]), fmod($rec['totals'][4]*60, 60));
$total = sprintf("%d:%02d", floor($rec['hours_total']), fmod($rec['hours_total']*60, 60));
$nights = $rec['nights_total'];

// Output each line
// --- Totals (two aligned columns) ---
$pdf->Ln(1);
$pdf->SetFont('helvetica', '', 10);

// Define column widths based on usable page width
$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
$labelW = $pageWidth * 0.15;  // left label
$valueW = $pageWidth * 0.15;  // right-aligned value



// Generate values with correct formatting
$worked  = sprintf("%d:%02d", floor($rec['totals'][1]), fmod($rec['totals'][1]*60, 60));
$holiday = sprintf("%d:%02d", floor($rec['totals'][3]), fmod($rec['totals'][3]*60, 60));
$sick    = sprintf("%d:%02d", floor($rec['totals'][4]), fmod($rec['totals'][4]*60, 60));
$total   = sprintf("%d:%02d", floor($rec['hours_total']), fmod($rec['hours_total']*60, 60));
$nights  = $rec['nights_total'];

// Output each line
printTotalRow1($pdf, 'Total Worked (D):', $worked, $labelW, $valueW);
printTotalRow1($pdf, 'Total Holiday (H):', $holiday, $labelW, $valueW);
printTotalRow1($pdf, 'Total Sickness (S):', $sick, $labelW, $valueW);
printTotalRow1($pdf, 'Hours Total (D+H+S):', $total, $labelW, $valueW);
printTotalRow1($pdf, 'Nights (N):', $nights, $labelW, $valueW);



// --- Further Information + Office Use Section ---
// --- Further Information + Office Use Section ---
$pdf->Ln(8);
$pdf->SetFont('helvetica', '', 10);

$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];

// Split roughly 70/30 between main and office-use columns
$leftW  = $pageWidth * 0.6;
$rightW = $pageWidth * 0.4;

// Header row
$pdf->SetFont('', 'B');
$pdf->Cell($leftW, 8, 'Further Information:', 1, 0, 'L');
$pdf->Cell($rightW, 8, 'Office Use only', 1, 1, 'C');

// Left column multi-line blank box, right column three stacked rows
$leftH = 24; // height for notes area

// Draw left blank box
$pdf->SetFont('', '');
$pdf->Cell($leftW, $leftH, '', 1, 0, 'L');

// Right-hand stacked rows
$rowH = $leftH / 3;

// Row 1: Date received
$pdf->SetFont('', 'B');
$pdf->Cell($rightW / 2, $rowH, 'Date received', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->Cell($rightW / 2, $rowH, '', 1, 1, 'L');

// Row 2: Payroll completed
$pdf->SetFont('', 'B');
$pdf->Cell($leftW, $rowH, '', 0, 0); // skip left section
$pdf->Cell($rightW / 2, $rowH, 'Payroll completed', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->Cell($rightW / 2, $rowH, '', 1, 1, 'L');

// Row 3: Employee paid
$pdf->SetFont('', 'B');
$pdf->Cell($leftW, $rowH, '', 0, 0); // skip left section
$pdf->Cell($rightW / 2, $rowH, 'Employee paid', 1, 0, 'L');
$pdf->SetFont('', '');
$pdf->Cell($rightW / 2, $rowH, '', 1, 1, 'L');




    // --- Signature & Notes ---


// Page width calculations
$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
$colWidth  = $pageWidth / 2;

// Employee signature image (left column)
// --- Signature Section (Employer left with image + date, Employee right with lines) ---
$pdf->Ln(10);

// Calculate usable width
$margins = $pdf->getMargins();
$pageWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
$colWidth  = $pageWidth / 2;

$yStart = $pdf->GetY();
$lineHeight = 6;
$imageWidth = 40;
$imageHeight = 15; // estimated image height

// --- LEFT COLUMN: Employer (image + today's date) ---
$signaturePath = $baseURL . "/filestore/images/wccms/prc.jpg";
$imgY = $yStart - 2; // small tweak to align visually
if (file_exists($_SERVER['DOCUMENT_ROOT'] . parse_url($signaturePath, PHP_URL_PATH))) {
    $pdf->SetXY($margins['left'] + 5, $imgY);
    $pdf->Image($signaturePath, '', '', $imageWidth, $imageHeight);
}
$pdf->Ln($imageHeight + 3);
$yDate = $pdf->GetY();
$pdf->SetXY($margins['left'], $yDate);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($colWidth, $lineHeight, date('D d M Y'), 0, 0, 'L');

// --- RIGHT COLUMN: Employee (signature + date lines) ---
$pdf->SetXY($margins['left'] + $colWidth, $yStart);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($colWidth, $lineHeight, 'Employee Signature ____________________________', 0, 1, 'L');

// Date line directly below employee signature
$pdf->SetXY($margins['left'] + $colWidth, $yDate);
$pdf->Cell($colWidth, $lineHeight, 'Date __________________', 0, 1, 'L');




    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, "Employers Only: Please return to:\nHealth Your Way CIC, Payroll, PO Box 240, Wirral CH31 9FQ\nor email to: payroll@healthyourway.co.uk", 0, 'L');
}

ob_end_clean();
$pdf->Output('employee-timesheet.pdf', 'I');
