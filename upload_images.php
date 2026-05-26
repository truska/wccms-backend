<!-- START recordAddv3.php -->
<!-- WiteCanvasCMS ver 3.0 -->
<?php

error_reporting(0);
include('setting/main-top-files.php'); // Added by salva TDR | 16.1.2023


// We set the base URL for the site and the images and files folder
// In main-top-files
/*
if ($prefs['prefSSL'] == 'Yes') {
   $baseURL = "https://" . $_SERVER['SERVER_NAME'] . "";
} else {
   $baseURL = "http://" . $_SERVER['SERVER_NAME'] . "";
}
*/

$images_folder = dirname(__FILE__) . "/../filestore/images/test";
$files_folder = dirname(__FILE__) . "/../filestore/files/test";

// include('wideimage/lib/WideImage.php');

$fileuploadname = "";

if ($userlevel > '20') {
   echo "<script>alert('Open ID = [" . $_GET['id'] . "]');</script>";
   echo "<script>alert('Post name = [" . $_POST['name'] . "]');</script>";
   echo "<script>alert('Post Submit = [" . $_POST['submit'] . "]');</script>";
}

$formnumber = securityCheck($_GET['frm'], 'number');

// --- START added by salva TDR | 16.1.2023 ---
$FORM = new RecordAdd($formnumber);
$form = $FORM->getForm();
$table = $FORM->getTable();
$form_fields = $FORM->getFormFields();
// echo "<pre>";
// print_r($form);
// print_r($table);
// print_r($form_fields);
// echo "</pre>";
// --- END added by salva TDR | 16.1.2023 ---

// include('include/setlogdata.php');

