<?php
/**
 * RecordReport
 * Renders vertical (field -> value) reports from cms_form/cms_form_field.
 * Supports: text, textarea, date/datetime/time (UK format), select (static options).
 */
class RecordReport
{
    public $form;
    public $table;
    public $form_fields = [];
    public $tablename;

    private $formQuery;
    private $TableQuery;
    private $FormFieldsQuery;
    private $optionsCache = [];   // <-- Item 1: add cache here

    function __construct($form_id)
    {
        $form_id = intval($form_id);

        // Load form
        $query = "SELECT * FROM `cms_form` WHERE `id` = '$form_id' LIMIT 1";
        $this->formQuery = $query;
        $form = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
        if (!$form) {
            // Fail early but safely
            $this->form = null;
            return;
        }

        $this->form = $form;

        // Load cms_table to get actual table name
        $query = "SELECT * FROM `cms_table` WHERE `id` = '" . $form['table'] . "' LIMIT 1";
        $this->TableQuery = $query;
        $table = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);
        if ($table) {
            $this->table = $table;
            $this->tablename = $table['name']; // <- actual DB table name
        }

        // Load only report fields
        $query = "SELECT * FROM `cms_form_field`
                  WHERE `form` = '$form_id'
                    AND `archived` = 0
                    AND `inreport` = 'Yes'
                  ORDER BY `reportsort` ASC, `sort` ASC";
        $this->FormFieldsQuery = $query;

        $res = DB::query($query);
        if ($res) {
            $this->form_fields = mysqli_fetch_all($res, MYSQLI_ASSOC) ?: [];
        }
    }

    /** Get form */
    public function getForm() { return $this->form; }

    /** Report fields (already filtered/sorted) */
    public function getFormFields() { return $this->form_fields; }

    /** Fetch all rows to show (adjust later for filters/date range) */
    public function getTableContent()
    {
        if (empty($this->tablename)) return [];
        $q = "SELECT * FROM `{$this->tablename}` ORDER BY `id` DESC";
        $res = DB::query($q);
        return $res ? (mysqli_fetch_all($res, MYSQLI_ASSOC) ?: []) : [];
    }

    /** Lookup field type row by id (same as RecordEdit) */
    public function getFieldType($field_id)
    {
        $q = "SELECT * FROM `cms_field` WHERE `id` = '" . intval($field_id) . "' LIMIT 1";
        $res = DB::query($q);
        return $res ? (mysqli_fetch_array($res, MYSQLI_ASSOC) ?: null) : null;
    }

    /** Static options from cms_form_field_options (for Select/Radio) */
    public function getStaticOptions($form_field_id)
    {
        $q = "SELECT * FROM `cms_form_field_options` WHERE `form_field` = '" . intval($form_field_id) . "' ORDER BY `sort` ASC, `id` ASC";
        $res = DB::query($q);
        if (!$res) return [];
        $rows = mysqli_fetch_all($res, MYSQLI_ASSOC) ?: [];
        // Normalise into value => label
        $out = [];
        foreach ($rows as $r) {
            // Try common column names
            $val = $r['value'] ?? ($r['val'] ?? ($r['id'] ?? null));
            $lab = $r['label'] ?? ($r['name'] ?? ($r['text'] ?? $val));
            if ($val !== null) $out[(string)$val] = (string)$lab;
        }
        return $out;
    }

    /** UK date/time formatting helpers */
    private function fmtDate($v)
    {
        // Accepts YYYY-MM-DD or any strtotime-able; returns dd-mm-yyyy
        $v = trim((string)$v);
        if ($v === '' || $v === '0000-00-00') return '';
        $ts = strtotime($v);
        if ($ts === false) return htmlspecialchars($v);
        return date('d-m-Y', $ts);
    }
    private function fmtDateTime($v)
    {
        // Accepts YYYY-MM-DD HH:MM:SS; returns dd-mm-yyyy HH:MM
        $v = trim((string)$v);
        if ($v === '' || $v === '0000-00-00 00:00:00') return '';
        $ts = strtotime($v);
        if ($ts === false) return htmlspecialchars($v);
        return date('d-m-Y H:i', $ts);
    }
    private function fmtTime($v)
    {
        $v = trim((string)$v);
        if ($v === '' || $v === '00:00:00') return '';
        $ts = strtotime($v);
        if ($ts === false) return htmlspecialchars($v);
        return date('H:i', $ts);
    }

     // Item 2: helper to map value=>label from soursesql
     private function getOptionsMapFromSourceSQL(array $ff): array
     {
         $ffId = (int)$ff['id'];
         if (isset($this->optionsCache[$ffId])) {
             return $this->optionsCache[$ffId];
         }
 
         $sql = trim($ff['soursesql'] ?? '');
         if ($sql === '') {
             return $this->optionsCache[$ffId] = [];
         }
 
         $res = DB::query($sql);
         if (!$res) {
             return $this->optionsCache[$ffId] = [];
         }
 
         $map = [];
         while ($row = mysqli_fetch_assoc($res)) {
             // You confirmed soursesql always returns `id` and `name`
             if (isset($row['id']) && isset($row['name'])) {
                 $map[(string)$row['id']] = (string)$row['name'];
             }
         }
 
         return $this->optionsCache[$ffId] = $map;
     }


     public function renderValue(array $row, array $ff)
     {
         $col      = $ff['name'];
         $raw      = $row[$col] ?? '';
         $datatype = (int)($ff['datatype'] ?? 0);
         $fieldId  = (int)($ff['field'] ?? 0);
     
         // Select from table (18) or custom lookup (29)
         if (in_array($fieldId, [18, 29], true)) {
            if ($raw === '' || $raw === null) {
                return '';
            }
            $map = $this->getOptionsMapFromSourceSQL($ff);
            return htmlspecialchars($map[(string)$raw] ?? (string)$raw);
        }
        
     
         // Select from options (16)
         if ($fieldId === 16) {
             $opts = $this->getStaticOptions($ff['id']);
             if (!empty($opts)) {
                 $key = (string)$raw;
                 if (array_key_exists($key, $opts)) {
                     return htmlspecialchars($opts[$key]);
                 }
             }
             return htmlspecialchars((string)$raw);
         }
     
         // Fallback to formatters for other field types
         return formatFieldValue($raw, $datatype, $fieldId);
     }
     
    



}
