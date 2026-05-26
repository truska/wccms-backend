<!-- recordCopyv3 -->
<?php
error_reporting(1);

include('setting/main-top-files.php'); // Added by salva TDR | 16.03.2023

if ($_GET['frm'] && $_GET['id']) {
   if (!$formID = securityCheck($_GET['frm'], 'number')) {
      die('Error in the form'); // If the user try to insert something different from a number, we kill the script
   }
   if (!$recordID = securityCheck($_GET['id'], 'number')) {
      die('Error in the record'); // If the user try to insert something different from a number, we kill the script
   }

   $sql = "SELECT * FROM `cms_form`
   WHERE `id` = $formID";
   $form = mysqli_fetch_array(DB::query($sql), MYSQLI_ASSOC);

   if ($form) {
      $sql = "SELECT * FROM `cms_table`
      WHERE `id` = " . $form['table'];
      $table = mysqli_fetch_array(DB::query($sql), MYSQLI_ASSOC);

      if ($table) {
         $sql = "SELECT * FROM " . $table['name'] . "
         WHERE id = $recordID";
         $record = mysqli_fetch_array(DB::query($sql), MYSQLI_ASSOC);

         $sql = "INSERT INTO " . $table['name'] . " SET ";
         foreach ($record as $key => $value) {
            if ($key != 'id') {
               if ($key == 'showonweb') { // If the field is the showonweb, we set it to No
                  $value = 'No';
               }
               $value = securityCheck($value); 
               $sql .= "`$key` = '$value', "; 
            }
         }
         $sql = substr($sql, 0, -2); // remove last comma

         $response = DB::query($sql);

         // $action = "Copy Record ". $recordID ." | " . $table['name'];
         // $logtable = $tb;
         // $notes = "";

         $logtable = $table['name'];
         $action = "Copy Record ".$recordID." in Form " . $formID;
         $sqlquery = mysqli_real_escape_string($conn, $sql);
         $notes = "Making a COPY of Record ".$recordID."";
         $username = $_SESSION["useremail"];



         if ($response) {
            $query = "SELECT id from " . $table['name'] . " 
            ORDER BY id DESC 
            LIMIT 1";
            // set last id
            $recordnumber = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC)['id'];

            $errors = array_merge($errors, $insertResponse);
            saveLog($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordnumber);
      
            echo "<script>
               window.location='recordEditv".$prefs['prefCMSVer'].".php?frm=$formID&id=$recordnumber&copy=success'
            </script>";
         } else {
            saveLog($username, $action, $sqlquery, $logtable, 'FAIL', $notes, 0);

            echo "<script>
               window.location='recordViewv".$prefs['prefCMSVer'].".php?frm=$formID&copy=error'
            </script>";
         }
      } else {
         die('Error in the table');
      }
   }
} else {
   die('Error in the params');
}
?>