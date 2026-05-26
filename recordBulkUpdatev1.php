<!-- recordBulkupdatev3 -->
<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
error_reporting(1);

include('setting/main-top-files.php'); // Added by salva TDR | 7.12.2022

?>
<!-- TruskaCMS ver 3.0.0 -->



<?php



$url = "recordEditv".$prefs['prefCMSVer'].".php";


$toast = [];

?>

<!DOCTYPE html>
<html lang="en">
<!-- start html tag -->

<head>
   <link href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet">
   <link href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.bootstrap4.min.css" rel="stylesheet">
   <link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap4.min.css" rel="stylesheet">


   <?php
   include("include/header-code.php");

   ?>


</head>

<body>
   <!-- Fixed navbar -->
   <?php
    include("include/header.php"); // Added by salva TDR | 2.12.2022
    include("include/sidebar.php");

    include("fetch_column_names.php");
   ?>

<style>
        /* Style for the modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        /* Modal content */
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            text-align: center;
        }

        /* Close button */
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .form-group {
            display: block;
            width: 100%;
            margin-bottom: 10px;
        }

        .form-group label {
         display: block;
        }

        .form-group input,
        .form-group select {
            width: 25%;
        }
        .form-group .comment {
            font-size: 12px;
            color: #666;
        }
    </style>


   <section id="main-content">
      <section class="wrapper site-min-height">

         <!-- page start-->
         <section class="card" style="width:100%;margin-left: -10px">
            <div class="row">
               <div class="card-body">
                  <!-- <div class="col-md-1 hidden-sm hidden-xs"></div>  -->
                  <div class="col-sm-12 col-md-10 col-lg-10" style="margin-top:20px;">
                     <h2>BULK UPDATE DATABASE <strong></h2>
                     <div class="alert alert-danger" role="alert">
                     

                     <h4 class="alert-heading">WARNING!</h4>
                       <p> Use with Caution - changes made cannot be undone!</p>
                    </div>
                  </div>


                  <div class="col-sm-12 col-md-12 col-lg-12" style="margin-top:20px; overflow-x: scroll;">

                        <h3>Body of page here</h3>


                        <form id="updateForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="table_name_select">Table Name:</label>
                                <select class="form-control" name="table_name" id="table_name_select" onchange="populateColumns()">
                                <option value='0'>Please Select Table</option>
                                    <?php
                                    // Fetch table names from 'cms_table' where `showonweb` is 'Yes'
                                    $sql = "SELECT name FROM cms_table WHERE showonweb = 'Yes'";
                                    $result = $conn->query($sql);

                                    // Check if there are any rows returned
                                    if ($result->num_rows > 0) {
                                        // Output data of each row
                                        while($row = $result->fetch_assoc()) {
                                            // Output option tag for each table name
                                            echo "<option value='" . $row["name"] . "'>" . $row["name"] . "</option>";
                                        }
                                    } else {
                                        echo "<option value=''>No tables found</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="where_clause">WHERE Clause:</label>
                                <input class="form-control" type="text" name="where_clause">
                                <div class="comment">Add a condition to filter records (optional)</div>
                            </div>

                            <div class="form-group">
                                <label for="column1_select">Column 1:</label>
                                <select class="form-control" name="column1" id="column1_select">
                                    <option value="">Select Column</option>
                                </select>
                                <div class="comment">Choose the column to update</div>
                            </div>

                            <div class="form-group">
                                <label for="value1">Value 1:</label>
                                <input class="form-control" type="text" name="value1">
                                <div class="comment">Enter the new value for Column 1</div>
                            </div>

                            <div class="form-group">
                                <label for="column2_select">Column 2:</label>
                                <select class="form-control" name="column2" id="column2_select">
                                    <option value="">Select Column</option>
                                </select>
                                <div class="comment">Choose the column to update (optional)</div>
                            </div>

                            <div class="form-group">
                                <label for="value2">Value 2:</label>
                                <input class="form-control" type="text" name="value2">
                                <div class="comment">Enter the new value for Column 2 (optional)</div>
                            </div>

                            <div class="form-group">
                                <label for="column3_select">Column 3:</label>
                                <select class="form-control" name="column3" id="column3_select">
                                    <option value="">Select Column</option>
                                </select>
                                <div class="comment">Choose the column to update (optional)</div>
                            </div>

                            <div class="form-group">
                                <label for="value3">Value 3:</label>
                                <input class="form-control" type="text" name="value3">
                                <div class="comment">Enter the new value for Column 3 (optional)</div>
                            </div>

                            <button type="button" class="btn btn-primary" id="submitButton" onclick="openModal()">Update</button>
                        </form>



<script>
    function populateColumns() {
        var tableName = document.getElementById("table_name_select").value;
        var column1Select = document.getElementById("column1_select");
        var column2Select = document.getElementById("column2_select");
        var column3Select = document.getElementById("column3_select");

        // Clear existing options
        column1Select.innerHTML = '<option value="">Select Column</option>';
        column2Select.innerHTML = '<option value="">Select Column</option>';
        column3Select.innerHTML = '<option value="">Select Column</option>';

        // Fetch column names from the selected table
/*
            fetch(`fetch_column_names.php?table_name=${tableName}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); // Parse response as JSON
                })
                .then(data => {
                    // Process JSON data
                })
                .catch(error => console.error('Error fetching column names:', error));
*/


        fetch(`fetch_column_names.php?table_name=${tableName}`)
            .then(response => response.json())
            .then(data => {
                console.log(data); // Debugging statement
                // Populate column select options
                data.forEach(column => {
                    column1Select.innerHTML += `<option value="${column}">${column}</option>`;
                    column2Select.innerHTML += `<option value="${column}">${column}</option>`;
                    column3Select.innerHTML += `<option value="${column}">${column}</option>`;
                });
            })
            .catch(error => console.error('Error fetching column names:', error));
    }

    // Populate columns when the page loads
    populateColumns();
</script>



<!-- The Modal -->
<div id="myModal" class="modal">
    <!-- Modal content -->
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h4>Confirm the changes:</h4>
        <div id="confirmation"></div>
        <h4>SQL Query:</h4>
        <p id="sqlQuery"></p>
        <button onclick="executeUpdate()">Confirm</button>
        <button onclick="closeModal()">Cancel</button>
    </div>
</div>

<script>
    function openModal() {
        var form = document.getElementById("updateForm");
        var modal = document.getElementById("myModal");
        var confirmation = document.getElementById("confirmation");
        var formData = new FormData(form);
        var entries = formData.entries();
        var confirmationText = "";
        var sqlQuery = "UPDATE `" + formData.get("table_name") + "` SET ";

        // Constructing the SET part of the SQL query
        var setValues = [];
        if (formData.get("column1") && formData.get("value1")) {
            setValues.push("`" + formData.get("column1") + "` = '" + formData.get("value1") + "'");
        }
        if (formData.get("column2") && formData.get("value2")) {
            setValues.push("`" + formData.get("column2") + "` = '" + formData.get("value2") + "'");
        }
        if (formData.get("column3") && formData.get("value3")) {
            setValues.push("`" + formData.get("column3") + "` = '" + formData.get("value3") + "'");
        }
        sqlQuery += setValues.join(", ");

        // Constructing the WHERE part of the SQL query
        sqlQuery += " WHERE " + formData.get("where_clause");

        // Constructing the confirmation message
        for (const pair of entries) {
            confirmationText += pair[0] + ": " + pair[1] + "<br>";
        }

        // Displaying confirmation message and SQL query
        confirmation.innerHTML = confirmationText;
        document.getElementById("sqlQuery").innerText = sqlQuery;
        modal.style.display = "block";
    }

    function closeModal() {
        var modal = document.getElementById("myModal");
        modal.style.display = "none";
    }

    function executeUpdate() {
        document.getElementById("updateForm").submit();
    }
</script>


                        <?php

    // Include database connection file
    include("include/header.php");

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {


    // Retrieve form data
    $table_name = $_POST['table_name'];
    $where_clause = $_POST['where_clause'];
    $column1 = $_POST['column1'];
    $value1 = $_POST['value1'];
    $column2 = $_POST['column2'];
    $value2 = $_POST['value2'];
    $column3 = $_POST['column3'];
    $value3 = $_POST['value3'];

    // Validate inputs (you might want to add more validation)
    if (empty($table_name) || empty($where_clause) || empty($column1) || empty($value1)) {
        echo "Please fill in all required fields.";
    } else {
        // Construct and execute SQL query
        $sql = "UPDATE `$table_name` SET `$column1` = '$value1'";
        if (!empty($column2) && !empty($value2)) {
            $sql .= ", `$column2` = '$value2'";
        }
        if (!empty($column3) && !empty($value3)) {
            $sql .= ", `$column3` = '$value3'";
        }
        $sql .= " WHERE $where_clause";

        $logtable = $table_name;
        $action = "Bulk Update" ;
        $sqlproductlog = $sql;

// Check if the database connection is established
if ($conn) {
    // Execute the SQL query
    if ($conn->query($sql) === TRUE) {
        echo "<h3 style='color:green; padding-top:20px;'>Table updated successfully.</h3>";

        // Log successful action
        $notes = 'Bulk update completed';
        savelogV2('', $action, $sqlproductlog, $logtable, 'SUCCESS', $notes,  $recordnumber);
    } 
    else 
    {
        echo "<h3 style='color:red; padding-top:20px;'>Error updating table: " . $conn->error . "</h3>";

        // Log failed action
        $notes = 'Bulk update failed';
        savelogV2('', $action, $sqlproductlog, $logtable, 'FAIL', $notes,  $recordnumber);
    }
} else {
    echo "<h3 style='color:red; padding-top:20px;'>Database connection error.</h3>";
}

        // Close connection
      //  $conn->close();
    }
}
?>

                  </div>
               </div>
            </div>
         </section>
         <div>            
            <h6>This page is to allow the bulk updating of data</h6>
            <p>It will allow you to update up to 3 fields in a single table based on 1 WHERE criteria.<p>
                <p>The where clause must be written in long hand uisg MySQL e.g.</p>
                <code>section = 4 AND manuf = 3</code>
                <p>If unsure contact your admin - changes made form this form <span style='color:red;'>CANNOT</span> be undone</p>
         </div>
      </section>
   </section>

   <?php
   include("include/footer.php");
   echo "</div>";
   include("include/footer-code.php");
   ?>
</body>

<!-- Bootstrap core JavaScript-->







</html>

<!-- END recordBulkupdatev1 -->