<?php
function cms_apply_shortcodes(string $html): string {
  if ($html === '') {
    return $html;
  }

  return preg_replace_callback(
    '/\[\[([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\]\]/',
    function (array $matches): string {
      $code = strtolower($matches[1] ?? '');
      $modifier = strtolower($matches[2] ?? '');

      if ($code === '') {
        return $matches[0];
      }

      if ($code === 'email') {
        $email = (string) cms_pref('prefEmail', '');
        if ($email === '') {
          return '';
        }
        if ($modifier === 'link') {
          $safeEmail = cms_h($email);
          return '<a href="mailto:' . $safeEmail . '">' . $safeEmail . '</a>';
        }
        return cms_h($email);
      }

      if (preg_match('/^tel(\d*)$/', $code, $parts)) {
        $number = $parts[1] !== '' ? $parts[1] : '1';
        $prefKey = 'prefTel' . $number;
        $tel = cms_tel_data($prefKey, 'prefTelIntCode', '');
        $display = $tel['display'];
        $dial = $tel['dial'];

        if ($display === '') {
          return '';
        }

        if ($modifier === 'link') {
          $safeDisplay = cms_h($display);
          $safeDial = cms_h($dial !== '' ? $dial : preg_replace('/\s+/', '', $display));
          return '<a href="tel:' . $safeDial . '">' . $safeDisplay . '</a>';
        }

        return cms_h($display);
      }

      return $matches[0];
    },
    $html
  );
}
