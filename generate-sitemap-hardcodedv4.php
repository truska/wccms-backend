<!-- START generate-sitemapv4 -->
<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
error_reporting(1);

include('setting/main-top-files.php'); // Added by salva TDR | 7.12.2022

?>
<!-- TruskaCMS ver 4.0.0 -->
<?php


// Include if needed
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
<!-- Remove Script if not needed -->
   <script>
      $(document).ready(function() {
         var tblname = "<?php echo $tbName ?>";
         var issortable = "<?php echo $issortable;  ?>";
         var sortcol = "<?php echo $sortcol; ?>";

         <?php
         if (count($toast) > 0) {
            echo "const toast = new Toast('" . $toast[0]['message'] . "', '" . $toast[0]['type'] . "');";
            echo "toast.show();";
         }
         ?>

         if (issortable == "Yes") {
            $("#sortable").sortable({
               stop: function(event, ui) {
                  // alert($(this).sortable('serialize'));
                  //alert(tblname);
                  var itemOrder = $('#sortable').sortable("toArray");
                  var list = "";

                  for (var i = 0; i < itemOrder.length; i++) {
                     data = "" + i + ":" + itemOrder[i] + "+";
                     console.log("Position: " + i + " ID: " + itemOrder[i]);
                     list += data;
                  }

                  console.log(data);

                  $.ajax({
                     url: 'sorttables.php',
                     type: 'post',
                     data: 'data=' + list + '&tblname=' + tblname + '&sortcol=' + sortcol,
                     success: function(msg) {
                        alert(msg)
                     }
                  })
               }
            })
         }
      })
   </script>
</head>

<body>
   <!-- Fixed navbar -->
   <?php
   include("include/header.php"); // Added by salva TDR | 2.12.2022
   include("include/sidebar.php");

   // GET DATA funtions
   ?>


   <style>
    /* Local Styles as needed */
   </style>

   <section id="main-content">
      <section class="wrapper site-min-height">
<!-- Main page Content here-->
<!-- ---------------------------------------------------------------------  -->
 <?php



// Function to generate the sitemap XML
define('SITEMAP_FILE', $_SERVER['DOCUMENT_ROOT'] . '/sitemap-dev.xml');

function generateSitemap() {
    global $conn;
    
    // XML header
    $xmlContent = "<?xml version='1.0' encoding='UTF-8'?>\n";
    $xmlContent .= "<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>\n";
    
    // Query to get pages that should be included
    $query = "SELECT slug, googlesitemappriority FROM pages WHERE googlesitemap = 'Yes' AND showonweb = 'Yes' AND archived = 0";
    $result = mysqli_query($conn, $query);
    
    $lastmod = date('Y-m-d'); // Default last modified date
    $pageCount = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/' . htmlspecialchars($row['slug']);
        $priority = !empty($row['googlesitemappriority']) ? $row['googlesitemappriority'] : '0.5';
        
        $xmlContent .= "  <url>\n";
        $xmlContent .= "    <loc>{$url}</loc>\n";
        $xmlContent .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xmlContent .= "    <priority>{$priority}</priority>\n";
        $xmlContent .= "  </url>\n";
        
        $pageCount++;
    }
    
    $xmlContent .= "</urlset>";
    
    // Write to file
    file_put_contents(SITEMAP_FILE, $xmlContent);
    
    return $pageCount;
}

