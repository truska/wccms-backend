<!-- ver -->
<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
error_reporting(1);

include('setting/main-top-files.php'); // Added by salva TDR | 7.12.2022

?>
<!-- TruskaCMS ver 4.0.0 -->

<?php

$url = "recordEditv".$prefs['prefCMSVer'].".php";

$toast = [];

?>
<!DOCTYPE html>
<html lang="en">
<!-- start html tag -->

<head>
   <link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap4.min.css" rel="stylesheet">


   <?php
   include("include/header-code.php");


   ?>


</head>

<body>
   <!-- Fixed navbar -->
   <?php
   include("include/header.php"); // Added by salva TDR | 2.12.2022
   include("include/sidebar.php");
    ?>

   <section id="main-content">
      <section class="wrapper site-min-height">

         <!-- page start-->
         <h3 style="padding-top:30px;">Version No: 2023-05-23</h3>
         <h5>Latest Notes:</h5>
         <p>Added 2FA and preferences Editing</p>
        <hr>
        <h5>Previous</h5>
        <table style='width:80%'>
            <tr><th style='width:15%'>Version</th><th>Notes</th></tr>
            <tr><td>Version</td><td>Notes</td></tr>
            <tr><td>Version</td><td>Notes</td></tr>
        </table>
        <hr>
        <h5>Last Sync with dev</h5>
        <?php
            // Specify the file path
            $file = './ver.php';

            // Check if the file exists
            if (file_exists($file)) {
                // Get the file's last modified time
                $lastModifiedTime = filemtime($file);

                // Format the timestamp into a readable date
                $formattedDate = date("F d Y H:i:s.", $lastModifiedTime);

                // Display the last modified date
                echo "The file was last modified on: " . $formattedDate;
            } else {
                echo "The file does not exist.";
            }
            ?>

      </section>
   </section>

   <?php
   include("include/footer.php");
   echo "</div>";
   include("include/footer-code.php");
   ?>
</body>

<!-- Bootstrap core JavaScript-->



</html>

<!-- END ver -->