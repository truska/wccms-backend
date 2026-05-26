<?php
/**
 * RecordEdit
 * 
 * @version 1.0.0
 * @author salva TDR
 * 
 * @var int $record_id  Record id
 * @var array $form  Form data
 * @var array $table  Table data
 * @var array $form_fields  Form fields data
 * @var int $maximg  Max images
 * @var int $showgallery  Show gallery
 * @var string $tablename  Table name
 * @var string $css  Custom CSS
 * @var string $dataform  Data form if not default
 */
class RecordEdit
{
   public $record_id;
   public $form;
   public $table;
   public $dataform;
   public $form_fields;
   public $maximg;
   public $showgallery;
   public $tablename;

   protected $formFields = [];   // ✅ add this line here

   private $formQuery;
   private $TableQuery;
   private $FormFieldsQuery;
   private $css;

   function __construct($form_id, $record_id,$dataform)
   {
      $this->record_id = $record_id;
      // Form
      $query = "SELECT * FROM `cms_form` 
      WHERE `id` = '" . $dataform . "' ";

      $this->formQuery = $query;
      $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($form) {
         $this->form = $form;
         $this->maximg = $form['maxgalleryimage'];
         $this->showgallery = $form['showgallery'];
         $this->tablename = $form['name'];
         $this->css = $form['css'];
         // Table
         $query = "SELECT * FROM `cms_table` 
         WHERE `id` = '" . $form['table'] . "' ";
         $this->TableQuery = $query;
         $table = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
         if ($table) {
            $this->table = $table;
            $this->tablename = $table['name'];
         } else {
            $this->table = null;
         }

         // Field names
         $query = "SELECT * FROM `cms_form_field` 
         WHERE `form` = '" . $dataform . "'
         ORDER BY `sort` ASC";
         $this->FormFieldsQuery = $query;
         // if (!empty($form['sort1'])) {
         //    $query .= " ORDER BY `" . $form['sort1'] . "` " . $form['sort1order'] . "";
         // }elseif (!empty($form['sort2'])) {
         //    $query .= ", `" . $form['sort2'] . "` " . $form['sort2order'] . "";
         // }else {
         //    $query .= " ORDER BY `table`";
         // }
         $form_fields = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);
         if ($form_fields) {
            $this->form_fields = $form_fields;
         } else {
            $this->form_fields = null;
         }
      } else {
         $this->form = null;
         $this->table = null;
         $this->form_fields = null;
         $this->maximg = null;
         $this->showgallery = null;
         $this->tablename = null;
      }
    //  error_log("Form FieldSQL: ".$query);
    //  error_log("Form Fields loaded: ".print_r($form_fields, true));
   }

   /**
    * Get form query
    *
    * @return string  form query
    */
    public function getFormQuery() {
      return $this->formQuery;
   }


   function normalizeDateTimeForDB($value, $fieldType) {
      // Only handle Flatpickr datetime fields (type 28)
      if ($fieldType == 28 && preg_match('/\d{2}\/\d{2}\/\d{4}/', $value)) {
          $dt = DateTime::createFromFormat('d/m/Y H:i', $value);
          if ($dt) {
              return $dt->format('Y-m-d H:i:s'); // MySQL-friendly
          }
      }
      return $value; // return unchanged for other field types
  }
  

   /**
    * Get table query
    *
    * @return string  table query
    */
   public function getTableQuery() {
      return $this->TableQuery;
   }

   /**
    * Get form fields query
    *
    * @return string  form fields query
    */
   public function getFormFieldsQuery() {
      return $this->FormFieldsQuery;
   }

   /**
    * Get form
    *
    * @return array  form data
    */
   function getForm()
   {
      return $this->form;
   }


   /**
    * Get table
    *
    * @return array  table data
    */
   function getTable()
   {
      return $this->table;
   }

   /**
    * Get form fields
    *
    * @return array  form fields data
    */
   function getFormFields()
   {
      return $this->form_fields;
   }

   /**
    * Get form field by field ID
    *
    * @param string $field  Field ID
    * @return array|null  form field data or null
    */
   function getFormField($field)
   {
      $response = null;
      // $formFields = isset($this->form_fields) ? $this->form_fields : [];

      if (is_array($this->form_fields)) {
         foreach ($this->form_fields as $ff) {
            if ($ff['field'] == $field) {
               $response = $ff;
            }
         }
      }

      return $response;
   }

   /**
    * Get form field by name
    *
    * @param string $field  Field name
    * @return array|null  form field data or null
    */
   function getFormFieldByName($field_name)
   {
      $response = null;

      if (is_array($this->form_fields)) {
         foreach ($this->form_fields as $ff) {
            if ($ff['name'] == "$field_name") {
               $response = $ff;
            }
         }
      }

      return $response;
   }

   /**
    * Get max gallery image
    *
    * @return int  max gallery image
    */
   function getMaxImg()
   {
      return $this->maximg;
   }

   /**
    * Get gallery
    *
    * @return array|null  Gallery data or null
    */
   function getGallery()
   {
      $query = "SELECT * FROM `gallery` 
      WHERE `record_id` = '{$this->record_id}'
      ORDER BY `sort` ASC";
      $gallery = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($gallery) {
         return $gallery;
      } else {
         return null;
      }
   }

   /**
 * Get custom CSS
 *
 * @return string Custom CSS from the form
 */
