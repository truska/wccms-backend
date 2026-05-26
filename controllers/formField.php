<?php

class FormField
{
   protected $rowformfield;
   protected $rowfield;
   protected $ContentValue;
   protected $required;
   protected $itemclass;
   protected $infomark;
   protected $TypeDebug;
   protected $maxGalleryImages = 10;

   public function __construct($rowformfield, $rowfield, $ContentValue, $TypeDebug, $PageType, $infomark = '')
   {
      $this->rowformfield = $rowformfield;
      $this->rowfield = $rowfield;
      $this->ContentValue = $ContentValue;
      $this->infomark = $infomark;

      $this->maxGalleryImages = (!empty($rowformfield['maxgalleryimages']) && $rowformfield['maxgalleryimages'] > 0)
      ? $rowformfield['maxgalleryimages']
      : 10;

      // SET REQUIRED
      if ($rowformfield["required"] == 'Yes') {
         $this->required = 'required';
      } else {
         $this->required = '';
      }
      // SET WIDTH STYLE
      switch ($rowformfield["class"]) {
         case 'small':
         case 'm-25':
            $this->itemclass = 'col-12 col-md-6 col-lg-3';
            break;
         case 'medium':
         case 'm-50':
            $this->itemclass = 'col-12 col-md-9 col-lg-6';
            break;
         case 'm-100':
            $this->itemclass = 'col-12 col-md-12 col-lg-9';
            break;
         default:
            $this->itemclass = 'col-12';
            break;
      }
      
      $this->TypeDebug = $TypeDebug;
   }


   /*
      * Render form field
      *
      * @param int $recordnumber  Record number
      * @param int $formnumber  Form number
      * @param bool $gallery  Gallery
      * @param string $baseURL  Base URL
      * @return string  Form field HTML
   */


   public function render($recordnumber = 0, $formnumber = 0, $gallery = false, $baseURL = '')
   {
      switch ($this->rowfield['id']) {
         case 1:
            return $this->renderTextField();
         case 2:
            return $this->renderPasswordField();
         case 3:
            return $this->renderRadioField();
         case 4:
            return $this->renderCheckboxField();
         case 5:
            return $this->renderColourPickerField();
         case 6:
            return $this->renderBootstrapDateField(); // NOT WORKING RIGHT NOW 
         case 7:
            return $this->renderEmailField();
         case 8:
            return $this->renderInputField();
         case 9:
            return $this->renderNumberField();
         case 10:
            return $this->renderRangeField();
         case 11:
            return $this->renderSearchField();
         case 12:
            return $this->renderTelField();
         case 13:
            return $this->renderTimeField();
         case 14:
            return $this->renderInputField();
         case 15:
            return $this->renderInputField();
         case 16:
            return $this->renderSelectField();
         case 17:
            return $this->renderRadioField(true);
         case 18:
            return $this->renderSelectFromTableField($recordnumber, $formnumber, $gallery);
         case 19:
            return $this->renderTextAreaTinymceField();
         case 20:
            return $this->renderTextAreaField();
         case 21:
            return $this->renderFileUploadField($recordnumber, $formnumber, $gallery);
         case 22:
            return $this->renderDateField();
         case 23:
            return $this->renderGalleryField($recordnumber, $formnumber);
            // Add more cases for other field types as needed
         case 24:
            return $this->renderDownloadCV($baseURL);
         case 25:
            // return $this->renderDownloadCV();
         case 26:
            // return $this->renderDownloadCV();
         case 27:
            //  return $this->renderDownloadCV();
         case 28:
            return $this->renderDateTimeField();
         case 29: // Custom Lookup 1
            return $this->renderCustomLookup1($recordnumber, $formnumber);
            
         default:
            return '';
      }
   }


