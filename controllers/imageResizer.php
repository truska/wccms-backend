<?php

/**
 * Image Resizer
 *
 * @version    1.0.0
 * @author     Salva TDR
 * 
 * @var string $originalImage  Original image
 * @var array $resizedImages  Resized images
 * @var array $errors  Errors
 * @var array $scaled_sizes  Scaled sizes
 */
class ImageResizer
{
   private $originalImage;
   private $resizedImages = array();
   private $defaultResize;
   private $errors = array();
   private $scaled_sizes;

   public function __construct($image, $scaled_sizes = false, $defaultResize = 0)
   {
      $this->originalImage = $image;

      // If $scaled_sizes is a array then use it
      if (is_array($scaled_sizes)) {
         $this->scaled_sizes = $scaled_sizes;
      } else {
         // Default scaled sizes
         $this->setDefaultScaledSizes();
      }

      $this->defaultResize = $defaultResize; // Default resize for all images
   }

   public function setDefaultResize($size)
   {
      $this->defaultResize = $size;
   }

   public function setScaledSizes($scaled_sizes)
   {
      $this->scaled_sizes = $scaled_sizes;
   }

   private function setDefaultScaledSizes()
   {
      $this->scaled_sizes = array(
         'xs' => array('width' => 200),
         'sm' => array('width' => 400),
         'md' => array('width' => 800),
         'lg' => array('width' => 1500)
      );
   }

   public function getScaledSizes()
   {
      return $this->scaled_sizes;
   }

   public function uploadImage($folder, $name)
   {
      $name = $this->cleanName($name);
      $originalSize = getimagesize($this->originalImage);
      $originalWidth = $originalSize[0];
      $originalHeight = $originalSize[1];
      $ratio = $originalWidth / $originalHeight;

      if ($this->defaultResize > 0 && $originalWidth > $this->defaultResize) { // Resize image
         $resizeWidth = $this->defaultResize; // px
         $resizeHeight = intval($resizeWidth / $ratio);
         $resizeImage = imagecreatetruecolor($resizeWidth, $resizeHeight);
         $originalImage = $this->getOriginalImage($this->originalImage);
         imagecopyresampled($resizeImage, $originalImage, 0, 0, 0, 0, $resizeWidth, $resizeHeight, $originalWidth, $originalHeight);
         if (!$this->saveImage($resizeImage, $folder . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in `$folder`",
               "resizeImage" => $resizeImage,
               "folder" => $folder . $name,
               "width" => $resizeWidth,
               "height" => $resizeHeight,
               "mime" => $originalSize["mime"]
            ];
         }
      } else { // Save original image
         if (!$this->saveImage($this->getOriginalImage($this->originalImage), $folder . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in `$folder`",
               "normalImage" => $this->getOriginalImage($this->originalImage),
               "folder" => $folder . $name,
               "width" => $originalWidth,
               "height" => $originalHeight,
               "mime" => $originalSize["mime"]
            ];
         }
      }
   }

