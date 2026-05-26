<?php

require_once (dirname(__FILE__) . '/../../../../private/dbcon.php');
require_once (dirname(__FILE__) . '/../../includes/db.php');

function getOliTimeDateRange($range, $start, $end) {
    $warn = false;
    $label = 'Unknown';
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');

    switch ($range) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            $label = 'Today';
            break;

        case 'thisweek':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d', strtotime('sunday this week'));
            $label = 'This Week';
            break;

        case 'thismonth':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $label = 'This Month';
            break;

        case 'lastmonth':
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            $label = 'Last Month';
            break;

        case 'custom':
            if (!empty($start) && !empty($end)) {
                $startDate = $start;
                $endDate = $end;
                $label = 'Custom';
            } else {
                $warn = true;
                $label = 'Custom (Missing Dates)';
            }
            break;

        default:
            $warn = true;
            $label = 'Invalid Range';
            break;
    }
    // DEBUG
    //echo "<pre>Returning: ", print_r([$startDate, $endDate, $warn, $label], true), "</pre>";

    return [$startDate, $endDate, $warn, $label];
}


function getOliTimeRecordsGroupedByStaff($startDate, $endDate) {
    $sql = "
        SELECT 
            oli_times.*,
            oli_staff.name AS staff_first,
            oli_staff.surname AS staff_last
        FROM oli_times
        LEFT JOIN oli_staff ON oli_staff.id = oli_times.name
        WHERE DATE(oli_times.timefrom) <= ? 
        AND DATE(oli_times.timeto) >= ?
        ORDER BY oli_staff.surname, oli_staff.name, oli_times.timefrom
    ";
    $rows = cms_db_fetch_all($sql, [$endDate, $startDate]);
    
    $grouped = [];

    foreach ($rows as $row) {
        $staffID = $row['name'];
        $fullName = trim($row['staff_first'] . ' ' . $row['staff_last']);
        if (!isset($grouped[$staffID])) {
            $grouped[$staffID] = [
                'staff_id'    => $staffID,
                'staff_first' => $row['staff_first'],
                'staff_last'  => $row['staff_last'],
                'staff_name'  => $fullName,
                'entries'     => []
            ];
        }

        $grouped[$staffID]['entries'][] = $row;

    }

    return $grouped;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $id = intval($_POST['id']);
    $status = ($_POST['status'] === 'Yes') ? 'Yes' : 'No';

    $result = verifyOliTimeRecord($id, $status);
    echo $result ? 'OK' : 'FAIL';
    exit;
}

function verifyOliTimeRecord($id, $status) {
    try {
        cms_db_execute(
            "UPDATE oli_times SET verified = :status WHERE id = :id",
            [
                ':status' => $status,
                ':id' => (int) $id,
            ]
        );
        return true;
    } catch (Throwable $e) {
        error_log("Verify SQL FAILED: " . $e->getMessage());
        return false;
    }
}


function getPeriodsByYear($year) {
    $sql = "SELECT id, period, hmrcyear,
                   DATE_FORMAT(datefrom, '%d %b') AS datefrom,
                   DATE_FORMAT(dateto, '%d %b %Y') AS dateto
            FROM oli_payeschedule
            WHERE hmrcyear = ?
            ORDER BY period ASC";
    return cms_db_fetch_all($sql, [(int) $year]);
}

// --- AJAX Handler for Payroll Periods ---
if (isset($_GET['action']) && $_GET['action'] === 'getPeriods') {
    $year = intval($_GET['year'] ?? date("Y"));
    $periods = getPeriodsByYear($year);


    header('Content-Type: application/json');
    echo json_encode($periods);
    exit;
}


?>
