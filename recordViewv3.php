<!-- recordViewv3 -->
<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
error_reporting(1);

include('setting/main-top-files.php'); // Added by salva TDR | 7.12.2022

?>
<!-- TruskaCMS ver 4.0.0 -->

<!-- Require sorttables.php for sort to work -->

<?php

// echo "start pre render loading" ; 
//Bring Fwd variables

// --- START edited by salva TDR | 7.12.2022 ---

// $formnumber = $_GET['frm']; // Old code, 0 security

// * We need to improve the security of the site, we force to get just a number and we remove the possibility to insert any sql injection or whatever (now is in a function) *
if ($_GET['frm']) {
   if (!$formnumber = securityCheck($_GET['frm'], 'number')) {
      die('Error in the form'); // If the user try to insert something different from a number, we kill the script
   }
}

// * Removed the old edit page, now we control all edits with recordEditv3 *
// if ($formnumber == 11) {
//    $url = "edit.pagel2.php";
// } else {
//    $url = "recordEditv3.php";
// }

$url = "recordEditv".$prefs['prefCMSVer'].".php";

// --- START Added by salva TDR | 12.12.2022 ---
$VIEW = new RecordView($formnumber);
$table = $VIEW->getTable();

if (isset($_GET["show"])) { // If the user change the status of show on web
   // Get the record id
   if (!$recordID = securityCheck($_GET['id'], 'number')) {
      die('Error in the record id');
   }
   // Get the show value
   if (!$showdata = securityCheck($_GET['show'])) {
      die('Error in the show value');
   }
   // Update the record
   $updateshowonweb = $VIEW->updateShowOnWeb($recordID, $table['name'], $showdata);

      $logtable = $table['name'];
      $action = "Show/Hide Record ".$recordID." in Form " . $formnumber ;
      $sqlquery = mysqli_real_escape_string($conn, $updateshowonweb["query"]);
      $notes = "Toggling Show/Hide of Record ".$recordID."";
      $username = $_SESSION["useremail"];

   if ($updateshowonweb) {
      saveLog($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordID);
      echo "<script>
         window.location='recordViewv3.php?frm=$formnumber&status=success'
      </script>";
   } else {
      saveLog($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordID);
      echo "<script>
         window.location='recordViewv3.php?frm=$formnumber&status=error'
      </script>";
   }
}

if (isset($_GET['act'])) {
   if (!$action = securityCheck($_GET['act'])) {
      die('Error in the action');
   }

   if ($action === "delete") {
      if (!$recordID = securityCheck($_GET['id'])) {
         die('Error in the record id');
      }

      $delete = $VIEW->deleteContent($recordID, $table['name']);

      $logtable = $table['name'];
      $action = "DELETE Record ".$recordID." in Form " . $formnumber;
      $sqlquery = mysqli_real_escape_string($conn, $delete["query"]);
      $notes = "Deleting of Record ".$recordID."";
      $username = $_SESSION["useremail"];

      if ($delete["success"]) {
         // log Success
         saveLog($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordID);
         echo "<script>
            window.location='recordViewv3.php?frm=$formnumber&delete=success'
         </script>";
      } else {
         saveLog($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordID);
         echo "<script>
            window.location='recordViewv3.php?frm=$formnumber&delete=error'
         </script>";
      }
   }

   if ($action === 'undelete') {
      if (!$recordID = securityCheck($_GET['id'])) {
         die('Error in the record id');
      }



      $undelete = $VIEW->undeleteContent($recordID, $table['name']);


      
         $logtable = $table['name'];
         $action = "UNDELETE Record ".$recordID." in Form " . $formnumber;
         $sqlquery = mysqli_real_escape_string($conn, $undelete["query"]);
         $notes = "UnDeleting Record ".$recordID."";
         $username = $_SESSION["useremail"];

      if ($undelete['success']) {
         // log Success
         saveLog($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordID);
         echo "<script>
            window.location='recordViewv3.php?frm=$formnumber&undelete=success'
         </script>";
      } else {
         saveLog($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordID);
         echo "<script>
            window.location='recordViewv3.php?frm=$formnumber&undelete=error'
         </script>";
      }
   }
}

