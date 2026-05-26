<!-- START contactEdit.php -->
<!-- TruskaCMS ver 4.0.1 -->

<?php
error_reporting(0);
//	include("include/dbcon.php");
   include('include/session.php');
   include('wideimage/lib/WideImage.php');
//Bring Fwd variables
$formnumber = $_GET['frm'] ; // Form number passed through
$recordnumber = $_GET['id'] ; // Record number passed through
//
   include('include/setlogdata.php');

$TypeDebug = 'Yes' ; // Yes or No

$galleryID=$rowcontent['gallery'];
$galleryData = "SELECT * FROM `gallery` WHERE `id` = '" . $recordnumber . "' ";
$galleryQuery = mysqli_query($conn,$galleryData);
$galleryArr = mysqli_fetch_assoc($galleryQuery) ;

$counterfields = 1 ;
	//Get the Form
	$selectform = "SELECT * FROM `cms_form` WHERE `id` = '" . $formnumber . "' ";
		//echo "selectform (15) = " . $selectform . "<br>" ;
	$queryform = mysqli_query($conn,$selectform);
	$rowform = mysqli_fetch_assoc($queryform) ;

	// Get the Field names
	$selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $formnumber . "' ORDER BY `table` ";
		//echo "selectformfield (21) = " . $selectformfield . "<br>" ;
	$queryformfield = mysqli_query($conn,$selectformfield);
	$rowformfield = mysqli_fetch_assoc($queryformfield) ;

	// Get the Table name
		$selecttable = "SELECT * FROM `cms_table` WHERE `id` = '" . $rowformfield["table"] . "' ";
		//echo "selecttable (27) = " .$selecttable . "<br>" ;
	$querytable = mysqli_query($conn,$selecttable);
	$rowtable = mysqli_fetch_assoc($querytable) ;

echo "<div  class='hidden' style='border-bottom;cyan thin solid; background-color:cyan; min-height:30px;'>" ;

echo "</div>" ;

// PROCESS THE FORM IF COMPLETED
if(isset($_POST['submit']))
{
	
	//Get the Form
	$selectform = "SELECT * FROM `cms_form` WHERE `id` = '" . $formnumber . "' ";
		//	echo $selectform . "<br>" ;
	$queryform = mysqli_query($conn,$selectform);
	$rowform = mysqli_fetch_assoc($queryform) ;

// Get the fields name
	$selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `table` ";
		//echo $selectformfield . "<br>" ;
	$queryformfield = mysqli_query($conn,$selectformfield);
	$rowformfield = mysqli_fetch_assoc($queryformfield) ;

		$selecttable = "SELECT * FROM `cms_table` WHERE `id` = '" . $rowformfield["table"] . "' ";
		//	echo $selecttable . "<br>" ;
		$querytable = mysqli_query($conn,$selecttable);
		$rowtable = mysqli_fetch_assoc($querytable) ;

	// value method	$insert = "INSERT INTO `" . $rowtable["name"] . "` (`id` " ;
	$insert = "UPDATE `" . $rowtable["name"] . "` SET " ;

	//Get the Fields	
	$selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `order` ";
			//echo $selectformfield . "<br>" ;
	$queryformfield = mysqli_query($conn,$selectformfield);

	$selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `table` ";
			//echo $selectformfield . "<br>" ;
	$queryformfield = mysqli_query($conn,$selectformfield);

		$counter = 1 ;

		foreach($_POST as $var => $val)
		{

			$$var = mysqli_real_escape_string($conn, $var) ;
			$$val = mysqli_real_escape_string($conn, $val) ;
		//echo $$var. " @ " .$$val."<br>";	



			if ($counter == 1 OR $$var == 'submit') 
				{
					// $insert = $insert . "&#39&quot; .mysqli_real_escape_string($conn, '" . $val . "') .&quot;&#39<br>" ;
				}
				elseif($$var == "checkbox")
				{
					$data = json_encode($_POST['checkbox']);
					$insert = $insert . " , `" . $$var . "` = '" . $data . "' " ;
				}
			
				elseif($$var == "password")
				{
					$data = md5($$val);
					$insert = $insert . " , `" . $$var . "` = '" . $data . "' " ;
				}

				elseif($$var == "image")
				{
					if ($$val)
					{
						$insert = $insert . " , `" . $$var . "` = '" . $$val . "' " ;
					}
					
				}
			
				else
				{
				if ($counter == 2) 
					{
						$insert = $insert . " `" . $$var . "` = '" . $$val . "' " ;
					}
					else {
						// value method	$insert = $insert . " , '" . $$val . "'" ;
						$insert = $insert . " , `" . $$var . "` = '" . $$val . "' " ;
					}
				}	
			$counter ++ ;
		}
		
//echo $insert . "<hr>" ;	

		//include image upload functionality
		include "include/imageUpload.php";

	// value method	$insert = $insert . ") " ;
	$insert = $insert . " WHERE `" . $rowtable["name"] . "`.`id` = " . $recordnumber . " ; ";
// ------------------------------	

		$query = mysqli_query($conn,$insert);

	//	echo $insert . "<hr>" ;	
  if($query){
  		addLog("Update",$rowform["title"],$insert);
   		$loc = $rowform['afterEdit']; 
		if($loc){			
			$str1 = str_replace("##",$_GET['frm'],$loc);
			$redirectTo = str_replace("@@",$_GET['id'],$str1);
			echo "<script>alert('Content Saved - $insert');</script>";
			header("Location: " .$redirectTo. "");
		}else{
			echo "<script>alert('Content Saved - $insert');</script>";
		}
  }
  else{
	echo "<hr>" ;
    echo "error here".mysqli_error();
	echo "<hr>" ;
  }

}