   public function resize($folder, $name)
   {
      $name = $this->cleanName($name);
      $originalSize = getimagesize($this->originalImage);
      $originalWidth = $originalSize[0];
      $originalHeight = $originalSize[1];
      $ratio = $originalWidth / $originalHeight;

      // -- XS size --
      // if the width is not null, more than 0 or not empty then resize the image
      if (
         $this->scaled_sizes['xs']['width'] != null ||
         $this->scaled_sizes['xs']['width'] > 0 ||
         $this->scaled_sizes['xs']['width'] != ''
      ) {
         $xsWidth = $this->scaled_sizes['xs']['width']; // px
         $xsHeight = intval($xsWidth / $ratio);
         $xsImage = imagecreatetruecolor($xsWidth, $xsHeight);
         $originalImage = $this->getOriginalImage($this->originalImage);
         imagecopyresampled($xsImage, $originalImage, 0, 0, 0, 0, $xsWidth, $xsHeight, $originalWidth, $originalHeight);
         if (!$this->saveImage($xsImage, $folder . "xs/" . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in the folder xs",
               "xsImage" => $xsImage,
               "folder" => $folder . "xs/",
               "width" => $xsWidth,
               "height" => $xsHeight,
               "name" => $name,
               "mime" => $originalSize["mime"]
            ];
         }
         imagedestroy($xsImage);
         $this->resizedImages["xs"] = $folder . "xs/" . $name . "." . $this->getExtension($originalSize["mime"]);
      }

      // -- Small size --
      // if the width is not null, more than 0 or not empty then resize the image
      if (
         $this->scaled_sizes['sm']['width'] != null ||
         $this->scaled_sizes['sm']['width'] > 0 ||
         $this->scaled_sizes['sm']['width'] != ''
      ) {
         $smallWidth = $this->scaled_sizes['sm']['width']; // px
         $smallHeight = intval($smallWidth / $ratio);
         $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
         $originalImage = $this->getOriginalImage($this->originalImage);
         imagecopyresampled($smallImage, $originalImage, 0, 0, 0, 0, $smallWidth, $smallHeight, $originalWidth, $originalHeight);
         if (!$this->saveImage($smallImage, $folder . "sm/" . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in the folder sm",
               "smallImage" => $smallImage,
               "folder" => $folder . "sm/",
               "width" => $smallWidth,
               "height" => $smallHeight,
               "name" => $name,
               "mime" => $originalSize["mime"]
            ];
         }
         imagedestroy($smallImage);
         $this->resizedImages["small"] = $folder . "sm/" . $name . "." . $this->getExtension($originalSize["mime"]);
      }

      // -- Normal size --
      // if the width is not null, more than 0 or not empty then resize the image
      if (
         $this->scaled_sizes['md']['width'] != null ||
         $this->scaled_sizes['md']['width'] > 0 ||
         $this->scaled_sizes['md']['width'] != ''
      ) {
         $normalWidth = $this->scaled_sizes['md']['width']; // px
         $normalHeight = intval($normalWidth / $ratio);
         $normalImage = imagecreatetruecolor($normalWidth, $normalHeight);
         $originalImage = $this->getOriginalImage($this->originalImage);
         imagecopyresampled($normalImage, $originalImage, 0, 0, 0, 0, $normalWidth, $normalHeight, $originalWidth, $originalHeight);
         if (!$this->saveImage($normalImage, $folder . "md/" . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in the folder md",
               "normalImage" => $normalImage,
               "folder" => $folder . "md/" . $name,
               "width" => $normalWidth,
               "height" => $normalHeight,
               "mime" => $originalSize["mime"]
            ];
         }
         imagedestroy($normalImage);
         $this->resizedImages["normal"] = $folder . "md/" . $name . "." . $this->getExtension($originalSize["mime"]);
      }

      // -- Large size --
      // if the width is not null, more than 0 or not empty then resize the image
      if (
         $this->scaled_sizes['lg']['width'] != null ||
         $this->scaled_sizes['lg']['width'] > 0 ||
         $this->scaled_sizes['lg']['width'] != ''
      ) {
         if ($originalWidth <= $this->scaled_sizes['md']['width']) {
            $largeWidth = $originalWidth;
            $largeHeight = $originalHeight;
         } else {
            $largeWidth = $this->scaled_sizes['lg']['width']; // px
            $largeHeight = intval($largeWidth / $ratio);
         }
         $largeImage = imagecreatetruecolor($largeWidth, $largeHeight);
         $originalImage = $this->getOriginalImage($this->originalImage);
         imagecopyresampled($largeImage, $originalImage, 0, 0, 0, 0, $largeWidth, $largeHeight, $originalWidth, $originalHeight);
         if (!$this->saveImage($largeImage, $folder . "lg/" . $name, $originalSize["mime"])) {
            $this->errors[] = [
               "error" => "Error saving the image " . $name . " in the folder lg",
               "largeImage" => $largeImage,
               "folder" => $folder . "lg/" . $name,
               "width" => $largeWidth,
               "height" => $largeHeight,
               "mime" => $originalSize["mime"]
            ];
         }
         imagedestroy($largeImage);
         $this->resizedImages["large"] = $folder . "lg/" . $name . "." . $this->getExtension($originalSize["mime"]);
      }

      // Save the original image using the function uploadImage
      $this->uploadImage($folder, $name);

      // -- Save original image --
      // if (!move_uploaded_file($this->originalImage, $folder . $name . "_original" . "." . $this->getExtension($originalSize["mime"]))) {
      //    $this->errors[] = "Error saving the image original " . $name;
      // }
      // $this->resizedImages["original"] = $folder . $name . "_original" . "." . $this->getExtension($originalSize["mime"]);
   }

   public function getResizedImages()
   {
      return $this->resizedImages;
   }

   public function getErrors()
   {
      return $this->errors;
   }

   public function cleanName($name)
   {
      $name = strtolower($name); // Convertir a minúsculas
      $name = preg_replace("/[\s]/", "-", $name); // Primero, reemplazar espacios por guiones
      $name = preg_replace("/[^a-zA-Z0-9\-_]/", "", $name); // Remover caracteres especiales excepto guiones medios y bajos
      $name = preg_replace("/-+/", "-", $name); // Remover guiones múltiples
      $name = preg_replace("/^-/", "", $name); // Remover guión al inicio
      $name = preg_replace("/-$/", "", $name); // Remover guión al final
      return $name;
   }

   private function getOriginalImage($image)
   {
      $mime = getimagesize($image)["mime"];
      switch ($mime) {
         case "image/jpeg":
            return imagecreatefromjpeg($image);
         case "image/png":
            return imagecreatefrompng($image);
         case "image/gif":
            return imagecreatefromgif($image);
         default:
            return false;
      }
   }

