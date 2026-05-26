<!-- START recordEditv3  -->
<!DOCTYPE html>

<?php
// Turn off error reporting
error_reporting(0);
// Turn on error reporting
// error_reporting(1);

include ('setting/main-top-files.php'); // Added by salva TDR | 9.12.2022

// all now in header-code.php
//	include("include/dbcon.php");
//  include('include/session.php');
//  include('wideimage/lib/WideImage.php');

//Bring Fwd variables - Edited by salva TDR | 12.12.2022
// In main-top-files
/*
$baseURL = "https://" . $_SERVER['SERVER_NAME'] . "";
*/

if (!$formnumber = securityCheck($_GET['frm'], 'number')) {
	die('Error in the form'); // If the user try to insert something different from a number, we kill the script
}
if (!$recordnumber = securityCheck($_GET['id'], 'number')) {
	die('Error in the id'); // If the user try to insert something different from a number, we kill the script
}
$imagecounter = 1;

// include('include/setlogdata.php');
$TypeDebug = 'Yes'; // Yes or No
$img1 = 0;
$toast = [];
// $galleryID = $rowcontent['gallery']; // Removed by salva TDR | 13.12.2022

// --- START Edited by salva TDR | 13.12.2022 ---
$FORM = new RecordEdit($formnumber, $recordnumber);
$gallery = $FORM->getGallery(); // Get gallery (not working now)
$form = $FORM->getForm(); // Get form data
$table = $FORM->getTable(); // Get table data
$rowcontent = $FORM->getTableContent(); // Get the Actual Table Content
$form_fields = $FORM->getFormFields(); // Get form fields data

// echo "<pre>";
// echo "Gallery: ";
// print_r($gallery);
// echo "form: ";
// print_r($form);
// echo "table: ";
// print_r($table);
// echo "rowcontent: ";
// print_r($rowcontent);
// echo "Form Fields: ";
// print_r($form_fields);
// echo "</pre>";

$tablename = $table["name"];
$customcss = $FORM->getCss();
$maximg = $FORM->getMaxImg(); // Get the max number of images from `maxgalleryimage` field in the cms_form table.
$counterfields = 1;

// echo "<pre>";
// print_r($form);
// echo "</pre>";
// echo "<pre>";
// print_r($form_fields);
// echo "</pre>";
// --- END Edited by salva TDR | 13.12.2022 ---

