<?php
if (!function_exists('cms_icon_class')) {
  require_once __DIR__ . '/lib/cms_icons.php';
}

/**
 * Verify a menu table exists and reject unsafe table names.
 */
function cms_menu_table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return false;
  }
  $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
  $stmt->execute([':table' => $table]);
  return (bool) $stmt->fetchColumn();
}

$cmsMenuRows = [];
$cmsMenuError = null;
$cmsMenuUserRole = 1;
$cmsMenuPath = '/';
$cmsMenuQuery = [];

// Resolve the current request path/query for active menu highlighting.
if (!empty($_SERVER['REQUEST_URI'])) {
  $parsed = parse_url($_SERVER['REQUEST_URI']);
  if (!empty($parsed['path'])) {
    $cmsMenuPath = $parsed['path'];
  }
  if (!empty($parsed['query'])) {
    parse_str($parsed['query'], $cmsMenuQuery);
  }
}

/**
 * Determine whether a menu URL should be marked active for the current request.
 */
function cms_menu_is_active(string $url, string $currentPath, array $currentQuery): bool {
  if ($url === '') {
    return false;
  }
  $parsed = parse_url($url);
  if (!empty($parsed['path'])) {
    if (basename($parsed['path']) !== basename($currentPath)) {
      return false;
    }
  }
  if (!empty($parsed['query'])) {
    parse_str($parsed['query'], $targetQuery);
    foreach ($targetQuery as $key => $value) {
      if (!isset($currentQuery[$key]) || (string) $currentQuery[$key] !== (string) $value) {
        return false;
      }
    }
  }
  return true;
}

// Load menu rows the current user can access.
if (isset($CMS_USER['id']) && $DB_OK && $pdo instanceof PDO && cms_menu_table_exists($pdo, 'cms_menu')) {
  try {
    $roleStmt = $pdo->prepare('SELECT userrole FROM cms_users WHERE id = :id LIMIT 1');
    $roleStmt->execute([':id' => (int) $CMS_USER['id']]);
    $roleValue = $roleStmt->fetchColumn();
    if ($roleValue !== false && $roleValue !== null) {
      $cmsMenuUserRole = max(1, (int) $roleValue);
    }
  } catch (PDOException $e) {
    $cmsMenuUserRole = 1;
  }

  try {
    $stmt = $pdo->prepare("SELECT * FROM cms_menu WHERE showonweb = 'Yes' AND archived = 0 AND userrole <= :role ORDER BY section ASC, subsection ASC, id ASC");
    $stmt->execute([':role' => $cmsMenuUserRole]);
    $cmsMenuRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $cmsMenuRows = [];
    $cmsMenuError = 'Menu unavailable';
  }
}

// Group menu rows by section for rendering.
$menuSections = [];
foreach ($cmsMenuRows as $row) {
  $sectionId = (int) ($row['section'] ?? 0);
  if (!isset($menuSections[$sectionId])) {
    $menuSections[$sectionId] = ['rows' => []];
  }
  $menuSections[$sectionId]['rows'][] = $row;
}

ksort($menuSections);

