<?php
/**
 * Return the standard HTML email signature used by the CMS.
 */
function cms_email_signature(): string {
  $baseUrl = function_exists('cms_base_url') ? rtrim((string) cms_base_url(), '/') : '';
  if ($baseUrl === '') {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $isHttps = (
      (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
      (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $baseUrl = ($isHttps ? 'https://' : 'http://') . $host;
  }

  $logoFile = trim((string) cms_pref('prefLogoEmail', ''));
  if ($logoFile === '') {
    $logoFile = trim((string) cms_pref('prefLogo', ''));
  }
  if ($logoFile === '') {
    $logoFile = 'ms-timber-logo.jpg';
  }

  if (preg_match('#^https?://#i', $logoFile)) {
    $logoSrc = $logoFile;
  } else {
    if (strpos($logoFile, '/') === false) {
      $logoFile = '/filestore/images/logos/' . ltrim($logoFile, '/');
    } else {
      $logoFile = '/' . ltrim($logoFile, '/');
    }
    $logoSrc = $baseUrl . $logoFile;
  }

  $logoName = trim((string) cms_pref('prefLogoName', ''));
  if ($logoName === '') {
    $logoName = trim((string) cms_pref('prefSiteName', ''));
  }
  if ($logoName === '') {
    $logoName = trim((string) cms_pref('prefCompanyName', ''));
  }
  if ($logoName === '') {
    $logoName = 'MS Timber';
  }

  return '<hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">'
    . '<div style="display:flex;align-items:center;gap:12px;">'
    . '<img src="' . htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($logoName, ENT_QUOTES, 'UTF-8') . '" style="height:36px;width:auto;">'
    . '<div style="font-family:Arial, sans-serif;font-size:13px;color:#6b7280;">'
    . '<strong style="color:#111827;">' . htmlspecialchars($logoName, ENT_QUOTES, 'UTF-8') . '</strong><br>'
    . 'Website enquiry'
    . '</div>'
    . '</div>';
}
