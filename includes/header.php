<!-- CMS top navigation bar -->
<?php
$cmsHeaderLogo = trim((string) cms_pref('prefLogo', 'witecanvas-logo-l.png', 'cms'));
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
        <img src="<?php echo $baseURL; ?>/filestore/images/logos/witecanvas-logo-s.png" alt="User" class="cms-user-avatar">
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
