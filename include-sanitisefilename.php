<!-- START include-sanitisefilename.php -->
<!-- Input file name = <?php echo $inputname;; ?> -->
<?php

$slug1 = $inputname;
$pronameV = substr($slug1, 0, 100);
$pronameV = str_replace(".", "_", $pronameV);
$pronameV = str_replace(",", "_", $pronameV);
$pronameV = str_replace("/", "", $pronameV);
$pronameV = str_replace("\\", "", $pronameV);
$pronameV = str_replace("'", "", $pronameV);
$pronameV = str_replace("*", "", $pronameV);
$pronameV = str_replace("?", "", $pronameV);
$pronameV = str_replace("@", "_", $pronameV);
$pronameV = str_replace("&", "", $pronameV);
$pronameV = str_replace("%", "", $pronameV);
$pronameV = str_replace("(", "", $pronameV);
$pronameV = str_replace(")", "", $pronameV);
$pronameV = str_replace("[", "", $pronameV);
$pronameV = str_replace("]", "", $pronameV);
$pronameV = str_replace("=", "", $pronameV);
$pronameV = str_replace("-", "_", $pronameV);
$pronameV = str_replace(" ", "_", $pronameV);
$pronameV = str_replace("__", "_", $pronameV);

$slug = strtolower($pronameV);
?>
<!-- Ouput file name = <?php echo $slug;; ?> -->
<!-- END include-sanitisefilename.php -->