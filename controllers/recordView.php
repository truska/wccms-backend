<?php
class RecordView
{
   public $form;
   public $table;
   private $formQuery;
   private $tableQuery;
   private $tableViewQuery;

   function __construct($form_id)
   {
      $query = "SELECT * FROM `cms_form` 
      WHERE `id` = '" . $form_id . "' ";
      $this->formQuery = $query;
      $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($form) {
         $this->form = $form;

         $query = "SELECT * FROM `cms_table` 
         WHERE `id` = '" . $form['table'] . "' ";
         $this->tableQuery = $query;
         $table = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

         if ($table) {
            $this->table = $table;
         }else {
            $this->table = null;
         }
      }else {
         $this->form = null;
         $this->table = null;
      }
   }

   /**
    * Get form query
    *
    * @return string  form query
    */
   public function getFormQuery() {
      return $this->formQuery;
   }
   
   /**
    * Get table query
    *
    * @return string  table query
    */
   public function getTableQuery() {
      return $this->tableQuery;
   }

   /**
    * Get table view query
    *
    * @return string  table view query
    */
    public function getTableViewQuery() {
      return $this->tableViewQuery;
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
   function getTable() {
      return $this->table;
   }

   /**
    * Get table view
    *
    * @return array|null  table view data
    */
   function getTableView($debug = false) {
      $query = "SELECT * FROM `" . $this->table['name'] . "`";
      if ($this->form["showarchived"] == 'Yes') {$showarchived = '1';} else {$showarchived = '0' ;} 

      // Start the WHERE clause
    $whereConditions = [];

        // Add existing where1 condition if present
        if ($this->form["where1"]) {
         $whereConditions[] = $this->form["where1"];
     }
 
     // Add archived condition
     $whereConditions[] = "`archived` = '$showarchived'";
 
     // Add showdays condition if it's greater than 0
     if (isset($this->form["showdays"]) && $this->form["showdays"] > 0) {
         $showDays = intval($this->form["showdays"]);
         // Use `DATE_SUB()` to calculate the date $showDays ago
         $whereConditions[] = "`created` >= DATE_SUB(NOW(), INTERVAL $showDays DAY)";
     }
 
     // Append the WHERE conditions to the query
     if (!empty($whereConditions)) {
         $query .= " WHERE " . implode(' AND ', $whereConditions);
     }

     /*
      if ($this->form["where1"]) {
         $query .= " WHERE " . $this->form["where1"] . " 
         AND `archived` = '$showarchived' ";
      }else {
         $query .= " WHERE `archived` = '$showarchived'  ";
      }

      */
      
      if ($this->form["sort1"]) {
         $query .= " ORDER BY `" . $this->form["sort1"] . "` " . $this->form["sort1order"] . "";
      }
      if ($this->form["sort2"]) {
         $query .= " , `" . $this->form["sort2"] . "` " . $this->form["sort2order"] . "";
      }
      if ($this->form["showlimit"]) {
         $query .= " LIMIT " . $this->form["showlimit"] . "";
      }

      $this->tableViewQuery = $query;

      $tableView = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($debug) {
         return [
            'query' => $query,
            'tableView' => $tableView
         ];
      }

      if ($tableView) {
         return $tableView;
      }else {
         return null;
      }
   }

   function getColTable($table, $text) {
      $query = "SELECT * FROM `" . $table . "` WHERE `id` = '" . $text . "'";
      $colTable = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
      
      if ($colTable) {
         return $colTable;
      }else {
         return null;
      }
   }

   function getActions() {
      $query = "SELECT * FROM `cms_form_actions` 
      WHERE `form` = '" . $this->form['id'] . "' 
      AND `showonweb` = 'Yes'
      ORDER BY `sort` ASC";
      $actions = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);

      if ($actions) {
         return $actions;
      }else {
         return null;
      }
   }

   function getAction($action) {
      $query = "SELECT * FROM `cms_actions`
      WHERE `id` = {$action}";
      $action = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($action) {
         return $action;
      }else {
         return null;
      }
   }

   public function getIconById($icon) {
      $query = "SELECT * FROM `icons` 
      WHERE `id` = {$icon}";
      $icon = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($icon) {
         return $icon;
      }else {
         return null;
      }
   }

   /**
    * Update show on web
    *
    * @param int $id Record id
    * @param string $table Table name
    * @param string $showonweb Show status (Yes/No)
    *
    * @return bool  true/false
    */
   public function updateShowOnWeb($id, $table, $showonweb) {
      $query = "UPDATE `$table` 
      SET `showonweb` = '{$showonweb}' 
      WHERE `id` = {$id}";
      $result = DB::query($query);

      if ($result) {
         return true;
      }else {
         return false;
      }
   }

   public function deleteContent($id, $table) {
      $query = "UPDATE `$table` 
      SET `archived` = true 
      WHERE `id` = {$id}";
      $result = DB::query($query);

         // Check if the query was successful
         $success = $result ? true : false;

         // Return an associative array containing the query and the success status
         return [
            'query' => $query,
            'success' => $success
         ];
      
      /*
      if ($result) {
         return true;
      }else {
         return false;
      }
   */
   }

   public function undeleteContent($id, $table) {
      $query = "UPDATE `$table` 
      SET `archived` = false 
      WHERE `id` = {$id}";
      $result = DB::query($query);

         // Check if the query was successful
         $success = $result ? true : false;

         // Return an associative array containing the query and the success status
         return [
            'query' => $query,
            'success' => $success
         ];
      /*
      if ($result) {
         return true;
      }else {
         return false;
      }
      */
   }
}