$form = $VIEW->getForm();
$actions = $VIEW->getActions();

$issortable = $form["issortable"];
$sortcol = $form["sortcol"];

$tbName = $table['name'];
// --- END Added by salva TDR | 12.12.2022 ---

// --- START Removed by salva TDR | 12.12.2022 ---
//Get the Form
// $selectform = "SELECT * FROM `cms_form` WHERE `id` = '" . $formnumber . "' ";

// $queryform = mysqli_query($conn, $selectform);

// $rowform = mysqli_fetch_assoc($queryform);

// $issortable = $rowform["issortable"]; 
// $sortcol = $rowform["sortcol"]; 

// $selecttable = "SELECT * FROM `cms_table` WHERE `id` = '" . $rowform["table"] . "' ";

// $querytable = mysqli_query($conn, $selecttable);

// $rowtable = mysqli_fetch_assoc($querytable);

// $tbName = $rowtable['name'];
// --- END Removed by salva TDR | 12.12.2022 ---

function get_category($id)
{
   include('include/session.php');
   $selectCat = "SELECT * FROM `blog_categories` WHERE `id` = '" . $id . "' ";
   $queryCat = mysqli_query($conn, $selectCat);
   $rowCat = mysqli_fetch_assoc($queryCat);
   $name = $rowCat["name"];
   return $name;
}

$toast = [];

if (isset($_GET["copy"])) { // Copy record
   if ($_GET['copy'] == 'success') {
      $toast[] = array(
         "message" => "Record copied successfully",
         "type" => "success"
      );
   } else {
      $toast[] = array(
         "message" => "Error copying record",
         "type" => "error"
      );
   }
}
if (isset($_GET["status"])) { // Show/hide status

   if ($_GET['status'] == 'success') {
      $toast[] = array(
         "message" => "Status updated successfully",
         "type" => "success"
      );
   } else {
      $toast[] = array(
         "message" => "Error updating status",
         "type" => "error"
      );
   }
}

if (isset($_GET["delete"])) { // Delete record
   if ($_GET['delete'] == 'success') {
      $toast[] = array(
         "message" => "Record deleted successfully",
         "type" => "success"
      );
   } else {
      $toast[] = array(
         "message" => "Error deleting record",
         "type" => "error"
      );
   }
}

if (isset($_GET["undelete"])) { // Undelete record
   if ($_GET['undelete'] == 'success') {
      $toast[] = array(
         "message" => "Record undeleted successfully",
         "type" => "success"
      );
   } else {
      $toast[] = array(
         "message" => "Error undeleting record",
         "type" => "error"
      );
   }
}
?>

<!DOCTYPE html>
<html lang="en">
<!-- start html tag -->

<head>
   <link href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet">
   <link href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.bootstrap4.min.css" rel="stylesheet">
   <link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap4.min.css" rel="stylesheet">


   <?php
   include("include/header-code.php");

   // --- START Removed by salva TDR | 2.12.2022 ---
   // $title = $title . " | View Record";
   // echo "<title>$title</title>";
   // --- END Removed by salva TDR | 2.12.2022 ---

   ?>

   <script>
      $(document).ready(function() {
         var tblname = "<?php echo $tbName ?>";
         var issortable = "<?php echo $issortable;  ?>";
         var sortcol = "<?php echo $sortcol; ?>";

         <?php
         if (count($toast) > 0) {
            echo "const toast = new Toast('" . $toast[0]['message'] . "', '" . $toast[0]['type'] . "');";
            echo "toast.show();";
         }
         ?>

         if (issortable == "Yes") {
            $("#sortable").sortable({
               stop: function(event, ui) {
                  // alert($(this).sortable('serialize'));
                  //alert(tblname);
                  var itemOrder = $('#sortable').sortable("toArray");
                  var list = "";

                  for (var i = 0; i < itemOrder.length; i++) {
                     data = "" + i + ":" + itemOrder[i] + "+";
                     console.log("Position: " + i + " ID: " + itemOrder[i]);
                     list += data;
                  }

                  console.log(data);

                  $.ajax({
                     url: 'sorttables.php',
                     type: 'post',
                     data: 'data=' + list + '&tblname=' + tblname + '&sortcol=' + sortcol,
                     success: function(msg) {
                        alert(msg)
                     }
                  })
               }
            })
         }
      })
   </script>
