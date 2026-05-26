<!-- START recordAddv4 -->
<!-- WiteCanvasCMS ver 3.0 -->
<?php

error_reporting(1);
include('setting/main-top-files.php'); // Added by salva TDR | 16.1.2023


$TypeDebug = $prefs["prefCMSDebugOn"]; // Yes or No
$PageType = 'Add'; // Add, Edit, Copy, Delete

// include('wideimage/lib/WideImage.php');

$fileuploadname = "";

if ($TypeDebug == 'Yes' or $userlevel > '20') {
	echo "<script>alert('Open ID = [" . $_GET['id'] . "]');</script>";
	echo "<script>alert('Post name = [" . $_POST['name'] . "]');</script>";
	echo "<script>alert('Post Submit = [" . $_POST['submit'] . "]');</script>";
}

if (!$formnumber = securityCheck($_GET['frm'], 'number')) {
	die('Error in the form');
}

// --- START added by salva TDR | 16.1.2023 ---
$FORM = new RecordAdd($formnumber);
$form = $FORM->getForm();
$table = $FORM->getTable();
$form_fields = $FORM->getFormFields(true);
// echo "<pre>";
// print_r($form);
// print_r($table);
// print_r($form_fields);
// echo "</pre>";
// --- END added by salva TDR | 16.1.2023 ---

if (isset($_POST['submit'])) {
	$updateData = [];
	$errors = array();

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
				$slugValue = 'No-Value-For-Slug'; // Fallback value in case none of the fields are available
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
	}

	// Check if Internal `Name` exists
	if (isset($_POST['name']) ) {
		// Check if internal name is empty
		if (empty($_POST['name'])) {
			// Generate a value for internal name
			if (!empty($_POST['title'])) {
				$nameValue = $_POST['title'];
			} elseif (!empty($_POST['heading'])) {
				$nameValue = $_POST['heading'];
			} else {
				$nameValue = 'No Name Value For Internal Name'; // Fallback value in case none of the fields are available
			}
			$_POST['name'] = $nameValue;
		}
	}

// --- DEBUG: confirm form_fields availability ---
if (!isset($form_fields)) {
    error_log("DEBUG ADD: form_fields variable NOT SET in recordAddv4.php");
} else {
    error_log("DEBUG ADD: form_fields variable contains " . count($form_fields) . " items");
}

// --- Build POST data safely ---
foreach ($_POST as $key => $value) {
    error_log("DEBUG ADD: starting field '$key' with raw value '$value'");
    $updateData[$key] = securityCheck($value);
}

error_log("DEBUG ADD: insertTableContent will be called with " . count($updateData) . " fields");

// --- Call insert with full context ---
$insertResponse = $FORM->insertTableContent($updateData, $form_fields);


	$logtable = $table['name'];
	$action = "Insert New Record into Form " . $formnumber;
	$sqlquery = mysqli_real_escape_string($conn, $insertResponse['query']);
	$notes = $insertResponse['message'];
	$username = $_SESSION["useremail"];

	if ($insertResponse['status'] == 'error') {
		$errors = array_merge($errors, $insertResponse);
		saveLogV2($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordnumber);
	} else {
		saveLogV2($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordnumber);
	}

	if (count($errors) > 0) {
		echo "<pre>";
		echo "Errors: ";
		print_r($errors);
		echo "</pre>";
		die;
	}

	echo "<script>
   	window.location='recordEditv{$prefs["prefCMSVer"]}.php?frm={$formnumber}&id={$insertResponse['recordID']}&insert={$insertResponse['status']}&msg={$insertResponse['message']}';
   </script>";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<!--
<script>
(function () {
  const orig = window.flatpickr;
  Object.defineProperty(window, 'flatpickr', {
    configurable: true,
    enumerable: true,
    get() { return orig; },
    set(fn) {
      const wrapper = function(selector, opts) {
        try { console.log('[flatpickr called on]', selector, opts && opts.defaultDate); } catch(e){}
        return fn.apply(this, arguments);
      };
      Object.setPrototypeOf(wrapper, fn); // preserve static props
      Object.defineProperty(window, 'flatpickr', { value: wrapper, configurable: true });
    }
  });
})();
</script>

