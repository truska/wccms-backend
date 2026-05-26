<?php
/**
 * formatFieldValue
 * Shared formatter for CMS field values
 *
 * @param mixed  $value      Raw DB value
 * @param int    $datatype   Formatting rule (matches cms_form_field.datatype)
 * @param int    $dataOption Option for variant formatting (like date styles)
 * @return string            Formatted safe HTML
 */

 function formatFieldValue($value, $datatype = 0, $fieldId = 0)
 {
     $value = trim((string)$value);
 
     switch ($fieldId) {
         case 6:   // Date
         case 22:  // Date (alt)
             $ts = strtotime($value);
             if ($ts === false || $value === '') {
                 return htmlspecialchars($value);
             }
             switch ($datatype) {
                 case 10: return date("D d M Y", $ts);      // Mon 21 Feb 2025
                 case 11: return date("d M Y", $ts);        // 21 Feb 2025
                 case 12: return date("d/m/Y", $ts);        // 21/02/2025
                 default: return date("Y-m-d", $ts);        // fallback
             }
 
         case 13:  // Time only
             $ts = strtotime($value);
             if ($ts === false || $value === '') {
                 return htmlspecialchars($value);
             }
             // Could later add variants if needed, for now always H:i
             return date("H:i", $ts);
             //error_log("TIME DEBUG: raw=$value, ts=" . date("Y-m-d H:i:s", $ts));
 
         case 28:  // DateTime
             $ts = strtotime($value);
             if ($ts === false || $value === '') {
                 return htmlspecialchars($value);
             }
             switch ($datatype) {
                 case 10: return date("D d M Y H:i", $ts);  // Mon 21 Feb 2025 14:30
                 case 11: return date("d M Y H:i", $ts);    // 21 Feb 2025 14:30
                 case 12: return date("d/m/Y H:i", $ts);    // 21/02/2025 14:30
                 default: return date("Y-m-d H:i", $ts);    // fallback
             }
 
         case 7: // Email (this is actually a datatype in your earlier scheme, but keep for clarity)
             if ($value !== '') {
                 return "<a href='mailto:" . htmlspecialchars($value) . "'>" . htmlspecialchars($value) . "</a>";
             }
             return '';
 
         case 12: // Telephone (same note as above)
             if ($value !== '') {
                 $telHref = preg_replace('/\s+|-/', '', $value);
                 return "<a href='tel:" . htmlspecialchars($telHref) . "'>" . htmlspecialchars($value) . "</a>";
             }
             return '';

        case 19:  // Formatted HTML text
            return $value; // output as-is (HTML preserved)

        case 20:  // Plain text (safe, keep line breaks)
            return nl2br(htmlspecialchars($value));
 
         default:
             return htmlspecialchars($value);
     }
     
 }
 

/**
 * Normalize any date/time value from form POST to SQL-safe Y-m-d H:i:s
 * Handles Flatpickr (d/m/Y H:i), HTML datetime-local (Y-m-dTH:i), 
 * and plain dates/times.  Returns unchanged if not a valid date/time.
 */
function wccmsNormalizeDateTime($value, $fieldType = null) {

    if (empty($value)) return $value;

    // --- accept ISO or already-SQL format ---
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        // remove trailing “T” if present (from datetime-local)
        return str_replace('T', ' ', $value);
    }

    // --- accept Flatpickr UK format ---
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value)) {
        $dt = DateTime::createFromFormat('d/m/Y H:i', $value)
           ?: DateTime::createFromFormat('d/m/Y', $value);
        if ($dt) return $dt->format('Y-m-d H:i:s');
    }

    // --- accept time-only values (for field 13 etc.) ---
    if (preg_match('/^\d{1,2}:\d{2}/', $value)) {
        return date('Y-m-d ') . $value . ':00';
    }

    // if nothing matched, return original
    return $value;
}


function wccmsDefaultDateTimeValue($fieldType) {
    switch ($fieldType) {
        case 6: // date
        case 22:
            return date('Y-m-d');
        case 13: // time only
            return date('H:i');
        case 28: // datetime
        default:
            return date('Y-m-d H:i');
    }
}

