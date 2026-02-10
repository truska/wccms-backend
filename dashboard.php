<?php
require_once __DIR__ . '/includes/boot.php';
// Protect the CMS dashboard from anonymous access.
cms_require_login();
include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <h1 class="h3 mb-0">Welcome to your Web Site Management</h1>
    </div>

    <!-- Tabbed navigation for dashboard sections. -->
    <ul class="nav nav-tabs cms-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="welcome-tab" data-bs-toggle="tab" data-bs-target="#welcome" type="button" role="tab">Welcome</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">Stats</button>
      </li>
    </ul>

    <div class="tab-content pt-4">
      <div class="tab-pane fade show active" id="welcome" role="tabpanel" aria-labelledby="welcome-tab">
        <div class="cms-card">
          <h2 class="h4 mb-3"><?php echo cms_h($CMS_NAME); ?></h2>
          <dl class="cms-kv">
            <dt>Site Name</dt>
            <dd><?php echo cms_h($CMS_SITE_NAME); ?></dd>
            <dt>Site URL</dt>
            <dd><?php echo cms_h($CMS_SITE_URL); ?></dd>
            <dt>User Name</dt>
            <dd><?php echo cms_h($CMS_USER['display_name'] ?? ''); ?></dd>
            <dt>User Level</dt>
            <dd><?php echo cms_h($CMS_USER['role'] ?? ''); ?></dd>
          </dl>
        </div>
      </div>
      <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
        <div class="cms-card">
          <p class="mb-0">Stats will populate here once wired to the database.</p>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
