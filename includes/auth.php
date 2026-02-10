<?php
// Ensure session is available for CMS auth.
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Return the currently logged in CMS user (or null if none).
 */
function cms_current_user(): ?array {
  return $_SESSION['cms_user'] ?? null;
}

/**
 * Check whether a CMS user is logged in.
 */
function cms_is_logged_in(): bool {
  return isset($_SESSION['cms_user']);
}

/**
 * Enforce login by redirecting to the CMS login page.
 */
function cms_require_login(): void {
  if (!cms_is_logged_in()) {
    $loginUrl = function_exists('cms_base_url') ? cms_base_url('/wccms/login.php') : '/wccms/login.php';
    header('Location: ' . $loginUrl);
    exit;
  }
}

/**
 * Attempt to authenticate a CMS user by username/email and password.
 * On success, hydrates the session user record.
 */
function cms_login(string $identifier, string $password, string &$error = null): bool {
  global $pdo, $DB_OK;

  if (!$DB_OK || !($pdo instanceof PDO)) {
    $error = 'Database unavailable.';
    return false;
  }

  $sql = 'SELECT u.id, u.username, u.name, u.firstname, u.surname, u.password, u.userrole, u.archived, u.showonweb, r.name AS role_name
          FROM cms_users u
          LEFT JOIN cms_userrole r ON r.id = u.userrole
          WHERE u.username = :id
          LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $identifier]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user || (int) $user['archived'] === 1 || $user['showonweb'] === 'No') {
    $error = 'Invalid login.';
    return false;
  }

  if (!password_verify($password, $user['password'])) {
    $error = 'Invalid login.';
    return false;
  }

  // Prefer the explicit display name; fall back to first/last or username.
  $displayName = $user['name'] ?? '';
  if (!$displayName) {
    $displayName = trim(($user['firstname'] ?? '') . ' ' . ($user['surname'] ?? ''));
  }

  $_SESSION['cms_user'] = [
    'id' => (int) $user['id'],
    'username' => $user['username'],
    'email' => $user['username'],
    'display_name' => $displayName ?: $user['username'],
    'role' => $user['role_name'] ?: (string) $user['userrole'],
  ];

  return true;
}

/**
 * Clear the CMS login session and session cookies.
 */
function cms_logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}