<script>
window._flatpickrWatch = [];
const oldFlatpickr = window.flatpickr;
window.flatpickr = function(...args) {
  console.log('Flatpickr called on', args[0]);
  window._flatpickrWatch.push(args);
  return oldFlatpickr.apply(this, args);
};
</script>
-->

	<?php
	echo "<title>" . $form['title'] . " | Add Record</title>";
	include("include/header-code.php");
	?>
	<style>
		.gallery_product img {
			width: 75px;
			height: 75px;
			margin-bottom: 20px;
		}

		.current img {
			border: #CE559A 2px solid;
		}

		.gallery_product {
			position: relative;
			display: inline-block;
		}

		.select_button a {
			padding: 10px;
			background-color: #9C4755;
			color: #c9d4f6;
		}

		.select_button {
			margin-bottom: 25px;
		}
		.form-group {
			padding-bottom:15px;
		}
		.form-group.Gallery {
			display: none;
		}
		.form-group label {
			font-size: 14px;
			font-weight: 600;
		}
		.comment {
			font-style: italic;
		}
	</style>
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
					echo "<h3><span style='fontweight:200px;'>Add New </span>" . $form["title"] . "</h3>";
				?>
			</div>
			<div class="content">
				<?php
				$infomark = "<i class='fas fa-info-circle' style='color:#28998B; padding-left:10px;'></i>";
				?>

				<!-- FORM HERE -->
				<form role="form" class="form-horizontal" method="post" enctype="multipart/form-data">
					<?php
					echo "<p><span style='color:red;'>*</span> Read Only</p><hr>" ;
					echo "<input type='hidden' name='formnumber' value='" . $formnumber . "' />";

					foreach ($form_fields as $rowformfield) {
						// echo "<pre>";
						// print_r($rowformfield);
						// echo "</pre>";
						// set Required 
						if ($rowformfield["required"] == 'Yes') {
							$required = 'required';
						} else {
							$required = '';
						}

						//Get the Field Type	
						$rowfield = $FORM->getFieldType($rowformfield["field"]);

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
						$infomark = (!empty($rowformfield['tooltip'])) ? "<i class='fas fa-info-circle  tooltip-icon'></i>" : '';
						$FORMFIELD = new FormField($rowformfield, $rowfield, '', $TypeDebug, $PageType, $infomark);
						echo $FORMFIELD->render(0,$formnumber);

						if ($rowfield["id"] == 118) // New Slug Creation Select Statement From Table
						{
							//Get the Select Data from D/B	
							// - $recordnumber doesn't exist because this is the add page - salva TDR | 17.1.2023 
							$selectfieldoptions =  $rowformfield["sourcesql"];
							$selectfieldoptions = str_replace("{{mainID}}", $recordnumber, $selectfieldoptions);
							$queryfieldoptions = mysqli_query($conn, $selectfieldoptions);
							echo "<div class='form-group'>";
							echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "";
							if ($rowformfield["tooltip"]) {
								echo "<a href='#' data-bs-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'>" . $infomark . "</a>";
								if ($TypeDebug == 'Yes') {
									echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
								}
							}
							echo "</label>";
							echo "<select name='" . $rowformfield["name"] . "'  class='form-control " . $rowformfield["class"] . "'>";
							echo "<option value='0' selected >Select Option</option>";
							while ($rowfieldoptions = mysqli_fetch_assoc($queryfieldoptions)) {
								// Selected ???	
								if ($formnumber == '23') {
									echo "<option value='" . $rowfieldoptions["name"] . "'>" . $rowfieldoptions["name"] . "</option>";
								} else {
									echo "<option value='" . $rowfieldoptions["id"] . "'>" . $rowfieldoptions["name"] . " (" . $rowfieldoptions["id"] . ")</option>";
								}
							}
							echo "</select>";
							echo "</div>";
						}
					}
					?>
					<div class='form-group'>
						<button data-popover="popover" data-content="Save" data-placement="top" data-trigger="hover" type="submit" name="submit" class="btn btn-success" style="margin-top:15px;" id="sbt"><i class="fa fa-check"></i>Save New Record</button>
					</div>
				</form>

				<!-- Form Ends Here -->

				<?php
				if ($form["text"]) {
					echo "<h3>Notes:</h3>";
					echo $form["text"];
				}
				?>

				<!-- Trigger the modal with a button -->
				<?php
					$selectform2 = "SELECT * FROM `gallery`";
					$selectform3 = "SELECT * FROM `folder_name`";
					$queryform3 = mysqli_query($conn, $selectform3);
					$queryform0 = mysqli_query($conn, $selectform2);
				?>

				<!-- Modal -->
				<div class="modal fade" id="myModal" role="dialog">
					<div class="modal-dialog">
						<!-- Modal content-->
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal">&times;</button>
							</div>
							<div class="modal-body">
								<div class="row">
									<div class="gallery col-lg-12 col-md-12 col-sm-12 col-xs-12">
										<div class="gallery_product col-lg-6 col-md-6 col-sm-2 col-xs-6">
											<h1 class="gallery-title">Gallery</h1>
										</div>
										<div class="gallery-filter gallery_product col-lg-6 col-md-6 col-sm-2 col-xs-6">
											<div align="right">
												<select class="select">
													<option value="all">All</option>
													<?php
													while ($row3 = mysqli_fetch_assoc($queryform3)) {
														echo '<option value="f' . $row3["name"] . '">' . $row3["name"] . '</option>';
													} ?>
												</select>
												<?php
												$arr = array();
												$images = array();
												$dates = array();
												$folders = array();
												$ids = array();

												while ($row1 = mysqli_fetch_assoc($queryform0)) {
													$date1 = $row1["date"];
													$ids[] = $row1["id"];
													$date1 = date('F-Y', strtotime($date1));
													$arr[] = $date1;
													$images[] = $row1["image1"];
													$dates[] = $row1["date"];
													$folders[] = $row1["folder_name"];
												}

												$list = array_unique($arr);
												$list = array_values($list);

												//print_r($list);
												?>

												<select class="select">
													<option value="all">All</option>
													<?php
													for ($i = 0; $i < sizeof($list); $i++) {
														echo '<option value="' . $list[$i] . '">' . $list[$i] . '</option>';
													}
													?>
												</select>
											</div>
										</div>
									</div>
									<div class="select_button" align="right">
										<select id="image_type">
											<option value="lg">Select Size</option>
											<option value="sm">Thumbnail</option>
											<option value="st">Standard</option>
											<option value="lg">Large</option>
										</select>

										<a class="" onclick="select_img();" href="javascript:void(0);">Select Image</a>
										<input type="hidden" id="check_image" />
									</div>

									<?php
									for ($j = 0; $j < sizeof($images); $j++) {
										$date = $dates[$j];
										$date2 = date('F-Y', strtotime($date));
										echo '<div id="' . $images[$j] . '"  att="' . $ids[$j] . '" class="gallery_product f' . $folders[$j] . ' col-lg-2 col-md-2 col-sm-2 col-xs-6 filter ' . $date2 . '"><img src="' . $baseURL . '/filestore/images/' . $folders[$j] . '/' . $images[$j] . '" class="img-responsive"></div>';
									}
									?>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Trigger END the modal with a button -->

				<div class="col-sm-2 col-md-2"></div>
			</div>
		</section>
	</section>
	<?php
	// include("include/footer.php"); // - footer.php doesn't exist inside wccms/include folder - Salva TDR | 17.1.2023
	include("include/footer-code.php");
	echo "</div>";
	// include('autoload.php'); // Removed by salva TDR | 17.1.2023
	?>

	<!-- Start of original script -->

	<script type="text/javascript">
		tinymce.init({
			selector: 'textarea',
			menubar: true,
		});

		$(document).ready(function() {
			$.post('ajaxrequest/ajax.folder.php', {
				folder: 'content'
			}, function(data) {}).fail(function() {});

			$(".select_folder").change(function(event) {
				var folder = $(this).val();
				// alert(folder);
				if (folder == 0) {
					alert("Select Folder");
				} else {
					//alert($(this).val());
					$.post('ajaxrequest/ajax.folder.php', {
						folder: folder
					}, function(data) {
						// show the response
						//alert(data);
					}).fail(function() {
						// just in case posting your form failed
						//alert( "Posting failed." );
					});
				}
			});
		});
	</script>

	<!-- end of original script -->

	<script type="text/javascript">
		function check_field(id) {
			//alert(id);
			$('#check_image').val(id);
		}

		function select_img() {
			var imgurl = $('.current').attr('id'); //image_type
			var imgtype = $('#check_image').val();
			var image_type = $('#image_type').val();
			var img_id = $('.current').attr('att');

			//alert(img_id);

			if (imgurl) {
				$("#gallery_image").val(image_type + '-' + imgurl);
				$('#img_' + imgtype).text(image_type + '-' + imgurl);
				$('.gallery').val(img_id);
				//  $('#myModal').modal('toggle');
				$(".close").trigger("click");
				var currentField = $('#check_image').val();
				// alert(currentField);
				$('.' + currentField).val(imgurl);
				//alert(imgurl);
			} else {
				alert('Please Select Image!');
			}
		}

		$(document).ready(function() {
			$('.select').on('change', function() {
				var value = this.value;
				// alert(value);
				if (value == "all") {
					//$('.filter').removeClass('hidden');
					$('.filter').show('1000');
				} else {
					// $('.filter[filter-item="'+value+'"]').removeClass('hidden');
					// $(".filter").not('.filter[filter-item="'+value+'"]').addClass('hidden');
					$(".filter").not('.' + value).hide('3000');
					$('.filter').filter('.' + value).show('3000');
				}
			});

			if ($(".filter-button").removeClass("active")) {
				$(this).removeClass("active");
			}

			$(this).addClass("active");

			$('.gallery_product').on('click', function() {
				$('.gallery_product').removeClass('current');
				$(this).addClass('current');
			});
		});
	</script>
</body>

</html>

<!-- END recordAddv4 -->