<?php
// fetch_column_names.php

// Include database connection file if necessary
include("include/dbcon.php");

if(isset($_GET['table_name'])) {
    $table_name = $_GET['table_name'];

    // Query to fetch column names for the specified table
    $sql = "SHOW COLUMNS FROM $table_name";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $columns = array();
        // Fetch column names
        while($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        // Send column names as JSON response
        echo json_encode($columns);
    } else {
        // No columns found
        echo json_encode(array("error" => "No columns found"));
    }
} else {
    // Table name parameter not provided
    echo json_encode(array("error" => "Table name not provided"));
}
?> 