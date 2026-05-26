<!-- START header-top -->
<?php
include("dbcon.php");
include('session.php');
include("functions.php");

//$prefs=loadPrefs($conn);
//include("logrecord.php");

if (!isset($_SESSION["user"])) {
	echo "<script>window.location='index.php'</script>";
}
?>