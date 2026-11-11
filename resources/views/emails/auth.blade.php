<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f2f9f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f9f5;padding:32px 12px;">
  <tr><td align="center">
    <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
      <tr><td style="background:#0c874e;padding:24px 32px;">
        <span style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">yoka</span>
        <span style="font-size:20px;font-weight:700;color:#ffffff;margin-left:4px;">POS</span>
      </td></tr>
      <tr><td style="padding:32px;">
        <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#0f172a;">{{ $heading }}</h1>
        <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">Halo {{ $name }},</p>
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;">{{ $intro }}</p>
        <p style="margin:0 0 24px;">
          <a href="{{ $url }}" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 32px;border-radius:999px;">{{ $button }}</a>
        </p>
        <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#64748b;">{{ $note }}</p>
        <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;">Kalau tombol di atas tidak bisa diklik, salin alamat ini ke browser:<br>
          <a href="{{ $url }}" style="color:#059669;word-break:break-all;">{{ $url }}</a></p>
      </td></tr>
      <tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center;">
        Email ini dikirim otomatis oleh Yoka POS. Jangan membalas email ini.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
