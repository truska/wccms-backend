<!DOCTYPE html>

<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
// error_reporting(1);

include('setting/main-top-files.php'); 

//Bring Fwd variables - Edited by salva TDR | 12.12.2022
// In main-top-files
/*
$baseURL = "https://" . $_SERVER['SERVER_NAME'] . "";
*/

// if (!$formnumber = securityCheck($_GET['frm'], 'number')) {
// 	die('Error in the form'); // If the user try to insert something different from a number, we kill the script
// }
// if (!$recordnumber = securityCheck($_GET['id'], 'number')) {
// 	die('Error in the id'); // If the user try to insert something different from a number, we kill the script
// }


$TypeDebug = 'Yes'; // Yes or No
$toast = [];

try {
   $PRF = new Preferences();
   $preferences = $PRF->getPreferences(); // Get preferences
   $tabs = $PRF->getPreferencesTabs(); // Get preferences tabs

   // echo "<pre>";
   // echo "Preferences: ";
   // print_r($preferences);
   // echo "count tabs: " . count($tabs);
   // echo "</pre>";
   
} catch (\Exception $e) {
   $toast[] = array(
		"message" => $e->getMessage(),
      "type" => "error"
	);
}

if (isset($_POST['submit'])) {
	$updateData = [];

   if (!empty($_POST) && count($_POST) > 2) { // if there is data to update
		foreach ($_POST as $key => $value) {
			$updateData[$key] = securityCheck($value);
		}
		$updateResponse = $PRF->updatePreferences($updateData);

      if (count($updateResponse['errors']) > 0) {
         $toast[] = array(
            "message" => "Error updating preferences",
            "type" => "error"
         );
      } else {
         $toast[] = array(
            "message" => "Preferences updated successfully",
            "type" => "success"
         );
      }

	  // Masking Key Data

		// Fetch logmask values
		$logmaskData = getLogmaskData($updateData);

		// Create masked SQL query
		$sqlproductlog = createMaskedSqlQuery($updateData, $logmaskData);

		$logtable = 'preferences';
		$action = "Update preferences table";
		//$sqlproductlog = implode("; ", $updateResponse['queries']); // Combine all queries into a single string
		$notes = (count($updateResponse['errors']) > 0) ? 'Error updating preferences' : 'Preferences updated successfully';

		if (count($updateResponse['errors']) > 0) {
			savelogV2('', $action, $sqlproductlog, $logtable, 'FAIL', $notes, 0);
		} else {
			savelogV2('', $action, $sqlproductlog, $logtable, 'SUCCESS', $notes,  0);
		}

		// Get new preferences with changes
		$preferences = $PRF->getPreferences(true);
	}
}

?>

<html lang="en">

<head>
	<?php
	include("include/header-code.php"); // Added by Salva TDR | 5.12.2022
	?>
	<script>
		$(document).ready(function() {
			<?php
			if (count($toast) > 0) {
				echo "const toast = new Toast('" . $toast[0]['message'] . "', '" . $toast[0]['type'] . "');";
				echo "toast.show();";
			}
			?>

		})

	</script>

	<style type="text/css">
		#drop_file_zone {
			background-color: #EEE;
			border: #999 5px dashed;
			width: 290px;
			height: 200px;
			padding: 8px;
			font-size: 18px;
		}

		#drag_upload_file {
			width: 50%;
			margin: 0 auto;
		}

		#drag_upload_file p {
			text-align: center;
		}

		#drag_upload_file #selectfile {
			display: none;
		}

		.formmessage {
			font-size: 12px;
			font-style: italic;
		}
		.nav-pills .nav-link, .nav-pills  > .nav-link {
			background-color: #C8F097 ;
		}
	</style>
</head>

