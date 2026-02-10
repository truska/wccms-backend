<?php
/**
 * Return the standard HTML email signature used by the CMS.
 */
function cms_email_signature(): string {
  return '<hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">'
    . '<div style="display:flex;align-items:center;gap:12px;">'
    . '<img src="https://itfix.witecanvas.com/filestore/images/logos/witecanvas-logo-s.png" alt="wITeCanvas" style="height:36px;width:auto;">'
    . '<div style="font-family:Arial, sans-serif;font-size:13px;color:#6b7280;">'
    . '<strong style="color:#111827;">wITeCanvas CMS</strong><br>'
    . 'Support powered by Truska'
    . '</div>'
    . '</div>';
}
