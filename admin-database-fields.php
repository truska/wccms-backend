<!-- START admin-database-fields [20250630] -->
<!DOCTYPE html>

<?php
$pageType = 'admin' ;

// Check for and if missing added several fields to all tables

// use $action# = Yes / No to select what to run

$action1 = "No" ;
$action2 = "No" ;
$action3 = "No" ;
$action4 = "No" ;
$action5 = "No" ;
$action6 = "No" ;
$action7 = "No" ;

// If ?fx=1 is in the URL, override actions to Yes
if (isset($_GET['fx']) && $_GET['fx'] == '1') {
    $action1 = "Yes";
    $action2 = "Yes";
    $action3 = "Yes";
    $action4 = "Yes";
    $action5 = "Yes";
    $action6 = "Yes";
    $action7 = "Yes";
}


$action1desc = 'Action 1: check and if missing add fields showonweb, created, modified and archived to ALL tables' ;
$action2desc = 'Action 2: cms_form_field adding new fields if missing and renaming some fields';
$action3desc = 'Action 3: cms_form - checking and adding new fields if missing';
$action4desc = 'Action 4: add/alter fields in preferences table';
$action5desc = 'Action 5: updating preferences table with specific new rows';
$action6desc = 'Action 6: adding specific hard-coded FIELDS to various tables';
$action7desc = 'Action 7: adding specific hard-coded ROWS to various tables';

// Turn off error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('setting/main-top-files.php'); 
require_once(__DIR__ . "/../../private/dbm.php");

?>

<html lang="en">

<head>
    <?php
        include("include/header-code.php");
    ?>

</head>

