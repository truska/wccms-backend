<?php
require_once __DIR__ . '/includes/boot.php';
// Destroy CMS session then return to login.
cms_logout();
header('Location: /wccms/login.php');
exit;