</head>

<body>
   <!-- Fixed navbar -->
   <?php
   include("include/header.php"); // Added by salva TDR | 2.12.2022
   include("include/sidebar.php");

   $tableView = $VIEW->getTableView(); // Added by salva TDR | 12.12.2022

   // --- START Removed by salva TDR | 12.12.2022 ---
   //Get the Form
   // $selectform = "SELECT * FROM `" . $tbName . "`";

   // if ($rowform["where1"]) {
   //    $selectform .= " WHERE " . $rowform["where1"] . " ";
   // }

   // if ($rowform["sort1"]) {
   //    $selectform .= " ORDER BY `" . $rowform["sort1"] . "` " . $rowform["sort1order"] . "";
   // }

   // if ($rowform["sort2"]) {
   //    $selectform .= " , `" . $rowform["sort2"] . "` " . $rowform["sort2order"] . "";
   // }

   // $queryform = mysqli_query($conn, $selectform);
   // --- END Removed by salva TDR | 12.12.2022 ---

   //$rowform = mysqli_fetch_assoc($queryform) ;

   ?>

   <!-- ** START removed by salva TDR | 9.12.2022 ** -->
   <!-- Why do you have this css links in the body? -->

   <!-- <link href="css/bootstrap.min.css" rel="stylesheet">
   <link href="css/bootstrap-reset.css" rel="stylesheet"> -->
   <!--external css-->
   <!-- <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" /> -->
   <!--right slidebar-->
   <!-- <link href="css/slidebars.css" rel="stylesheet"> -->
   <!--Form Wizard-->
   <!-- <link rel="stylesheet" type="text/css" href="css/jquery.steps.css" /> -->
   <!-- Custom styles for this template -->
   <!-- <link href="css/style.css" rel="stylesheet">
   <link href="css/style-responsive.css" rel="stylesheet" /> -->
   <!-- ** END removed by salva TDR | 9.12.2022 ** -->

   <style>
      td .narrow {
         max-width: 70px;
      }

      .positive {
         color: limegreen;
      }

      .negative {
         color: darkred;
      }

      .ex-col input {
         display: none;
      }

      table.listtable tr td {
         font-weight: 300;
         vertical-align: middle;
      }
   </style>

   <section id="main-content">
      <section class="wrapper site-min-height">

         <!-- page start-->
         <section class="card" style="width:100%;margin-left: -10px">
            <div class="row">
               <div class="card-body">
                  <!-- <div class="col-md-1 hidden-sm hidden-xs"></div>  -->
                  <div class="col-sm-12 col-md-10 col-lg-10" style="margin-top:20px;">
                     <h2>Manage <strong><?php echo $form["title"]; ?></strong></h2>
                  </div>
                  <div class="col-sm-12 col-md-2 col-lg-2" style="margin-top:20px;">
                     <h4><a href="recordAddv3.php?frm=<?php echo $formnumber; ?>">Add New</a></h4>
                  </div>

                  <div class="col-sm-12 col-md-12 col-lg-12" style="margin-top:20px; overflow-x: scroll;">

                     <table id="blogTable" class="table table-striped table-bordered listtable" style="width:100%;font-weight:600">

                        <thead>
                           <?php
                           $thstring = "<tr><th>ID</th>";
                           $tdstring = "<tr><td class='narrow'></td><td class='narrow'></td>";
                           // Thee line need to be dynamic based on col#type
                           $colCount = 1;
                           $searchCount = "0,";
                           $selectCount = "";

                           // --- START Edited by salva TDR | 12.12.2022 ---
                           if ($form["col1"]) {
                              if ($form["col1name"]) {
                                 $col1name = $form["col1name"];
                              } else {
                                 $col1name = $form["col1"];
                              }
                              $thstring = $thstring . "<th>" . ucfirst($col1name) . "</th>";

                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col1type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col1type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }

                           if ($form["col2"]) {
                              if ($form["col2name"]) {
                                 $col2name = $form["col2name"];
                              } else {
                                 $col2name = $form["col2"];
                              }
                              $thstring = $thstring . "<th>" . ucfirst($col2name) . "</th>";
                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col2type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col2type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }

                           if ($form["col3"]) {
                              if ($form["col3name"]) {
                                 $col3name = $form["col3name"];
                              } else {
                                 $col3name = $form["col3"];
                              }

                              $thstring = $thstring . "<th>" . ucfirst($col3name) . "</th>";

                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col3type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col3type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }

                           if ($form["col4"]) {

                              if ($form["col4name"]) {
                                 $col4name = $form["col4name"];
                              } else {
                                 $col4name = $form["col4"];
                              }

                              $thstring = $thstring . "<th>" . ucfirst($col4name) . "</th>";
                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col4type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col4type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }



                           if ($form["col5"]) {
                              if ($form["col5name"]) {
                                 $col5name = $form["col5name"];
                              } else {
                                 $col5name = $form["col5"];
                              }

                              $thstring = $thstring . "<th>" . ucfirst($col5name) . "</th>";
                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col5type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col5type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }

                           if ($form["col6"]) {

                              if ($form["col6name"]) {
                                 $col6name = $form["col6name"];
                              } else {
                                 $col6name = $form["col6"];
                              }

                              $thstring = $thstring . "<th>" . ucfirst($col6name) . "</th>";
                              $tdstring = $tdstring . "<td></td>";

                              if ($form["col6type"] == 'Search') {
                                 $searchCount .= strval($colCount) . ",";
                              }

                              if ($form["col6type"] == 'Select') {
                                 if ($selectCount) {
                                    $selectCount = $selectCount . "," . $colCount . "";
                                 } else {
                                    $selectCount = $selectCount . "" . $colCount . "";
                                 }
                              }

                              $colCount++;
                           }

                           $thstring = $thstring . "<th>Action</th></tr>";

                           //$tdstring = $tdstring . "<td class='narrow ghai'></td><td class='narrow'></td><td class='narrow'></td></tr>";

                           $tdstring = $tdstring . "</tr>";

                           // Remove the last , from $searchCount
                           $searchCount = rtrim($searchCount, ",");

                           echo $thstring;
                           echo $tdstring;
                           ?>
                        </thead>

                        <tbody id="sortable">
                           <?php
                           if ($form) {
                              $col1 = $form["col1"];
                              $col2 = $form["col2"];
                              $col3 = $form["col3"];
                              $col4 = $form["col4"];
                              $col5 = $form["col5"];
                              $col6 = $form["col6"];

                              foreach ($tableView as $row) {
                                 $text1 = $row[$col1];
                                 $text2 = $row[$col2];
                                 $text3 = $row[$col3];
                                 $text4 = $row[$col4];
                                 $text5 = $row[$col5];
                                 $text6 = $row[$col6];
                                 $showonweb = $row["showonweb"];

                                 //showonweb icons
                                 if ($showonweb == "Yes") {
                                    $show = " <i class='" . getIcon('Show') . "'></i>";
                                    $bgcolor = getIconBgColour('Show');
                                 } else {
                                    $show = " <i class='" . getIcon('Hide') . "'></i>";
                                    $bgcolor = getIconBgColour('Hide');
                                 }

                                 $copy = ' <i class="' . getIcon('Copy') . '"></i>';
                                 $bgcolorcopy = getIconBgColour('Copy');

                                 //end   

                                 if ($row["showonweb"] == 'Yes') {
                                    $showicon = 'fa-check positive';
                                 } else {
                                    $showicon = 'fa-times negative';
                                 }

                                 if ($form["col1table"]) {
                                    // $selectcol1 = "SELECT * FROM `" . $form["col1table"] . "` WHERE `id` = '" . $text1 . "'";
                                    // $querycol1 = mysqli_query($conn, $selectcol1);
                                    // $rowcol1 = mysqli_fetch_assoc($querycol1);

                                    $rowcol1 = $VIEW->getColTable($form["col1table"], $text1);

                                    $text1 = $rowcol1["name"] . " (" . $text1 . ")";
                                 }

                                 if ($form["col2table"]) {
                                    // $selectcol2 = "SELECT * FROM `" . $form["col2table"] . "` WHERE `id` = '" . $text2 . "'";
                                    // $querycol2 = mysqli_query($conn, $selectcol2);
                                    // $rowcol2 = mysqli_fetch_assoc($querycol2);

                                    $rowcol2 = $VIEW->getColTable($form["col2table"], $text2);

                                    $text2 = $rowcol2["name"] . " (" . $text2 . ")";
                                 }

                                 if ($form["col3table"]) {
                                    // $selectcol3 = "SELECT * FROM `" . $form["col3table"] . "` WHERE `id` = '" . $text3 . "'";
                                    // $querycol3 = mysqli_query($conn, $selectcol3);
                                    // $rowcol3 = mysqli_fetch_assoc($querycol3);

                                    $rowcol3 = $VIEW->getColTable($form["col3table"], $text3);

                                    $text3 = $rowcol3["name"] . " (" . $text3 . ")";
                                 }

                                 if ($form["col4table"]) {
                                    // $selectcol4 = "SELECT * FROM `" . $form["col4table"] . "` WHERE `id` = '" . $text4 . "'";
                                    // $querycol4 = mysqli_query($conn, $selectcol4);
                                    // $rowcol4 = mysqli_fetch_assoc($querycol4);

                                    $rowcol4 = $VIEW->getColTable($form["col4table"], $text4);

                                    $text4 = $rowcol4["name"] . " (" . $text4 . ")";
                                 }

                                 if ($form["col5table"]) {
                                    // $selectcol5 = "SELECT * FROM `" . $form["col5table"] . "` WHERE `id` = '" . $text5 . "'";
                                    // $querycol5 = mysqli_query($conn, $selectcol5);
                                    // $rowcol5 = mysqli_fetch_assoc($querycol5);

                                    $rowcol5 = $VIEW->getColTable($form["col5table"], $text5);

                                    $text5 = $rowcol5["name"] . " (" . $text5 . ")";
                                 }

                                 if ($form["col6table"]) {
                                    // $selectcol6 = "SELECT * FROM `" . $form["col6table"] . "` WHERE `id` = '" . $text6 . "'";
                                    // $querycol6 = mysqli_query($conn, $selectcol6);
                                    // $rowcol6 = mysqli_fetch_assoc($querycol6);

                                    $rowcol6 = $VIEW->getColTable($form["col6table"], $text6);

                                    $text6 = $rowcol6["name"] . " (" . $text6 . ")";
                                 }

                                 $tid = $row["id"];

                                 echo "<tr id='$tid'>";

                                 //echo "<td style='max-width:'><a href='$url?frm=" . $formnumber . "&id=" . $row["id"] . "'><i class='fa fa-pencil-square-o fa-2x' aria-hidden='true' style='color:green' ;'></i></a></td>";

                                 echo "<td>" . $row["id"] . "</td>";

                                 // * Doesn't have sense. The col1function doesn't exist in the table *
                                 if ($form["col1"]) {
                                    echo "<td>" . $form["col1function"] . stripslashes($text1) . "</td>";
                                 }
                                 if ($form["col2"]) {
                                    echo "<td>" . $form["col2function"] . $text2 . "</td>";
                                 }
                                 if ($form["col3"]) {
                                    echo "<td>" . $form["col3function"] . $text3 . "</td>";
                                 }
                                 if ($form["col4"]) {
                                    echo "<td>" . $form["col4function"] . $text4 . "</td>";
                                 }
                                 if ($form["col5"]) {
                                    echo "<td>" . $form["col5function"] . $text5 . "</td>";
                                 }
                                 if ($form["col6"]) {
                                    echo "<td>" . $form["col6function"] . $text6 . "</td>";
                                 }
                                 // ************************

                                 // --- START ACTIONS BUTTONS ---

                                 echo "<td style='width:100px'>";
                                 if (isset($actions)) {
                                    foreach ($actions as $action) {
                                       $act = $VIEW->getAction($action['action']);
                                       if ($act['id'] == 4) { // Show/hide
                                          $ex_icons = explode(",", $act['icon']);
                                          if ($showonweb == "Yes") {
                                             $show = "No";
                                             $icon = $VIEW->getIconById($ex_icons[0]);
                                          } else {
                                             $show = "Yes";
                                             $icon = $VIEW->getIconById($ex_icons[1]);
                                          }
                                       } else {
                                          $icon = $VIEW->getIconById($act['icon']);
                                       }

                                       if ($act['link'] == 'Yes') {
                                          $link = replaceURL($act['link_href'], $formnumber, $row["id"], $show);
                                          echo "<a href='{$link}' title='{$icon['title']}'>
                                             <button type='button'class='btn' style='background-color:{$icon['colour']};border-color:{$icon['colour']};color:{$icon['textcolour']}; width:40px;'>
                                                <i class='{$icon['code']}'></i>
                                             </button>
                                          </a>";
                                       } else {
                                          if ($act['confirm'] == 'Yes') {
                                             $link = replaceURL($act['link_href'], $formnumber, $row["id"]);
                                             $confirm = "onclick='checkConfirm(\"{$act['confirm_text']}\", \"{$link}\")'";
                                             echo "<script>
                                                function checkConfirm(text, redirect) {
                                                   if (confirm(text)) {
                                                      window.location.href = redirect;
                                                   }
                                                }
                                             </script>";
                                          } else {
                                             $confirm = "";
                                          }
                                          echo "<button {$confirm} type='button'class='btn' style='background-color:{$icon['colour']};border-color:{$icon['colour']};color:{$icon['textcolour']}; width:40px;'>
                                             <i class='{$icon['code']}'></i>
                                          </button>";
                                       }
                                    }
                                 } else {
                                    echo "<p>No actions</p>";
                                 }
                                 echo "</td>";

                                 // echo "<td style='width:100px'> <a href='$url?frm=" . $formnumber . "&id=" . $row["id"] . "' title='" . getIconTitle('Edit') . "'>
                                 //    <button type='button'class='btn' style='background-color:" . getIconBgColour('Edit') . ";border-color: border-color:" . getIconBgColour('Order') . ";color:" . getIconTextColour('Order') . ";' >
                                 //       <i class='" . getIcon("Edit") . "'></i>
                                 //    </button>
                                 // </a>
                                 // <a href='$url?frm=" . $formnumber . "&id=" . $row["id"] . "' title='" . getIconTitle('Delete') . "'>
                                 //    <button type='button'class='btn' style='background-color:" . getIconBgColour('Delete') . ";border-color: border-color:" . getIconBgColour('Order') . ";color:" . getIconTextColour('Order') . ";' >
                                 //       <i class='" . getIcon("Delete") . "'></i>
                                 //    </button>
                                 // </a>
                                 // <a href='?frm=" . $formnumber . "&id=" . $row["id"] . "&show=$showonweb&tb=$tbName' title='" . getIconTitle('Show') . "'>
                                 //    <button type='button'class='btn' style='background-color:$bgcolor;border-color: border-color:" . getIconBgColour('Order') . ";color:" . getIconTextColour('Order') . ";'  >$show</button>
                                 // </a>
                                 // <a href='recordCopyv3.php?frm=" . $formnumber . "&id=" . $row["id"] . "&show=$showonweb&tb=$tbName' title='" . getIconTitle('Copy') . "'>
                                 //    <button type='button'class='btn' style='background-color:$bgcolor;border-color: border-color:" . getIconBgColour('Copy') . ";color:" . getIconTextColour('Copy') . ";'  >$copy</button></a>
                                 // </td>";
                                 echo '</tr>';

                                 // --- END ACTIONS BUTTONS ---
                              }
                           } else {
                              echo "<tr><td colspan='$colCount'>No records found</td></tr>";
                           }

                           ?>
                        </tbody>

                        <tfoot>
                           <?php echo $thstring; ?>
                        </tfoot>
                     </table>
                  </div>
               </div>
            </div>
         </section>
         <div>
            <?php
            // Display View Page notes at bottom of form (from cms_foms)
            echo "<h5>Form Notes</h5>" ;
            echo $form["viewnotes"]; 
            echo "<p>Records to show: ".$user["recordstoshow"]."</p>" ;
            echo "<hr>" ;
            
            if (
               $prefs['prefFooterDebugOn'] == 'Yes' ||
               ($_SERVER['REMOTE_ADDR'] == $prefs['prefTruskaIP'] ||
                  $_SERVER['REMOTE_ADDR'] == $prefs['prefCoderIP'] ||
                  $_SERVER['REMOTE_ADDR'] == $prefs['prefClientIP'] ||
                  $_SERVER['REMOTE_ADDR'] == $prefs['prefClient1IP']
               )
            ) { // Show query in admin/debug mode
               $formQuery = $VIEW->getFormQuery();
               $tableQuery = $VIEW->getTableQuery();
               $tableViewQuery = $VIEW->getTableViewQuery();
               echo "<h5>Query Debug</h5>";
               echo "<p><b>Form query: </b>{$formQuery}</p>";
               echo "<p><b>Table query: </b>{$tableQuery}</p>";
               echo "<p><b>Table view query: </b>{$tableViewQuery}</p>";
            }
            ?>
         </div>
      </section>
   </section>

   <?php
   include("include/footer.php");
   echo "</div>";
   include("include/footer-code.php");
   ?>