if (isset($_POST['generate_sitemap'])) {
    $pagesWritten = generateSitemap();
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            let modal = `<div class='modal fade' id='sitemapModal' tabindex='-1' role='dialog'>
                <div class='modal-dialog' role='document'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h5 class='modal-title'>Sitemap Generation Complete</h5>
                            <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        </div>
                        <div class='modal-body'>
                            <p>Sitemap generated successfully!</p>
                            <p><strong>Pages Written:</strong> ${pagesWritten}</p>
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-primary' onclick=\"window.location.href='dashboard.php'\">Return to Dashboard</button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modal);
            $('#sitemapModal').modal('show');
        });
    </script>";
}
?>

<section id="main-content">
    <section class="wrapper site-min-height">
        <h3>Sitemap Generator</h3>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Page URL</th>
                    <th>Last Modified</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT slug, googlesitemappriority FROM pages WHERE googlesitemap = 'Yes' AND showonweb = 'Yes' AND archived = 0";
                $result = mysqli_query($conn, $query);
                
                $lastmod = date('Y-m-d'); // Default last modified date
                
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/' . htmlspecialchars($row['slug']);
                        $priority = !empty($row['googlesitemappriority']) ? $row['googlesitemappriority'] : '0.5';
                        echo "<tr><td>{$url}</td><td>{$lastmod}</td><td>{$priority}</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No records found</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <form method="post">
            <button type="submit" name="generate_sitemap" class="btn btn-primary">Generate Sitemap</button>
        </form>
    </section>
</section>



   <?php
   include("include/footer.php");
   include("include/footer-code.php");
   ?>
</body>

<!-- Bootstrap core JavaScript-->
<!-- Remove SCRIPT if not needed -->
<script>
   function hideColumn() {
      var i = 0;
      $('#blogTable thead tr:eq(0) th').each(function(item) {
         if ($(this).is(':visible')) {
            i = i + 1;
         }
      });

      $('#salesTable thead tr:eq(1) td').each(function(item) {
         if (i <=
            item) {
            $(this).hide();
         }
      });
   }

   $(document).ready(function() {
      var table = $('#blogTable').DataTable({
         lengthChange: true,
         lengthMenu: [
            [10, 25, 50, 100, 200, 500],
            [10, 25, 50, 100, 200, 500]
         ],
         responsive: true,
         ordering: true,
         "order": [
            [0, "desc"]
         ],
         orderCellsTop: true,
         columnDefs: [{
               targets: <?php echo $colCount; ?>,
               searchable: false,
               orderable: false
            },
            {
               targets: 1,
               className: 'noVis'
            }
         ],
         dom: 'Bfrtip',
         buttons: [
            'copy', 'excel', 'pdf', 'print', 'csv'
         ],
         pageLength: <?php echo ($user["recordstoshow"] > 0 ? $user['recordstoshow'] : 15); ?>, // Edited by salva TDR | 9.12.2022
         initComplete: function() {
            this.api().columns([<?php echo $selectCount; ?>]).every(function() { // SELECT
               var column = this;
               var select = $('<select id="seect' + column[0][0] + '" class="select-datatable form-control"><option value="">All</option></select>')
                  .appendTo($('thead tr:eq(1) td').eq(this.index()))
                  .on('change', function() {
                     var val = $.fn.dataTable.util.escapeRegex(
                        $(this).val()
                     );

                     column
                        .search(val ? '^' + val + '$' : '', true, false)
                        .draw();
                  });

               column.data().unique().sort().each(function(d, j) {
                  select.append('<option value="' + d + '">' + d + '</option>');
                  if (column[0][0] == "10") {
                     $('#defaultYYMM').append('<option value="' + d + '">' + d + '</option>');
                  }
               });
            });

            this.api().columns([<?php echo $searchCount; ?>]).every(function() { // SEARCH
               var column = this;
               var select = $('<input class="input-datatable form-control" type="text" placeholder="Search" />')
                  .appendTo($('thead tr:eq(1) td').eq(this.index())).on('keyup change', function() {
                     if (column.search() !== this.value) {
                        column
                           .search(this.value)
                           .draw();
                     }
                  });
            });
         },
      });

      table.buttons().container().appendTo('#blogTable_wrapper .col-md-3:eq(0)');

      hideColumn();
   });

   $(window).resize(function() {
      hideColumn();
   });
</script>

</html>

<!-- END generate-sitemapv4 -->