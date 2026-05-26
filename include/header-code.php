<!-- START header-code -->
<?php $cmsAssetBase = "/wccms"; ?>

<title>wITeCanvas CMS - <?php echo $prefs["prefSiteName"]; ?></title>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="wITeCanvas CMS System">
<meta name="author" content="wITeCanvas">
<meta name="keyword" content="wITeCanvas, cms, truska, digita">
<link rel="shortcut icon" href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/img/witecanvas-favicon.ico">
<meta name="robots" content="noindex, nofollow" />

<!-- ✅ jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<!-- ✅ Bootstrap CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Optional Reset / Slidebar / Wizard / Custom styles -->
<link href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/css/bootstrap-reset.css" rel="stylesheet">
<link href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/css/slidebars.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/css/jquery.steps.css" />
<link href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/css/style.css" rel="stylesheet">
<link href="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/css/style-responsive.css" rel="stylesheet">

<!-- ✅ Font Awesome 6.4 (CDN with token) -->
<script src="https://kit.fontawesome.com/<?php echo $prefs["prefFontAwsomeToken"]; ?>" crossorigin="anonymous"></script>

<!-- ✅ Dropzone -->
<script src="https://rawgit.com/enyo/dropzone/master/dist/dropzone.js"></script>
<link rel="stylesheet" href="https://rawgit.com/enyo/dropzone/master/dist/dropzone.css">

<!-- ✅ DataTables CSS (still used) -->
<link href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.bootstrap4.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap4.min.css" rel="stylesheet">

<!-- Flatpickr Date picker for managed formating -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- ✅ TinyMCE -->
<?php if ($prefs["prefTinyMCE"]) { ?>
  <script src="https://cdn.tiny.cloud/1/<?php echo $prefs["prefTinyMCE"]; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<?php } else { ?>
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<?php } ?>

<!-- ✅ Optional dynamic CSS injection -->
<!--
<?php if ($customcss) echo "<style>{$customcss}</style>"; ?>
-->

<!-- ✅ Toast Notifications -->
<script src="<?php echo htmlspecialchars($cmsAssetBase, ENT_QUOTES); ?>/js/Toast.js"></script>

<!-- END header-code -->