<body>
	<section id="container" class="" data-top="recordPreferences|">
		<?php
		include("include/header.php");
		include("include/sidebar.php");
		?>

		<!--main content start-->

		<section id="main-content">
			<section class="wrapper site-min-height">
				<!-- page start-->
				<div class="row">
					<div class="col-lg-12">
						<?php
						$key = array_keys($rowcontent)[1];
						?>
						<h2>Edit: Preferences</h2>

						<section class="card">
							<div class="card-body">
								<!-- Set up Tabs -->
								<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
									<?php
                           if (count($tabs) > 0) {
                              foreach ($tabs as $tab) {
                                 $tabName = ($tab['prefName']) ? $tab['prefName'] : $tab['prefId'];
								 			$tabNotes = ($tab['prefNotes']) ? $tab['prefNotes'] : $tab['prefId'];

                                 echo "<li class='nav-item mr-1'>";
                                 echo "<a class='nav-link " . ($tab['prefId'] == 1 ? "active" : "") . "' id='pills-" . strtolower($tabName) . "-tab' data-toggle='pill' href='#pills-" . strtolower($tabName) . "' role='tab' aria-controls='pills-" . strtolower($tabName) . "' aria-selected='false'>{$tabName}</a>";
                                 echo "</li>";
                              }
                           }
									?>
								</ul>

								<div class="tab-content" id="pills-tabContent">
									<?php
                           if (count($tabs) > 0) {
                              foreach ($tabs as $tab) {
                                 $tabName = ($tab['prefName']) ? $tab['prefName'] : $tab['prefId'];
								 			$tabNotes = ($tab['prefNotes']) ? $tab['prefNotes'] : $tab['prefId'];
   
                                 echo "<div class='tab-pane fade " . ($tab['prefId'] == 1 ? "active show" : '') . "' id='pills-" . strtolower($tabName) . "' role='tabpanel' aria-labelledby='pills-" . strtolower($tabName) . "-tab'>";
                                    echo '<form role="form" class="form-horizontal" method="post" enctype="multipart/form-data">';
                                       echo "<input type='hidden' name='formnumber' value='" . $formnumber . "' />"; // pass forward the Form ID 
                                       foreach ($preferences as $rowformfield) {
                                          // if is not the tab, we skip it
                                          if ($rowformfield['prefCat'] != $tab['prefId']) {
                                             continue;
                                          }
   
                                          $imgfld = 0;
                                          $required = '';
                                          $itemclass = 'width:50%';
   
                                          //Get the Field Type	
                                          $rowfield = $PRF->getFieldType($rowformfield["field"]);
   
                                          $ContentValue = ($rowformfield['value']) ? $rowformfield['value'] : '';

                                          //echo "<span style='font-size:9px;'>Field: {$rowformfield['field']}</span><br>";
   
                                          $FORMFIELD = new FormField($rowformfield, $rowfield, $ContentValue, $TypeDebug);
                                          echo $FORMFIELD->render();
                                       }
                                       ?>
                                       <div class='form-group button-fixed-right-bottom'>
                                          <input type="hidden" id="check_image" />
                                          <button data-popover="popover" data-content="Save" data-placement="top" data-trigger="hover" type="submit" name="submit" class="btn btn-success" id="sbt"><i class="fa fa-check"></i>UPDATE RECORD</button>
                                       </div>
                                    </form>
                                 <hr>
                                 <h6><em>Notes:</em></h6>
                                 <p><?php echo $tab['prefNotes']; ?></p>
                                 <!-- <p><?php echo $tab['text']; ?></p>  -->
                                 <?php echo "</div>";
                              }
                           } ?>
								</div>
							</div>
						</section>
					</div>
				</div>
				<div>
					<?php
					if (
						$prefs['prefFooterDebugOn'] == 'Yes' ||
						($_SERVER['REMOTE_ADDR'] == $prefs['prefTruskaIP'] ||
							$_SERVER['REMOTE_ADDR'] == $prefs['prefCoderIP'] || 
							$_SERVER['REMOTE_ADDR'] == $prefs['prefClientIP'] || 
							$_SERVER['REMOTE_ADDR'] == $prefs['prefClient1IP']
						)
					) { // Show query in admin/debug mode
						$preferencesQuery = $PRF->getPreferencesQuery();

						echo "<p><b>Preferences query: </b>{$preferencesQuery}</p>";
					}
					?>
				</div>
			</section>
		</section>
	</section>
	<?php
	// include("include/footer.php"); // - footer.php doesn't exist inside wccms/include folder - Salva TDR | 17.1.2023
	// echo "</div>"; // Removed by salva TDR | 17.1.2023
	include("include/footer-code.php");
	include("include-tinymce.php");
	?>
</body>

</html>
