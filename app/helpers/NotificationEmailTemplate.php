<?php
/**
 * Renders the shared Staffroom layout for notification emails.
 */
class NotificationEmailTemplate
{
    public static function render($title, $message)
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:24px;background:#f4f7f9;font-family:Arial,Helvetica,sans-serif;color:#27313a;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #dce4e8;">'
            . '<tr><td style="padding:24px 32px;background:#00466a;color:#ffffff;"><strong style="font-size:24px;">StaffRoom</strong></td></tr>'
            . '<tr><td style="padding:32px;"><h1 style="margin:0 0 16px;font-size:22px;color:#00466a;">' . $safeTitle . '</h1>'
            . '<div style="font-size:15px;line-height:1.6;">' . $safeMessage . '</div></td></tr>'
            . '<tr><td style="padding:18px 32px;background:#f4f7f9;color:#687782;font-size:12px;">This notification was sent by StaffRoom. Please log in to view the full details.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
?>