if (isset($_POST['submit'])) {
   echo "<pre>";
   print_r($_POST);
   echo "</pre>";

   die;

   $id = 1;

   $sqlgetpath = "SELECT * FROM preferences WHERE name = 'prefImagePath'";
   $querygetpath = mysqli_query($conn, $sqlgetpath);

   while ($resgetpath = mysqli_fetch_assoc($querygetpath)) {
      $path = $resgetpath["value"];
   }

   $targetDir = "../filestore/";

   //end file upload

   foreach ($_FILES as $key => $value) {
      $filename = $_FILES[$key]['name'];
      $proname = $_POST['name'];
      $pronameV = substr($proname, 0, 50);

      /* Add by DC to increase validation */
      $pronameV = str_replace(".", "-", $pronameV);
      $pronameV = str_replace(",", "-", $pronameV);
      $pronameV = str_replace("\/", "-", $pronameV);
      $pronameV = str_replace("'", "", $pronameV);
      $pronameV = str_replace("*", "", $pronameV);
      $pronameV = str_replace("?", "", $pronameV);
      $pronameV = str_replace("@", "-", $pronameV);
      $pronameV = str_replace("=", "-", $pronameV);
      $pronameV = str_replace(" ", "-", $pronameV);
      $pronameV = str_replace("--", "-", $pronameV);
      $pronameV = strtolower($pronameV);

      $targetfilename = "$pronameV-$recordnumber-" . $id . "-" . rand(1, 99999) . ".jpg";
      //$targetfilename1="$proname-$formnumber-".$id."-".rand(1,99999).".jpg";
      //$targetfilename=preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $targetfilename1);
      //$targetfilename=strtolower($targetfilename);
      //$targetfilename=str_replace(' ', '-', $targetfilename);
      //$targetfilename=$id."-".rand(1,99999).".jpg";

      $targetFile = $targetDir . "temp/$targetfilename";
      $filename = $_FILES[$key]['name'];

      if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetFile)) {
         //new file upload code
         $sqlgetimgsize = "SELECT * FROM cms_form_field WHERE form = $formnumber and field = 21";
         $querygetimgsize = mysqli_query($conn, $sqlgetimgsize);

         while ($resimgsize = mysqli_fetch_assoc($querygetimgsize)) {
            $small = $resimgsize["sm_max_width"];
            $stsize = $resimgsize["st_max_width"];
            $large = $resimgsize["lg_max_width"];
            $tn = $resimgsize["tn_max_width"];
            $foldername = $resimgsize["file_folder_name"];
            $mediatype = $resimgsize["mediatype"];
            $mandatory = $resimgsize["mandatory_img"];
            $mandatory_imgsize = $resimgsize["$mandatory"];

            // echo "<script>alert('$mandatory_imgsize')</script>";
            //	if($small!=0){
            //$foldername="s";

            $imgwidth = $mandatory_imgsize;
            $imgheight = "";
            $source = "$targetFile";
            $destination = "$targetDir$mediatype/$foldername/$targetfilename";

            //echo "<script>alert('$targetfilename')</script>";

            if (copy($source, $destination)) {
               // echo "<script>alert('done')</script>";
               $img = imgResize($destination, $imgwidth, $imgheight);
            }

            //small code
            if (!is_null($small)) {
               $smallfolder = "images/sm/$targetfilename";
               $destinationsmall = "$targetDir$smallfolder";
               copy($source, $destinationsmall);

               //echo "<script> alert('$destinationsmall') </script>";
               $img = imgResize($destinationsmall, $small, $imgheight);
            }



            //medium code

            if (!is_null($stsize)) {
               $mediumfolder = "images/md/$targetfilename";
               $destinationmedium = "$targetDir$mediumfolder";
               copy($source, $destinationmedium);
               //echo "<script> alert('$destinationsmall') </script>";
               $img = imgResize($destinationmedium, $stsize, $imgheight);
            }

            if (!is_null($large)) {
               $largefolder = "images/lg/$targetfilename";
               $destinationlarge = "$targetDir$largefolder";
               copy($source, $destinationlarge);
               //echo "<script> alert('$destinationsmall') </script>";
               $img = imgResize($destinationlarge, $large, $imgheight);
            }

            if (!is_null($tn)) {
               $tnfolder = "images/xs/$targetfilename";
               $destinationtn = "$targetDir$tnfolder";
               copy($source, $destinationtn);
               //echo "<script> alert('$destinationsmall') </script>";
               $img = imgResize($destinationtn, $tn, $imgheight);
            }
         }

         //end new file upload code
         /*
        	$sqlsize="select * from imagesize";
         $querysize=mysqli_query($conn,$sqlsize);

         while($ressize=mysqli_fetch_array($querysize)){
            $foldername=$ressize["foldername"];
            $imgwidth=$ressize["imagewidth"];
            $imgheight=$ressize["imageheight"];
            $source="$targetFile";
            $destination="$targetDir/$foldername/$targetfilename";
            
				copy($source,$destination);
           	$img=imgResize($destination,$imgwidth,$imgheight);
            $count++;
		  	}
			*/
      } else {
         echo "Error !!!";
      }
   }

   //end file upload

   //Get the Form
   // $selectform = "SELECT * FROM `cms_form` WHERE `id` = '" . $formnumber . "' ";
   // $queryform = mysqli_query($conn, $selectform);
   // $rowform = mysqli_fetch_assoc($queryform);

   // Get the table name
   // $selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `table` ";
   // $queryformfield = mysqli_query($conn, $selectformfield);
   // $rowformfield = mysqli_fetch_assoc($queryformfield);

   // $selecttable = "SELECT * FROM `cms_table` WHERE `id` = '" . $rowformfield["table"] . "' ";
   // $querytable = mysqli_query($conn, $selecttable);
   // $rowtable = mysqli_fetch_assoc($querytable);

   $insert = "INSERT INTO `" . $rowtable["name"] . "` SET ";

   //Get the Fields	
   // $selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `order` ";
   //echo $selectformfield . "<br>" ;
   // $queryformfield = mysqli_query($conn, $selectformfield);

   // $selectformfield = "SELECT * FROM `cms_form_field` WHERE `form` = '" . $rowform["id"] . "' ORDER BY `table` ";
   //echo $selectformfield . "<br>" ;
   // $queryformfield = mysqli_query($conn, $selectformfield);

   $multipleimg = "";
   $counter = 1;

   foreach ($_POST as $var => $val) {
      //echo "<script>alert('$var - $val')</script>";
      $$var = mysqli_real_escape_string($conn, $var);
      $$val = mysqli_real_escape_string($conn, $val);

      while ($resgetfile = mysqli_fetch_assoc($queryformfield)) {
         $files = $resgetfile["field"];
         $fieldname = $resgetfile["name"];

         if ($files == 21) {
            $isimage = 1;
            //$var=$fieldname;
            $field_name = $fieldname;
            //$file_name=$_FILES["$fieldname"]["name"];
            $file_name = $targetfilename;

            if ($file_name) {
               $multipleimg .= "$field_name='$file_name',";
            }
            echo "$fieldname - " . $_FILES["$fieldname"]["name"];
         }
      }

      if ($isimage == 1) {
         //echo $imgfieldname."-". $_FILES["image"]["name"];
         //  $$val=$_FILES["image"]["$fieldname"];
      }

      // echo "<br> $isimage";

      if ($counter == 1 or $$var == 'submit') {
         // $insert = $insert . "&#39&quot; .mysqli_real_escape_string($conn, '" . $val . "') .&quot;&#39<br>" ;
      } else if ($$var == "checkbox") {
         $data = json_encode($_POST['checkbox']);
         $insert = $insert . " , `" . $$var . "` = '" . $data . "' ";
      } else if ($$var == "password") {
         $data = md5($$val);
         $insert = $insert . " , `" . $$var . "` = '" . $data . "' ";
      } else {

         if ($counter == 2) {
            $insert = $insert . " `" . $$var . "` = '" . $$val . "' ";
         } else {
            $insert = $insert . " , `" . $$var . "` = '" . $$val . "' ";
         }
      }

      $counter++;
   }

   $multipleimg1 = substr($multipleimg, 0, -1);

   //include image upload functionality
   //include ("include/imageUpload.php");

   if ($multipleimg1) {
      $insert .= ",$multipleimg1";
   }
   $insert = $insert . " ";

   // echo $insert;

   /*
	echo "<p>Insert: " .$insert . "</p>";

	echo "<p>Form: " .$selectform . "</p>";

	echo "<p>Field: " .$selectformfield . "</p>";

	echo "<p>Table: " .$selecttable . "</p>";

	*/

   $sqlproductlog = $insert;
   $action = 'Insert Record';
   $notes = "";

   // ------------------------------	

   $query = mysqli_query($conn, $insert);

   $sqllastrecord = "SELECT * FROM " . $rowtable["name"] . " ORDER BY `id` DESC LIMIT 1";
   $querylastrecord = mysqli_query($conn, $sqllastrecord);

   $rowlastrecord = mysqli_fetch_assoc($querylastrecord);
   $contentid = $rowlastrecord["id"];
   //	$lastId = mysql_insert_id();

   if ($query) {
      // --- START Edited by salva TDR | 17.1.2023 ---
      // * You have the same code just above, so I commented it out *

      // $sqllastrecord = "SELECT * FROM " . $rowtable["name"] . " ORDER BY `id` DESC LIMIT 1";
      // $querylastrecord = mysqli_query($conn, $sqllastrecord);

      // $rowlastrecord = mysqli_fetch_assoc($querylastrecord);
      // $contentid = $rowlastrecord["id"];
      // --- END Edited by salva TDR | 17.1.2023 ---

      savelogV2('', $action, $sqlproductlog, $rowtable["name"], 'SUCCESS', $notes, $contentid);
      $redirectTo = "recordViewv3.php?frm=$formnumber";
      //  header("Location:$redirectTo");
      echo "<script>window.location='$redirectTo'</script>";
   } else {
      savelogV2('', $action, $sqlproductlog, $rowtable["name"], 'FAIL', $notes, 'N/A');
      echo "error" . mysqli_error($conn);
   }
}

