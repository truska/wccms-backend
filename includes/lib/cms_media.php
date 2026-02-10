<?php
function cms_media_parse_exts(?string $extList): array {
  if ($extList === null) {
    return [];
  }
  $items = array_filter(array_map('trim', explode(',', strtolower($extList))));
  return array_values(array_filter(array_map(static function ($ext) {
    $ext = ltrim($ext, '.');
    return $ext !== '' ? $ext : null;
  }, $items)));
}

function cms_media_accept_attr(?string $extList): string {
  $exts = cms_media_parse_exts($extList);
  if (!$exts) {
    return '';
  }
  return implode(',', array_map(static fn($ext) => '.' . $ext, $exts));
}

function cms_media_clean_base(string $name): string {
  $name = strtolower($name);
  $name = preg_replace('/\\s+/', '-', $name);
  $name = preg_replace('/[^a-z0-9-]/', '', $name);
  $name = preg_replace('/-+/', '-', $name);
  $name = trim($name, '-');
  return $name !== '' ? $name : 'file';
}

function cms_media_base_dir(): string {
  return rtrim(dirname(__DIR__, 3) . '/filestore', '/');
}

function cms_media_path(string $mediatype, string $folder, string $size = ''): string {
  $parts = [cms_media_base_dir(), $mediatype];
  if ($folder !== '') {
    $parts[] = trim($folder, '/');
  }
  if ($size !== '') {
    $parts[] = trim($size, '/');
  }
  return rtrim(implode('/', $parts), '/') . '/';
}

function cms_media_url(string $mediatype, string $folder, string $filename, string $size = '', bool $preferWebp = true): string {
  $base = rtrim(cms_base_url('/filestore/' . trim($mediatype, '/')), '/');
  $folder = trim($folder, '/');
  if ($folder !== '') {
    $base .= '/' . $folder;
  }
  if ($size !== '') {
    $base .= '/' . trim($size, '/');
  }
  $base .= '/';

  if ($preferWebp && preg_match('/\\.(jpe?g|png|gif)$/i', $filename)) {
    $webp = preg_replace('/\\.[^.]+$/', '.webp', $filename);
    $webpPath = cms_media_path($mediatype, $folder, $size) . $webp;
    if (file_exists($webpPath)) {
      return $base . $webp;
    }
  }

  return $base . $filename;
}

function cms_media_unique_name(string $dir, string $base, string $ext): string {
  $name = $base;
  $suffix = 1;
  while (file_exists($dir . $name . '.' . $ext)) {
    $name = $base . '-' . $suffix;
    $suffix++;
  }
  return $name;
}

function cms_media_ensure_dir(string $dir): bool {
  if (is_dir($dir)) {
    return is_writable($dir);
  }
  return mkdir($dir, 0775, true);
}

function cms_media_get_image_resource(string $path, string $mime) {
  switch ($mime) {
    case 'image/jpeg':
      return imagecreatefromjpeg($path);
    case 'image/png':
      return imagecreatefrompng($path);
    case 'image/gif':
      return imagecreatefromgif($path);
    case 'image/webp':
      return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
    default:
      return false;
  }
}

function cms_media_save_image($image, string $dest, string $ext, int $quality = 90): bool {
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      return imagejpeg($image, $dest, $quality);
    case 'png':
      return imagepng($image, $dest, 6);
    case 'gif':
      return imagegif($image, $dest);
    case 'webp':
      return function_exists('imagewebp') ? imagewebp($image, $dest, $quality) : false;
    default:
      return false;
  }
}

function cms_media_resize_image($source, int $targetWidth, int $targetHeight) {
  $dest = imagecreatetruecolor($targetWidth, $targetHeight);
  imagealphablending($dest, false);
  imagesavealpha($dest, true);
  imagecopyresampled($dest, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($source), imagesy($source));
  return $dest;
}