</body>

<!-- Bootstrap core JavaScript-->

<script>
   function hideColumn() {
      var i = 0;
      $('#blogTable thead tr:eq(0) th').each(function(item) {
         if ($(this).is(':visible')) {
            i = i + 1;
         }
      });

      $('#salesTable thead tr:eq(1) td').each(function(item) {
         if (i <=
            item) {
            $(this).hide();
         }
      });
   }

   $(document).ready(function() {
      var table = $('#blogTable').DataTable({
         lengthChange: true,
         lengthMenu: [
            [10, 25, 50, 100, 200, 500],
            [10, 25, 50, 100, 200, 500]
         ],
         responsive: true,
         ordering: true,
         "order": [
            [0, "desc"]
         ],
         orderCellsTop: true,
         columnDefs: [{
               targets: <?php echo $colCount; ?>,
               searchable: false,
               orderable: false
            },
            {
               targets: 1,
               className: 'noVis'
            }
         ],
         dom: 'Bfrtip',
         buttons: [
            'copy', 'excel', 'pdf', 'print', 'csv'
         ],
         pageLength: <?php echo ($user["recordstoshow"] > 0 ? $user['recordstoshow'] : 15); ?>, // Edited by salva TDR | 9.12.2022
         initComplete: function() {
            this.api().columns([<?php echo $selectCount; ?>]).every(function() { // SELECT
               var column = this;
               var select = $('<select id="seect' + column[0][0] + '" class="select-datatable form-control"><option value="">All</option></select>')
                  .appendTo($('thead tr:eq(1) td').eq(this.index()))
                  .on('change', function() {
                     var val = $.fn.dataTable.util.escapeRegex(
                        $(this).val()
                     );

                     column
                        .search(val ? '^' + val + '$' : '', true, false)
                        .draw();
                  });

               column.data().unique().sort().each(function(d, j) {
                  select.append('<option value="' + d + '">' + d + '</option>');
                  if (column[0][0] == "10") {
                     $('#defaultYYMM').append('<option value="' + d + '">' + d + '</option>');
                  }
               });
            });

            this.api().columns([<?php echo $searchCount; ?>]).every(function() { // SEARCH
               var column = this;
               var select = $('<input class="input-datatable form-control" type="text" placeholder="Search" />')
                  .appendTo($('thead tr:eq(1) td').eq(this.index())).on('keyup change', function() {
                     if (column.search() !== this.value) {
                        column
                           .search(this.value)
                           .draw();
                     }
                  });
            });
         },
      });

      table.buttons().container().appendTo('#blogTable_wrapper .col-md-3:eq(0)');

      hideColumn();
   });

   $(window).resize(function() {
      hideColumn();
   });
</script>


</html>

<!-- END recordViewv3 -->