   private function escape_html($text)
   {
      return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
   }

   
   // Case 1
   protected function renderTextField()
   {
      echo "<!-- RENDER {$this->rowformfield["label"]} [{$this->rowformfield["name"]}] -->\n";
      $ContentValue = stripslashes($this->ContentValue);
      $ContentValue = $this->escape_html($ContentValue);

      if ($this->rowformfield["allowedit"] == 'Yes') {
         $output = "<div class='form-group {$this->rowformfield["label"]} {$this->itemclass}' >";
         $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

            if ($this->TypeDebug == 'Yes') { // Debug Mode
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }

         $output .= "</label>";
         $output .= "<input type='{$this->rowfield["type"]}' style='font-weight:700;' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' name='{$this->rowformfield["name"]}' placeholder='{$this->rowformfield["placeholder"]}' value='{$ContentValue}' {$this->required}>";
         $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
         $output .= "</div>";
      } else {
         $output = "<h6>{$this->rowformfield["label"]} = <strong>{$ContentValue}</strong></h6>";
      }

      return $output;
   }


   //Case 9
   protected function renderNumberField()
   {
      if ($this->rowformfield["max"] == 0) {
         $max = '';
      } else {
         $max = $this->rowformfield["max"];
      }

      $output = "<div class='form-group {$this->itemclass}' >";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label>";
      $output .= "<input type='" . $this->rowfield["type"] . "' class='form-control " . $this->rowformfield["class"] . "' id='exampleInputEmail1' name='" . $this->rowformfield["name"] . "' placeholder='" . $this->rowformfield["placeholder"] . "' min='" . $this->rowformfield["min"] . "' max='" . $max . "'  step='" . $this->rowformfield["step"] . "' value='" . $this->ContentValue . "' $this->required>";
      $output .= "<span class='comment'>" . $this->rowformfield["comment"] . "</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 7
   protected function renderEmailField()
   {
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label>";
      $output .= "<input type='email' name='{$this->rowformfield["name"]}' style='font-weight:700;' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' value='{$this->ContentValue}' {$this->required}>";
      $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 8
   protected function renderInputField()
   {
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='{$this->rowformfield["name"]}'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label><br>";
      $output .= "<input type='{$this->rowfield["type"]}' name='{$this->rowformfield["name"]}' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' value='{$this->ContentValue}' placeholder='{$this->rowformfield["placeholder"]}' {$this->required}>";
      $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>"; 

      return $output;
   }


   // Case 12
   protected function renderTelField()
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='{$this->rowformfield["name"]}'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }
         $output .= "</label><br>";
         $output .= "<input type='{$this->rowfield["type"]}' style='font-weight:700;' name='{$this->rowformfield["name"]}' class='form-control' id='exampleInputEmail1' {$this->required} value='{$this->ContentValue}' placeholder='{$this->rowformfield["placeholder"]}'>";
         $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
         $output .= "</div>";
      } else {
         $output = "<h6>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h6>";
      }