// END FORM PROCESSING
?>

<!DOCTYPE html>
<html lan1g="en">
  
<head>
 <?php 
	include("include/header-code.php");
?>


<title>EDIT Record | TruskaCMS</title>
<style>
.gallery_product img {
    width: 75px;
    height: 75px;
    margin-bottom: 20px;
}
.current img {
    border: #CE559A 2px solid;
}
.gallery_product{
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
.form-group.Gallery {
    display: none;
}
</style>

</head>
<body>

  <!-- Fixed navbar -->
<?php 
	include("include/header.php");
	echo "<div id='cl-wrapper' class='fixed-menu'>" ;
	include("include/sidebar.php");
?>

<?php 
?>
		
	<div class="container-fluid content-area" id="pcont">
					
	<!--	<div class="cl-mcont">
		
		</div> -->
		<!--
		<div class="row">
			<ul class="breadcrumb pull-right">
				    <li><a href="/">Home</a></li>
				    <li><a href="/recordEdit.php?id=<?php echo $formnumber; ?>">List</a></li>
			</ul>
		</div>	
-->
			<div class="row">
			
				<div class="col-sm-1 col-md-1"></div> 

				<div class="col-sm-10 col-md-10 col-lg-8">
					<div class="block-flat">
						<div class="header" >
						<?php 
							echo "<h3>Edit " . $rowform["title"] . "</h3>" ;
						?>
						</div>
						<div class="content">



<?php
	//Inpout Variable to be passed forward from else where
	//$formnumber = 6 ;
?>

<!-- FORM HERE -->
	<form role="form" class="form-horizontal" method="post" enctype="multipart/form-data">
<!--	<form action='dbform_post.php' method='post' enctype='multipart/form-data'> -->

		<?php
		
		echo "<input type='hidden' name='formnumber' value='" . $formnumber . "' />" ; // pass forward the Form ID 
		//Get the Fields	
		$selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' AND `showonweb` = 'Yes' ORDER BY `order` ";
		
		$queryformfield = mysqli_query($conn,$selectformfield);
		while ($rowformfield = mysqli_fetch_assoc($queryformfield) ) 
		{

			// set Required 
			if ($rowformfield["required"] == 'Yes') {$required = 'required';} else {$required = '';}

			//Get the Table Information	
			$selecttable = "SELECT * FROM `cms_table` WHERE `id` = '" . $rowformfield["table"] . "' ";
			//	echo $selecttable . "<br>" ;
			$querytable = mysqli_query($conn,$selecttable);
			$rowtable = mysqli_fetch_assoc($querytable) ;
			
			//Get the Field Type	
			$selectfield = "SELECT * FROM `cms_field` WHERE `id` = '" . $rowformfield["field"] . "' ";
					//echo $selectfield . "<br>" ;
			$queryfield = mysqli_query($conn,$selectfield);
			$rowfield = mysqli_fetch_assoc($queryfield) ;


			//Get the Actual Table Content Type	
			$selectcontent = "SELECT * FROM `" . $rowtable["name"] . "` WHERE `id` = '" . $recordnumber . "' ";
	//echo $selectcontent . "<br>" ;
			$querycontent = mysqli_query($conn,$selectcontent);
			$rowcontent = mysqli_fetch_assoc($querycontent) ;
			
			//$myvar = "subheading";
			$myvar = $rowformfield["name"] ;
			//echo "field name = " . $myvar . "<br>" ;
			$ContentValue = $rowcontent[$myvar] ;
			
		//		echo "<br>DeDug: Record Type id = " . $rowfield["id"] . "<br>" ;

// -----------------------------------

	// CREATION OF THE FORM BASED ON DATABASE DATA START
			
	//--------------------------------------------------
	
		if ($counterfields == 1 ) 
		{
			echo "<h4><strong>Record ID = " . $rowcontent["id"] . "</strong></h4>" ;
			$counterfields ++ ;
		}

			
	//--------------------------------------------------
			
			if ($rowfield["id"] == 1 ) // Text Field
			{
			//	$ContentValue = $rowcontent["Key"] ;
				if ($rowformfield["allowedit"] == 'Yes') 
				{
					echo "<div class='form-group ".$rowformfield["label"]."'>" ;
						echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
							if ($rowformfield["tooltip"]) {
								echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
								if ($TypeDebug == 'Yes') {
									echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
								}
							}
					echo "</label>" ;
						echo "<input type='" . $rowfield["type"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' name='" . $rowformfield["name"] . "' placeholder='" . $rowformfield["placeholder"] . "' value='" . $ContentValue . "' $required style=>" ;
					echo "<span>" . $rowformfield["comment"] . "<span>" ;
					echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . $ContentValue . "</strong></h4>" ;
				}
			}
			
// -----------------------------------
			
			if ($rowfield["id"] == 2 ) // PASSWORD Field
			{
				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) 
						{
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo " </label>" ;
					echo "<input type='" . $rowfield["type"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' name='" . $rowformfield["name"] . "' placeholder='" . $rowformfield["placeholder"] . "' value='" . $ContentValue . "' $required style=>" ;
				echo "</div>" ;
			}
			
// ----------------------
			
			if ($rowfield["id"] == 3 ) // Radio Button - Input from Options table
			{
			//Get the Radio Options	
			$selectfieldoptions = "SELECT * FROM `cms_form_field_options` WHERE `form_field` = '" . $rowformfield["id"] . "' ";
					//echo $selectfieldoptions . "<br>" ;
			$queryfieldoptions = mysqli_query($conn,$selectfieldoptions);			

				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label><br>" ;
				while ($rowfieldoptions = mysqli_fetch_assoc($queryfieldoptions) )
				{
					if ($rowfieldoptions["checked"] == 'Yes') {
					$checked = $rowfieldoptions["checked"] ;
					}
					echo "<input type='radio' name='" . $rowformfield["name"] . "' value='" . $rowfieldoptions["value"] . "' " . $checked . "> " . $rowfieldoptions["display"] . "<br>" ;
				}
				//	echo "<input type='radio' name='" . $rowformfield["name"] . "' value='no'> No" ;    	    	
				echo "</div>" ;
			}

// -----------------------------------------
			
			if ($rowfield["id"] == 4 ) // Check Box from Input
			{ 
			$storedData = json_decode($ContentValue);
			$selectfieldoptions = "SELECT * FROM `cms_form_field_options` WHERE `form_field` = '" . $rowformfield["id"] . "' ";
				$queryfieldoptions = mysqli_query($conn,$selectfieldoptions);			

				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label><br>" ;
				while ($rowfieldoptions = mysqli_fetch_assoc($queryfieldoptions) )
				{
					if (in_array($rowfieldoptions["value"], $storedData)) {$checked = 'checked';} else {$checked = '';}

					echo "<input type='checkbox' name='" . $rowformfield["name"] . "[]' value='" . $rowfieldoptions["value"] . "' " . $checked . "> " . $rowfieldoptions["display"] . "<br>" ;
				}
				//	echo "<input type='radio' name='" . $rowformfield["name"] . "' value='no'> No" ;    	    	
				echo "</div>" ;
			}
			
// -----------------------------------------
			
			if ($rowfield["id"] == 5 ) // Colour Picker
			{ 
				echo "<div class='form-group'>" ;
				echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
				echo "<input type='color' name='" . $rowformfield["name"] . "' class='form-control inputColor " . $rowformfield["class"] . "' id='exampleInputEmail1' value='" . $ContentValue . "' $required style=>" ;
				echo "</div>" ;
			}

// -----------------------------------------
			
			if ($rowfield["id"] == 22 ) // Date 
			{ 
				echo "<div class='form-group' style='max-width:200px;'>" ;
				echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
				?>
				
				<?php
					echo "<input type='text' id='datetimepicker' value='".$ContentValue."' name='". $rowformfield['name']."' style=>" ;
				
				echo "</div>" ;
			}
			
			
			if ($rowfield["id"] == 6 ) // Date  (Bootstrap)
			{ 
				if ($rowformfield["allowedit"] == 'Yes') 
				{
					echo "<div class='form-group'>" ;
						echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
							if ($rowformfield["tooltip"]) {
								echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
								if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
								echo " - " . $ContentValue . "" ;
							}
						echo "</label>" ;

						echo "<div data-min-view='2' data-date-format='yyyy-mm-dd' class='input-group date datetime col-md-8 col-xs-7'>" ;
							echo "<input name='" . $rowformfield["name"] . "' size='16' value='" . $ContentValue ."' readonly class='form-control " . $rowformfield["class"] . "' type='text' style='width:100%;max-width:".$rowformfield["width"]."'>" ;
							echo "<span class='input-group-addon btn btn-primary'><span class='glyphicon glyphicon-th'></span></span>" ;
						echo "</div>" ;
					
						$pieces = explode('-', flipdate($ContentValue,'-','-')); 
						echo "Current Value = <strong>" . date('D M j Y', mktime(0, 0, 0, $pieces[1]  , $pieces[0] , $pieces[2])) . "</strong>" ;


					echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . $ContentValue . "</strong></h4>" ;
				}
				
		}
		  
// -----------------------------------------
			
			if ($rowfield["id"] == 7 ) // EMAIL Address
			{ 
				echo "<div class='form-group'>" ;
				echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
				echo "<input type='email' name='" . $rowformfield["name"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' value='" . $ContentValue . "' $required style=>" ;
				echo "</div>" ;
			}

// -----------------------------------------
			if ($rowfield["id"] == 8 ) // Month
			{ 
		// Month
				
				echo "<div class='form-group'>" ;
				echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
					if ($rowformfield["tooltip"]) {
						echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
						if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
					}
				echo "</label><br>" ;
					echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control' id='exampleInputEmail1' $required  value='" . $ContentValue . "'  style=>" ;	
				echo "</div>" ;
			}
			
// -----------------------------------------
			
			if ($rowfield["id"] == 9 ) // Number
			{
				if ($rowformfield["max"] == 0)
				{
					$max = '' ;
				}
				else{
					$max = $rowformfield["max"] ;
				}
				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
						echo "<input type='" . $rowfield["type"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' name='" . $rowformfield["name"] . "' placeholder='" . $rowformfield["placeholder"] . "' min='" . $rowformfield["min"] . "' max='" . $max . "'  step='" . $rowformfield["step"] . "' value='" . $ContentValue . "' $required style=>" ;
				echo "</div>" ;
			}

// ---------------------------------------------------------------
			
				if ($rowfield["id"] == 10 ) // Range
			{
				if ($rowformfield["max"] == 0)
				{
					$max = '100' ;
				}
				else{
					$max = $rowformfield["max"] ;
				}
				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
				
				
					echo "<input type='" . $rowfield["type"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' name='" . $rowformfield["name"] . "' placeholder='" . $rowformfield["placeholder"] . "' min='" . $rowformfield["min"] . "' max='" . $max . "' step='" . $rowformfield["step"] . "' value='" . $ContentValue . "' style=>" ;
				echo "</div>" ;
			}


// -----------------------------------------
			if ($rowfield["id"] == 11 ) // Search
			{ 
		// Search
				echo "<div class='form-group'>" ;
				echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
					if ($rowformfield["tooltip"]) {
						echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
						if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
					}
				echo "</label><br>" ;
					echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' value='" . $ContentValue . "' $required placeholder='" . $rowformfield["placeholder"]."' >" ;	
				echo "</div>" ;
			}

// -----------------------------------------
			if ($rowfield["id"] == 12 ) // Tel
			{ 
		// Tele
				if ($rowformfield["allowedit"] == 'Yes') 
				{
					echo "<div class='form-group'>" ;
						echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
							if ($rowformfield["tooltip"]) {
								echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
								if ($TypeDebug == 'Yes') {
										echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
									}
							}
						echo "</label><br>" ;
							echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control' id='exampleInputEmail1' $required value='" . $ContentValue . "' placeholder='" . $rowformfield["placeholder"]."' >" ;	
					echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . $ContentValue . "</strong></h4>" ;
				}

			}

// -----------------------------------------
			if ($rowfield["id"] == 13 ) // Time
			{ 
				if ($rowformfield["allowedit"] == 'Yes') 
				{
					//$ContentValue =  date('g:i', strtotime($ContentValue));
					echo "<div class='form-group'>" ;
					echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
									echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
								}
						}
					echo "</label><br>" ;
						echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' $required value='" . $ContentValue . "' style=>" ;	
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . date('g:i a', strtotime($ContentValue)) . "</strong></h4>" ;
					echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" .date('g:i a', strtotime($ContentValue)) . "</strong></h4>" ;
				}
			}

// -----------------------------------------
			if ($rowfield["id"] == 14 ) // URL Web Address
			{ 
		// URL
				echo "<div class='form-group'>" ;
				echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
					if ($rowformfield["tooltip"]) {
						echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ;
						if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
					}
				echo "</label><br>" ;					
					echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' $required value='" . $ContentValue . "'   placeholder='" . $rowformfield["placeholder"]."' style=>" ;	
				echo "</div>" ;
			}

// -----------------------------------------
			if ($rowfield["id"] == 15 ) // Week
			{ 
		// Week of th Year
				echo "<div class='form-group'>" ;
				echo "<label for='" . $rowformfield["name"] . "'>" . $rowformfield["label"] . "" ;
					if ($rowformfield["tooltip"]) {
						echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
						if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
					}
				echo "</label><br>" ;					
					echo "<input type='" . $rowfield["type"] . "' name='" . $rowformfield["name"] . "' class='form-control " . $rowformfield["class"] . "' id='exampleInputEmail1' $required  value='" . $ContentValue . "' style=>" ;	
				echo "</div>" ;
			}
// -----------------------------------------
			
			if ($rowfield["id"] == 16 ) // Select Statement From Input Options
			{
				if ($rowformfield["allowedit"] == 'Yes') 
				{

					//Get the Select Options	
					$selectfieldoptions = "SELECT * FROM `cms_form_field_options` WHERE `form_field` = '" . $rowformfield["id"] . "' ";
						//echo $selectfieldoptions . "<br>" ;
					$queryfieldoptions = mysqli_query($conn,$selectfieldoptions);	

						echo "<div class='form-group'>" ;
							echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
								if ($rowformfield["tooltip"]) {
									echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
									if ($TypeDebug == 'Yes') {
										echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
									}
								}
						echo "</label>" ;
							echo "<select name='" . $rowformfield["name"] . "'  class='form-control " . $rowformfield["class"] . "' size='1' style=>" ;
						while ($rowfieldoptions = mysqli_fetch_assoc($queryfieldoptions) )
						{
							if ($ContentValue == $rowfieldoptions["value"])
							{
								echo "<option value='" . $rowfieldoptions["value"] . "' selected='selected'>" . $rowfieldoptions["display"] . "</option>" ;
							}
							else
							{
								echo "<option value='" . $rowfieldoptions["value"] . "' >" . $rowfieldoptions["display"] . "</option>" ;
							}

						}
							echo "</select>    " ;	
						echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . $ContentValue . "</strong></h4>" ;
				}
			}

// -----------------------------------------
			
			if ($rowfield["id"] == 17 ) // Radio Button YES / NO
			{
			//Get the Radio Options	for simple YES / NO

				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label><br>" ;
					echo "<input type='radio' name='" . $rowformfield["name"] . "' " . ($ContentValue == 'Yes' ?  checked : '') . " value='Yes'>&nbsp;&nbsp;Yes<br />" ;
					echo "<input type='radio' name='" . $rowformfield["name"] . "' " . ($ContentValue == 'No' ?  checked : '') . " value='No'>&nbsp;&nbsp;No" ;
				echo "</div>" ;
			}

// -----------------------------------------
		

					
			if ($rowfield["id"] == 18 ) // Select Statement From Table
			{
				if ($rowformfield["allowedit"] == 'Yes') 
				{
				
				//Get the Select Data from D/B	
				$selectfieldoptions =  $rowformfield["sourcesql"] ;

				$selectfieldoptions = str_replace("{{mainID}}", $recordnumber, $selectfieldoptions);

				$queryfieldoptions = mysqli_query($conn,$selectfieldoptions);	

					echo "<div class='form-group'>" ;
						echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
							if ($rowformfield["tooltip"]) {
								echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
								if ($TypeDebug == 'Yes') {
									echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
								}
							}
					echo "</label>" ;

					echo "<select name='" . $rowformfield["name"] . "'  class='form-control " . $rowformfield["class"] . "' size='1' style=>" ;
					while ($rowfieldoptions = mysqli_fetch_assoc($queryfieldoptions) )
					{
						if ($ContentValue == $rowfieldoptions["id"])
						{
						echo "<option value='" . $rowfieldoptions["id"] . "' selected='selected'>" . $rowfieldoptions["name"] . "</option>" ;
						}
						else
						{
							if($formnumber ==23){ $gallery_folder_name=$galleryArr['folder_name'];
                                  echo "<option ".(($gallery_folder_name==$rowfieldoptions["name"])?'selected="selected"':"")." value='" . $rowfieldoptions["name"] . "'>" . $rowfieldoptions["name"] . "</option>" ;
							} else{
								echo "<option value='" . $rowfieldoptions["id"] . "'>" . $rowfieldoptions["name"] . "</option>" ;
							}
						
						}
					}
						echo "</select>    " ;	
					echo "</div>" ;
				}
				else
				{
					echo "<h4>" . $rowformfield["label"] . " = <strong>" . $ContentValue . "</strong></h4>" ;
				}
			}

// -----------------------------------------
			
			if ($rowfield["id"] == 19 ) // Text AREA Formatable using TINYMCE
			{
				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
						echo "<textarea name='" . $rowformfield["name"] . "' id='tinymcetextarea' class='form-control " . $rowformfield["class"] . "' rows='10' >" . $ContentValue . "</textarea>" ;
				echo "</div>" ;
			}

// -----------------------------------------
			

			if ($rowfield["id"] == 20 ) // Text AREA Plain text
			{	
				
				echo "<div class='form-group'>" ;
					echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
						echo "<textarea name='" . $rowformfield["name"] . "' id='plaintextarea' class='form-control " . $rowformfield["class"] . "' rows='10' style=>" ;
					//	echo "" . $rowformfield["placeholder"] . "" ;
						echo $ContentValue ;
						echo "</textarea>" ;
				echo "</div>" ;

			}

// -----------------------------------------
/*
			if ($rowfield["id"] == 21 ) // Image
			{ 

				if ($rowformfield["showedit"] == 'Yes') {
                    
                echo "<div class='form-group'>" ;
				
				// If existing image listed display it
				$myvar1 = $rowformfield["name"] ;
				if ($rowcontent[$myvar1]) 
				{
					echo "<img src='http://" . $_SERVER['SERVER_NAME'] . "/filestore/" . $rowformfield["mediatype"] . "/" . $rowformfield["file_folder_name"] . "/" . $rowcontent[$myvar1] . "' class='pull-right' style='max-width: 120px;'>" ;
				} else {
					echo "<img src='http://" . $_SERVER['SERVER_NAME'] . "/filestore/" . $rowformfield["mediatype"] . "/" . $rowcontent["folder_name"] . "/" . $rowcontent['gallery'] . "' class='pull-right' style='max-width: 120px;'>" ;

				}

				echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
						if ($rowformfield["tooltip"]) {
							echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
							if ($TypeDebug == 'Yes') {
								echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
							}
						}
				echo "</label>" ;
                
                if ($rowformfield["allowedit"] == 'Yes') {
				echo "<input type='file' name='" . $rowformfield["name"] . "' class='form-control medium" . $rowformfield["class"] . "' id='exampleInputEmail1' $required >" ;
                }
                
                
				if(! empty($ContentValue )){
					echo "Current File = " . $ContentValue . "";
				} else {
					echo "Current File = " . $rowcontent["gallery"] . "";
				}
				
				
				echo "</div>" ;
			}
   */         
            // -----------------------------------------

			if ($rowfield["id"] == 21 OR $rowfield["id"] == 23 ) // Image and Image from Gallery
			{  
				if ($rowformfield["showedit"] == 'Yes') { 
                    
                    $galleryID=$rowformfield["name"];
                    if (is_numeric($rowcontent[$galleryID])) {
                        $imagesource = "Gallery" ;
                    }
                    else
                    {
                        $imagesource = "Upload" ;
                    } 
              //      echo "<p>Gallery id: " . $rowcontent[$galleryID] . " Sourced from " . $imagesource . "</p>" ;

                    echo "<div class='form-group'>" ;

                        if ($imagesource == 'Gallery') {
                            // $galleryID=$rowcontent['gallery'];
                             $galleryData = "SELECT * FROM `gallery` WHERE `id` = '" . $rowcontent[$galleryID] . "' ";
                              //	 $galleryData = "SELECT * FROM `gallery` WHERE `id` = '" . $galleryID . "' ";
                              // echo $selectform . "<br>" ;
                            $galleryQuery = mysqli_query($conn,$galleryData);
                            $galleryArr = mysqli_fetch_assoc($galleryQuery) ;

                            //    if ($rowcontent[$myvar1]) 
                            echo "<img src='http://" . $_SERVER['SERVER_NAME'] . "/filestore/" . $rowformfield["mediatype"] . "/" . $galleryArr["folder_name"] . "/" . $galleryArr['image1'] . "' class='pull-right' style='max-width: 120px;'>" ;
                        }
                        else 
                        {
                            echo "<img src='http://" . $_SERVER['SERVER_NAME'] . "/filestore/" . $rowformfield["mediatype"] . "/" . $rowformfield["file_folder_name"] . "/" . $ContentValue . "' class='pull-right' style='max-width: 120px;'>" ;
                        } 

                        echo "<label for='exampleInputEmail1'>" . $rowformfield["label"] . "" ;
                                if ($rowformfield["tooltip"]) {
                                    echo "<a href='#' data-toggle='tooltip' title='" . $rowformfield["tooltip"] . "'><span class='glyphicon glyphicon glyphicon-info-sign' aria-hidden='true' style='padding-left:15px;'></span></a>" ; 
                                    if ($TypeDebug == 'Yes') {
                                        echo "<span style='color:#dddddd;'>&nbsp;&nbsp;(" . $rowfield["id"] . ")</span>";
                                    }
                                }
                        echo "</label>" ;

                        if ($rowformfield["allowedit"] == 'Yes') {
                            echo "<input type='file' name='" . $rowformfield["name"] . "' class='form-control medium" . $rowformfield["class"] . "' id='exampleInputEmail1' $required >" ;

                            if($rowfield["id"] == 23) { // Insert Gallery Tools
                                // Gallery Image
                                echo "<br>OR  <button type='button' class='btn btn-info btn-lg' data-toggle='modal' data-target='#myModal'>Upload From Gallery</button><span id='select_img'></span>";
                                 echo  "<br>OR <a href='' . $baseURL . '/truskacms/recordAdd.php?id=23' target='_blank'>Add new Image to Gallery</a>" ;
                            }
                        }
                        if ($imagesource == 'Gallery') {
                            echo "<br>Current: Gallery ID = " . $ContentValue . " | " . $galleryArr['image1'] . " - Sourced from " . $imagesource . "";
                        } 
                        else 
                        {
                            echo "<br>Current: " . $ContentValue . " - Sourced from " . $imagesource . "";
                        }
                    echo "</div>" ;
                }
            }

// -----------------------------------------
            
    }

// ----------------------------------------------		
			//			echo "<button type='submit' class='btn btn-default'>Add New Record</button>" ;
?>	
			<div class='form-group'>
				<button data-popover="popover" style="" data-content="Save" data-placement="top" data-trigger="hover"  type="submit" name="submit" class="btn btn-success" id="sbt"><i class="fa fa-check"></i>UPDATE RECORD</button>
			</div>
		</form>
<!-- Form Ends Here -->

				<?php
					if ($rowform["text"])
					{
						echo "<h3>Notes:</h3>";
						echo $rowform["text"] ;
					}
				?>

<!--START Gallery Stuff -->
	 <?php 
$selectform2 = "SELECT * FROM `gallery`";
	$queryform0 = mysqli_query($conn,$selectform2);
	$queryform2 = mysqli_query($conn,$selectform2);
	$rowform2 = mysqli_fetch_assoc($queryform2);
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
					            <h1 class="gallery-title">Gallery</h1></div>
					            <div class="gallery-filter gallery_product col-lg-6 col-md-6 col-sm-2 col-xs-6">
					             <div align="right">
					              <?php $arr=array();  $images=array(); $dates=array(); $folders=array(); $ids=array();
					              while($row1= mysqli_fetch_assoc($queryform0)) {
					                  $date1=$row1["date"];
					                  $ids[]=$row1["id"];
					                   $date1= date('F-Y', strtotime($date1));
					                   $arr[]=$date1;
					                   $images[]=$row1["image1"];
					                   $dates[]=$row1["date"];
					                   $folders[]=$row1["folder_name"];
					                 }
					                 $list=array_unique($arr);
					                  $list=array_values($list);
					                 //print_r($list);

					                 ?>
					        	<select>
					        		<option value="all">All</option>
					        		<?php for($i=0;$i<sizeof($list);$i++){
					              echo '<option value="'.$list[$i].'">'.$list[$i].'</option>';
					            }?>
					        	
					        	</select>
					        </div>  </div>
					        
					       </div><div class="select_button" align="right"><a onclick="select_img();" href="javascript:void(0);">Select Image</a></div>
					           <?php
					             for($j=0;$j<sizeof($images);$j++){
					                   $date= $dates[$j];
					                   $date2 = date('F-Y', strtotime($date));
                                      echo '<div id="'.$images[$j].'"  att="'.$ids[$j].'" class="gallery_product col-lg-2 col-md-2 col-sm-2 col-xs-6 filter '.$date2.'"><img src="' . $baseURL . '/filestore/images/'.$folders[$j].'/'.$images[$j].'" class="img-responsive"></div>';
					                       
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
                            
<!--END Gallery Stuff -->
				<div class="col-sm-2 col-md-2"></div>
						
			</div>
			
		
			
		  </div>
		</div> 
		
	</div>
</div>
  <?php 
	//	include("include/footer.php");
echo "</div>";
	include("include/footer-code.php");
	
	function flipdate($dt, $seperator_in, $seperator_out)
		{
		return implode($seperator_out, array_reverse(explode($seperator_in, $dt)));
		}
 include('autoload.php');
  ?>
    
<!-- New script re Gallery -->
      <script type="text/javascript">
  	function select_img(){
  		var imgurl=$('.current').attr('id');
  		var img_id=$('.current').attr('att');
  		if(imgurl){
  			 $('#select_img').text(imgurl );
          $('.gallery').val(img_id );
         //  $('#myModal').modal('toggle');
           $( ".close" ).trigger( "click" );
        
  			//alert(imgurl);
  		} else {
  			alert('Please Select Image!');
  		}
  	}
  	$(document).ready(function(){
    
   $('select').on('change', function() {
        var value = this.value;

       // alert(value);
        
        if(value == "all")
        {
            //$('.filter').removeClass('hidden');
            $('.filter').show('1000');
        }
        else
        {
//            $('.filter[filter-item="'+value+'"]').removeClass('hidden');
//            $(".filter").not('.filter[filter-item="'+value+'"]').addClass('hidden');
            $(".filter").not('.'+value).hide('3000');
            $('.filter').filter('.'+value).show('3000');
            
        }
    });
    
    if ($(".filter-button").removeClass("active")) {
$(this).removeClass("active");
}
$(this).addClass("active");

$('.gallery_product').on('click', function(){
    $('.gallery_product').removeClass('current');
    $(this).addClass('current');
});

});
  </script>

    <!-- End New Script re Gallery-->
    
<!-- Original Script b4 Gallery-->
 <!--   
<script type="text/javascript">
	$(document).ready(function () {

        $(".select_folter").change(function (event) {
            var folder=$(this).val();

            if(folder==0){
            	alert("Select Folder");
            } else {
            	//alert($(this).val());
                $.post('ajaxrequest/ajax.folder.php', { folder: folder}, function(data){
             
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
-->
    <!-- End Ogig script b4 gallery -->
    

  </body>

<!-- Mirrored from condorthemes.com/cleanzone/index.html by HTTrack Website Copier/3.x [XR&CO'2013], Tue, 11 Mar 2014 11:27:23 GMT -->
</html>
<!-- END contactView.php -->
