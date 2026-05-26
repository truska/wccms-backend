<!-- START base page [20250710] -->
<!DOCTYPE html>

<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
 error_reporting(1);

include('../../setting/main-top-files.php'); 


// Find latest available payroll year to use by default
$defaultPayrollYear = null;
$yearsList = [];
$yearsRows = cms_db_fetch_all("SELECT DISTINCT hmrcyear FROM oli_payeschedule ORDER BY hmrcyear DESC");
foreach ($yearsRows as $y) {
  $yearsList[] = (int) $y["hmrcyear"];
}
$defaultPayrollYear = $yearsList ? $yearsList[0] : (int) date("Y");
$periodsEndpoint = "/wccms/custom/reports/reportsOliTimeController.php";
?>

<html lang="en">

<head>    <?php
        include("../../include/header-code.php");
    ?>

</head>

<body>
    <section id="container" class="">
        <?php
        include("../../include/header.php");
        include("../../include/sidebar.php");
        ?>


        <section id="main-content">
            <section class="wrapper site-min-height">

                <!-- START page main body area -->
                <section class="card" style="width:100%;margin-left: -10px">
                    <div class="row">
                        <div class="card-body">
                            <!-- <div class="col-md-1 hidden-sm hidden-xs"></div>  -->
                            <div class="col-sm-12 col-md-10 col-lg-10" style="margin-top:20px;">
                            
                            <h2 class="mb-4">OliTime Reports</h2>

                            <div class="row g-3">
                                <!-- Card 1: Report Selection -->
                                <div class="col-12 col-md-4">
                                  <div class="card h-100">
                                    <div class="card-header bg-primary text-white">1. Select Report</div>
                                    <div class="card-body">
                                      <select class="form-select" id="reportType">
                                        <option value="1">Report 1 - Summary</option>
                                        <option value="2">Report 2 - Detailed</option>
                                        <option value="3">Report 3 - Time Sheets</option>
                                      </select>
                                    </div>
                                  </div>
                                </div>

                                <!-- Card 2: Predefined Date Ranges -->
                                <div class="col-12 col-md-4">
                                  <div class="card h-100">
                                    <div class="card-header bg-info text-white">2. Select Date Range</div>
                                    <div class="card-body">
                                      <select class="form-select" id="dateRange">
                                        <option value="custom">Custom Date Range</option>
                                        <option value="today">Today</option>
                                        <option value="thisweek">This Week to Date</option>
                                        <option value="thismonth">This Month to Date</option>
                                        <option value="lastmonth">Last Month</option>

                                      </select>
                                    </div>
                                  </div>
                                </div>

                                <!-- Card 3: Custom Date Range -->
                                <div class="col-12 col-md-4">
                                  <div class="card h-100">
                                    <div class="card-header bg-warning text-dark">3. Custom Date Range</div>
                                    <div class="card-body">
                                      <div class="mb-3">
                                        <label for="startDate" class="form-label">From:</label>
                                        <input type="date" class="form-control" id="startDate">
                                      </div>
                                      <div>
                                        <label for="endDate" class="form-label">To:</label>
                                        <input type="date" class="form-control" id="endDate">
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>

                                <!-- View Report Button -->
                                <div class="text-end mt-4">
                                  <a href="#" class="btn btn-success" id="viewReportBtn">View Report</a>
                                </div>


<!-- Payroll Selection Row -->
<div class="row g-3 mt-4">
  <div class="col-12">
    <h2 class="mb-3">OliTime Payroll</h2>
  </div>

  <div class="col-12 col-md-6">
    <div class="card h-100">
      <div class="card-header bg-secondary text-white">Select Year & Period</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="payrollYear" class="form-label">Select Year</label>
          <select class="form-select" id="payrollYear"></select>
        </div>
        <div class="mb-3">
          <label for="payrollPeriod" class="form-label">Select Period</label>
          <select class="form-select" id="payrollPeriod"></select>
        </div>
        <div class="text-end">
          <a href="#" id="viewPayrollBtn" class="btn btn-success">View Payroll Report</a>
        </div>
      </div>
    </div>
  </div>
</div>




                            </div>


                            <?php


                            // START FOOTER FIXED STUFF

                            include("../../include/footer-code.php");
                            include("../../include-tinymce.php");
                            ?>
                        </div>
                    </div>
                </section>
                <!-- END page main body area -->
            </section>
        </section>
    </section>