      return $output;
   }


   // Case 10
   protected function renderRangeField()
   {
      if ($this->rowformfield["max"] == 0) {
         $max = '100';
      } else {
         $max = $this->rowformfield["max"];
      }
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label>";
      $output .= "<input type='{$this->rowfield["type"]}' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' name='{$this->rowformfield["name"]}' placeholder='{$this->rowformfield["placeholder"]}' min='{$this->rowformfield["min"]}' max='" . $max . "' step='{$this->rowformfield["step"]}' value='{$this->ContentValue}'>";
      echo "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      echo "</div>";

      return $output;
   }


   // Case 2
   protected function renderPasswordField()
   {
       $output = "<div class='form-group {$this->itemclass}'>";
       $output .= "<label for='{$this->rowformfield["name"]}'>{$this->rowformfield["label"]}";
   
       if ($this->rowformfield["tooltip"]) {
           $output .= "<a href='#' data-bs-toggle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
           if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
           }
       }
   
       $output .= "</label>";
   
       // ✅ If a value exists (e.g., hashed in DB), show ********** instead
       $displayValue = '';
       if (!empty($this->ContentValue)) {
           $displayValue = '**********';
       }
   
       $output .= "<input type='password' 
                   class='form-control {$this->rowformfield["class"]}' 
                   id='{$this->rowformfield["name"]}' 
                   name='{$this->rowformfield["name"]}' 
                   placeholder='{$this->rowformfield["placeholder"]}' 
                   value='{$displayValue}' {$this->required}>";
   
       $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
       $output .= "</div>";
   
       return $output;
   }


   // Case 
   private function getRadioOptions()
   {
      $query = "SELECT * 
      FROM `cms_form_field_options` 
      WHERE `form_field` = '" . $this->rowformfield['id'] . "' 
      ORDER BY `sort`";
      $result = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }


   // Case  
   private function getSelectFromQuery($query, $recordnumber)
   {
      $q = str_replace("{{mainID}}", $recordnumber, $query);
      $result = mysqli_fetch_all(DB::query($q), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }

   // Case 3
   protected function renderRadioField($yesNo = false)
   {
      $radioOptions = $this->getRadioOptions();

      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";
      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label><br>";

      if ($yesNo) {
         $output .= "<input type='radio' name='" . $this->rowformfield["name"] . "' " . ($this->ContentValue == 'Yes' ?  'checked' : '') . " value='Yes'>&nbsp;&nbsp;Yes<br>";
         $output .= "<input type='radio' name='" . $this->rowformfield["name"] . "' " . ($this->ContentValue == 'No' ?  'checked' : '') . " value='No'>&nbsp;&nbsp;No";
         $output .= "<br><span class='comment'>{$this->rowformfield["comment"]}</span>";
      } else {
         foreach ($radioOptions as $rOption) {
            if ($rOption["checked"] == 'Yes') {
               $checked = $rOption["checked"];
            }
            $output .= "<input type='radio' name='{$this->rowformfield["name"]}' value='{$rOption["value"]}' {$checked}> {$rOption["display"]}<br>";
         }
      }
      $output .= "</div>";

      return $output;
   }


   // Case 4
   protected function renderCheckboxField()
   {
      $storedData = json_decode($this->ContentValue);
      $fieldOptions = $this->getRadioOptions();

      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label><br>";

      foreach ($fieldOptions as $fOption) {
         if (in_array($fOption["value"], $storedData)) {
            $checked = 'checked';
         } else {
            $checked = '';
         }
         $output .= "<input type='checkbox' name='{$this->rowformfield["name"]}[]' value='{$fOption["value"]}' {$checked}> {$fOption["display"]}<br>";
      }
      $output .= "</div>";

      return $output;
   }


   // Case 5
   protected function renderColourPickerField()
   {
      $output = "<div class='form-group {$this->itemclass}' >";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label>";

      $output .= "<input type='color' name='{$this->rowformfield["name"]}' class='form-control inputColor {$this->rowformfield["class"]}' id='exampleInputEmail1' value='{$this->ContentValue}' {$this->required}>";
      echo "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 6
   protected function renderBootstrapDateField()
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
            $output .= " - " . $this->ContentValue . "";
         }
         $output .= "</label>";
         $output .= "<div data-min-view='2' data-date-format='yyyy-mm-dd' class='input-group date datetime col-md-8 col-xs-7' id='bDate-{$this->rowformfield['id']}'>";
         $output .= "<input name='{$this->rowformfield["name"]}' size='16' value='{$this->ContentValue}' readonly class='form-control {$this->rowformfield["class"]}' type='text' style='max-width:{$this->rowformfield["width"]}'>";
         $output .= "<span class='input-group-addon btn btn-primary'><span class='glyphicon glyphicon-calendar'></span></span>";
         $output .= "</div>";
         $timestamp = strtotime($this->ContentValue);
         $formatted_date = date('D M j Y', $timestamp);
         $output .= "Current Value = <strong>{$formatted_date}</strong>";
         $output .= "</div>";
         $output .= "<script>
         $(function () {
            $('#bDate-{$this->rowformfield["id"]}').datepicker({
               autoclose: true,
               todayHighlight: true
            });
         });
         </script>";
      } else {
         $output = "<h4>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h4>";
      }

      return $output;
   }


   // Case 11
   protected function renderSearchField()
   {
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='{$this->rowformfield["name"]}'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label><br>";
      $output .= "<input type='{$this->rowfield["type"]}' name='{$this->rowformfield["name"]}' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' value='{$this->ContentValue}' {$this->required} placeholder='{$this->rowformfield["placeholder"]}'>";
      $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 21
   protected function renderFileUploadField($recordnumber, $formnumber, $gallery)
   {
      $imgfld = 0;
      $img1 = 0;

      if ($this->rowformfield["showedit"] == 'Yes') {
         $output = "<div class='row'>";
            $output .= "<div class='col-md-8'>";
               $output .= "<div class='form-group  {$this->itemclass}'> ";
                  $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";
                     if ($this->rowformfield["tooltip"]) {
                        $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

                        if ($this->TypeDebug == 'Yes') {
                           $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
                        }
                     }
                  $output .= "</label>";

                  if ($this->rowformfield["allowedit"] == 'Yes') { // Allow Edit  permitted
                     $output .= "<input type='file' name='{$this->rowformfield["name"]}' class='form-control medium{$this->rowformfield["class"]}' id='exampleInputEmail1' >";
                  }

               if ($this->ContentValue) {
                  $imgfld = $imgfld + 1;
                  $output .= "<br><a onclick='confirmDelete(\"{$this->rowformfield["name"]}\")' style='color:red; cursor: pointer;'>Delete</a> Current: Upload Image = {$this->ContentValue} | {$gallery['image1']} ";
                  // Use confirm dialog box to delete image
                  $output .= "<script>
                  function confirmDelete(fieldName) {
                     var x = confirm('Are you sure you want to delete?')
                     const URL = 'deleteimage.php?id=$recordnumber&name='+ fieldName +'&frm=$formnumber'
                     if (x)
                        window.location = URL;
                     else
                        return false;
                  }
                  </script>";
               } else {
                  $output .= "<br>Current: No file added";
               }
               $output .= "</div>";
            $output .= "</div>";

            // Show Thumbnail of image
            $output .= "<div class='col-md-4'>";

               // Check if is a image or pdf 
               if ($this->rowformfield["mediatype"] == 'files') {
                  $output .= "<div style='display: flex;align-items: center;justify-content: end;width: 100%;height: 100%;'>
                     <i class='far fa-file-pdf' style='font-size: 2rem;'></i>
                  </div>";
               } else {
                  if ($this->ContentValue) {
                     $output .= "<img src='https://" . $_SERVER['SERVER_NAME'] . "/filestore/{$this->rowformfield["mediatype"]}/{$this->rowformfield["file_folder_name"]}/{$this->ContentValue}' class='pull-right ghai2' style='max-width: 120px;'>";
                  } else {
                     $output .= "<img src='https://" . $_SERVER['SERVER_NAME'] . "/wccms/img/no-image.jpg' class='pull-right ghai2' style='padding-top:30px;max-width: 80px;'>";
                  }
               }

               $img1++;
            $output .= "</div>";
            $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
         $output .= "</div>";
      }

      return $output;
   }


   // Case 18
   protected function renderSelectFromTableField($recordnumber, $formnumber, $gallery)
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $queryfieldoptions = $this->getSelectFromQuery($this->rowformfield["sourcesql"], $recordnumber);
         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }
         $output .= "</label>";
         $output .= "<select name='{$this->rowformfield["name"]}'  class='form-control {$this->rowformfield["class"]}' size='1'>";

         foreach ($queryfieldoptions as $select) {
            if ($this->ContentValue == $select["id"]) {
               $output .= "<option value='{$select["id"]}' selected='selected'>{$select["name"]}</option>";
            } else {
               if ($formnumber == 23) {
                  $gallery_folder_name = $gallery['folder_name'];

                  $output .= "<option " . (($gallery_folder_name == $select["name"]) ? 'selected="selected"' : "") . " value='{$select["name"]}'>{$select["name"]}</option>";
               } else {
                  $output .= "<option value='{$select["id"]}'>{$select["name"]}</option>";
               }
            }
         }
         $output .= "</select>";
         $output .= "</div>";
      } else {
         $output = "<h4>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h4>";
      }

      return $output;
   }

   // Case 19
   protected function renderTextAreaTinymceField()
   {
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]} | WYGIWYG)</span>";
         }
      }
      $output .= "</label>";
      $output .= "<textarea name='{$this->rowformfield["name"]}' id='tinymcetextarea' class='form-control {$this->rowformfield["class"]}' rows='10' >{$this->ContentValue}</textarea>";
      $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 20
   protected function renderTextAreaField()
   {
      $output = "<div class='form-group {$this->itemclass}'> ";
      $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

      if ($this->rowformfield["tooltip"]) {
         $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

         if ($this->TypeDebug == 'Yes') {
            $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
         }
      }
      $output .= "</label>";
      $output .= "<textarea name='{$this->rowformfield["name"]}' id='plaintextarea' class='form-control {$this->rowformfield["class"]}' rows='10'>{$this->ContentValue}</textarea> ";
      $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
      $output .= "</div>";

      return $output;
   }


   // Case 22
   protected function renderDateField()
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }
         $output .= "</label>";
         $output .= "<input class='form-control' type='date' id='datetimepicker' value='{$this->ContentValue}' name='{$this->rowformfield['name']}'>";
         $output .= "</div>";
      } else {
         $output = "<h6>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h6>";
      }

      return $output;
   }


   // Case 28
   //New managed with STEP and flatpickr - needs cdn in header/footer code
   protected function renderDateTimeField()
   {
       global $conn, $userid;
   
       // Ensure $userid is available
       if (empty($userid) && isset($_SESSION['userid'])) {
           $userid = $_SESSION['userid'];
       }
   
      // error_log("userid : " . $userid);
   
       $id = 'dt_' . $this->rowformfield['id'];
       $step = (float)($this->rowformfield['step'] ?? 0);
       $useManaged = ($step >= 9);
       $minuteStep = (int)$step;
   
       // 1. Format current ContentValue (if set)
       $flatpickrValue = '';
       // If this is a new 'Add' form, ignore any default coming from DB
      if ($this->PageType === 'Add') {
         $this->ContentValue = '';
      }

       if (!empty($this->ContentValue)) {
           $time = trim($this->ContentValue);
           if (preg_match('/^\d{2}:\d{2}(:\d{2})?(\.\d+)?$/', $time)) {
               $flatpickrValue = date("Y-m-d") . ' ' . substr($time, 0, 5);
           } else {
               $ts = strtotime($time);
               if ($ts) {
                   $flatpickrValue = date("Y-m-d H:i", $ts);
               }
           }
       }
   
         // 2. Fallback to customlookup1 value if still blank
         if (empty($flatpickrValue) && !empty($this->rowformfield['customlookup1'])) {
            $lookupSQL = str_replace('{{userid}}', (int)$userid, $this->rowformfield['customlookup1']);
            $result = mysqli_query($conn, $lookupSQL);
            if ($result && $row = mysqli_fetch_assoc($result)) {
               $defaultTimeOnly = reset($row);
               if (!empty($defaultTimeOnly)) {
                  $flatpickrValue = date('Y-m-d') . ' ' . substr($defaultTimeOnly, 0, 5);
               }
            }
         }

         // 3. Final fallback – if still blank, use today's date/time
         if (empty($flatpickrValue)) {
            $defaultTime = date('H:i');     // or '00:00' if preferred
            $flatpickrValue = date('Y-m-d') . ' ' . $defaultTime;
         }

         // after you’ve set $flatpickrValue (e.g. "2025-10-07 12:00")
         $displayValue = '';
         if (!empty($flatpickrValue)) {
            $ts = strtotime($flatpickrValue);
            if ($ts !== false) {
               $displayValue = date('d/m/Y H:i', $ts); // Match your flatpickr dateFormat
            }
         }
   
       // 4. Begin rendering
       if ($this->rowformfield["allowedit"] == 'Yes') {
           $output = "<div class='form-group {$this->itemclass}'>";
           $output .= "<label for='{$id}'>{$this->rowformfield["label"]}";
   
           if ($this->rowformfield["tooltip"]) {
               $output .= "<a href='#' data-bs-toggle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";
               if ($this->TypeDebug == 'Yes') {
                   $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
               }
           }
           $output .= "</label>";
   
           if ($useManaged) {
               $output .= "
               <div class='input-group flatpickr' id='wrap-{$id}' data-wrap='true'>
                  <input type='text' id='{$id}' name='{$this->rowformfield['name']}' class='form-control' value='{$displayValue}'' data-input autocomplete='off'
                  inputmode='none'>
                  <span class='input-group-text' data-toggle><i class='fa fa-calendar'></i></span>
               </div>
           
               ";
               //error_log("renderDateTimeField JSValue for {$id} = [" . $flatpickrValue . "]");
               $output .= "<script>
               document.addEventListener('DOMContentLoaded', function () {
                 const opts = {
                   enableTime: true,
                   dateFormat: 'd/m/Y H:i',
                   minuteIncrement: {$minuteStep},
                   time_24hr: true,
                   wrap: true,
                   allowInput: true
                 };
                 " . (!empty($displayValue) ? "opts.defaultDate = '{$displayValue}';" : "") . "
                 flatpickr('#wrap-{$id}', opts);
               });
               </script>";
               
               
           } else {
               $stepAttr = ($step > 0) ? " step='{$step}'" : "";
               $output .= "<input class='form-control' type='datetime-local' id='{$id}' name='{$this->rowformfield['name']}' value='{$this->ContentValue}'{$stepAttr}>";
           }
   
           $output .= "</div>";
       } else {
           $output = "<h6>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h6>";
       }

       //error_log("renderDateTimeField {$this->PageType} field={$this->rowformfield['name']} value={$flatpickrValue}");

       return $output;
   }

   // Case 16
   protected function renderSelectField()
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $fieldOptions = $this->getRadioOptions();

         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='exampleInputEmail1'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }
         $output .= "</label>";
         $output .= "<select name='{$this->rowformfield["name"]}'  class='form-control {$this->rowformfield["class"]}' size='1'>";

         foreach ($fieldOptions as $fOption) {
            if ($this->ContentValue == $fOption["value"]) {
               $output .= "<option value='" . $fOption["value"] . "' selected='selected'>" . $fOption["display"] . "</option>";
            } else {
               $output .= "<option value='" . $fOption["value"] . "' >" . $fOption["display"] . "</option>";
            }
         }
         $output .= "</select>";
         $output .= "</div>";
      } else {
         $output = "<h6>{$this->rowformfield["label"]} = <strong>{$this->ContentValue}</strong></h6>";
      }

      return $output;
   }


   // Case 13
   protected function renderTimeField()
   {
      if ($this->rowformfield["allowedit"] == 'Yes') {
         $output = "<div class='form-group {$this->itemclass}'> ";
         $output .= "<label for='{$this->rowformfield["name"]}'>{$this->rowformfield["label"]}";

         if ($this->rowformfield["tooltip"]) {
            $output .= "<a href='#' data-bs-toogle='tooltip' title='{$this->rowformfield["tooltip"]}'>&nbsp;{$this->infomark}</a>";

            if ($this->TypeDebug == 'Yes') {
               $output .= "<span style='color:#dddddd;'>&nbsp;&nbsp;({$this->rowfield["id"]})</span>";
            }
         }
         $output .= "</label><br>";
         $output .= "<input type='{$this->rowfield["type"]}' name='{$this->rowformfield["name"]}' class='form-control {$this->rowformfield["class"]}' id='exampleInputEmail1' {$this->required} value='{$this->ContentValue}'>";
         $output .= "<h6>{$this->rowformfield["label"]} = <strong>" . date('g:i a', strtotime($this->ContentValue)) . "</strong></h6>";
         $output .= "<span class='comment'>{$this->rowformfield["comment"]}</span>";
         $output .= "</div>";
      } else {
         $output = "<h6>{$this->rowformfield["label"]} = <strong>" . date('g:i a', strtotime($this->ContentValue)) . "</strong></h6>";
      }

      return $output;
   }


   private function getTable($form_id)
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


   // Case ??
   private function getProductImages($recordnumber)
   {
      $query = "SELECT * FROM `pro_product_images` 
       WHERE productid = $recordnumber 
       ORDER BY sort ASC";
      $images = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($images) {
         return $images;
      } else {
         return null;
      }
   }

   // Case ??
   private function getGalleryImages($recordnumber, $form)
   {
      $query = "SELECT * FROM `gallery` 
      WHERE `record_id` = $recordnumber 
      AND `form_id` = $form
      ORDER BY sort ASC";
      $images = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($images) {
         return $images;
      } else {
         return null;
      }
   }


   //Case ??
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


   // Case 23
   protected function renderGalleryField($recordnumber, $formnumber)
   {
      $output = '<form action="controllers/image_processor.php?id=' . $recordnumber . '&frm=' . $formnumber . '" method="post" enctype="multipart/form-data" class="dropzone" id="my-dropzone">
         <div class="fallback">
            <input type="file" name="images[]" multiple>
			</div>
		</form>';
      $output .= "<button type='button' class='btn btn-success' id='submit-all'>Upload Images</button>";
      $output .= "<div id='response' style='background-color: white;'>
      </div>";
      $output .= '<script>
      const MAX_FILES = '. $this->maxGalleryImages .';
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
      </script>';

      if ($formnumber == 11) { // Product 
         $images_data = $this->getProductImages($recordnumber);
      } else { // Other forms
         // $table = $this->getTable($formnumber);
         // $content = $this->getContent($table['name'], $recordnumber);
         // $colName = $this->rowformfield["name"];
         // $images_data = $content[$colName];
         $images_data = $this->getGalleryImages($recordnumber, $formnumber);
      }

      $countimg = count($images_data);

      $output .= "<form method='post' enctype='multipart/form-data'>
      <center>
      <table class='table'>
      <tbody id='sortable'>";

      if ($formnumber == 11) { // Product
         $i = 0;

         foreach ($images_data as $resimglist) {
            $img_id = $resimglist["id"];
            $img_src = "/filestore/images/products/sm/" . $resimglist["image"];
            $img_name = $resimglist["image"];
            $alttag = $resimglist["alttag"];
            $img_caption = $resimglist["caption"];
            $sort = $resimglist["sort"];
            $showonweb = $resimglist["showonweb"];

            if ($showonweb == "Yes") {
               $checked = "checked";
            } else {
               $checked = "";
            }

            $sort += 1;

            $output .= "<tr id='$img_id'>
               <td><img src='$img_src' width=100px></td>
               <td>
                  Alt <input type='text' class='form-control w-25' value='$alttag' name='alt-$img_id'>
                  Caption <input type='text' class='form-control w-25 ' value='$img_caption' name='caption-$img_id'>
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
      } else { // Other forms
         $i = 0;

         foreach ($images_data as $resimglist) {
            $img_id = $resimglist["id"];
            $img_src = "/filestore/{$resimglist['folder_name']}/" . $resimglist["image"];
            $img_name = $resimglist["image"];
            $alttag = $resimglist["alttag"];
            $img_caption = $resimglist["caption"];
            $sort = $resimglist["sort"];
            $showonweb = $resimglist["showonweb"];

            if ($showonweb == "Yes") {
               $checked = "checked";
            } else {
               $checked = "";
            }

            $sort += 1;

            $output .= "<tr id='$img_id'>
               <td><img src='$img_src' width=100px></td>
               <td>
                  Alt <input type='text' class='form-control w-25'  value='$alttag' name='alt-$img_id'>
                  Caption <input type='text' class='form-control w-25' value='$img_caption' name='caption-$img_id'>
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


      }
      $output .= "</tbody>";
      $output .= "</table>";
      $output .= "</center>";
      $output .= "<center>";
      if ($countimg > 0 ) {
         $output .= "<input type='submit' name='updateGallery' class='btn btn-success w-25' value='Update Images' >";
      }else {
         $output .= "<input type='submit' name='updateGallery' class='btn btn-success w-25' value='Update Images' disabled>";
      }
      $output .= "</center>";
      $output .= "</form>";
      $output .= "<hr>
      <p>Images in gallery = $countimg</p>";

      return $output;
   }


   // Case 24
   protected function renderDownloadCV($baseURL) 
   {
      $ContentValue = stripslashes($this->ContentValue);
      $ContentValue = $this->escape_html($ContentValue);

      $output = "<button type='button' class='btn btn-primary' onclick='window.open(\"$baseURL/wccms/uploadedfiles/" . $ContentValue . "\", \"_blank\")'>Download </button>";

      return $output;
   }
   

   // Case 29
   public function renderCustomLookup1($recordnumber = 0, $formnumber = 0) 
   {
      global $conn, $userid; // <-- use the global $userid that is already correct

      //error_log("Input user ID to function: ".$userid) ;
      //error_log("Session User ID to function: ".$_SESSION['userid']) ;
  
      $fieldname = $this->rowformfield['name'] ?? '';
      $label     = $this->rowformfield['label'] ?? '';
      $comment   = $this->rowformfield['comment'] ?? '';
      $lookupSQL = trim($this->rowformfield['customlookup1'] ?? '');
      $itemclass = $this->itemclass;
  
      $lookupValue = '';
      $staffID = null;
  
      if ($lookupSQL !== '') {
          //error_log("CustomLookup1 using CMS user ID: " . $userid);
  
          $lookupSQL = str_replace('{{userid}}', (int)$userid, $lookupSQL);
          $lookupSQL = str_replace('{{recordid}}', (int)$recordnumber, $lookupSQL);
  
          //error_log("CustomLookup1 final SQL: " . $lookupSQL);
  

         $res = mysqli_query($conn, $lookupSQL);
         if ($res) {
            if ($row = mysqli_fetch_assoc($res)) {
                  //error_log("CustomLookup1 row: " . print_r($row, true));
                  $lookupValue = $row['name'] ?? reset($row);
                  $staffID     = $row['value'] ?? null;
            }
         } else {
            //error_log("CustomLookup1 SQL error: " . mysqli_error($conn));
         }
      }

      if ($lookupValue === '' || $lookupValue === null) {
         if ($this->PageType === 'Edit') {
            $lookupValue = $this->ContentValue ?? '';
         } else {
            $lookupValue = '';
         }
      }

      $output  = "<div class='form-group {$itemclass}'>";
      $output .=   "<label for='{$fieldname}'>{$label}</label>";
      $output .=   "<input type='text' class='form-control {$this->rowformfield["class"]}' 
                        value='" . htmlspecialchars($lookupValue, ENT_QUOTES, 'UTF-8') . "' readonly>";
      $output .=   "<input type='hidden' id='{$fieldname}' name='{$fieldname}' value='" . (int)$staffID . "'>";
      if (!empty($comment)) {
         $output .= "<span class='comment'>{$comment}</span>";
      }
      $output .= "</div>";

      return $output;
   }
  
}