$cmsVersion = trim((string) cms_pref('prefCSMVer', '1.0', 'cms'));
$cmsSidebarLogo = trim((string) cms_pref('prefLogo1', 'witecanvas-logo-s.png', 'cms'));
?>
<aside id="cmsSidebar" class="cms-sidebar">
  <div class="cms-sidebar-inner">
    <?php if (!$cmsMenuRows): ?>
      <div class="cms-menu-section">
        <button class="cms-menu-title" type="button">
          <i class="fa-solid fa-user-gear"></i>
          <span>Admin</span>
          <i class="fa-solid fa-chevron-down ms-auto"></i>
        </button>
        <div class="cms-menu-sub">
          <a href="<?php echo $CMS_BASE_URL; ?>/dashboard.php" class="cms-menu-link">CMS Home</a>
          <a href="<?php echo $baseURL; ?>/index.php" class="cms-menu-link" target="_blank" rel="noopener">Site Home</a>
        </div>
      </div>
    <?php else: ?>
      <nav class="cms-menu">
        <?php foreach ($menuSections as $sectionId => $section): ?>
          <?php
          $rows = $section['rows'];
          usort($rows, static function ($a, $b) {
            $sa = (int) ($a['subsection'] ?? 0);
            $sb = (int) ($b['subsection'] ?? 0);
            if ($sa === $sb) {
              return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $sa <=> $sb;
          });

          $parent = null;
          $children = [];
          foreach ($rows as $row) {
            $sub = (int) ($row['subsection'] ?? 0);
            if ($sub === 0 && !$parent) {
              $parent = $row;
            } else {
              $children[] = $row;
            }
          }

          if (!$parent && $rows) {
            $parent = array_shift($rows);
            $children = $rows;
          }

          if (!$parent) {
            continue;
          }

          $hasDropdown = !empty($children);
          $iconClass = cms_icon_class($pdo, $parent['icon'] ?? null);
          $title = $parent['title'] ?? 'Menu';
          $formId = $parent['form'] ?? null;
          $url = $parent['url'] ?? '';
          $var1 = $parent['var1'] ?? '';
          $target = $parent['target'] ?? '';
          if (!$url && $formId) {
            $url = $CMS_BASE_URL . '/recordViewv5.php?frm=' . urlencode((string) $formId);
          }
          if ($url && $var1) {
            $url .= (str_contains($url, '?') ? '&' : '?') . $var1;
          }
          $isActive = $url ? cms_menu_is_active($url, $cmsMenuPath, $cmsMenuQuery) : false;
          ?>
          <?php if ($hasDropdown): ?>
            <div class="cms-menu-group">
              <?php
              $childActive = false;
              foreach ($children as $item) {
                $childUrl = $item['url'] ?? '';
                $childForm = $item['form'] ?? null;
                $childVar = $item['var1'] ?? '';
                if (!$childUrl && $childForm) {
                  $childUrl = $CMS_BASE_URL . '/recordViewv5.php?frm=' . urlencode((string) $childForm);
                }
                if ($childUrl && $childVar) {
                  $childUrl .= (str_contains($childUrl, '?') ? '&' : '?') . $childVar;
                }
                if ($childUrl && cms_menu_is_active($childUrl, $cmsMenuPath, $cmsMenuQuery)) {
                  $childActive = true;
                  break;
                }
              }
              $expanded = $childActive ? 'true' : 'false';
              ?>
              <button class="cms-menu-item cms-menu-toggle <?php echo $childActive ? 'active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#cms-menu-<?php echo $sectionId; ?>" aria-expanded="<?php echo $expanded; ?>">
                <?php if ($iconClass): ?>
                  <i class="<?php echo cms_h($iconClass); ?>"></i>
                <?php endif; ?>
                <span><?php echo cms_h($title); ?></span>
                <i class="fa-solid fa-chevron-down ms-auto"></i>
              </button>
              <div class="collapse cms-menu-sub <?php echo $childActive ? 'show' : ''; ?>" id="cms-menu-<?php echo $sectionId; ?>">
                <?php foreach ($children as $item): ?>
                  <?php
                  $childTitle = $item['title'] ?? 'Link';
                  $childUrl = $item['url'] ?? '';
                  $childForm = $item['form'] ?? null;
                  $childVar = $item['var1'] ?? '';
                  $childTarget = $item['target'] ?? '';
                  if (!$childUrl && $childForm) {
                    $childUrl = $CMS_BASE_URL . '/recordViewv5.php?frm=' . urlencode((string) $childForm);
                  }
                  if ($childUrl && $childVar) {
                    $childUrl .= (str_contains($childUrl, '?') ? '&' : '?') . $childVar;
                  }
                  $childIsActive = $childUrl ? cms_menu_is_active($childUrl, $cmsMenuPath, $cmsMenuQuery) : false;
                  ?>
                  <a class="cms-menu-link <?php echo $childIsActive ? 'active' : ''; ?>" href="<?php echo cms_h($childUrl ?: '#'); ?>"<?php echo $childTarget ? ' target="' . cms_h($childTarget) . '"' : ''; ?>>
                    <?php echo cms_h($childTitle); ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <a class="cms-menu-item <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo cms_h($url ?: '#'); ?>"<?php echo $target ? ' target="' . cms_h($target) . '"' : ''; ?>>
              <?php if ($iconClass): ?>
                <i class="<?php echo cms_h($iconClass); ?>"></i>
              <?php endif; ?>
              <span><?php echo cms_h($title); ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <div class="cms-sidebar-meta">
      <div>Site: <?php echo cms_h($CMS_SITE_NAME); ?></div>
      <div>User: <?php echo cms_h($CMS_USER['display_name'] ?? 'Guest'); ?></div>
      <div>Username: <?php echo cms_h($CMS_USER['email'] ?? ''); ?></div>
      <div>Role: <?php echo cms_h((string) $cmsMenuUserRole); ?></div>
      <div>CMS Ver: <?php echo cms_h($cmsVersion !== '' ? $cmsVersion : '1.0'); ?></div>
      <div>User IP: <?php echo cms_h($_SERVER['REMOTE_ADDR'] ?? ''); ?></div>
    </div>

    <div class="cms-sidebar-footer">
      <img src="<?php echo $baseURL; ?>/filestore/images/logos/<?php echo cms_h($cmsSidebarLogo !== '' ? $cmsSidebarLogo : 'witecanvas-logo-s.png'); ?>" alt="wITeCanvas" class="cms-sidebar-logo">
      <div>© wITeCanvas 2020 - 2026</div>
      <div>Ver: <?php echo cms_h($cmsVersion !== '' ? $cmsVersion : '1.0'); ?></div>
    </div>
  </div>
</aside>
