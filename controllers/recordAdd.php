<?php
/**
 * recordAdd
 * 
 * @version 1.0.0
 * @author salva TDR
 * 
 * @var array $form Form data
 * @var array $table Table data
 * @var array $form_fields Form fields data
 */
class RecordAdd
{
   public $form;
   public $table;
   public $form_fields;

   function __construct($form_id)
   {
      // Form
      $query = "SELECT * FROM `cms_form` 
      WHERE `id` = '" . $form_id . "' ";
      $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($form) {
         $this->form = $form;

         // Table
         $query = "SELECT * FROM `cms_table` 
         WHERE `id` = '" . $form['table'] . "' ";
         $table = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

         if ($table) {
            $this->table = $table;
         } else {
            $this->table = null;
         }

         // Field names
         $query = "SELECT * FROM `cms_form_field` 
         WHERE `form` = '" . $form_id . "'
         ORDER BY `sort` ASC";
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
      }
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

   //Converet Date from Flatpickr to correct format for saving
   function normalizeDateTimeForDB($value) {
      if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value)) {
         $dt = DateTime::createFromFormat('d/m/Y H:i', $value);
         if ($dt) {
            return $dt->format('Y-m-d H:i:s');
         }
      }
      return $value;
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
    * @param int $showadd  get only fields with showadd = Yes
    * @return array  form fields data
    */
   public function getFormFields($showadd = null)
   {
      if ($showadd == 1) {
         $response = [];

         if (is_array($this->form_fields)) {
            foreach ($this->form_fields as $ff) {
               if ($ff['showadd'] == 'Yes') {
                  array_push($response, $ff);
               }
            }
         }

         return $response;
      } else {
         return $this->form_fields;
      }
   }

   /**
    * Get Field Type
    *
    * @param int $field_id  Field ID
    * @return array|null  Field Type data or null
    */
   public function getFieldType($field_id)
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

   
   private function getLastInsert() {
      $query = "SELECT * FROM `" . $this->table['name'] . "` 
      ORDER BY `id` DESC 
      LIMIT 1";
      $lastRecord = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($lastRecord) {
         return $lastRecord;
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
    function insertTableContent($data)
    {
        // --- get field metadata once ---
        $formFields = $this->getFormFields(true);
    
        $query = "INSERT INTO `" . $this->table['name'] . "` (";
        foreach ($data as $key => $value) {
            if ($key != 'formnumber' && $key != 'submit') {
                $query .= "`$key`, ";
            }
        }
        $query = substr($query, 0, -2); // remove last comma
        $query .= ") VALUES (";
    
        foreach ($data as $key => $value) {
            if ($key != 'formnumber' && $key != 'submit') {
    
                // --- find matching field info if it exists ---
                $fieldType   = null;
                $fieldDatatype = 0; // default
                foreach ($formFields as $ff) {
                    if ($ff['name'] === $key) {
                        $fieldType = (int)$ff['field'];
                        $fieldDatatype = isset($ff['datatype']) ? (int)$ff['datatype'] : 0;
                        break;
                    }
                }
    
                // --- normalize date/time formats for relevant types ---
                if (in_array($fieldType, [6, 13, 22, 28])) {
                    $value = wccmsNormalizeDateTime($value, $fieldType);
                }
    
                // --- NEW: handle password field (field = 2) ---
                if ($fieldType === 2) {
                    if ($fieldDatatype === 0) {
                        // default behaviour – save MD5 hash
                        if (!empty($value)) {
                            $value = md5($value);
                        }
                    } else {
                        // datatype = 1 → raw, leave as is
                    }
                }
    
                //error_log("Saving field $key (type $fieldType, datatype $fieldDatatype): $value");
    
                $safeValue = addslashes($value);
                $query .= "'$safeValue', ";
            }
        }
    
        $query = substr($query, 0, -2); // remove last comma
        $query .= ")";
        $response = DB::query($query);
    
        $lastRecord = $this->getLastInsert();
    
        if ($response) {
            return [
                'status' => 'success',
                'message' => 'Record added successfully',
                'recordID' => $lastRecord['id'],
                'query' => $query
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Error adding record',
                'query' => $query
            ];
        }
    }
    
    
}