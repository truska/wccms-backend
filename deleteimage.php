<?php
include("setting/main-top-files.php"); 

if (!$id = securityCheck($_GET["id"], 'number')) {
  $URL = "recordEditv".$prefs['prefCMSVer'].".php?frm=$frm&id=$id&del=error&msg=Wrong record id";
  echo "<script>window.location= '$URL'</script>";
}

if (!$frm = securityCheck($_GET["frm"], 'number')) {
  $URL = "recordEditv".$prefs['prefCMSVer'].".php?frm=$frm&id=$id&del=error&msg=Wrong form id";
  echo "<script>window.location= '$URL'</script>";
}

$col = securityCheck($_GET["name"]);

// Get form
$sql = "SELECT cms_form.*,t.title as table_title, t.name as table_name, t.showonweb as table_showonweb FROM `cms_form` 
LEFT JOIN `cms_table` t ON t.id = cms_form.table
WHERE cms_form.id = $frm";
$form = mysqli_fetch_array(DB::query($sql), MYSQLI_ASSOC);

// Get record
$sql = "SELECT * FROM `{$form['table_name']}` 
WHERE id = $id";
$record = mysqli_fetch_array(DB::query($sql), MYSQLI_ASSOC); 

// Update record
$sql = "UPDATE `{$form['table_name']}` SET `$col` = NULL 
WHERE id = $id";
$querydelimg = DB::query($sql);


$action = "Remove Image";
$form = $form['table_name'];
$notes = "$col Deleted";

if ($querydelimg) {
  $URL = "recordEditv".$prefs['prefCMSVer'].".php?frm=$frm&id=$id&del=success&msg=$col deleted successfully";
  saveLogV2('', $action, $sql, $form, 'SUCCESS', $notes, $id);
  echo "<script>window.location= '$URL'</script>";
} else {
  $URL = "recordEditv".$prefs['prefCMSVer'].".php?frm=$frm&id=$id&del=error&msg=Error deleting $col";
  saveLogV2('', $action, $sql, $form, 'FAIL', $notes, $id);
  echo "<script>window.location= '$URL'</script>";
}
