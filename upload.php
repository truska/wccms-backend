<?php
include("include/dbcon.php");

$sqlgetpath="SELECT * FROM preferences WHERE name='prefImagePath'";
$querygetpath=mysqli_query($conn,$sqlgetpath);

while($resgetpath=mysqli_fetch_assoc($querygetpath)){
   $path=$resgetpath["value"];
}

$targetDir = "../$path";

//$targetDir="../filestore/images/products/";

$id=$_GET["idvalue"];


$sqlpref="SELECT * FROM preferences WHERE name='prefMaxImage'";

$querypref=mysqli_query($conn,$sqlpref);

while($respref=mysqli_fetch_assoc($querypref)){
   $value=$respref["value"];
} 


$selectproname="SELECT * FROM products WHERE id = $id";
$queryproname=mysqli_query($conn,$selectproname);
$resproname=mysqli_fetch_assoc($queryproname);
$productname1=$resproname["name"];
$productname2=substr($productname1,0,50);
$productname=str_replace(" ","-",$productname2);



$count=1;

if (!empty($_FILES)) {
 $targetFile = $targetDir.$id."-".$productname.rand(1,99999).".jpg";
    $targetfilename=$id."-".$productname."-".rand(1,99999).".jpg";
    
    

 $filename=$_FILES['file']['name'];
 move_uploaded_file($_FILES['file']['tmp_name'],$targetFile);
    
    
            
    
    
$sqlinsertimg="insert into `pro_product_images`(image,productid) values ('$targetfilename','$id' )";

$queryinsertimg=mysqli_query($conn,$sqlinsertimg);
$sqlsize="select * from imagesize";
          $querysize=mysqli_query($conn,$sqlsize);
          while($ressize=mysqli_fetch_array($querysize)){

            $foldername=$ressize["foldername"];
            $imgwidth=$ressize["imagewidth"];
            $imgheight=$ressize["imageheight"];

            $source="$targetFile";
            $destination="$targetDir/$foldername/$targetfilename";

              
              
              
              

              
           
           // copy($source,$destination);
    
            
            copy($source,$destination);

           $img=imgResize($destination,$imgwidth,$imgheight);

            $count++;
           }



}





function imgResize($path,$w,$h) {



           $x = getimagesize($path);            
           $width  = $x['0'];
           $height = $x['1'];

            $f1=$height/$width;
            $f2=$f1*$w;;
           $rs_width  = $w;//resize to half of the original width.
           $rs_height = $f2;//resize to half of the original height.

           switch ($x['mime']) {
              case "image/gif":
                 $img = imagecreatefromgif($path);
                 break;
              case "image/jpg":
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
            case "jpeg":
                 imagejpeg($img_base, $path);
                 break;
              case "png":
                 imagepng($img_base, $path);  
                 break;
           }

        }
