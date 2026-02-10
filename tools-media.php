<?php
require_once __DIR__ . '/includes/boot.php';
cms_require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $root = trim((string) ($_POST['root'] ?? ''));
  $root = preg_replace('/[^a-zA-Z0-9_-]/', '', $root);
  $sizes = ['original', 'master', 'lg', 'md', 'sm', 'xs'];

  if ($root === '') {
    $error = 'Please provide a folder name.';
  } else {
    $baseDir = __DIR__ . '/../filestore/images/' . $root;
    $created = [];
    if (!is_dir($baseDir)) {
      if (!mkdir($baseDir, 0777, true)) {
        $error = 'Failed to create base folder.';
      } else {
        $created[] = $baseDir;
      }
    }

    if (!$error) {
      foreach ($sizes as $size) {
        $path = $baseDir . '/' . $size;
        if (!is_dir($path)) {
          if (mkdir($path, 0777, true)) {
            $created[] = $path;
          } else {
            $error = 'Failed to create ' . $size . ' folder.';
            break;
          }
        }
      }
    }

    if (!$error) {
      chmod($baseDir, 0777);
      foreach ($sizes as $size) {
        $path = $baseDir . '/' . $size;
        if (is_dir($path)) {
          chmod($path, 0777);
        }
      }
      $message = $created ? 'Created: ' . implode(', ', array_map('basename', $created)) : 'All folders already exist.';
    }
  }
}

include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Media Tools</h1>
        <p class="text-muted mb-0">Create image folder structures for uploads.</p>
      </div>
    </div>

    <div class="cms-card">
      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo cms_h($message); ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo cms_h($error); ?></div>
      <?php endif; ?>

      <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-media.php">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label" for="root">Top Level Folder</label>
            <input class="form-control" type="text" id="root" name="root" placeholder="e.g. products" required>
            <div class="form-text">Creates `original`, `master`, `lg`, `md`, `sm`, `xs` under `filestore/images/{name}`.</div>
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit">Create Folders</button>
          </div>
        </div>
      </form>
    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