<body>
    <style>
        h3 {
            padding-top:30px;
        }
    </style>
    <section id="container" class="">
        <?php
        include("include/header.php");
        include("include/sidebar.php");
        ?>


        <section id="main-content">
            <section class="wrapper site-min-height">

                <!-- page start-->
                <section class="card" style="width:100%;margin-left: -10px">
                    <div class="row">
                        <div class="card-body">
                            <!-- <div class="col-md-1 hidden-sm hidden-xs"></div>  -->
                            <div class="col-sm-12 col-md-10 col-lg-10" style="margin-top:20px;">
                                <h2>Check and adjust Database tables </h2>
                                <?php
                                echo "<h4>Settings</h4>" ;
                                echo "<p>".date('m/d/Y h:i:s a', time())."<br>" ;
                                echo "".$action1." - ".$action1desc."<br>" ;
                                echo "".$action2." - ".$action2desc."<br>" ;
                                echo "".$action3." - ".$action3desc."<br>" ;
                                echo "".$action4." - ".$action4desc."<br>" ;
                                echo "".$action5." - ".$action5desc."<br>" ;
                                echo "".$action6." - ".$action6desc."<br>" ;
                                echo "".$action7." - ".$action7desc."</p>" ;
                                ?>
                            </div>


                            <?php
                            // Update all tables
                            $result = DatabaseUpdater::updateDatabaseSchema($action1,$action1desc);
                            print_r($result);

                            // Specifically update 'cms_form_field' table
                            DatabaseUpdater::updateCmsFormFieldTable($action2,$action2desc);
                            DatabaseUpdater::updateCmsFormTable($action3,$action3desc);
                            DatabaseUpdater::updatePreferencesFields($action4,$action4desc);
                            DatabaseUpdater::updatePreferencesTable($action5,$action5desc);
                            DatabaseUpdater::updateSpecificFields($action6,$action6desc);
                            DatabaseUpdater::updateSpecificRows($action7,$action7desc);


                            class DatabaseUpdater
                            {
                                // This function updates all tables with certain fields
                                public static function updateDatabaseSchema($action1,$action1desc) {
                                    $action = $action1 ; // Set to 'Yes' to apply changes, 'No' to only simulate and log - needs set in 2nd function as well
                                    echo "<h5>".$action1desc."</h5>" ;
                                    // Get all table names from the database
                                    $tablesResult = DB::query("SHOW TABLES");
                                    if (!$tablesResult) {
                                        return "Error fetching tables.";
                                    }

                                    $tables = mysqli_fetch_all($tablesResult, MYSQLI_NUM);
                                    $output = [];
                                    foreach ($tables as $tableRow) {
                                        $table = $tableRow[0];
                                        $tableChanges = ["<br>"];

                                        // Define the fields and SQL to add them if missing
                                        $fieldsToAdd = [
                                            'showonweb' => "ALTER TABLE `$table` ADD `showonweb` ENUM('Yes', 'No') NOT NULL DEFAULT 'Yes'",
                                            'created' => "ALTER TABLE `$table` ADD `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
                                            'modified' => "ALTER TABLE `$table` ADD `modified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
                                            'archived' => "ALTER TABLE `$table` ADD `archived` TINYINT(1) NOT NULL DEFAULT 0"
                                        ];

                                        foreach ($fieldsToAdd as $fieldName => $sql) {
                                            $checkFieldExists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$fieldName'");
                                            if (!$checkFieldExists || mysqli_num_rows($checkFieldExists) == 0) {
                                                if ($action == 'Yes') {
                                                    $result = DB::query($sql);
                                                    if ($result) {
                                                        $tableChanges[] = "✅ Added `$fieldName` to $table <br>";
                                                    } else {
                                                        $tableChanges[] = "❌ Failed to add `$fieldName` to $table <br>";
                                                    }
                                                } else {
                                                    $tableChanges[] = "⚠️ Needs to add `$fieldName` to $table  [Turn Action On to apply change]<br>";
                                                }
                                            } else {
                                                $tableChanges[] = "`$fieldName` already exists in table $table <br>";
                                            }
                                        }

                                        if (!empty($tableChanges)) {
                                            $output[$table] = $tableChanges;
                                        }
                                    }

                                    if (empty($output)) {
                                        return "No changes needed or all fields already exist.";
                                    }                                   

                                    return $output;
                                }

                                // This function specifically updates the 'cms_form_field' table
                                public static function updateCmsFormFieldTable($action2,$action2desc)
                                {
                                    $action = $action2; // Set at top of file
                                    $table = 'cms_form_field';
                                    echo "<h5>".$action2desc."</h5>" ;
                                    // Check and rename `order` to `sort` if it exists
                                    $orderExists = DB::query("SHOW COLUMNS FROM `$table` LIKE 'order'");
                                    if ($orderExists && mysqli_num_rows($orderExists) > 0) {
                                        if ($action == 'Yes') {
                                            DB::query("ALTER TABLE `$table` CHANGE `order` `sort` INT(11)");
                                        }
                                        else
                                        {
                                            echo "Needs to rename `order` to 'sort' in $table  [Turn Action On to apply change]<br>";
                                        }
                                        echo "`order` renamed to `sort` in $table <br>";
                                    } 
                                    else 
                                    {
                                        echo "`sort` [not order] already exists in table $table <br>";
                                    }


                                    // Define the fields and SQL to add them if missing
                                    $fieldsToAdd = [
                                        'tab' => [
                                            'sql' => "ALTER TABLE `$table` ADD `tab` VARCHAR(255) NOT NULL DEFAULT '1' ",
                                            'position' => ['order', 'sort']
                                        ],
                                        'default_size' => [
                                            'sql' => "ALTER TABLE `$table` ADD `default_size` VARCHAR(255) AFTER `file_ext`"
                                        ],
                                        'resize_status' => [
                                            'sql' => "ALTER TABLE `$table` ADD `resize_status` TINYINT(1) AFTER `file_ext`"
                                        
                                        ],
                                        'override_filename' => [
                                            'sql' => "ALTER TABLE `$table` ADD `override_filename` ENUM('Yes','No') NOT NULL DEFAULT 'No' AFTER `resize_status`"
                                        ],
                                        'issortable' => [
                                            'sql' => "ALTER TABLE `$table` ADD `issortable` ENUM('Yes','No') NOT NULL DEFAULT 'No' AFTER `override_filename`"
                                        ],
                                        'md_max_width' => [
                                            'sql' => "ALTER TABLE `$table` ADD `md_max_width` INT(50) NULL AFTER `lg_max_width`"
                                        ],
                                        'default-resize' => [
                                            'sql' => "ALTER TABLE `$table` ADD `default-resize` INT(16) NULL AFTER `resize_status`"
                                        ]

                                    ];

                                    // Add fields if they do not exist
                                    foreach ($fieldsToAdd as $fieldName => $info) {
                                        $checkFieldExists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$fieldName'");
                                        if (!$checkFieldExists || mysqli_num_rows($checkFieldExists) == 0) {
                                            // Special handling for `tab` because it has conditional positioning
                                            if ($fieldName == 'tab') {
                                                foreach ($info['position'] as $positionField) {
                                                    $positionExists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$positionField'");
                                                    if ($positionExists && mysqli_num_rows($positionExists) > 0) {
                                                        $info['sql'] .= "`$positionField`";
                                                        break;
                                                    }
                                                }
                                            }

                                            if ($action == 'Yes') {
                                                $result = DB::query($info['sql']);
                                                if ($result) {
                                                    echo "✅ Added `$fieldName` to $table <br>";
                                                } else {
                                                    echo "❌ Failed to add `$fieldName` to $table <br>";
                                                }
                                            } else {
                                                echo "⚠️ Needs to add `$fieldName` to $table [Turn Action On to apply change]<br>";
                                            }
                                        } else {
                                            echo "`$fieldName` already exixts in table $table <br>";
                                        }
                                    }
                                    

                                    // If 'tab' exists, update its values where NULL or 0 to '1'
                                    $checkTabExists = DB::query("SHOW COLUMNS FROM `$table` LIKE 'tab'");
                                    if ($checkTabExists && mysqli_num_rows($checkTabExists) > 0) {
                                        if ($action == 'Yes') {
                                            $updateResult = DB::query("UPDATE `$table` SET `tab` = '1' WHERE `tab` IS NULL OR `tab` = '0'");
                                            if ($updateResult) {
                                                echo "`tab` values updated to '1' where they were NULL or 0 in $table<br>";
                                            } else {
                                                echo "Failed to update `tab` values in $table<br>";
                                            }
                                        } else {
                                            echo "❌ Needs to update `tab` values to '1' where NULL or 0 [Turn Action On to apply change]<br>";
                                        }
                                    }


                                    echo "<p><strong>Update completed for table $table</strong></p><br>";
                                }

                                // This function specifically updates the 'cms_form' table
                                public static function updateCmsFormTable($action3,$action3desc)
                                {
                                    $action = $action3 ; // Set at top of file
                                    $table = 'cms_form';
                                    echo "<h5>".$action3desc."</h5>" ;


                                    // Define the fields and SQL to add them if missing
                                    $fieldsToAdd = [
                                        //COL
                                        'col4' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col4` VARCHAR(32) AFTER 
                                            `col3table` " 
                                        ],
                                        'col5' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col5` VARCHAR(32) AFTER 
                                            `col4` " 
                                        ],
                                        'col6' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col6` VARCHAR(32) AFTER 
                                            `col5` " 
                                        ],   

                                        //ColNAME
                                        'col1name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col1name` VARCHAR(32) AFTER 
                                            `table` " 
                                        ],
                                        'col2name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col2name` VARCHAR(32) AFTER 
                                            `col1` " 
                                        ],
                                        'col3name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col3name` VARCHAR(32) AFTER 
                                            `col2` " 
                                        ],
                                        'col4name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col4name` VARCHAR(32) AFTER 
                                            `col3` " 
                                        ],
                                        'col5name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col5name` VARCHAR(32) AFTER 
                                            `col4` " 
                                        ],
                                        'col6name' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col6name` VARCHAR(32) AFTER 
                                            `col5` " 
                                        ],


                                        //ColTABLE
                                        'col1table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col1table` VARCHAR(32) AFTER 
                                            `col1name` " 
                                        ],
                                        'col2table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col2table` VARCHAR(32) AFTER 
                                            `col2name` " 
                                        ],
                                        'col3table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col3table` VARCHAR(32) AFTER 
                                            `col3name` " 
                                        ],
                                        'col4table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col4table` VARCHAR(32) AFTER 
                                            `col4name` " 
                                        ],
                                        'col5table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col5table` VARCHAR(32) AFTER 
                                            `col5name` " 
                                        ],
                                        'col6table' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col6table` VARCHAR(32) AFTER 
                                            `col6name` " 
                                        ],

                                        //ColTABLE
                                        'col1type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col1type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER 
                                            `col1table` " 
                                        ],
                                        'col2type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col2type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER 
                                            `col2table` " 
                                        ],
                                        'col3type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col3type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER 
                                            `col3table` " 
                                        ],
                                        'col4type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col4type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER 
                                            `col4table` " 
                                        ],
                                        'col5type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col5type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER
                                            `col5table` " 
                                        ],
                                        'col6type' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col6type` ENUM('None', 'Search', 'Select') NOT NULL DEFAULT 'Search' AFTER 
                                            `col6table` " 
                                        ],

                                        //ColDATA
                                        'col1data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col1data` VARCHAR(64) AFTER 
                                            `col1type` " 
                                        ],
                                        'col2data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col2data` VARCHAR(64) AFTER 
                                            `col2type` " 
                                        ],
                                        'col3data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col3data` VARCHAR(64) AFTER 
                                            `col3type` " 
                                        ],
                                        'col4data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col4data` VARCHAR(64) AFTER 
                                            `col4type` " 
                                        ],
                                        'col5data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col5data` VARCHAR(64) AFTER 
                                            `col5type` " 
                                        ],
                                        'col6data' => [
                                            'sql' => "ALTER TABLE `$table` ADD `col6data` VARCHAR(64) AFTER 
                                            `col6type` " 
                                        ],

                                    ];

                                    // Add fields if they do not exist
                                    foreach ($fieldsToAdd as $fieldName => $info) {
                                        $checkFieldExists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$fieldName'");
                                        if (!$checkFieldExists || mysqli_num_rows($checkFieldExists) == 0) {
                                            
                                            // Special handling for `tab` because it has conditional positioning
                                            /*if ($fieldName == 'tab') {
                                                foreach ($info['position'] as $positionField) {
                                                    $positionExists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$positionField'");
                                                    if ($positionExists && mysqli_num_rows($positionExists) > 0) {
                                                        $info['sql'] .= "`$positionField`";
                                                        break;
                                                    }
                                                }
                                            }
                                            */

                                            if ($action == 'Yes') {
                                                $result = DB::query($info['sql']);
                                                if ($result) {
                                                    echo "Added `$fieldName` to $table <br>";
                                                } else {
                                                    echo "Failed to add `$fieldName` to $table <br>";
                                                }
                                            } else {
                                                echo "❌ Missing Field:  `$fieldName` to $table [Turn Action On to apply change]<br>";
                                            }
                                        } else {
                                            echo "☑️ `$fieldName` already exists in table $table <br>";
                                        }
                                    }

                                    echo "<p><strong>Update completed for table $table</strong></p><br>";
                                }

                                
                                public static function updatePreferencesFields($action4,$action4desc) {
                                    $action = $action4;
                                    $table = 'preferences';
                                    echo "<h3>Adding/Updating Preferences Table Fields</h3>";
                                    echo "<h5>".$action4desc."</h5>" ;

                                    $fieldsToAdd = [
                                        //ALTER
                                        'userlevel' => "ALTER TABLE `$table` CHANGE `userrole` `userlevel` INT(6) NOT NULL DEFAULT '30'",
                                        'sort' => "ALTER TABLE `$table` CHANGE `order` `sort` INT(8) NOT NULL ",
                                        // ADD

                                        'label' => "ALTER TABLE `$table` ADD `label` VARCHAR(32) NOT NULL AFTER `name`",
                                        'notes' => "ALTER TABLE `$table` ADD `notes` LONGTEXT NOT NULL AFTER `value`",                                  
                                        'class' => "ALTER TABLE `$table` ADD `class` VARCHAR(16) NOT NULL AFTER `field`",                    

                                        'comment' => "ALTER TABLE `$table` ADD `comment` LONGTEXT NOT NULL AFTER `sort`",
                                        'placeholder' => "ALTER TABLE `$table` ADD `placeholder` LONGTEXT NOT NULL AFTER `comment`",
                                        'required' => "ALTER TABLE `$table` ADD `required` ENUM('Yes','No') NOT NULL DEFAULT 'No' AFTER `placeholder`",
                                        'max' => "ALTER TABLE `$table` ADD `max` INT(4) NOT NULL AFTER `required`",
                                        'min' => "ALTER TABLE `$table` ADD `min` INT(4) NOT NULL AFTER `max`",
                                        'step' => "ALTER TABLE `$table` ADD `step` INT(6) NOT NULL AFTER `min`",                        

                                        'tooltip' => "ALTER TABLE `$table` ADD `tooltip` VARCHAR(512) NOT NULL AFTER `step`",
                                        'logmask' => "ALTER TABLE `$table` ADD `logmask` INT(8) NOT NULL AFTER `tooltip`",
                                        'showoncms' => "ALTER TABLE `$table` ADD `showoncms` ENUM('Yes','No') NOT NULL DEFAULT 'No' AFTER `logmask`",

                                    ];

                                    foreach ($fieldsToAdd as $field => $sql) {
                                        $exists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$field'");
                                        if (!$exists || mysqli_num_rows($exists) == 0) {
                                            if ($action == 'Yes') {
                                                $ok = DB::query($sql);
                                                echo $ok ? "✅ Added/Changed field: $field<br>" : "❌ Failed to update field: $field<br>";
                                            } else {
                                                echo "❌ Missing field: $field [Set Action 4 to Yes to apply]<br>";
                                            }
                                        } else {
                                            echo "☑️ $field Already exists: <br>";
                                        }
                                    }
                                }



                                public static function updatePreferencesTable($action5,$action5desc) {
                                    $action = $action5;
                                    echo "<h3>Updating Preferences Table</h3>";
                                    echo "<h5>".$action5desc."</h5>" ;
                                    echo "<h6>Using: cms_database_preference_update</h6>";

                                    $records = [];
                                    $result = DBM::query("SELECT `name`, `value`, `label`, `prefCat`, `field`, `userlevel`, `sort`, `showoncms`, `notes`, `comment` FROM cms_database_preference_update WHERE archived = 0 AND showonweb = 'Yes' ORDER BY id ASC");

                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($rec = mysqli_fetch_assoc($result)) {
                                            $records[] = $rec;
                                        }
                                    } 
                                    else 
                                    {
                                        echo "⚠️ No active preference entries found in cms_database_preference_update.<br>";
                                    }



                                    foreach ($records as $rec) {
                                        $name = $rec['name'];
                                        $exists = DB::query("SELECT * FROM preferences WHERE name = '$name'");

                                        if (mysqli_num_rows($exists) == 0) {
                                            if ($action == 'Yes') {
                                                $columns = implode(", ", array_keys($rec));
                                                $values = implode(", ", array_map(function($v) { return "'" . addslashes($v) . "'"; }, array_values($rec)));
                                                $sql = "INSERT INTO preferences ($columns) VALUES ($values)";
                                               // echo "DEBUG SQL: $sql<br>";
                                                $result = DB::query($sql);

                                                if ($result) {
                                                    echo "✅ Added preference: $name<br>";
                                                } else {
                                                    echo "❌ Failed to add preference: $name<br>";
                                                }
                                            } else {
                                                echo "❌ Missing preference: $name [Set action to Yes to apply]<br>";
                                            }
                                        } else {
                                            echo "☑️ $name Already exists: <br>";
                                        }
                                    }
                                }


                                public static function updateSpecificFields($action6,$action6desc) {
                                    $action = $action6;
                                    $tableResults = [];
                                    echo "<h3>Updating Random Table</h3>";
                                    echo "<h5>".$action6desc."</h5>" ;
                                    echo "<h6>Using: cms_database_admin_field_update</h6>";

                                    $customFieldUpdates = [] ;

                                    $result = DBM::query("SELECT `table`, `field`, `type`, `after` FROM cms_database_admin_field_update WHERE archived = 0 AND showonweb = 'Yes' ORDER BY `table`, `sort` ASC");
                                    if ($result && mysqli_num_rows($result) > 0) {
                                         while ($row = mysqli_fetch_assoc($result)) {
                                             $customFieldUpdates[] = [
                                                 'table' => $row['table'],
                                                 'field' => $row['field'],
                                                 'type' => $row['type'],
                                                 'after' => $row['after']
                                             ];
                                         }
                                     } 
                                     else 
                                     {
                                         echo "⚠️ No active records found in cms_database_admin_update.<br>";
                                     }
 
                                
                                    foreach ($customFieldUpdates as $item) {
                                        $table = $item['table'];
                                        $field = $item['field'];
                                        $afterClause = '';

                                        if (isset($item['after'])) {
                                            $afterCheck = DB::query("SHOW COLUMNS FROM `$table` LIKE '{$item['after']}'");
                                            if ($afterCheck && mysqli_num_rows($afterCheck) > 0) {
                                                $afterClause = " AFTER `{$item['after']}`";
                                            } else {
                                                echo "⚠️ AFTER field `{$item['after']}` does not exist in `$table`. `$field` will be added at the end.<br>";
                                                if ($action == 'No') {
                                                    echo "⚠️ ADD `{$item['after']}` to `$table` before `$field` to maintain field order.<br>";
                                                }
                                            }
                                        }
                                
                                        $sql = "ALTER TABLE `$table` ADD `$field` {$item['type']}{$afterClause}";
                                
                                        $exists = DB::query("SHOW COLUMNS FROM `$table` LIKE '$field'");

                                        if (!$exists || mysqli_num_rows($exists) == 0) {
                                            if ($action == 'Yes') {
                                                $ok = DB::query($sql);
                                                echo $ok ? "✅ Added field `$field` to `$table`<br>" : "❌ Failed to add `$field` to `$table`<br>";
                                                $tableResults[] = "Added field `$field` to `$table`";
                                            } else {
                                                echo "❌ Missing `$field` in `$table` [Set Action 6 to Yes to apply]<br>";
                                                $tableResults[] = "Missing field `$field` in `$table`";
                                            }
                                        } else {
                                            echo "☑️  `$field` already exists in `$table`<br>";
                                            $tableResults[] = "Already exists: `$field` in `$table`";
                                        }
                                    }
                                }

                                public static function updateSpecificRows($action7,$action7desc) {
                                    $action = $action7;
                                    echo "<h3>Updating Specific Rows into Tables</h3>";
                                    echo "<h5>".$action7desc."</h5>" ;
                                    echo "<h6>Using: cms_database_admin_row_update</h6>";
                                
                                    $rowInserts = [];
                                    $result = DBM::query("SELECT `table`, `value`, `rowid`, `autoinsert` FROM cms_database_admin_row_update WHERE archived = 0 AND showonweb = 'Yes' ORDER BY id ASC");
                                
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $rowInserts[] = [
                                                'table' => $row['table'],
                                                'value' => $row['value'],
                                                'rowid' => trim($row['rowid']),
                                                'autoinsert' => strtolower(trim($row['autoinsert']))
                                            ];
                                        }
                                    } else {
                                        echo "⚠️ No active records found in cms_database_admin_row_update.<br>";
                                        return;
                                    }
                                
                                    foreach ($rowInserts as $item) {
                                        $table = $item['table'];
                                        $value = $item['value'];
                                        $rowid = $item['rowid'];
                                        $autoinsert = $item['autoinsert'];
                                
                                        // SPECIFIC RECORD CHECK (rowid set)
                                        if (!empty($rowid)) {
                                            $check = DB::query("SELECT * FROM `$table` WHERE `id` = '$rowid'");
                                            if ($check && mysqli_num_rows($check) > 0) {
                                                echo "☑️ Specific Field Exists (id = $rowid) in `$table`<br>";
                                                continue;
                                            }
                                
                                            if ($autoinsert === 'no') {
                                                echo "❌ Specific Field missing (id = $rowid) in `$table` but autoinsert is OFF<br>";
                                                continue;
                                            }
                                
                                            // Inject rowid in place of NULL
                                            $valueModified = preg_replace('/\(\s*NULL\s*,/', "($rowid,", $value, 1);
                                            $sql = "INSERT INTO `$table` $valueModified";
                                
                                            if ($action === 'Yes') {
                                                $ok = DB::query($sql);
                                                echo $ok ? "✅ Inserted Specific Row (id = $rowid) into `$table`<br>" : "❌ Insert Failed into `$table`<br>";
                                            } else {
                                                echo "🔶 Would insert Specific Row (id = $rowid) into `$table` [Set Action 7 to Yes to apply]<br>";
                                            }
                                        }
                                
                                        // GENERAL RECORD CHECK (rowid not set)
                                        else {
                                            $nameMatch = '';
                                            if (preg_match("/\(\s*NULL\s*,\s*'([^']+)'/", $value, $matches)) {
                                                $nameMatch = addslashes($matches[1]);
                                                $check = DB::query("SELECT * FROM `$table` WHERE `name` = '$nameMatch'");
                                                if ($check && mysqli_num_rows($check) > 0) {
                                                    echo "✅ GENERAL Field Exists as name ('$nameMatch') in `$table`<br>";
                                                    continue;
                                                } else {
                                                    echo "🔶 GENERAL Field Does NOT Exist as name ('$nameMatch') in `$table`<br>";
                                                }
                                            }
                                
                                            if ($autoinsert === 'no') {
                                                echo "❌ General Row missing in `$table` but autoinsert is OFF<br>";
                                                continue;
                                            }
                                
                                            $sql = "INSERT INTO `$table` $value";
                                
                                            if ($action === 'Yes') {
                                                $ok = DB::query($sql);
                                                echo $ok ? "✅ Inserted General Row into `$table`<br>" : "❌ Insert Failed into `$table`<br>";
                                            } else {
                                                echo "🔶 Would insert General Row into `$table` [Set Action 7 to Yes to apply]<br>";
                                            }
                                        }
                                    }
                                }
                                

                            }

                                    // If ?fx=1 is in the URL, override actions to Yes
                                    if (isset($_GET['fx']) && $_GET['fx'] == '1') {
                                        echo "<p></p><h4>Datadate <strong>UPDATE</strong> completed<h4>";
                                    }
                                    else
                                    {
                                        echo "<p></p><h4>Database <strong>CHECK</strong> completed <h4>";
                                    }
                                    echo "<p><a href='".$baseURL."/wccms/dashboard.php' class='btn btn-success btn-lg'>Back to Dashboard</a></p>";

                                    echo "<p><a href='".$baseURL."/wccms/admin-database-fields.php' class='btn btn-info btn-lg'>Re-Run Test</a></p>";
                                    echo "<p><a href='".$baseURL."/wccms/admin-database-fields.php?fx=1' class='btn btn-danger btn-lg'>Run Fix</a><br><small class='text-danger'>⚠️ Run with caution – consult support if in any doubt</small></p>";

                            // START FOOTER FIXED STUFF

                            include("include/footer-code.php");
                            include("include-tinymce.php");
                            ?>
                        </div>
                    </div>
                </section>
            </section>
        </section>
    </section>

</body>

</html>

<!-- END admin-database-fields -->