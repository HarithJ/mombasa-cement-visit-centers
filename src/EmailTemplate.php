<?php
declare(strict_types=1);
final class EmailTemplate {
    public static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function date(string $value): string {
        return (new DateTimeImmutable($value, new DateTimeZone('Africa/Nairobi')))->format('j F Y');
    }
    public static function text(array $content): string {
        $lines = $content['paragraphs'];
        $lines[] = '';
        foreach ($content['details'] as $label => $value) $lines[] = $label.': '.$value;
        foreach ($content['notes'] as $note) { $lines[] = ''; $lines[] = $note; }
        if (!empty($content['action'])) { $lines[] = ''; $lines[] = $content['action']['label'].':'; $lines[] = $content['action']['url']; }
        if (!empty($content['contacts'])) {
            $lines[] = ''; $lines[] = $content['contactHeading'];
            foreach ($content['contacts'] as $person) $lines[] = ($person['name'] !== '' ? $person['name'].' — ' : '').$person['phone'].' · '.$person['email'];
        }
        $lines[] = ''; $lines[] = 'Warm regards,'; $lines[] = 'The Nyumba team';
        return implode("\n", $lines);
    }
    public static function html(array $content): string {
        $e = self::escape(...);
        $body = '';
        foreach ($content['paragraphs'] as $paragraph) $body .= '<p style="margin:0 0 18px;font-size:16px;line-height:1.7;color:#31483e;overflow-wrap:anywhere;word-break:break-word;">'.$e($paragraph).'</p>';
        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:26px 0;">';
        foreach ($content['details'] as $label => $value) {
            $body .= '<tr><th scope="row" align="left" valign="top" style="width:36%;padding:13px 10px 13px 0;border-bottom:1px solid #dfe5dc;font-size:13px;line-height:1.6;font-weight:400;color:#526459;">'.$e($label).': </th><td valign="top" style="padding:13px 0;border-bottom:1px solid #dfe5dc;font-size:14px;line-height:1.6;color:#173f35;font-weight:600;word-break:break-word;overflow-wrap:anywhere;">'.$e((string)$value).'</td></tr>';
        }
        $body .= '</table>';
        foreach ($content['notes'] as $note) $body .= '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#31483e;overflow-wrap:anywhere;word-break:break-word;">'.$e($note).'</p>';
        if (!empty($content['action'])) {
            $action = $content['action'];
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0;"><tr><td bgcolor="#173f35" style="background:#173f35;border-radius:4px;mso-padding-alt:16px 24px;"><a href="'.$e($action['url']).'" style="display:inline-block;padding:16px 24px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">'.$e($action['label']).' &rarr;</a></td></tr></table>';
        }
        if (!empty($content['contacts'])) {
            $body .= '<h2 style="margin:30px 0 12px;font-size:18px;color:#173f35;">'.$e($content['contactHeading']).'</h2>';
            foreach ($content['contacts'] as $person) {
                $body .= '<p style="margin:0 0 16px;font-size:14px;line-height:1.9;overflow-wrap:anywhere;word-break:break-word;">'.($person['name'] !== '' ? '<strong>'.$e($person['name']).'</strong><br>' : '').'<a href="tel:'.$e($person['tel']).'" style="color:#245e48;">'.$e($person['phone']).'</a><br><a href="mailto:'.$e($person['email']).'" style="color:#245e48;">'.$e($person['email']).'</a></p>';
            }
        }
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($content['title']).'</title></head>'
            .'<body style="margin:0;padding:0;background:#f3f2eb;font-family:Arial,Helvetica,sans-serif;">'
            .'<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">'.$e($content['preheader']).'</div>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f3f2eb"><tr><td align="center" style="padding:24px 12px;">'
            .'<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;">'
            .'<tr><td bgcolor="#173f35" style="padding:34px 24px;background:#173f35;color:#ffffff;">'
            .'<p style="margin:0 0 34px;font-size:24px;letter-spacing:4px;font-weight:bold;color:#ffffff;">NYUMBA<span style="font-size:11px;letter-spacing:2px;"> GROUP</span></p>'
            .'<p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:#d7d9b5;text-transform:uppercase;">'.$e($content['eyebrow']).'</p>'
            .'<h1 style="margin:0;font-family:Georgia,serif;font-size:34px;line-height:1.2;font-weight:normal;color:#ffffff;">'.$e($content['title']).'</h1>'
            .'</td></tr><tr><td height="4" bgcolor="#c3ad70" style="height:4px;font-size:0;line-height:0;">&nbsp;</td></tr>'
            .'<tr><td style="padding:28px 24px;">'.$body.'<p style="margin:30px 0 0;font-size:15px;line-height:1.7;color:#31483e;overflow-wrap:anywhere;word-break:break-word;">Warm regards,<br><strong>The Nyumba team</strong></p></td></tr>'
            .'<tr><td bgcolor="#eef1e9" style="padding:20px 24px;font-size:12px;line-height:1.7;color:#526459;">NYUMBA GROUP &nbsp; / &nbsp; VISIT WITH US<br>'.$e($content['footer']).'</td></tr></table>'
            .'<!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }
}
