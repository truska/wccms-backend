<!-- START generate-sitemapv4 -->
<?php
   // Turn on error reporting
   error_reporting(1);
   error_log("Into SiteMap Generator");
   include('setting/main-top-files.php');

   $toast = [];

   //$ping = 'No' ; // Set to 'Yes' to notify Google after generating sitemap
   $ping = $prefs['prefSitemapPing']  ; // Set from preferences
   $sitemapURLPath = '/sitemap.xml';
   // Define the sitemap file
   define('SITEMAP_FILE', $_SERVER['DOCUMENT_ROOT'] . $sitemapURLPath);

   function generateSitemap($writeToFile = false) {
      global $conn, $ping, $sitemapURLPath;
      error_log("Into generate 'generateSitemap' function");
      global $conn;

      $xmlContent = "<?xml version='1.0' encoding='UTF-8'?>\n";
      $xmlContent .= "<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>\n";

      $pageCount = 0;
      $lastmod = date('Y-m-d');
      $logQueries = [];
      $sitemapEntries = [];

      $sitemapQuery = "SELECT `table`, `where`, `path`, `showid` FROM cms_sitemap WHERE showonweb = 'Yes' AND archived = 0 ORDER BY sort ASC";
      error_log("Site map overview sql: " . $sitemapQuery);
      $sitemapResult = mysqli_query($conn, $sitemapQuery);

      while ($sitemapRow = mysqli_fetch_assoc($sitemapResult)) {
         $table = $sitemapRow['table'];
         $path = !empty($sitemapRow['path']) ? $sitemapRow['path'] . '/' : '';
         $whereClause = !empty($sitemapRow['where']) ? ' AND ' . $sitemapRow['where'] : '';
         $showId = ($sitemapRow['showid'] === 'Yes');

         $query = "SELECT id, slug, modified FROM $table WHERE showonweb = 'Yes' AND archived = 0" . $whereClause;
         error_log("Site map section sql: " . $query);
         $logQueries[] = $query;
         $result = mysqli_query($conn, $query);
         

         while ($row = mysqli_fetch_assoc($result)) {
               $lastmod = $row["modified"] ;
               $slug = mysqli_real_escape_string($conn, $row['slug']);
               $priorityQuery = "SELECT googlesitemappriority FROM pages WHERE slug = '$slug' AND googlesitemap = 'Yes' LIMIT 1";
               error_log("Site map priority sql: " . $query);
               $priorityResult = mysqli_query($conn, $priorityQuery);
               $priority = '0.9';
               if ($priorityRow = mysqli_fetch_assoc($priorityResult)) {
                  $priority = !empty($priorityRow['googlesitemappriority']) ? $priorityRow['googlesitemappriority'] : '0.5';
               }

               $idPart = ($showId) ? $row['id'] . '/' : '';
               $url = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $path . $idPart . htmlspecialchars($slug);

               $xmlContent .= "  <url>\n";
               $xmlContent .= "    <loc>{$url}</loc>\n";
               $xmlContent .= "    <lastmod>{$lastmod}</lastmod>\n";
               $xmlContent .= "    <priority>{$priority}</priority>\n";
               $xmlContent .= "  </url>\n";

               $sitemapEntries[] = [
                  'url' => $url,
                  'id' => ($showId) ? $row['id'] : '',
                  'lastmod' => $lastmod,
                  'priority' => $priority
               ];

               $pageCount++;
         }
      }

      $xmlContent .= "</urlset>";

      if ($writeToFile) {
         $writeSuccess = @file_put_contents(SITEMAP_FILE, $xmlContent);
         if ($writeSuccess === false) {
               error_log("❌ Failed to write to sitemap file at " . SITEMAP_FILE);
         } else {
               $logtable = 'N/A';
               $action = "Updated Sitemap";
               $sqlproductlog = implode("; ", $logQueries);
               $notes = "Site map updated - {$pageCount} entries";
               savelog('', $action, $sqlproductlog, $logtable, 'SUCCESS', $notes, '');

               if ($ping === 'Yes') {
                  $sitemapURL = 'https://' . $_SERVER['HTTP_HOST'] . $sitemapURLPath;

                  // Ping Google
                  $googlePing = 'https://www.google.com/ping?sitemap=' . urlencode($sitemapURL);
                  $googleResponse = @file_get_contents($googlePing);
                  error_log("📤 Google Ping URL: $googlePing");
                  error_log("📥 Google Response: $googleResponse");

                  // Ping Bing
                  $bingPing = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemapURL);
                  $bingResponse = @file_get_contents($bingPing);
                  error_log("📤 Bing Ping URL: $bingPing");
                  error_log("📥 Bing Response: $bingResponse");

                  // Ping Yandex
                  $yandexPing = 'https://webmaster.yandex.com/ping?sitemap=' . urlencode($sitemapURL);
                  $yandexResponse = @file_get_contents($yandexPing);
                  error_log("📤 Yandex Ping URL: $yandexPing");
                  error_log("📥 Yandex Response: $yandexResponse");
               }
         }
      }

      return $sitemapEntries;
   }

   $sitemapEntries = generateSitemap(false);
   $pageCount = count($sitemapEntries);
   $fileMissing = !file_exists(SITEMAP_FILE);

   if (isset($_POST['generate_sitemap'])) {
      $sitemapEntries = generateSitemap(true);
      $pageCount = count($sitemapEntries);
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
                              <p><strong>Pages Written:</strong> {$pageCount}</p>
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

<!DOCTYPE html>
<html lang="en">
<head>
   <?php include("include/header-code.php"); ?>
</head>
<body>
    <?php include("include/header.php"); ?>
    <?php include("include/sidebar.php"); ?>

   <section id="main-content">
      <section class="wrapper site-min-height">

         <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Sitemap Generator</h3>
            <p>Sitemap file name: <?php echo $sitemapURLPath ;?>
            <br>Ping Sitemaps on update:  <?php echo $prefs["prefSitemapPing"];?>
            </p>
            <?php if (!$fileMissing): ?>
               <a href="<?php echo $baseURL."/".$sitemapURLPath;?>" target="_blank" class="btn btn-secondary">View Current Sitemap</a>
            <?php endif; ?>
         </div>

         <?php if ($fileMissing): ?>
               <div class="alert alert-warning">⚠️ <?php echo $sitemapURLPath ; ?> file does not exist. The entries below are proposed but not yet saved.</div>
         <?php endif; ?>

         <table class="table table-striped">
               <thead>
                  <tr>
                     <th>Page URL</th>
                     <th>Last Modified</th>
                     <th>Priority</th>
                  </tr>
               </thead>
               <tbody>
                  <?php foreach ($sitemapEntries as $entry): ?>
                     <tr>
                           <td><?php echo $entry['url']; ?></td>
                           <td><?php echo $entry['lastmod']; ?></td>
                           <td><?php echo $entry['priority']; ?></td>
                     </tr>
                  <?php endforeach; ?>
               </tbody>
         </table>

         <form method="post">
               <button type="submit" name="generate_sitemap" class="btn btn-primary">Generate Sitemap</button>
         </form>

      </section>
   </section>

    <?php include("include/footer.php"); ?>
    <?php include("include/footer-code.php"); ?>
</body>
</html>
<!-- END generate-sitemapv4 -->
