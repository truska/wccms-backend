<!-- START reportsOliTime-1 Report 1 -->
<!DOCTYPE html>

<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageType = 'Edit'; // Add, Edit, Copy, Delete

include('../../setting/main-top-files.php'); 
include('reportsOliTimeController.php');

// 1. Determine date range
$range = $_GET['range'] ?? 'thismonth';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
//$warn = false;

list($startDate, $endDate, $warn, $label) = getOliTimeDateRange($range, $start, $end);

// 2. Load records grouped by staff
$records = getOliTimeRecordsGroupedByStaff($startDate, $endDate);
?>

<html lang="en">
<head>  <?php include("../../include/header-code.php"); ?>
</head>
<body>
    <style>
        .action-icon {
            display: inline-block;
            width: 15px;
            text-align: center;
            margin: 0 3px;
        }

        .placeholder-icon {
            display: inline-block;
            width: 15px;
            height: 1em;
            opacity: 0.1;
        }
        .text-icon-faint {
            color: #ddd !important;
        }
        .grand-total-row {
            font-weight: bold;
            font-size: 1.4rem;
            color: #333;
        }
    </style>
    <section id="container">
        <?php 
            include("../../include/header.php"); 
            include("../../include/sidebar.php"); 
        ?>
        <section id="main-content">
            <section class="wrapper site-min-height">
                <section class="card" style="width:100%;margin-left: -10px">
                <div class="card-body">
                        <div class="col-12" style="margin-top:20px;">
                            <h2>Report 1: Time Records by Staff</h2>

                            <?php if ($warn): ?>
                                <div class="alert alert-warning">No dates selected – defaulting to <strong>This Month To Date</strong>.</div>
                            <?php endif; ?>


                        
                            <h5 class="mb-3"><strong>Date Range:</strong> <?= date('D j M', strtotime($startDate)) ?> – <?= date('D j M', strtotime($endDate)) ?> (<?= $label ?>)</h5>

                                <!--
                                    <p class="mt-3 mb-4 text-muted">
                                        Showing records from <strong><?php echo date('D j M', strtotime($startDate)); ?></strong> 
                                        to <strong><?php echo date('D j M', strtotime($endDate)); ?></strong>
                                    </p>
                                -->

                            <table class="table table-bordered table-sm table-striped">

                                <thead>
                                    <tr class="table-light">
                                        <th>Staff <span style="font-weight: 200;">&nbsp|&nbspTime ID</span></th>
                                        <th>Time From</th>
                                        <th>Time To</th>
                                        <th>Total Time (Rounded) <span style="font-weight: 200;">&nbsp;&nbsp;[Actual Time]</span></th>
                                        <th>Type</th>                    
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                        $grandTotal = 0;

                                        foreach ($records as $staffID => $rows):
                                        $staffName = $rows['staff_name'];
                                        $staffId   = $rows['staff_id'];
                                        $entries = $rows['entries'];
                                        $staffTotal = 0;



                                        // echo '<tr class="table-primary fw-bold"><td colspan="7">' . htmlspecialchars($staffName) . '</td></tr>';
                                        echo '<tr class="table-primary fw-bold">';
                                            echo '<td colspan="5">';
                                                echo '<h6><strong>'.htmlspecialchars($staffName).'</strong></h6>';
                                            echo '</td>' ;
                                            echo '<td>' ;
                                                echo "<a href='".$baseURL."/wccms/recordEditv4.php?frm=11&id=".$rows['staff_id']."' target='_blank'><i class='fas fa-edit text-success ms-3  fa-2x' title='Edit staff' style='cursor:pointer;' data-staff-id=".$rows['staff_id']."'></i></a>";
                                            echo '</td>';
                                        echo '</tr>';

                                        foreach ($entries as $row):
                                            $seconds = strtotime($row['timeto']) - strtotime($row['timefrom']);
                                            $actualHours = $seconds / 3600;
                                            $rounded = ceil($actualHours * 2) / 2; // To the next 30 mins
                                            $rounded = ceil($actualHours * 4) / 4;  // To the next 15 mins
                                            $acthours = floor($seconds / 3600);
                                            $actminutes = floor(($seconds % 3600)/60) ;

                                            $entryId = $row['id'];  // ← oli_times.id from DB

                                            $staffTotal += $rounded;   // add to staff subtotal
                                            $grandTotal += $rounded;   // add to overall grand total

                                        //   $total = $rounded * $row['rate'];
                                        //   $staffTotal += $total;
                                        //   $grandTotal += $total;
                                            
                                            $hasComment = !empty(trim($row['comment']));
                                            $hasNote = !empty(trim($row['notes']));
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($entryId); ?></td>
                                            <td><?php echo date('D d M Y H:i', strtotime($row['timefrom'])); ?></td>
                                            <td><?php echo date('D d M Y H:i', strtotime($row['timeto'])); ?></td>
                                            <td>
                                                <?php echo number_format($rounded, 2); ?> <!--hrs <small><em>[<?php echo number_format($actualHours, 2); ?> actual]</em></small>-->
                                                <small><em>[<?php echo $acthours ."hrs : ". $actminutes ; ?>mins actual]</em></small>]

                                            </td>
                                            <td>Type</td>
                                                <!-- <td>£<?php echo number_format($row['rate'], 2); ?></td>
                                                <td>£<?php echo number_format($total, 2); ?></td> -->
                                            
                                            <td class="text-left">
                                                
                                                <?php
                                                    $noteClass = !empty(trim($row['notes'])) ? 'text-info' : 'text-icon-faint';
                                                    $commentClass = !empty(trim($row['comment'])) ? 'text-warning' : 'text-icon-faint';
                                                ?>

                                                <!-- Comment icon -->
                                                <span class="action-icon">
                                                    <i class="fas fa-comment-alt <?php echo $commentClass; ?> fa-lg view-notes-comments"
                                                        title="View Comment"
                                                        style="cursor:pointer;"
                                                        data-note="<?php echo htmlspecialchars($row['notes'] ?? '', ENT_QUOTES); ?>"
                                                        data-comment="<?php echo htmlspecialchars($row['comment'] ?? '', ENT_QUOTES); ?>">
                                                    </i>
                                                </span>

                                                <!-- Note icon -->
                                                <span class="action-icon">
                                                    <i class="fas fa-sticky-note <?php echo $noteClass; ?> fa-lg view-notes-comments"
                                                        title="View Note"
                                                        style="cursor:pointer;"
                                                        data-note="<?php echo htmlspecialchars($row['notes'] ?? '', ENT_QUOTES); ?>"
                                                        data-comment="<?php echo htmlspecialchars($row['comment'] ?? '', ENT_QUOTES); ?>">
                                                    </i>
                                                </span>

                                                <!-- Verify icon -->
                                                <span class="action-icon">
                                                    <?php if ($row['verified'] === 'Yes'): ?>
                                                        <i class="fas fa-check-circle text-success fa-lg"
                                                        title="Verified – click to unverify"
                                                        style="cursor:pointer;"
                                                        onclick="toggleVerify('<?php echo $row['id']; ?>', 'No');"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-times-circle text-danger fa-lg"
                                                        title="Not verified – click to mark verified"
                                                        style="cursor:pointer;"
                                                        onclick="toggleVerify('<?php echo $row['id']; ?>', 'Yes');"></i>
                                                    <?php endif; ?>
                                                </span>

                                                <!-- Edit button -->
                                                <span class="action-icon">
                                                    <i class="fas fa-edit text-success fa-lg"
                                                        style="cursor:pointer;"
                                                        title="Edit entry"
                                                        onclick="editEntry('<?php echo $row['id']; ?>');">
                                                    </i>
                                                </span>

                                            </td>

                                        </tr>

                                        <?php endforeach; ?>
                                            <tr class="table-secondary fw-bold">
                                                <td colspan="5" class="text-end">Subtotal for <?php echo htmlspecialchars($staffName); ?>:</td>
                                                <td colspan="2"><?php echo number_format($staffTotal, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <tr style="background-color: #fff;">
                                            <td colspan="7" style="padding: 4px;"></td>
                                        </tr>

                                        <tr class="table-dark grand-total-row">
                                            <td colspan="5" class="text-end">Grand Total:</td>
                                            <td colspan="2"><?php echo number_format($grandTotal, 2); ?></td>
                                        </tr>
                                    
                                </tbody>
                            </table>

                            <hr>
                            <h5>Total Staff: <?php echo count($records); ?></h5>
                        </div>

                        <?php include("../../include/footer-code.php"); ?>
                    </div>
                </section>
            </section>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.view-notes-comments').forEach(function (icon) {
            icon.addEventListener('click', function () {
            const note = this.getAttribute('data-note') || '[None]';
            const comment = this.getAttribute('data-comment') || '[None]';
            document.getElementById('notesModalNote').innerText = note;
            document.getElementById('notesModalComment').innerText = comment;

            const modal = new bootstrap.Modal(document.getElementById('notesModal'));
            modal.show();
            });
        });
        });
    </script>

    <script>
        let verifyTarget = { id: null, status: null };

        function toggleVerify(id, status) {
        verifyTarget.id = id;
        verifyTarget.status = status;
        const verb = (status === 'Yes') ? 'verify' : 'unverify';
        document.getElementById('verifyModalBody').innerHTML = `Are you sure you want to <strong>${verb}</strong> this time entry?`;
        const modal = new bootstrap.Modal(document.getElementById('verifyModal'));
        modal.show();
        }

        function confirmVerifyAction() {
        const { id, status } = verifyTarget;
        fetch('reportsOliTimeController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=verify&id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
        })
        .then(res => res.text())
        .then(res => {
            if (res === 'OK') {
            location.reload();
            } else {
            alert("Failed to update verification.");
            }
        });
        }
    </script>

    <script>
        function editEntry(id) {
        alert("Edit function for entry ID: " + id + " (to be implemented)");
        // TODO: Redirect or open modal
        }
    </script>

    <script>
        function viewNotesComments(note, comment) {
        document.getElementById('notesModalNote').innerText = note || '[None]';
        document.getElementById('notesModalComment').innerText = comment || '[None]';
        const modal = new bootstrap.Modal(document.getElementById('notesModal'));
        modal.show();
        }
    </script>

    <!-- Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1" aria-labelledby="verifyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="verifyModalLabel">Confirm Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="verifyModalBody">
                <!-- Filled dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmVerifyAction()">Yes, proceed</button>
            </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="notesModalLabel">View Notes & Comments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                <strong>Note:</strong>
                <div class="border rounded p-2 bg-light" id="notesModalNote"></div>
                </div>
                <div>
                <strong>Comment:</strong>
                <div class="border rounded p-2 bg-light" id="notesModalComment"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            </div>
        </div>
    </div>

</body>
</html>