public function getCss()
{
   return $this->css;
}
   /**
    * Get product images  
    *
    * @return array|null  Product images or null
    */
   function getProductImages()
   {
      $query = "SELECT * FROM `pro_product_images` 
      WHERE productid = " . $this->record_id . " 
      ORDER BY sort ASC";
      $images = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($images) {
         return $images;
      } else {
         return null;
      }
   }

   function updateProductImage($data)
   {
      $query = "UPDATE `pro_product_images`
      SET `alttag` = '" . $data['pro_alt'] . "',
      `caption` = '" . $data['pro_caption'] . "' 
      WHERE `id` = '" . $data['img_id'] . "'";

      $response = DB::query($query);

      if ($response) {
         return [
            'status' => 'success',
            'message' => 'Image ' . $data['img_id'] . ' updated successfully',
            'query' => $query
         ];
      } else {
         return [
            'status' => 'error',
            'message' => 'Image ' . $data['img_id'] . ' update failed',
            'query' => $query
         ];
      }
   }

   function updateGalleryImage($data)
   {
      $query = "UPDATE `gallery`
      SET `alttag` = '{$data['alttag']}',
      `caption` = '{$data['caption']}' 
      WHERE `id` = '{$data['img_id']}'";

      $response = DB::query($query);

      if ($response) {
         return [
            'status' => 'success',
            'message' => 'Image ' . $data['img_id'] . ' updated successfully',
            'query' => $query
         ];
      } else {
         return [
            'status' => 'error',
            'message' => 'Image ' . $data['img_id'] . ' update failed',
            'query' => $query
         ];
      }
   }

   /**
    * Get Field Type
    *
    * @param int $field_id  Field ID
    * @return array|null  Field Type data or null
    */
   function getFieldType($field_id)
   {
      $query = "SELECT * FROM `cms_field` 
      WHERE `id` = '" . $field_id . "'";
      $field = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($field) {
         return $field;
      } else {
         return null;
      }
   }

   /**
    * Get Table Content
    *
    * @return array|null  Table Content data or null
    */
   function getTableContent()
   {
      $query = "SELECT * FROM `" . $this->tablename . "` 
      WHERE `id` = '" . $this->record_id . "'";
      $content = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($content) {
         return $content;
      } else {
         return null;
      }
   }

   /**
    * Update Table Content
    *
    * @param array $data  Form data
    * @return array  Response
    */
   function updateTableContent($data , $formFields = [])
   {
      $this->formFields = $formFields; // ✅ make form fields available  

      $query = "UPDATE `" . $this->tablename . "` SET ";
    
        // ✅ Build a name-based lookup so we can safely find field metadata
        $fieldsByName = [];
        if (!empty($this->formFields)) {
            foreach ($this->formFields as $ff) {
                if (!empty($ff['name'])) {
                    $fieldsByName[$ff['name']] = $ff;
                }
            }
        }
    
        foreach ($data as $key => $value) {
         if ($key != 'formnumber' && $key != 'submit') {
     
             // --- get field meta; support both keyed and numeric $this->formFields ---
             $fieldMeta = isset($this->formFields[$key]) ? $this->formFields[$key] : null;
             if ($fieldMeta === null && !empty($this->formFields)) {
                 foreach ($this->formFields as $ff) {
                     if (!empty($ff['name']) && $ff['name'] === $key) { $fieldMeta = $ff; break; }
                 }
             }
     
             $fieldType     = $fieldMeta && isset($fieldMeta['field'])    ? (int)$fieldMeta['field']    : null;
             $fieldDatatype = $fieldMeta && isset($fieldMeta['datatype']) ? (int)$fieldMeta['datatype'] : 0;
     
             // --- normalise date/time only for date-ish field types ---
             if (in_array($fieldType, [6, 13, 22, 28], true)) {
                 $value = wccmsNormalizeDateTime($value, $fieldType);
             }
            // --- Special handling for password fields ---
            if ($fieldType == 2) {
               if ($value === '**********' || trim($value) === '') {
                  //error_log("DEBUG PASSWORD: skipped updating password for '$key'");
                  continue; // skip adding to $sets[]
               } else {
                  // hash the password before saving
                  $value = password_hash($value, PASSWORD_DEFAULT);
                  //error_log("DEBUG PASSWORD: hashed new password for '$key'");
               }
            }

            error_log("DEBUG field check: key=$key | found=" . ($fieldMeta ? 'YES' : 'NO') . " | type=$fieldType | datatype=$fieldDatatype | value=$value");
             // --- password handling: field = 2 ---
             if ($fieldType === 2) {
                 // If user left placeholder or blank, skip updating password
                 if ($value === '**********' || trim($value) === '') {
                     // do NOT add this field to SET clause (leave DB as-is)
                     continue;
                 }
     
                 if ($fieldDatatype === 0) {
                     // MD5 mode: avoid double hashing (accept 32-char hex as already hashed)
                     $trimmed = trim($value);
                     $looksHashed = (strlen($trimmed) === 32 && ctype_xdigit($trimmed));
                     if (!$looksHashed) {
                         $value = md5($trimmed);
                     } else {
                         $value = $trimmed;
                     }
                 }
                 // datatype = 1 => raw; falls through unchanged (we already handled placeholder/blank)
             }
     
             // --- escape and append to SET ---
             $safeValue = addslashes($value);
             $query .= "`$key` = '$safeValue', ";
         }
     }
     

    
        $query = substr($query, 0, -2); // remove last comma
        $query .= " WHERE `id` = '" . $this->record_id . "'";
    
        $response = DB::query($query);
    
        if ($response) {
            return [
                'status'  => 'success',
                'message' => 'Record updated successfully',
                'query'   => $query
            ];
        } else {
            return [
                'status'  => 'error',
                'message' => 'Record update failed',
                'query'   => $query
            ];
        }
   }
    
    
    



   /**
    * Get Radio Options
    *
    * @param int $form_field_ID  Form Field ID
    * @return array|null  Radio Options data or null
    */
   function getRadioOptions($form_field_ID)
   {
      $query = "SELECT * FROM `cms_form_field_options` 
      WHERE `form_field` = '$form_field_ID'";
      $result = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }

   /**
    * Get Select Options from Query
    *
    * @param string $query  Query
    * @return array|null  Select Options data or null
    */
   function getSelectFromQuery($query)
   {
      $q = str_replace("{{mainID}}", $this->record_id, $query);
      $result = mysqli_fetch_all(DB::query($q), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }

   /**
    * Get preferences by name
    *
    * @param string $name  Name
    * @return array|null  Preferences data or null
    */
   function getPreferencesByName($name)
   {
      $query = "SELECT * FROM `preferences` 
      WHERE `name` = '$name'";
      $result = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }

   /**
    * Get tab by ID
    *
    * @param int $tabID  Tab ID
    * @return array|null  Tab data or null
    */
   function getTab($tabID)
   {
      $query = "SELECT * FROM `cms_tabs` 
      WHERE `id` = '$tabID'";
      $result = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }

   public function getFormTabs()
   {
      $query = "SELECT DISTINCT ff.tab as `tabID`, t.name as `name`, t.sort as `sort`, t.text as `text`, t.icon as `icon`, t.showonweb as `showonweb` FROM `cms_form_field` ff
      LEFT JOIN `cms_tabs` t ON t.id = ff.tab
      WHERE ff.form = '{$this->form['id']}'
      AND ff.showonweb = 'Yes' 
      AND ff.archived = 0 
      ORDER BY t.sort ASC";
      $result = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($result) {
         return $result;
      } else {
         return null;
      }
   }
}

