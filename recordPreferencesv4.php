<!-- START recore Preferences v4 -->
 
<!DOCTYPE html>
<?php

	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(E_ALL);

	$pageType = 'prefs' ; // Used to prevent controller/formField.php from Loading on Preference editing page(s)
	include('setting/main-top-files.php'); 

	if (!isset($_GET['frm']) || !($formnumber = securityCheck($_GET['frm'], 'number'))) {
		//die('Error in the form - no form called'); // Safe: doesn't try to access undefined index
		echo "<div class='alert alert-danger'>No Preferences form selected.<br><a href='dashboard.php'>Click to go bBack to Dashboard</a></div>";
		exit;
	}
		$allowedForms = [
			1 => 'preferences',
			2 => 'preferencesText'
		];
		
		if (!$formnumber || !array_key_exists($formnumber, $allowedForms)) {
			echo "<div class='alert alert-danger'>Invalid preferences form selected.<br><a href='dashboard.php'>Click to go bBack to Dashboard</a></div>";
			exit;
		}

		$tablename = $allowedForms[$formnumber];

		// Set Preference Table
		$tablename ='invalid' ;
		if($_GET['frm'] == 1) {$tablename = 'preferences';}
		if($_GET['frm'] == 2) {$tablename = 'preferencesText';}

		// if (!$recordnumber = securityCheck($_GET['id'], 'number')) {
			// 	die('Error in the id'); // If the user try to insert something different from a number, we kill the script
		// }

	$TypeDebug = 'No'; // Yes or No
	$toast = [];

	try {
	$PRF = new Preferences($tablename);
	$preferences = $PRF->getPreferences(); // Get preferences
	$tabs = $PRF->getPreferencesTabs(); // Get preferences tabs

	/*
		echo "<pre>";
		echo "Preferences: ";
		print_r($preferences);
		echo "Tabs ";
		print_r($tabs);
		echo "count tabs: " . count($tabs);
		echo "</pre>";
	*/

	} catch (\Exception $e) {
	$toast[] = array(
			"message" => $e->getMessage(),
		"type" => "error"
		);
	}

	// Build a map of tab ID → tab name
	$tabIdMap = [];
	foreach ($tabs as $tab) {
		$tabIdMap[$tab['prefId']] = $tab['prefName'];
	}
	// Build a quick lookup of field name → tab info
	$fieldToTabMap = [];
	foreach ($preferences as $pref) {
		$tabId = $pref['prefCat'];
		$fieldToTabMap[$pref['name']] = [
			'tabId' => $tabId,
			'tabName' => $tabIdMap[$tabId] ?? 'Unknown'
		];
	}

	if (isset($_POST['submit'])) {
		$updateData = [];

		if (!empty($_POST) && count($_POST) > 2) { // if there is data to update
			foreach ($_POST as $key => $value) {
				$updateData[$key] = securityCheck($value);
			}
			$updateResponse = $PRF->updatePreferences($updateData);

			if (isset($updateResponse['errors']) && count($updateResponse['errors']) > 0) {
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

			$sqlPreferencelog = $updateResponse['sql'] ?? ''; // <- Grab the SQL that was executed
			$logtable = 'preferences';
			$action = "Update preferences table";
			//$sqlproductlog = '';
			$notes = (isset($updateResponse['errors']) && count($updateResponse['errors']) > 0)
			? 'Error updating preferences'
			: 'Preference Table updated successfully';
		
			// Grab first updated field to look up tab info
			$firstField = '';
			foreach ($updateData as $key => $val) {
				if ($key !== 'formnumber' && $key !== 'submit') {
					$firstField = $key;
					break;
				}
			}

			$tabInfo = $fieldToTabMap[$firstField] ?? ['tabId' => 0, 'tabName' => 'Unknown'];

			$contentid = $tabInfo['tabId']; // Use tab number as contentid (for filtering later)
			$notes .= " | Tab: {$tabInfo['tabName']} (ID: {$tabInfo['tabId']})";

			if (isset($updateResponse['errors']) && count($updateResponse['errors']) > 0) {
				error_log("Data into CMS LOG on FAILED update: ".$action." | ".$sqlPreferencelog." | ".$logtable." | ".$notes." | ". $contentid) ;
				savelogV2('', $action, $sqlPreferencelog, $logtable, 'FAIL', $notes, $contentid);
			} else {
				error_log("Data into CMS LOG on SUCCESSFUL ACTION: ".$action) ;
				error_log("Data into CMS LOG on SUCCESSFUL SQL: ".$sqlPreferencelog) ;
				error_log("Data into CMS LOG on SUCCESSFUL TABLE: ".$logtable) ;
				error_log("Data into CMS LOG on SUCCESSFUL NOTES: ".$notes) ;
				error_log("Data into CMS LOG on SUCCESSFUL CONTENTID: ".$contentid) ;
				savelogV2('', $action, $sqlPreferencelog, $logtable, 'SUCCESS', $notes, $contentid);
			}

			// Get new preferences with changes
			$preferences = $PRF->getPreferences(true);
		}
	}

?>

<html lang="en">

<head>
	<?php
		include("include/header-code.php"); 
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

						<h2>Edit: Preferences <span style="font-weight:200; font-size:1.5rem;">[<?php echo $tablename."] table";?></span></h2>

						<section class="card">
							<div class="card-body">

								<!-- Set up Tabs -->
								<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
									<?php
									foreach ($tabs as $tab) {
										$tabId = (int)$tab['prefId'];
										$slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $tab['prefName']));
										echo "<li class='nav-item mr-1'>";
										echo "<a class='nav-link " . ($tabId === 1 ? "active" : "") . "' id='pills-{$slug}-tab' data-bs-toggle='pill' href='#pills-{$slug}' role='tab' aria-controls='pills-{$slug}' aria-selected='false'>{$tab['prefName']}</a>";
										echo "</li>";
									}
									?>
								</ul>

								<div class="tab-content" id="pills-tabContent">
									<?php
										foreach ($tabs as $tab) {
											$tabId = (int)$tab['prefId'];
											$tabName = $tab['prefName'];
											$slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $tab['prefName']));

											echo "<div class='tab-pane fade " . ($tabId === 1 ? "active show" : '') . "' id='pills-{$slug}' role='tabpanel' aria-labelledby='pills-{$slug}-tab'>";

											echo "<h4 style='color:green;'>TEST: You are viewing tab <strong>{$tabName}</strong> (ID: {$tabId})</h4>";

											// Count fields for this tab
											$fieldsInTab = array_filter($preferences, function($row) use ($tabId) {
												return (int)$row['prefCat'] === $tabId;
											});

											if (count($fieldsInTab) === 0) {
												echo "<p class='text-muted'>No preferences found for this tab.</p>";
												echo "</div>"; // close tab-pane
												continue;
											}

											echo '<form role="form" class="form-horizontal" method="post" enctype="multipart/form-data">';
											echo "<input type='hidden' name='formnumber' value='" . htmlspecialchars($formnumber ?? '') . "' />";

											foreach ($fieldsInTab as $rowformfield) {
												$rowfield = $PRF->getFieldType($rowformfield["field"]);
												if (!$rowfield) {
													echo "<p style='color:red;'>Missing field type for field ID: {$rowformfield['field']}</p>";
													continue;
												}
											
												$ContentValue = $rowformfield['value'] ?? '';
												$PageType = 'edit' ; // Can probably be deleted from the system
												// DON’T pass itemclass — let FormField calculate it from $rowformfield['class']
												$FORMFIELD = new FormField($rowformfield, $rowfield, $ContentValue, $TypeDebug, $PageType);
												echo $FORMFIELD->render();
											}
											

											echo "<div class='form-group button-fixed-right-bottom'>";
											echo "<input type='hidden' id='check_image' />";
											echo "<button type='submit' name='submit' class='btn btn-success' id='sbt'><i class='fa fa-check'></i>UPDATE RECORD</button>";
											echo "</div>";
											echo "</form>";

											// Notes area
											echo "<hr><h6><em>Notes:</em></h6>";
											echo "<p>Tab Notes: ".htmlspecialchars($tab['prefNotes']) . "</p>";
											//echo "<p> Tab Text: ".$tab['text']."</p>";
											echo "</div>"; // end tab-pane
										}									
																			
									?>
									
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
		echo "<script>console.log('🧪 Before footer-code include');</script>"; 

		include("include/footer-code.php");
		include("include-tinymce.php");
	?>

	<script>
		console.log("jQuery version:", typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'jQuery not loaded');
	</script>

	<script>
		console.log("🧪 Bootstrap + jQuery Check");
		console.log("jQuery version:", typeof jQuery !== 'undefined' ? jQuery.fn.jquery : '❌ jQuery not loaded');

		if (typeof $.fn.tab === 'function') {
			console.log("✅ Bootstrap 4 Tab plugin is loaded (via jQuery)");
		} else {
			console.warn("❌ Bootstrap 4 Tab plugin not found (check script order or missing file)");
		}
	</script>

</body>

</html>
<!-- END record Preferences v4 -->