// PROCESS THE FORM IF COMPLETED
if (isset($_POST['submit'])) {
	// My new code
	$id = 1;
	// $targetDir = "../filestore/";
	$targetDir = dirname(__FILE__) . "/../filestore/";
	$errors = array();

	// Get preferences
	$preferences = $FORM->getPreferencesByName('prefImagePath');
	$path = $preferences ? $preferences["value"] : "";

	// $filenames = "";

	// echo "<pre>";
	// echo "-- PREFERENCES -- <br>";
	// print_r($preferences);
	// echo "-- FILES -- <br>";
	// print_r($_FILES);
	// echo "-- POST -- <br>";
	// print_r($_POST);
	// echo "</pre>";


	// -- START NEW SYSTEM | salva TDR | 02.03.2023 -- //
	foreach ($_FILES as $key => $value) {
		$filename = $_FILES[$key]['name'];

		if ($filename != "") {
			$field = $FORM->getFormFieldByName($key);

			// get name of the file without extension
			$filenameNoExt = pathinfo($filename, PATHINFO_FILENAME);

			// get the file extension
			$file_ext = pathinfo($filename, PATHINFO_EXTENSION);
			$file_ext = strtolower($file_ext);

			if ($file_ext == "jpeg") {
				$file_ext = "jpg";
			}

			// set the destination folder
			if (
				$field['file_folder_name'] != '' &&
				$field['file_folder_name'] != null
			) {
				$destination_folder = "{$targetDir}{$field['mediatype']}/{$field['file_folder_name']}/";
			} else {
				$destination_folder = "{$targetDir}{$formField['mediatype']}/";
			}

			// check if the extension is valid
			if (strpos($field['file_ext'], $file_ext) === false) {
				$e = [
					"file" => $filename,
					"error" => "Extension not valid"
				];
				$errors = array_merge($errors, $e);
				continue; // if the extension is not valid, we continue to the next file
			}

			// Set name of the file if the field is set to override the filename
			if ($field['override_filename'] == 'Yes') {
				$newName = "{$formnumber}-{$recordnumber}_";
				if (!empty($rowcontent['title'])) {
					$newName .= $rowcontent['title'];
				} elseif (!empty($rowcontent['name'])) {
					$newName .= $rowcontent['name'];
				} else {
					$newName .= $filenameNoExt;
				}
			}else {
				$newName = $filenameNoExt;
			}


			if ($field['mediatype'] == 'images') { // is a image
				// check if the file is an image
				if (!getimagesize($_FILES[$key]['tmp_name'])) {
					$e = [
						"file" => $filename,
						"error" => "File is not an image"
					];
					$errors = array_merge($errors, $e);
					continue;
				}

				// // Check if the file exists, if it does, we add a number to the name to avoid overwriting
				// $checkName = $image->cleanName($newName); // Clean the name to avoid problems with special characters

				// $counter = 0;
				// $originalName = $newName;
				// while (file_exists($destination_folder . $checkName . "." . $file_ext)) {
				// 	$counter++;
				// 	$newName = $originalName . "_" . $counter;
				// }

				if ($field['resize_status'] == 'Yes') {
					$scaled_sizes = array(
						'xs' => array('width' => $field['xs_max_width']),
						'sm' => array('width' => $field['sm_max_width']),
						'md' => array('width' => $field['md_max_width']),
						'lg' => array('width' => $field['lg_max_width'])
					);
				} else {
					$scaled_sizes = false;
				}

				if ($field['default-resize'] > 0) {
					$defaultResize = $field['default-resize'];
				} else {
					$defaultResize = 0;
				}

				$image = new ImageResizer($_FILES[$key]['tmp_name'], $scaled_sizes, $defaultResize);

				// Check if the file exists, if it does, we add a number to the name to avoid overwriting
				$checkName = $image->cleanName($newName);
				$toManyFiles = false;
				if (file_exists($destination_folder . $checkName . "." . $file_ext)) {
					$i = 1;
					while (file_exists($destination_folder . $checkName . "_" . $counter . "." . $file_ext)) {
						$i++;
						// Prevent the server go down if the file exists
						if ($i > 100) {
							$toManyFiles = true;
							break;
						}
					}

					if ($toManyFiles) {
						$newName = $newName . "_" . time();
					} else {
						$newName = $newName . "_" . $i;
					}
				}
				// $newName = $image->cleanName($newName);

				// echo "NewName: $newName <br>";
				// echo "FileExtension: $file_ext <br>";
				// echo "Filename: $filename <br>";
				// echo "destination_folder: $destination_folder <br>";

				// If the resize status is Yes, we resize the image in the 3 sizes + original and we upload them, else we just upload the original image
				if ($field['resize_status'] == 'Yes') {
					$image->resize($destination_folder, $newName);
				} else {
					$image->uploadImage($destination_folder, $newName);
				}

				$image_errors = $image->getErrors();

				if (!empty($image_errors)) {
					$errors = array_merge($errors, $image_errors);
				} else {
					$newName = $image->cleanName($newName);
					$data[$key] = $newName . "." . $file_ext;
					$updateResponse = $FORM->updateTableContent($data);

					$logtable = $table['name'];
					$action = "Update Data Form " . $formnumber;
					$sqlproductlog = $updateResponse['query'];
					$notes = 'Image uploaded';

					// echo "<pre>";
					// echo "Update Response: ";
					// print_r($updateResponse);
					// echo "</pre>";

					if ($updateResponse['status'] == 'error') {
						$errors = array_merge($errors, $updateResponse);
						savelog('', $action, $sqlproductlog, $table['name'], 'FAIL', $notes, $recordnumber);
					} else {
						savelog('', $action, $sqlproductlog, $table['name'], 'SUCCESS', $notes, $recordnumber);
					}
				}
			} else { // is a file
				$newName = sanitizeName($newName);
				$uploadFile = uploadFile($_FILES[$key]['tmp_name'], $destination_folder, $newName . "." . $file_ext);

				if ($uploadFile['status'] == 'success') {
					$data[$key] = $newName . "." . $file_ext;
					$updateResponse = $FORM->updateTableContent($data);

					$logtable = $table['name'];
					$action = "Update Data Form " . $formnumber;
					$sqlproductlog = $updateResponse['query'];
					$notes = 'file uploaded';

					// echo "<pre>";
					// echo "Update Response: ";
					// print_r($updateResponse);
					// echo "</pre>";

					if ($updateResponse['status'] == 'error') {
						$errors = array_merge($errors, $updateResponse);
						savelog('', $action, $sqlproductlog, $table['name'], 'FAIL', $notes, $recordnumber);
					} else {
						savelog('', $action, $sqlproductlog, $table['name'], 'SUCCESS', $notes, $recordnumber);
					}
				} else {
					$errors = array_merge($errors, $uploadFile);
				}
			}
		}
	}

	// -- START update table content | salva TDR | 02.03.2023 -- //
	$updateData = [];
	
	if (!empty($_POST) && count($_POST) > 2) { // if there is data to update

    	// Check if `slug` exists 
		if (isset($_POST['slug'])) {
			// Check if `slug` has a value 
			if (empty($_POST['slug'])) {
			// Generate a value for slug
			if (!empty($_POST['title'])) {
				$slugValue = $_POST['title'];
			} elseif (!empty($_POST['heading'])) {
				$slugValue = $_POST['heading'];
			} elseif (!empty($_POST['name'])) {
				$slugValue = $_POST['name'];
			} else {
				$slugValue = 'No Value For Slug'; // Fallback value in case none of the fields are available
			}
		}
		else
		{
			// If slug is not empty, use the existing value
			$slugValue = $_POST['slug'];
		}
			// Apply sanitization (example: lowercase, remove special characters, replace spaces with hyphens)
				$slugValue = strtolower($slugValue); // Convert to lowercase
				$slugValue = str_replace(['&', '+'], '-', $slugValue);
				$slugValue = preg_replace('/[^a-z0-9\s\-]/', '', $slugValue); // Remove special characters except spaces and hyphens
				$slugValue = str_replace(' ', '-', $slugValue); // Replace spaces with hyphens
				$slugValue = preg_replace('/-+/', '-', $slugValue); // Remove consecutive hyphens
				$slugValue = trim($slugValue, '-'); // Remove hyphens from the start and end
		
			// Add the sanitized slug value to the $_POST array
			$_POST['slug'] = $slugValue;
		
		}
		// End Slug Check

    	// Check if Internal `Name` exists 
		if (isset($_POST['name']) ) {

			// Check If internal name is empty		
			if (empty($_POST['name'])) {

				// Generate a value for slug
				if (!empty($_POST['title'])) {
					$nameValue = $_POST['title'];
				} elseif (!empty($_POST['heading'])) {
					$nameValue = $_POST['heading'];
				} else {
					$nameValue = 'No Name Value For Internal Name'; // Fallback value in case none of the fields are available
				}
			}
			else
			{
				// If slug is not empty, use the existing value
				$nameValue = $_POST['name'];
			}
			// Add the sanitized slug value to the $_POST array
			$_POST['name'] = $nameValue;
		}
		// End Name Check		

		foreach ($_POST as $key => $value) {
			$updateData[$key] = securityCheck($value);
		}

		$updateResponse = $FORM->updateTableContent($updateData);

		$logtable = $table['name'];
		$action = "Update Data Form " . $formnumber;
		$sqlquery = mysqli_real_escape_string($conn, $updateResponse['query']);
		$notes = $updateResponse['message'];
		$username = $_SESSION["useremail"];
		
		//debugging
		//$editlogdata = "Connection: ".$connection." | Query Status: ".$updateResponse['status'] . " | Username: ". $username . " | Table: ".$logtable." | Action: ".$action." | Notes: ".$notes." | RecordID: ".$recordnumber." | SQL: ".$sqlquery." | END";
		//error_log($editlogdata);
		
		if ($updateResponse['status'] == 'error') {
			$errors = array_merge($errors, $updateResponse);
			saveLog($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordnumber);
		} else {
			saveLog($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordnumber);
		}
		
	}

	// -- END Update table content | salva TDR | 02.03.2023 -- //

	if (count($errors) > 0) {
		echo "<pre>";
		echo "Errors: ";
		print_r($errors);
		echo "</pre>";
		die;
	}

	// -- We get the afterEdit field and we redirect to the page -- //
	$redirectTo = $form['afterEdit'];

	if (!empty($redirectTo)) {
		$redirectTo = str_replace("[frm]", $formnumber, $redirectTo);
		$redirectTo = str_replace("[id]", $recordnumber, $redirectTo);
		header("Location: " . $redirectTo . "");
	} else {
		// echo "<script>alert('Record updated successfully');</script>";
		$toast[] = array(
			"message" => "Record updated successfully",
			"type" => "success"
		);
	}
	// -------------------------------------------- //

	$rowcontent = $FORM->getTableContent(); // Get the Actual Table Content (after update)
	$form_fields = $FORM->getFormFields(); // Get form fields data (after update)

	// -- END NEW SYSTEM | salva TDR | 03.03.2023 -- // 
}