<script>
  const DEFAULT_PAYROLL_YEAR = <?= (int)$defaultPayrollYear ?>;
  const PAYROLL_YEARS = <?= json_encode($yearsList ?: [ (int)date("Y")-1, (int)date("Y"), (int)date("Y")+1 ]) ?>;
  const PERIODS_ENDPOINT = "<?= htmlspecialchars($periodsEndpoint, ENT_QUOTES) ?>";
</script>

<script>
document.getElementById('viewReportBtn').addEventListener('click', function(e) {
    e.preventDefault();

    const reportType = document.getElementById('reportType').value;
    const dateRange = document.getElementById('dateRange').value;
    let url = '';

    // Set month/year defaults
    let today = new Date();
    let start = document.getElementById('startDate').value;
    let end = document.getElementById('endDate').value;
    let selectedMonth = today.getMonth() + 1; // JS months are 0-based
    let selectedYear = today.getFullYear();

    if (dateRange === 'custom' && start) {
      let startDate = new Date(start);
      selectedMonth = startDate.getMonth() + 1;
      selectedYear = startDate.getFullYear();
    } else if (dateRange === 'lastmonth') {
      let d = new Date();
      d.setMonth(d.getMonth() - 1);
      selectedMonth = d.getMonth() + 1;
      selectedYear = d.getFullYear();
    } else if (dateRange === 'thismonth') {
      selectedMonth = today.getMonth() + 1;
      selectedYear = today.getFullYear();
    } else if (dateRange === 'today' || dateRange === 'thisweek') {
      selectedMonth = today.getMonth() + 1;
      selectedYear = today.getFullYear();
    }

    if (reportType === '3') {
      url = `reportsOliTime-2.php?month=${selectedMonth}&year=${selectedYear}`;
    } else {
      url = `reportsOliTime-${reportType}.php?range=${dateRange}`;
      if (dateRange === 'custom') {
        if (!start || !end) {
          alert('Please select both start and end dates');
          return;
        }
        url += `&start=${start}&end=${end}`;
      }
    }

    window.location.href = url;
  });
</script>




<script>
function loadPeriods(year) {
  // Show a loading option while fetching
  const periodSel = document.getElementById('payrollPeriod');
  periodSel.innerHTML = '<option value="">Loading…</option>';

  fetch(`${PERIODS_ENDPOINT}?action=getPeriods&year=${encodeURIComponent(year)}`)
    .then(res => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (!Array.isArray(data) || data.length === 0) {
        periodSel.innerHTML = '<option value="">[No periods found for this year]</option>';
        return;
      }
      let options = "";
      data.forEach(p => {
        options += `<option value="${p.id}" data-period="${p.period}">
                      Period ${p.period} (${p.datefrom} – ${p.dateto})
                    </option>`;
      });
      periodSel.innerHTML = options;
      // Ensure something is selected so the button has values
      periodSel.selectedIndex = 0;
      // Debug: uncomment to verify values quickly
      // console.log('Loaded periods:', data);
    })
    .catch(err => {
      console.error('Failed to load periods:', err);
      periodSel.innerHTML = '<option value="">[Failed to load periods]</option>';
    });
}

function populateYears() {
  const yearSel = document.getElementById('payrollYear');
  // Fill with years from the DB (plus fallback if needed)
  yearSel.innerHTML = (PAYROLL_YEARS.length ? PAYROLL_YEARS : [DEFAULT_PAYROLL_YEAR-1, DEFAULT_PAYROLL_YEAR, DEFAULT_PAYROLL_YEAR+1])
    .map(y => `<option value="${y}" ${y === DEFAULT_PAYROLL_YEAR ? 'selected' : ''}>${y}</option>`)
    .join('');

  // Now load periods for the default year from DB
  loadPeriods(DEFAULT_PAYROLL_YEAR);
}

document.getElementById('payrollYear').addEventListener('change', function() {
  loadPeriods(this.value);
});

populateYears();

// IMPORTANT: bind the payroll button (not the general "View Report" button)
document.getElementById('viewPayrollBtn').addEventListener('click', function(e) {
  e.preventDefault();
  const periodSel = document.getElementById('payrollPeriod');
  const frm = periodSel.value; // oli_payeschedule.id
  const periodNum = periodSel.options[periodSel.selectedIndex]?.dataset.period;

  if (!frm || !periodNum) {
    alert("Please select a valid year and period.");
    return;
  }

  // EXACT URL required:
  window.location.href = `reportsOliTime-2.php?frm=${encodeURIComponent(frm)}&period=${encodeURIComponent(periodNum)}`;
});
</script>



</body>

</html>

<!-- END base page -->
