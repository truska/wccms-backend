<!-- CMS top navigation bar -->
<?php
$cmsHeaderLogo = trim((string) cms_pref('prefLogo', 'witecanvas-logo-l.png', 'cms'));

if (!function_exists('cms_header_avatar_file')) {
  /**
   * Resolve the CMS header avatar filename from the logged-in user record.
   */
  function cms_header_avatar_file(array $cmsUser): string {
    $image = trim((string) ($cmsUser['image'] ?? ''));
    $baseDir = __DIR__ . '/../../filestore/images/wccms/';

    if ($image !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $image) && is_file($baseDir . $image)) {
      return $image;
    }

    $gender = strtolower(trim((string) ($cmsUser['gender'] ?? '')));
    if ($gender === 'female') {
      return 'female-user-icon.png';
    }

    return 'male-user-icon.png';
  }
}

if (
  isset($CMS_USER['id'], $pdo, $DB_OK) &&
  $DB_OK &&
  $pdo instanceof PDO &&
  (!array_key_exists('image', $CMS_USER) || !array_key_exists('gender', $CMS_USER))
) {
  try {
    $avatarStmt = $pdo->prepare('SELECT image, gender FROM cms_users WHERE id = :id LIMIT 1');
    $avatarStmt->execute([':id' => (int) $CMS_USER['id']]);
    $avatarRow = $avatarStmt->fetch(PDO::FETCH_ASSOC);
    if ($avatarRow) {
      $CMS_USER['image'] = trim((string) ($avatarRow['image'] ?? ''));
      $CMS_USER['gender'] = trim((string) ($avatarRow['gender'] ?? ''));
      $_SESSION['cms_user']['image'] = $CMS_USER['image'];
      $_SESSION['cms_user']['gender'] = $CMS_USER['gender'];
    }
  } catch (PDOException $e) {
    // Ignore avatar lookup failures and use fallback icons.
  }
}

$cmsUserAvatar = cms_header_avatar_file($CMS_USER ?? []);
?>
<header class="cms-topbar">
  <div class="cms-topbar-left">
    <button class="cms-burger" type="button" aria-label="Toggle menu" aria-controls="cmsSidebar" aria-expanded="true">
      <i class="fa-solid fa-bars"></i>
    </button>
    <img src="<?php echo $baseURL; ?>/filestore/images/logos/<?php echo cms_h($cmsHeaderLogo !== '' ? $cmsHeaderLogo : 'witecanvas-logo-l.png'); ?>" alt="wITeCanvas" class="cms-logo">
  </div>
  <div class="cms-topbar-center">
    <span class="cms-site-title"><?php echo cms_h($CMS_SITE_NAME); ?></span>
  </div>
  <div class="cms-topbar-right">
    <div class="dropdown">
      <button class="btn cms-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="<?php echo $baseURL; ?>/filestore/images/wccms/<?php echo cms_h($cmsUserAvatar); ?>" alt="User" class="cms-user-avatar">
        <span><?php echo cms_h($CMS_USER['display_name'] ?? 'Guest'); ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?php echo $CMS_BASE_URL; ?>/dashboard.php">CMS Home</a></li>
        <li><a class="dropdown-item" href="<?php echo $baseURL; ?>/index.php" target="_blank" rel="noopener">Site Home</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?php echo $CMS_BASE_URL; ?>/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</header>
