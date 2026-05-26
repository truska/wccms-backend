<!-- START report-exceptions-admin-login -->
<!-- WiteCanvasCMS ver 3.0 -->
<?php

error_reporting(1);
include('setting/main-top-files.php'); // Added by salva TDR | 16.1.2023

$TypeDebug = 'No'; // Yes or No

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<?php
	echo "<title>" . $form['title'] . " | Admin Log Exception Report</title>";
	include("include/header-code.php");
	?>
</head>

<body>
	<!-- Fixed navbar -->
	<?php
        include("include/header.php");
        include("include/sidebar.php");
        echo "<div id='cl-wrapper' class='fixed-menu'>";
	?>

        <section id="main-content">
            <section class="wrapper site-min-height">
                <!-- page start-->
                <div class="row">
                    <?php
                    echo "<h3>Log Exception Report " . $form["title"] . "</h3>";
                    ?>
                </div>
                <div class="content">
<?php
             /*   SELECT cl.username, cl.ip AS iplog, ci.ip AS allowedip, cl.created AS logcreated
                    FROM cms_log cl
                    LEFT JOIN cms_ip ci ON cl.ip = ci.ip
                    WHERE (ci.admin != 'Yes' OR ci.ip IS NULL)
                    AND cl.created >= NOW() - INTERVAL 1 YEAR;
*/
?>
                    <div class="col-sm-2 col-md-2"></div>
                </div>
            </section>
        </section>
	<?php
	    include("include/footer-code.php");
	    echo "</div>";
	?>

</body>

</html>

<!-- END report-exceptions-admin-login -->