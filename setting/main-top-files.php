<?php
require_once (dirname(__FILE__) . '/../../../private/dbcon.php');
require_once (dirname(__FILE__) . '/../includes/db.php');
require_once (dirname(__FILE__) . '/../include/session.php');
require_once (dirname(__FILE__) . '/../include/functions.php');

// Check and if set turn Error Reporting on fr CMS Globally
if (isset($prefs['prefShowErrorsCMS']) && $prefs['prefShowErrorsCMS'] === 'Yes') {
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
} else {
   error_reporting(0); // production default
   ini_set('display_errors', 0);
}

require_once (dirname(__FILE__) . '/../controllers/cmsUser.php'); // User class (admin)
require_once (dirname(__FILE__) . '/../logrecord.php');
require_once (dirname(__FILE__) . '/../controllers/User.php'); // User class (front)
require_once (dirname(__FILE__) . '/../controllers/menu.php');
require_once (dirname(__FILE__) . '/../controllers/imageResizer.php');
//require_once (dirname(__FILE__) . '/../controllers/formField.php');
require_once (dirname(__FILE__) . '/../controllers/preferences.php');
require_once (dirname(__FILE__) . '/../controllers/recordView.php');
require_once (dirname(__FILE__) . '/../controllers/recordEdit.php');
require_once (dirname(__FILE__) . '/../controllers/recordAdd.php');
require_once (dirname(__FILE__) . '/../controllers/formatHelpers.php');

if($pageType == 'prefs') {
   require_once (dirname(__FILE__) . '/../controllers/formFieldPrefs.php');
}
else
{
   require_once (dirname(__FILE__) . '/../controllers/formField.php');
}

$prefs = loadPrefs();
// $prefshop = loadShopPrefs();
$BASE_URL = $prefs['prefSiteUrl'];

// Bridge the newer CMS login session into the legacy session keys used by
// older custom/admin pages that still include this bootstrap.
if (!isset($_SESSION["useremail"]) && isset($_SESSION['cms_user']) && is_array($_SESSION['cms_user'])) {
   if (!empty($_SESSION['cms_user']['email'])) {
      $_SESSION["useremail"] = $_SESSION['cms_user']['email'];
   } elseif (!empty($_SESSION['cms_user']['username'])) {
      $_SESSION["useremail"] = $_SESSION['cms_user']['username'];
   }
}

if (!isset($_SESSION['userid']) && isset($_SESSION['cms_user']['id'])) {
   $_SESSION['userid'] = (int) $_SESSION['cms_user']['id'];
}

// Check For SSL (as set in Prefs) and et up base URL for all internal links
if ($prefs['prefSSL'] == 'Yes') {
   $baseURL = "https://" . $_SERVER['SERVER_NAME'] . "";
} else {
   $baseURL = "http://" . $_SERVER['SERVER_NAME'] . "";
}

// check if user is logged in
if (!isset($_SESSION["useremail"])) {
   if (
      $_SERVER['PHP_SELF'] !== '/wccms/index.php' &&
      $_SERVER['PHP_SELF'] !== '/wccms/reset.php' &&
      $_SERVER['PHP_SELF'] !== '/wccms/2fa.php'
   ) {
      header("Location: " . $BASE_URL . "/wccms/index.php");
      exit();
   }
} else {
   $USER = new CMSUser($_SESSION['useremail']);
   $user = $USER->getUser();
}

if (!isset($userid) && isset($_SESSION['userid'])) {
   $userid = $_SESSION['userid'];
}
