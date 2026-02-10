<?php
// Core CMS bootstrap: DB connection, auth helpers, email helpers, and shared prefs.
require_once __DIR__ . '/../../../private/dbcon.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/prefs.php';
require_once __DIR__ . '/../../includes/lib/cms_log.php';
require_once __DIR__ . '/../../includes/lib/cms_icons.php';
require_once __DIR__ . '/lib/cms_media.php';

// Common template variables.
$CMS_NAME = 'wITeCanvas CMS';
$CMS_SITE_NAME = cms_pref('prefSiteName', 'ITFix', 'cms');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseURL = cms_base_url();
$CMS_SITE_URL = $baseURL;
$CMS_BASE_URL = cms_base_url('/wccms');
$CMS_USER = cms_current_user();
