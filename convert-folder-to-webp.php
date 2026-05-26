<!-- START convert-folder-to-webp.php [20250729] -->
<!DOCTYPE html>

<?php
$pageType = 'admin';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('setting/main-top-files.php');

$fx = isset($_GET['fx']) ? $_GET['fx'] : null;
$dryRun = ($fx != '1'); // true = dry run, false = live mode

function scanAndConvertFolder(
    string $baseFolder,
    string $relativePath,
    bool $dryRun = true,
    array &$results = [],
    int $level = 0
) {
    $fullPath = rtrim($baseFolder . '/' . $relativePath, '/');

    if (!is_dir($fullPath)) return;
    $items = scandir($fullPath);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $itemPath = "$fullPath/$item";
        $relativeItemPath = "$relativePath/$item";

        if (is_dir($itemPath)) {
            if (stripos($item, 'archived') !== false) continue;
            scanAndConvertFolder($baseFolder, $relativeItemPath, $dryRun, $results, $level + 1);
        } elseif (preg_match('/\.(jpe?g|png)$/i', $item)) {
            $webpFile = preg_replace('/\.(jpe?g|png)$/i', '.webp', $item);
            $webpPath = "$fullPath/$webpFile";

            if (!file_exists($webpPath)) {
                $sourceMime = mime_content_type($itemPath);
                $originalSize = filesize($itemPath);
                $success = false;
                $convertedSize = 0;

                if (!$dryRun) {
                    switch ($sourceMime) {
                        case 'image/jpeg':
                            $img = imagecreatefromjpeg($itemPath);
                            $success = imagewebp($img, $webpPath, 80);
                            break;
                        case 'image/png':
                            $img = imagecreatefrompng($itemPath);
                            imagepalettetotruecolor($img);
                            imagealphablending($img, true);
                            imagesavealpha($img, true);
                            $success = imagewebp($img, $webpPath, 80);
                            break;
                    }
                    if ($success && isset($img)) {
                        imagedestroy($img);
                        $convertedSize = filesize($webpPath);
                    }
                }

                $results[] = [
                    'path' => $relativeItemPath,
                    'folder' => $relativePath,
                    'originalSize' => $originalSize,
                    'convertedSize' => $convertedSize,
                    'status' => $dryRun ? 'Will Convert' : ($success ? 'Converted' : 'Failed'),
                    'level' => $level
                ];
            }
        }
    }
}

$results = [];
$baseFolder = "/var/www/rxsource.com/web/filestore/images";
scanAndConvertFolder($baseFolder, '', $dryRun, $results);

$totalConverted = count(array_filter($results, fn($r) => $r['status'] === 'Converted'));
$modeLabel = $dryRun ? 'Dry Run (Preview Only)' : 'Live Mode';
$modeColor = $dryRun ? 'orange' : 'green';
?>

<html lang="en">
<head>
    <?php include("include/header-code.php"); ?>
</head>

<body>
    <section id="container">
        <?php
        include("include/header.php");
        include("include/sidebar.php");
        ?>

        <section id="main-content">
            <section class="wrapper site-min-height">

                <h1 style="padding-top: 20px;">WEBP Image Conversion</h1>
                <p><strong>Mode:</strong> <span style="color:<?= $modeColor ?>; font-weight:bold;"> <?= $modeLabel ?> </span></p>
                <div style="font-family:monospace; white-space:pre-line;">
<?php
if (empty($results)) {
    echo "No new images to convert.";
} else {
    foreach ($results as $r) {
        $indent = str_repeat('&nbsp;&nbsp;', $r['level']);
        switch ($r['status']) {
            case 'Converted':
                $statusIcon = '✔️';
                break;
            case 'Will Convert':
                $statusIcon = '🟡';
                break;
            case 'Failed':
                $statusIcon = '❌';
                break;
            default:
                $statusIcon = '❓';
        };
        $convertedSize = $r['convertedSize'] ? number_format($r['convertedSize'] / 1024, 1) . ' KB' : '-';
        $originalSize = number_format($r['originalSize'] / 1024, 1) . ' KB';
        echo "$indent$statusIcon {$r['status']}: /{$r['path']} (Original: $originalSize, New: $convertedSize)\n";
    }
    echo "\n<strong>Total images converted: $totalConverted</strong>\n";
}
?>
                </div>

                <?php if ($dryRun): ?>
                    <div style="margin-top: 30px;">
                        <a href="?fx=0" class="btn btn-success">Run TEST Mode</a>
                        <a href="?fx=1" class="btn btn-danger">Run LIVE Conversion</a>
                    </div>
                <?php endif; ?>

            </section>
        </section>
    </section>
</body>
</html>

<!-- END convert-folder-to-webp.php -->