//echo "<h2>" .$insert . "</h2>";

// END FORM PROCESSING

function imgResize($path, $w, $h)
{
   $x = getimagesize($path);
   $width  = $x['0'];
   $height = $x['1'];

   $f1 = $height / $width;
   $f2 = $f1 * $w;

   $rs_width  = $w; //resize to half of the original width.
   $rs_height = $f2; //resize to half of the original height.

   switch ($x['mime']) {
      case "image/gif":
         $img = imagecreatefromgif($path);
         break;
      case "image/jpg":
         break;
      case "image/jpeg":
         $img = imagecreatefromjpeg($path);
         break;
      case "image/png":
         $img = imagecreatefrompng($path);
         break;
   }

   $img_base = imagecreatetruecolor($rs_width, $rs_height);
   imagecopyresized($img_base, $img, 0, 0, 0, 0, $rs_width, $rs_height, $width, $height);

   $path_info = pathinfo($path);

   switch ($path_info['extension']) {
      case "gif":
         imagegif($img_base, $path);
         break;
      case "jpg":
         break;
      case "jpeg":
         imagejpeg($img_base, $path);
         break;
      case "png":
         imagepng($img_base, $path);
         break;
   }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
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

      .form-group.Gallery {
         display: none;
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

   <section class="main-content">
      <section class="wrapper site-min-height">

         <!-- START upload image (normal input) -->
         <!-- <form enctype="multipart/form-data" action="controllers/image_processor.php" method="post"> -->
         <!-- <div class="dropzone">
               <div class="dz-message">
                  Drag your images here or click to select them
               </div>
               <div class="fallback">
                  <input type="file" name="images[]" multiple>
               </div>
            </div> -->
         <!-- <input type="file" name="images[]" multiple>
            <input type="submit" value="Upload Images">
         </form> -->
         <!-- END upload image (normal input) -->

         <!-- START Upload image dropzone -->
         <form action="controllers/image_processor.php" method="post" enctype="multipart/form-data" class="dropzone" id="my-dropzone">
            <div class="fallback">
               <input type="file" name="images[]" multiple>
            </div>
         </form>
         <button type="button" id="submit-all">Upload Images</button>
         <div id="response" style="background-color: white;"></div>



         <form method="post" enctype="multipart/form-data">
            <center>
               <table class="table">

                  <tbody id="sortable">

                     <?php

                     $sqlgetimg = "select * from `pro_product_images` where productid=$id order by sort asc";

                     $querygetimg = mysqli_query($conn, $sqlgetimg);

                     $countimg = mysqli_num_rows($querygetimg);


                     $j = 1;

                     $i = 0;
                     while ($resimglist = mysqli_fetch_assoc($querygetimg)) {

                        $imgid = $resimglist["id"];

                        $imgsrc = $resimglist["image"];
                        $imgname = $resimglist["image"];
                        $alttag = $resimglist["alttag"];
                        $titletag = $resimglist["titletag"];
                        $captiontag = $resimglist["caption"];
                        $sort = $resimglist["sort"];
                        $showweb = $resimglist["showonweb"];

                        if ($showweb == "Yes") {
                           $checked = "checked";
                        } else {
                           $checked = "";
                        }

                        $sort = $sort + 1;
                        $imgsrc = "../filestore/images/products/$thumbnail/$imgsrc ";

                        echo "<tr id='$imgid' >
                           <td><img src='$imgsrc' width=100px> </td>
									<td > Alt <input type='text' class='form-control' style='width:25%' value='$alttag' name='alt-$imgid'>
									   Title <input type='text' class='form-control ' style='width:25%' value='$titletag' name='title-$imgid'>
										Caption <input type='text' class='form-control ' style='width:25%' value='$captiontag' name='caption-$imgid'>
										<div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
											<input type='checkbox'  $checked  class='custom-control-input' id='customSwitch$i' onchange='check(this,$imgid)'>
											<label class='custom-control-label' for='customSwitch$i'>Show on Web</label>
											<span style='font-size:14px;padding-left:20px;'><em>Filename:</em> $imgname</span>
										</div>
									</td>
									<td colspan=2>
									<a href='remove.img.php?img=$imgid&frm=$$formnumber&id=$recordnumber'><i class='far fa-trash-alt'></i></a></td>
								</tr>";
                        $i++;
                        $j++;
                     }
                     ?>
                  </tbody>
               </table>
            </center>

            <center>
               <input type="submit" name="proimage" class="btn btn-success" value="Update Images" style="width:20%">
            </center>
         </form>

         <script>
            Dropzone.options.myDropzone = {
               paramName: "images", // Nombre del parámetro de los archivos
               // maxFilesize: 2, // Tamaño máximo de los archivos en MB
               acceptedFiles: ".jpg,.jpeg,.png,.gif", // Extensiones de archivo aceptadas
               dictDefaultMessage: "Drag your images here or click to select them", // Mensaje predeterminado
               autoProcessQueue: false, // Deshabilitar el procesamiento automático
               // accept: function(file, done) {
               //    done();
               // },
               maxFiles: 10, // Número máximo de archivos
               uploadMultiple: true, // Habilitar la carga de múltiples archivos
               init: function() {
                  var myDropzone = this;
                  var submitButton = document.querySelector("#submit-all");
                  submitButton.addEventListener("click", function() {
                     myDropzone.processQueue(); // Procesar la cola de archivos
                     // document.getElementById("my-dropzone").submit(); // Enviar el formulario
                  });
               },
               success: function(file, response) {
                  console.log(response);
                  var div_response = document.querySelector("#response");
                  div_response.innerHTML = response;
               }
            };
         </script>
         <!-- END Upload image dropzone -->

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

<!-- END recordAddv3.php -->