<?php
// CMS preference loader and shared preferences cache.
require_once __DIR__ . '/lib/cms_prefs.php';
require_once __DIR__ . '/lib/cms_shortcodes.php';

$CMS_PREFS = cms_load_preferences('cms');
