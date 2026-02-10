<!-- Shared JS bundles (Bootstrap + CMS behaviors). -->
<!-- Confirmation modal (used for destructive actions). -->
<div class="modal fade" id="cmsConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Please confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" class="btn btn-danger" id="cmsConfirmYes">Confirm</a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php
  $tinyKey = trim((string) cms_pref('prefTinyMCEapi', '', 'cms'));
  $tinyMenu = trim((string) cms_pref('prefTinyMCEMenu', '', 'cms'));
  $tinyHeight = (int) cms_pref('prefTinyMCEHeight', 300, 'cms');
  $tinyToolbar = trim((string) cms_pref('prefTinyMCEToolbar', '', 'cms'));
  $tinyPlugins = trim((string) cms_pref('prefTinyMCEPlugins', '', 'cms'));

  $tinySrc = '';
  if ($tinyKey !== '') {
    if (preg_match('/^https?:\\/\\//i', $tinyKey)) {
      $tinySrc = $tinyKey;
    } else {
      $tinySrc = 'https://cdn.tiny.cloud/1/' . $tinyKey . '/tinymce/6/tinymce.min.js';
    }
  }
?>
<?php if ($tinySrc !== ''): ?>
  <script src="<?php echo cms_h($tinySrc); ?>" referrerpolicy="origin"></script>
  <script>
    if (window.tinymce) {
      tinymce.init(<?php
        $config = [
          'selector' => 'textarea.cms-tinymce',
          'height' => $tinyHeight > 0 ? $tinyHeight : 300,
          'menubar' => $tinyMenu !== '' ? $tinyMenu : false,
        ];
        if ($tinyToolbar !== '') {
          $config['toolbar'] = $tinyToolbar;
        }
        if ($tinyPlugins !== '') {
          $config['plugins'] = $tinyPlugins;
        }
        echo json_encode($config, JSON_UNESCAPED_SLASHES);
      ?>);
    }
  </script>
<?php endif; ?>
<script src="<?php echo $CMS_BASE_URL; ?>/js/cms.js"></script>
</body>
</html>