if (isset($_GET['remove'])) {
	$tableName = strtolower($form["title"]);
	// $updateID = $_GET['id']; // No security check - removed by salva TDR | 15.12.2022
	$removeImg = "UPDATE $tableName SET `image2` = ' ' 
	WHERE `id` = $recordnumber";
	$query = mysqli_query($conn, $removeImg);
}

// Update Single images (only product images)
if (isset($_POST["proimage"])) {
	$updatedone = 0;
	$images = $FORM->getProductImages();

	$result = array();

	foreach ($images as $proimg) {
		$data['img_id'] = $proimg["id"];
		$data['pro_alt'] = $_POST["alt-{$data['img_id']}"];
		$data['pro_caption'] = $_POST["caption-{$data['img_id']}"];

		$logtable = "pro_product_images";
		$action = "Update Product Images Tags | " . $proimg["id"];
		$notes = "imageID: $img_id";

		$response = $FORM->updateProductImage($data);

		if ($response['status'] == 'success') {
			$sqlproductlog = $response['query'];
			savelog('', $action, $sqlproductlog, $logtable, 'SUCCESS', $notes, $proimg["id"]);
		} else {
			$result[] = $response['message'];
			$sqlproductlog = $response['query'];
			savelog('', $action, $sqlproductlog, $logtable, 'FAIL', $notes, $proimg["id"]);
		}
	}

	if (count($result) > 0) {
		$errormsg = implode("<br>", $result);
		echo "<script> alert('" . $errormsg . "')</script>";
	} else {
		// -- We get the afterEdit field and we redirect to the page -- //
		// $afterEdit = $form['afterEdit'];
		// if (!empty($afterEdit)) {
		// 	$redirectTo = str_replace("##", $formnumber, $afterEdit);
		// 	// echo "AFTER EDIT: ". $afterEdit . "<br>";
		// 	// echo "REDIRECT TO: $redirectTo <br>";
		// 	header("Location: " . $redirectTo . "");
		// } else {
		// 	echo "<script>alert('Image info updated!');</script>";
		// }
		// -------------------------------------------- //
		echo "<script> 
			alert('Image info updated!');
			window.location='" . $baseURL . "/wccms/recordEditv".$prefs['prefCMSVer'].".php?frm=$formnumber&id=$recordnumber'
		</script>";
	}
}
// End update Single images (only product images)

