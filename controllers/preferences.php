<?php
/**
 * Preferences
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
 */
class Preferences
{
   public $preferences;
   private $preferencesQuery;
   protected $tablename;


   function __construct($tablename)
   {
      $this->tablename = $tablename;

      $query = "SELECT * FROM `{$this->tablename}` 
      WHERE `showoncms` = 'Yes' 
      AND `archived` = '0'
      ORDER BY `prefCat` ASC, `sort` ASC";

error_log("construct select: ".$query) ;


      $this->preferencesQuery = $query;
      $preferences = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if (count($preferences) > 0) {
         $this->preferences = $preferences;
      } else {
         // Return a error can be catch by the users
         throw new Exception("No preferences found [x]");
      }
   }

   public function getPreferences($refresh = false)
   {
      if ($refresh) {
         $query = "SELECT * FROM `{$this->tablename}` 
         WHERE `showoncms` = 'Yes' 
         AND `archived` = '0'
         ORDER BY `prefCat` ASC, `sort` ASC";
         $this->preferencesQuery = $query;

         $preferences = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

         if (count($preferences) > 0) {
            $this->preferences = $preferences;
         } else {
            // Return a error can be catch by the users
            throw new Exception("No preferences found [y]");
         }

      }
      
      return $this->preferences;
   }

   public function getPreferencesQuery()
   {
      return $this->preferencesQuery;
   }

   public function getPreferencesTabs()
   {
      $query = "SELECT DISTINCT `{$this->tablename}`.`prefCat` as `prefId`, `prefCat`.`name` as `prefName`, `prefCat`.`notes` as `prefNotes` 
      FROM `{$this->tablename}` 
      LEFT JOIN `prefCat` ON `{$this->tablename}`.`prefCat` = `prefCat`.`id`
      WHERE `showoncms` = 'Yes' 
      GROUP BY `{$this->tablename}`.`prefCat`
      ORDER BY `prefCat` ASC";

      error_log("tab select: ".$query) ;

      $preferencesTabs = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if (count($preferencesTabs) > 0) {
         return $preferencesTabs;
      } else {
         throw new Exception("Error processing the preferences tabs");
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

   public function updatePreferences($data)
   {
      $response = [];
      $allQueries = []; // ← to collect each executed query
      $errors = [];


      foreach ($data as $key => $value) {
         if ($key != 'formnumber' && $key != 'submit') {
            $query = "UPDATE `{$this->tablename}` 
            SET `value` = '$value' 
            WHERE `name` = '$key'";
            $allQueries[] = $query; // ✅ Add this line
            $result = DB::query($query);

            if ($result) {
               $response['log'][$key] = [
                  'status' => 'success',
                  'message' => "Preference [$key] updated successfully",
                  'query' => $query
               ];
            } else {
               $response['log'][$key] = [
                  'status' => 'error',
                  'message' => "Preference [$key] update failed",
                  'query' => $query
               ];
               $response['errors'][$key] = [
                  'status' => 'error',
                  'message' => "Preference [$key] update failed",
                  'query' => $query
               ];
            }
         }
      }
      return [
         'errors' => $errors,
         'sql' => implode(";\n", $allQueries) // ← return as single log string
     ];
      //return $response;
   }

   

}



function getLogmaskData($updateData) {
   $conn = DB::connection();
   if (!$conn) {
       error_log("Database connection failed.");
       return [];
   }

   $logmaskData = [];
   foreach (array_keys($updateData) as $key) {
       $sql = "SELECT logmask FROM {$this->tablename} WHERE name = '$key'";
       $result = DB::query($sql);

       if ($result && mysqli_num_rows($result) > 0) {
           $row = mysqli_fetch_assoc($result);
           $logmaskData[$key] = $row['logmask'];
       } else {
           $logmaskData[$key] = 0;
       }
   }

   return $logmaskData;
}

// Masking functions for Key data
function createMaskedSqlQuery($updateData, $logmaskData) {
   $maskedData = [];
   foreach ($updateData as $key => $value) {
       $logmask = isset($logmaskData[$key]) ? (int)$logmaskData[$key] : 0;
       if ($logmask > 0) {
           $maskedData[$key] = maskValue($value, $logmask);
       } else {
           $maskedData[$key] = $value;
       }
   }

   $sqlQueryParts = [];
   foreach ($maskedData as $key => $value) {
       $sqlQueryParts[] = "$key='$value'";
   }

   return "UPDATE {$this->tablename} SET " . implode(', ', $sqlQueryParts);
}

function maskValue($value, $logmask) {
   $keep_start = $logmask;
   $keep_end = $logmask;
   if (strlen($value) <= $keep_start + $keep_end) {
       return $value; // Do not mask if the value is too short
   }
   return substr($value, 0, $keep_start) . str_repeat('*', strlen($value) - $keep_start - $keep_end) . substr($value, -$keep_end);
}