   private function saveImage($image, $name, $mime)
   {
      switch ($mime) {
         case "image/jpeg":
            return imagejpeg($image, $name . ".jpg", 100);
         case "image/png":
            return imagepng($image, $name . ".png", 9);
         case "image/gif":
            return imagegif($image, $name . ".gif");
         default:
            return false;
      }
   }

   private function getExtension($mime)
   {
      switch ($mime) {
         case "image/jpeg":
            return "jpg";
         case "image/png":
            return "png";
         case "image/gif":
            return "gif";
         default:
            return false;
      }
   }

   /**
    * Get Content 
    *
    * @param string $table  Table name
    * @param int $id  Content id
    * @return array|null  Content data or null
    */
   public function getContent($table, $id)
   {
      $query = "SELECT * FROM `$table` 
      WHERE `id` = '$id'";
      $content = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($content) {
         return $content;
      } else {
         return null;
      }
   }

   public function getTable($form_id)
   {
      $query = "SELECT * FROM `cms_form` 
      WHERE `id` = '" . $form_id . "' ";
      $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
      if ($form) {
         $query = "SELECT * FROM `cms_table` 
         WHERE `id` = '" . $form['table'] . "' ";
         $table = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
         if ($table) {
            return $table;
         } else {
            return null;
         }
      } else {
         return null;
      }
   }

   public function getForm($form_id)
   {
      $query = "SELECT * FROM `cms_form` 
      WHERE `id` = '" . $form_id . "' ";
      $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($form) {
         return $form;
      } else {
         return null;
      }
   }

   public function getContentFromTable($tableName, $id)
   {
      $query = "SELECT * FROM `$tableName` 
      WHERE `id` = $id";
      $content = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($content) {
         return $content;
      } else {
         return null;
      }
   }

   /**
    * Save image in database
    *
    * @param string $image_name  Image name
    * @param int $id  Content id
    * @param string $form  Form id
    * @return array  True if success, false if not
    */
   public function saveInDatabase($image_name, $id, $form, $formName)
   {
      $sort = 0;

      $destination_folder = $this->getDestinationFolderByForm($form);
      $name = explode('.', $image_name)[0];

      $query = "INSERT INTO `gallery` (`record_id`, `form_id`, `form_name`, `name`, `image`, `date`, `folder_name`, `sort`) 
      VALUES ('$id', '$form', '$formName', '$name', '$image_name', NOW(), '$destination_folder', '$sort')";

      $result = DB::query($query);

      if ($result) {
         return [
            'status' => 'success',
            'message' => 'Successfully saved in database',
            'query' => $query
         ];
      } else {
         return [
            'status' => 'error',
            'message' => 'Error saving in database',
            'query' => $query
         ];
      }
   }

   /**
    * Get destination folder
    *
    * @param int $formID  Form id
    * @return string|null  Destination folder or null
    */
   public function getDestinationFolderByForm($formID)
   {
      $fieldID = 23;
      $query = "SELECT * FROM `cms_form_field` 
      WHERE `form` = $formID 
      AND `field` = $fieldID";
      $formField = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($formField) {
         // if $formField['file_folder_name'] exist and is different to empty o null then use it
         if ($formField['file_folder_name'] != '' && $formField['file_folder_name'] != null) {
            $destination_folder = "{$formField['mediatype']}/{$formField['file_folder_name']}/";
         } else {
            $destination_folder = "{$formField['mediatype']}/";
         }

         // $destination_folder = "{$formField['mediatype']}/" . ($formField['file_folder_name'] != '' ? $formField['file_folder_name'] . '/' : '');

         return $destination_folder;
      } else {
         return null;
      }
   }

   public function getFormField($formID, $field = 23)
   {
      $query = "SELECT * FROM `cms_form_field` 
      WHERE `form` = $formID 
      AND `field` = $field";
      $formField = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($formField) {
         return $formField;
      } else {
         return null;
      }
   }

   /**
    * Update Table Content
    *
    * @param array $data  Form data
    * @param string $tableName  Table name
    * @param int $recordID  Record id
    * @return array  Response
    */
   function updateTableContent($data, $tableName, $recordID)
   {
      $query = "UPDATE `$tableName` SET ";
      foreach ($data as $key => $value) {
         if (!in_array($key, ['formnumber', 'submit', 'activetab'])) {
         //if ($key != 'formnumber' && $key != 'submit') {
            $query .= "`$key` = '$value', ";
         }
      }
      
      $query = substr($query, 0, -2); // remove last comma

      if (trim($query) === "UPDATE `$tableName` SET") {
         return [
            'status' => 'error',
            'message' => 'No fields to update — all were excluded',
            'query' => $query
         ];
      }

      $query .= " WHERE `id` = '$recordID'";

      $response = DB::query($query);

      if ($response) {
         return [
            'status' => 'success',
            'message' => 'Record updated successfully',
            'query' => $query
         ];
      } else {
         return [
            'status' => 'error',
            'message' => 'Record update failed',
            'query' => $query
         ];
      }
   }
}