// Start update gallery images (other forms)
if (isset($_POST["updateGallery"])) {
	$updatedone = 0;
	$images = $FORM->getGallery();

	$result = array();

	foreach ($images as $proimg) {
		$data['img_id'] = $proimg["id"];
		$data['alttag'] = $_POST["alt-{$data['img_id']}"];
		$data['caption'] = $_POST["caption-{$data['img_id']}"];

		$logtable = "gallery";
		$action = "Update Image Tags | " . $proimg["id"];
		$notes = "imageID: {$data['img_id']}";

		$response = $FORM->updateGalleryImage($data);

		if ($response['status'] == 'success') {
			$sqlproductlog = $response['query'];
			savelog('', $action, $sqlproductlog, $logtable, 'SUCCESS', $notes, $proimg["id"]);
		} else {
			$result[] = $response['message'];
			$sqlproductlog = $response['query'];
			savelog('', $action, $sqlproductlog, $logtable, 'FAIL', $notes, $proimg["id"]);
		}
	}

	if (count($result) > 0) {
		$errormsg = implode("<br>", $result);
		echo "<script> alert('" . $errormsg . "')</script>";
	} else {
		$toast[] = array(
			"message" => "Image info updated!",
			"type" => "success"
		);

		// -- We get the afterEdit field and we redirect to the page -- //
		// $afterEdit = $form['afterEdit'];
		// if (!empty($afterEdit)) {
		// 	$redirectTo = str_replace("##", $formnumber, $afterEdit);
		// 	// echo "AFTER EDIT: ". $afterEdit . "<br>";
		// 	// echo "REDIRECT TO: $redirectTo <br>";
		// 	header("Location: " . $redirectTo . "");
		// } else {
		// 	echo "<script>alert('Image info updated!');</script>";
		// }
		// -------------------------------------------- //

		// echo "<script> 
		// 	alert('Image info updated!');
		// 	window.location='" . $baseURL . "/wccms/recordEditv3.php?frm=$formnumber&id=$recordnumber'
		// </script>";
	}
}
// End update gallery images (other forms)


