<!-- START recordReportv4 -->

<!DOCTYPE html>
<?php
  // Default Error Of - Preferences can turn them [prefShowErrorsCMS]
  error_reporting(0);
  ini_set('display_errors', 0);

  $PageType = 'Add'; // Add, Edit, Copy, Delete

  include('setting/main-top-files.php');
  include('controllers/recordReport.php');

// Validate inputs
if (!$formnumber = securityCheck($_GET['frm'], 'number')) {
    die('Error in the form');
}

// Init Report
$report = new RecordReport($formnumber);
$form   = $report->getForm();
$fields = $report->getFormFields();
$data   = $report->getTableContent();
?>
<html lang="en">
<head>
    <?php include("include/header-code.php"); ?>
    <style>
    .field-row { padding: 8px 0; border-bottom: 1px solid #eee; }
    .field-label { font-weight: 600; width: 30%; max-width: 250px; }
    .field-value { width: 70%; }
    @media print {
      .no-print { display: none !important; }
      .card { box-shadow: none; border: 1px solid #ddd; }
    }
    </style>
</head>

<body>
<section id="container" class="">
    <?php
    include("include/header.php");
    include("include/sidebar.php");
    ?>

    <!--main content start-->
    <section id="main-content">
        <section class="wrapper site-min-height">

            <div class="row">
                <div class="col-lg-12">

                    <h2>
                        <span style="font-weight:200;">
                          <?= htmlspecialchars($form['title'] ?? $form['name'] ?? 'Report'); ?>
                        </span>
                    </h2>

                    <div class="no-print mb-3">
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button>
                        <button class="btn btn-primary" id="sendReportBtn">Send Report</button>
                    </div>

                    <?php if (empty($fields)): ?>
                        <div class="alert alert-warning">No report fields enabled (set <code>inreport=Yes</code>).</div>
                    <?php endif; ?>

                    <?php if (empty($data)): ?>
                        <div class="alert alert-info">No records found in <code><?= htmlspecialchars($report->tablename ?? '') ?></code>.</div>
                    <?php else: ?>
                      <?php foreach ($data as $row): ?>
                            <section class="card">
                              <div class="card-body">

                                <!-- Record title with ID -->
                                <h5 style="font-weight:200;">
                                  <?= htmlspecialchars($form['title'] ?? $form['name'] ?? 'Record'); ?>
                                  [<?= intval($row['id']); ?>]
                                </h5>
                                <hr>

                                <?php foreach ($fields as $ff): ?>

                                        <div class="d-flex gap-3 field-row">
                                            <div class="field-label"><?= htmlspecialchars($ff['label'] ?? $ff['name']); ?></div>
                                            <div class="field-value"><?= $report->renderValue($row, $ff); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

        </section>
    </section>
</section>

<?php
include("include/footer-code.php");
// include("include/footer.php"); // (not present in your wccms/include, as you noted)
?>

</body>
</html>
<!-- END recordReportv1 -->
