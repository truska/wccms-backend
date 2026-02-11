<?php
function cms_log_action(string $action, string $table = null, $recordId = null, string $sqlText = null, string $formName = null, string $scope = 'cms', string $name = null): void {
  global $pdo, $DB_OK;

  if (!$DB_OK || !($pdo instanceof PDO)) {
    return;
  }

  $scope = ($scope === 'web') ? 'web' : 'cms';
  $userId = null;
  if (!empty($_SESSION['cms_user']['id'])) {
    $userId = (int) $_SESSION['cms_user']['id'];
  }

  $nameValue = $name ?: $action;
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

  try {
    $stmt = $pdo->prepare(
      'INSERT INTO cms_log (name, user_id, scope, action, form_name, record_id, table_name, sql_text, ip, user_agent, showonweb)
       VALUES (:name, :user_id, :scope, :action, :form_name, :record_id, :table_name, :sql_text, :ip, :user_agent, :showonweb)'
    );
    $stmt->execute([
      ':name' => $nameValue,
      ':user_id' => $userId,
      ':scope' => $scope,
      ':action' => $action,
      ':form_name' => $formName,
      ':record_id' => $recordId,
      ':table_name' => $table,
      ':sql_text' => $sqlText,
      ':ip' => $ip,
      ':user_agent' => $agent,
      ':showonweb' => 'Yes',
    ]);
  } catch (PDOException $e) {
    // Avoid cascading failures in core flows.
    return;
  }
}