if ($_GET['insert']) { // New record added
	$insertStatus = securityCheck($_GET['insert']);
	$insertMessage = securityCheck($_GET['msg']);

	$toast[] = array(
		"message" => $insertMessage,
		"type" => $insertStatus
	);
} elseif (isset($_GET["copy"])) {
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
} elseif (isset($_GET['del'])) { // Image deleted
	$delStatus = securityCheck($_GET['del']);
	$delMessage = securityCheck($_GET['msg']);

	$toast[] = array(
		"message" => $delMessage,
		"type" => $delStatus
	);
}
?>

<html lang="en">

<head>

	<?php
	include ("include/header-code.php"); // Added by Salva TDR | 5.12.2022
	?>
	<script>
		$(document).ready(function () {
			<?php
			if (count($toast) > 0) {
				echo "const toast = new Toast('" . $toast[0]['message'] . "', '" . $toast[0]['type'] . "');";
				echo "toast.show();";
			}
			?>

			$("#sortable").sortable({
				stop: function (event, ui) {
					var itemOrder = $('#sortable').sortable("toArray");
					let order = [];
					const formNumber = <?php echo $formnumber; ?>

					for (var i = 0; i < itemOrder.length; i++) {
						order.push({
							"position": i,
							"id": itemOrder[i]
						});
					}

					var formData = new FormData();
					formData.append('order', JSON.stringify(order));
					formData.append('formNumber', formNumber);

					$.ajax({
						url: 'sortdata.php',
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						dataType: 'json',
						success: function (response) {
							const toast = new Toast(response.message, response.status);
							toast.show();
						},
						error: function (xhr, status, error) {
							alert('Error in sort function');
							console.error(error);
						}
					})
				}
			});

			$("#sortable").disableSelection();
			getattr();
			getcomp();

			$('#attrselect').change(function () {
				var selectid = $('#attrselect').val();
				var selecttext = $('#attrselect option:selected').text();
				var proid = "<?php echo $id; ?>";
				//alert(selectid);
				var header = "<tr style='font-weight:bold'><td>Attribute Name</td><td>Value</td><td>Weight</td><td>ShowOnWeb</td><td>Action</td></tr>"

				$.ajax({
					url: 'ajax.getattr.php',
					type: 'post',
					data: 'attrid=' + selectid + '&proid=' + proid + '&attrtext=' + selecttext,
					success: function (msg) {
						//  alert(msg)
						$('#attrvalue').empty();
						$('#attrvalue').append(header);
						$('#attrvalue').append(msg);
					}
				})
			})
			//comp code
			$('#compselect').change(function () {
				var selectid = $('#compselect').val();
				var selecttext = $('#compselect option:selected').text();
				var proid = "<?php echo $id; ?>";
				//alert(selectid);
				var header = "<tr style='font-weight:bold'><td>Component Name</td><td>Value</td><td>Weight</td><td>Extra Cost</td><td>ShowOnWeb</td><td>Action</td></tr>"
				$.ajax({
					url: 'ajax.getcomp.php',
					type: 'post',
					data: 'attrid=' + selectid + '&proid=' + proid + '&attrtext=' + selecttext,
					success: function (msg) {
						// alert(msg)
						$('#compvalue').empty();
						$('#compvalue').append(header);
						$('#compvalue').append(msg);
					}
				})
			})
		})

		//comp function
		function getcomp() {
			var selectid = $('#compselect').val();
			var selecttext = $('#compselect option:selected').text();
			var proid = "<?php echo $id; ?>";
			//alert(selectid);
			var header = "<tr style='font-weight:bold'><td>Component Name</td><td>Value</td><td>Weight</td><td>Extra Cost</td><td>ShowOnWeb</td><td>Action</td></tr>"

			$.ajax({
				url: 'ajax.getcomp.php',
				type: 'post',
				data: 'attrid=' + selectid + '&proid=' + proid + '&attrtext=' + selecttext,
				success: function (msg) {
					// alert(msg)
					$('#compvalue').empty();
					$('#compvalue').append(header);
					$('#compvalue').append(msg);
				}
			})
		}

		//attr function
		function getattr() {
			var selectid = $('#attrselect').val();
			var selecttext = $('#attrselect option:selected').text();
			var proid = "<?php echo $id; ?>";
			//alert(selectid);
			var header = "<tr style='font-weight:bold'><td>Attribute Name</td><td>Value</td><td>Weight</td><td>ShowOnWeb</td><td>Action</td></tr>"

			$.ajax({
				url: 'ajax.getattr.php',
				type: 'post',
				data: 'attrid=' + selectid + '&proid=' + proid + '&attrtext=' + selecttext,
				success: function (msg) {
					//alert(msg)
					$('#attrvalue').empty();
					$('#attrvalue').append(header);
					$('#attrvalue').append(msg);
				}
			})
		}
	</script>
	<script>
		var limit = "<?php echo $maximg; ?>"; // It's useless because is allways empty - salva TDR | 13.12.2022

		Dropzone.options.myAwesomeDropzone = {
			maxFiles: limit,
			accept: function (file, done) {
				console.log("uploaded");
				done();
			},
			init: function () {
				this.on("maxfilesexceeded", function (file) {
					alert("No more files please!");
				});
			}
		};

		function check(id, imgid) {
			var data = id.id
			if ($('#' + data).is(':checked')) {
				state = "Yes";
			} else {
				state = "No";
			}

			$.ajax({
				url: 'ajax.changeshowonweb.php',
				type: 'post',
				data: 'imgid=' + imgid + '&state=' + state,
				success: function (msg) {
					//alert(msg)
				}
			})
		}
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

		label .cmsform {
			font-size: 20px;
			color:orange;
			background-color: pink;
		}

	</style>