function cms_media_store_upload(array $file, array $field, array $record, int $formId, int $recordId, array $options = []): array {
  $errors = [];
  $result = [
    'ok' => false,
    'errors' => &$errors,
    'filename' => '',
    'mediatype' => '',
    'folder' => '',
    'is_image' => false,
    'sizes' => [],
    'master_written' => false,
  ];

  if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || $file['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Upload failed.';
    return $result;
  }

  $mediatype = (string) ($field['mediatype'] ?? 'images');
  $folderName = (string) ($field['file_folder_name'] ?? '');
  $folder = $folderName !== '' ? trim($folderName, '/') : '';
  $result['mediatype'] = $mediatype;
  $result['folder'] = $folder;

  $allowedExts = cms_media_parse_exts($field['file_ext'] ?? '');
  $originalName = $file['name'] ?? '';
  $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  if ($ext === 'jpeg') {
    $ext = 'jpg';
  }

  if ($allowedExts && !in_array($ext, $allowedExts, true)) {
    $errors[] = 'Invalid file extension.';
    return $result;
  }

  $override = ($field['override_filename'] ?? 'No') === 'Yes';
  if ($override) {
    $title = (string) ($record['heading'] ?? $record['title'] ?? $record['name'] ?? $originalName);
    $base = $formId . '-' . $recordId . '-' . cms_media_clean_base($title);
  } else {
    $base = cms_media_clean_base(pathinfo($originalName, PATHINFO_FILENAME));
  }

  $baseDir = cms_media_path($mediatype, $folder);
  if (!cms_media_ensure_dir($baseDir)) {
    $errors[] = 'Failed to create upload directory: ' . $baseDir . ' (mediatype=' . $mediatype . ', folder=' . $folder . ')';
    return $result;
  }

  $base = cms_media_unique_name($baseDir, $base, $ext);
  $filename = $base . '.' . $ext;
  $result['filename'] = $filename;

  $storeOriginal = ($options['store_original'] ?? true) === true;
  $createWebp = ($options['create_webp'] ?? true) === true;

  $imageInfo = @getimagesize($file['tmp_name']);
  $isImage = is_array($imageInfo) && !empty($imageInfo['mime']);
  $result['is_image'] = $isImage;

  if (!$isImage) {
    $dest = $baseDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      $errors[] = 'Failed to move uploaded file.';
      return $result;
    }
    $result['ok'] = true;
    return $result;
  }

  $mime = $imageInfo['mime'];
  $source = cms_media_get_image_resource($file['tmp_name'], $mime);
  if (!$source) {
    $errors[] = 'Unsupported image type.';
    return $result;
  }

  $origWidth = imagesx($source);
  $origHeight = imagesy($source);
  $ratio = $origWidth > 0 ? ($origWidth / $origHeight) : 1.0;

  if ($storeOriginal) {
    $originalDir = cms_media_path($mediatype, $folder, 'original');
    if (!cms_media_ensure_dir($originalDir)) {
      $errors[] = 'Failed to create original directory: ' . $originalDir;
      return $result;
    }
    if (!move_uploaded_file($file['tmp_name'], $originalDir . $filename)) {
      $errors[] = 'Failed to move original upload to: ' . $originalDir;
      return $result;
    }
  }

  $defaultSize = (int) ($field['default_size'] ?? 0);
  $masterWidth = ($defaultSize > 0 && $origWidth > $defaultSize) ? $defaultSize : $origWidth;
  $masterHeight = (int) round($masterWidth / $ratio);
  $master = ($masterWidth === $origWidth) ? $source : cms_media_resize_image($source, $masterWidth, $masterHeight);
  cms_media_save_image($master, $baseDir . $filename, $ext, 90);
  $result['master_written'] = true;
  if ($createWebp && function_exists('imagewebp') && $ext !== 'webp') {
    cms_media_save_image($master, $baseDir . $base . '.webp', 'webp', 82);
  }
  $masterDir = cms_media_path($mediatype, $folder, 'master');
  if (cms_media_ensure_dir($masterDir)) {
    cms_media_save_image($master, $masterDir . $filename, $ext, 90);
    if ($createWebp && function_exists('imagewebp') && $ext !== 'webp') {
      cms_media_save_image($master, $masterDir . $base . '.webp', 'webp', 82);
    }
  }
  if ($master !== $source) {
    imagedestroy($master);
  }

  $scaled = [
    'xs' => (int) ($field['xs_max_width'] ?? 0),
    'sm' => (int) ($field['sm_max_width'] ?? 0),
    'md' => (int) ($field['md_max_width'] ?? 0),
    'lg' => (int) ($field['lg_max_width'] ?? 0),
  ];

  foreach ($scaled as $size => $width) {
    if ($width <= 0) {
      continue;
    }
    $targetWidth = $origWidth > $width ? $width : $origWidth;
    $targetHeight = (int) round($targetWidth / $ratio);
    $resized = ($targetWidth === $origWidth) ? $source : cms_media_resize_image($source, $targetWidth, $targetHeight);
    $sizeDir = cms_media_path($mediatype, $folder, $size);
    if (!cms_media_ensure_dir($sizeDir)) {
      $errors[] = 'Failed to create size directory: ' . $sizeDir;
      continue;
    }
    cms_media_save_image($resized, $sizeDir . $filename, $ext, 90);
    if ($createWebp && function_exists('imagewebp') && $ext !== 'webp') {
      cms_media_save_image($resized, $sizeDir . $base . '.webp', 'webp', 82);
    }
    $result['sizes'][] = $size;
    if ($resized !== $source) {
      imagedestroy($resized);
    }
  }

  imagedestroy($source);
  $result['ok'] = true;
  return $result;
}