</head>

<body>
	<section id="container" class="">
		<?php
		include ("include/header.php");
		include ("include/sidebar.php");
		?>

		<!--main content start-->

		<section id="main-content">
			<section class="wrapper site-min-height">
				<?php
				// echo "<pre>";
				// print_r($rowcontent);
				// echo "</pre>";
				?>

				<!-- page start-->
				<div class="row">
					<div class="col-lg-12">
						<?php
						$key = array_keys($rowcontent)[1];
						?>

						<h2><span style="font-weight:200;">Edit <?php echo $form["title"]; ?></span>:
							<?= $rowcontent[$key] . " <sup>(id: $recordnumber)</sup>"; ?></h2>
						<!-- 	<p><a href="?php echo $baseURL . "/product/" . $id . "/" . $proslug; ?>" target="_blank">Check Live Page</a> | </p>-->

						<section class="card">
							<div class="card-body">
								<!-- Set up Tabs -->
								<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
									<?php
									$tabs = $FORM->getFormTabs();

									foreach ($tabs as $tab) {
										if ($tab['showonweb'] == "No") {
											continue;
										}
										echo "hello" ;
										$iconTab = $MENU->getIcon($tab["icon"]);

										echo "<li class='nav-item mr-1'>";
										echo "<a class='nav-link " . ($tab['tabID'] == 1 ? "active" : "") . "' id='pills-" . strtolower($tab['name']) . "-tab' data-toggle='pill' href='#pills-" . strtolower($tab['name']) . "' role='tab' aria-controls='pills-" . strtolower($tab['name']) . "' aria-selected='false'><i class='$iconTab'></i> {$tab['name']}</a>";
										echo "</li>";
									}

									// * This filter need to be manually because when is a product, you have a gallery default on it and you haven't a field for it | salva TDR *
									if ($formnumber == 11) {
										$iconTab = $MENU->getIcon(77);

										echo "<li class='nav-item mr-1'>";
										echo "<a class='nav-link' id='pills-gallery-tab' data-toggle='pill' href='#pills-gallery' role='tab' aria-controls='pills-gallery' aria-selected='false'><i class='$iconTab'></i> Gallery</a>";
										echo "</li>";
									}
									?>
								</ul>

								<div class="tab-content" id="pills-tabContent">
									<?php
									foreach ($tabs as $tab) {
										echo "<div class='tab-pane fade " . ($tab['tabID'] == 1 ? "active show" : '') . "' id='pills-" . strtolower($tab['name']) . "' role='tabpanel' aria-labelledby='pills-" . strtolower($tab['name']) . "-tab'>";

										if ($tab['name'] != "Gallery") { ?>
											<form role="form" class="form-horizontal" method="post" enctype="multipart/form-data">
												<?php
												echo "<input type='hidden' name='formnumber' value='" . $formnumber . "' />"; // pass forward the Form ID 
										
												foreach ($form_fields as $rowformfield) {

													// if is not the tab, we skip it
													if ($rowformfield['tab'] != $tab['tabID']) {
														continue;
													}

													$imgfld = 0;

													// set Required 
													if ($rowformfield["required"] == 'Yes') {
														$required = 'required';
													} else {
														$required = '';
													}

													//Get the Field Type	
													$rowfield = $FORM->getFieldType($rowformfield["field"]);

													if ($rowfield['id'] == '24') { // Download CV
														$ContentValue = $rowcontent['uploadfilename'];
													} else {
														$ContentValue = $rowcontent[$rowformfield["name"]];
													}

													// echo "<br /> CONTENT VALUE -> " . $ContentValue . "<br />";
										
													// SET WIDTH STYLE
													$itemclass = $rowformfield["class"];
													// We change the all if statements to switch case
													switch ($itemclass) {
														case 'small':
															$itemclass = 'width:25%';
															break;
														case 'm-25':
															$itemclass = 'width:25%';
															break;
														case 'medium':
															$itemclass = 'width:50%';
															break;
														case 'm-50':
															$itemclass = 'width:50%';
															break;
														default:
															$itemclass = 'width:50%';
															break;
													}

													$FORMFIELD = new FormField($rowformfield, $rowfield, $ContentValue, $TypeDebug, $infomark);

													if ($rowformfield['showonweb'] == 'Yes') {
														if ($rowformfield['showedit'] == 'Yes') {
															echo $FORMFIELD->render($recordnumber, $formnumber, $gallery, $baseURL);
														}
													}
												}
												// --- END added by salva TDR | 04.01.2023 --- 
												?>
												<div class='form-group button-fixed-right-bottom'>
													<input type="hidden" id="check_image" />
													<button data-popover="popover" data-content="Save" data-placement="top"
														data-trigger="hover" type="submit" name="submit" class="btn btn-success" id="sbt"><i
															class="fa fa-check"></i>UPDATE RECORD</button>
												</div>
											</form>
										<?php } else { // Gallery tab
											foreach ($form_fields as $rowformfield) {
												// if is not the tab, we skip it
												if ($rowformfield['tab'] != $tab['tabID']) {
													continue;
												}

												$imgfld = 0;

												// set Required 
												if ($rowformfield["required"] == 'Yes') {
													$required = 'required';
												} else {
													$required = '';
												}

												//Get the Field Type	
												$rowfield = $FORM->getFieldType($rowformfield["field"]);

												$ContentValue = $rowcontent[$rowformfield["name"]];

												// echo "<br /> CONTENT VALUE -> " . $ContentValue . "<br />";
									
												// SET WIDTH STYLE
												$itemclass = $rowformfield["class"];
												// We change the all if statements to switch case
												switch ($itemclass) {
													case 'small':
														$itemclass = 'width:25%';
														break;
													case 'm-25':
														$itemclass = 'width:25%';
														break;
													case 'medium':
														$itemclass = 'width:50%';
														break;
													case 'm-50':
														$itemclass = 'width:50%';
														break;
													default:
														$itemclass = 'width:50%';
														break;
												}

												$FORMFIELD = new FormField($rowformfield, $rowfield, $ContentValue, $TypeDebug,$infomark);
												echo $FORMFIELD->render($recordnumber, $formnumber, $gallery, $baseURL);
											}
										} ?>
										<hr>
										<h6><em>Notes:</em></h6>
										<p><?php echo $form['text']; ?></p>
										<p><?php echo $tab['text']; ?></p>
										<?php echo "</div>";
									}

									if ($formnumber == 11) { // Product Gallery
										?>
										<!-- Image  tab -->
										<div class="tab-pane fade" id="pills-gallery" role="tabpanel"
											aria-labelledby="pills-gallery-tab">
											<!-- START edited by salva TDR | 25.1.2023 -->
											<!-- <form action="uploadnewspic.php?idvalue=?php echo $recordnumber; ?>&max=?php echo $maximg; ?>&tbl=?php echo $formnumber; ?>" class="dropzone" id="my-awesome-dropzone">
											</form> -->
											<form
												action="controllers/image_processor.php?id=<?php echo $recordnumber ?>&frm=<?php echo $formnumber ?>"
												method="post" enctype="multipart/form-data" class="dropzone" id="my-dropzone">
												<div class="fallback">
													<input type="file" name="images[]" multiple>
												</div>
											</form>
											<button type="button" class="btn btn-success" id="submit-all">Upload Images</button>
											<div id="response" style="background-color: white;">
											</div>

											<script>
												const MAX_FILES = 10;
												Dropzone.options.myDropzone = {
													paramName: "images", // Nombre del parámetro de los archivos
													// maxFilesize: 2, // Tamaño máximo de los archivos en MB
													acceptedFiles: ".jpg,.jpeg,.png,.gif", // Extensiones de archivo aceptadas
													dictDefaultMessage: "Drag your images here or click to select them", // Mensaje predeterminado
													autoProcessQueue: false, // Deshabilitar el procesamiento automático
													// accept: function(file, done) {
													//    done();
													// },
													maxFiles: MAX_FILES, // Número máximo de archivos
													parallelUploads: MAX_FILES, // Número máximo de archivos que se pueden cargar al mismo tiempo
													uploadMultiple: true, // Habilitar la carga de múltiples archivos
													init: function () {
														var myDropzone = this;
														var submitButton = document.querySelector("#submit-all");
														submitButton.addEventListener("click", function () {
															myDropzone.processQueue(); // Procesar la cola de archivos
															// document.getElementById("my-dropzone").submit(); // Enviar el formulario
														});
													},
													success: function (file, response) {
														console.log(response);
														var div_response = document.querySelector("#response");
														div_response.innerHTML = response;
													}
												};
											</script>

											<form method="post" enctype="multipart/form-data">
												<center>
													<table class="table">
														<tbody id="sortable">
															<?php
															$images_data = $FORM->getProductImages();
															// echo "<pre>";
															// print_r($images_data);
															// echo "</pre>";
														
															$countimg = count($images_data);
															$i = 0;

															foreach ($images_data as $resimglist) {
																$img_id = $resimglist["id"];
																if ($formnumber == 11) {
																	$img_src = "/filestore/images/products/sm/" . $resimglist["image"];
																} else {
																	$img_src = "/filestore/images/content/" . $resimglist["image"];
																}
																$img_name = $resimglist["image"];
																$alttag = $resimglist["alttag"];
																// $titletag = $resimglist["titletag"]; // this column is not in DB
																$img_caption = $resimglist["caption"];
																$sort = $resimglist["sort"];
																$showonweb = $resimglist["showonweb"];

																if ($showonweb == "Yes") {
																	$checked = "checked";
																} else {
																	$checked = "";
																}

																$sort += 1;

																echo "<tr id='$img_id'>
																	<td><img src='$img_src' width=100px></td>
																	<td> 
																		Alt <input type='text' class='form-control' style='width:25%' value='$alttag' name='alt-$img_id'>
																		Caption <input type='text' class='form-control ' style='width:25%' value='$img_caption' name='caption-$img_id'>
																		<div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
																			<input type='checkbox'  $checked  class='custom-control-input' id='customSwitch$i' onchange='check(this,$img_id)'>
																			<label class='custom-control-label' for='customSwitch$i'>Show on Web</label>
																			<span style='font-size:14px;padding-left:20px;'><em>Filename:</em> $img_name</span>
																		</div>
																	</td>
																	<td colspan=2>
																		<a href='remove.img.php?img=$img_id&frm=$formnumber&id=$recordnumber'><i class='far fa-trash-alt'></i></a>
																	</td>
																</tr>";
																$i++;
															}
															?>
														</tbody>

													</table>
												</center>

												<center>
													<input type="submit" name="proimage" class="btn btn-success" value="Update Images"
														style="width:20%">
												</center>
											</form>
											<hr>
											<p>Images in gallery = <?php echo $countimg; ?></p>
											<!-- END edited by salva TDR | 25.1.2023 -->
										</div>
										<!-- End Update Images -->
									<?php } ?>
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
						$formQuery = $FORM->getFormQuery();
						$tableQuery = $FORM->getTableQuery();
						$formFieldsQuery = $FORM->getFormFieldsQuery();

						echo "<p><b>Form query: </b>{$formQuery}</p>";
						echo "<p><b>Table query: </b>{$tableQuery}</p>";
						echo "<p><b>Form Fields query: </b>{$formFieldsQuery}</p>";
					}
					?>
				</div>
			</section>
		</section>
	</section>
	<?php
	// include("include/footer.php"); // - footer.php doesn't exist inside wccms/include folder - Salva TDR | 17.1.2023
	// echo "</div>"; // Removed by salva TDR | 17.1.2023
	include ("include/footer-code.php");
	include ("include-tinymce.php");
	?>
</body>

</html>

<!-- END recordEditv3 -->