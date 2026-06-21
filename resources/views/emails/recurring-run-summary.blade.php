<!DOCTYPE html>
<html lang="da">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1c1e21; background: #f0f2f5; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden;">
  <div style="background: #1877f2; padding: 16px 22px; color: #fff;">
    <div style="font-weight: 700; font-size: 16px;">The Playground</div>
    <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">Abonnementsopkrævninger — {{ $s['ran_at'] }}</div>
  </div>
  <div style="padding: 22px; line-height: 1.6;">
    <table style="width:100%; border-collapse: collapse;">
      <tr><td style="padding:6px 0;">Opkrævet</td><td style="padding:6px 0; text-align:right; font-weight:700;">{{ $s['charged'] }}</td></tr>
      <tr><td style="padding:6px 0;">Beløb i alt</td><td style="padding:6px 0; text-align:right; font-weight:700;">{{ number_format($s['total_cents'] / 100, 2, ',', '.') }} {{ strtoupper($s['currency']) }}</td></tr>
      <tr><td style="padding:6px 0;">Fejlede</td><td style="padding:6px 0; text-align:right; font-weight:700; color: {{ $s['failed'] > 0 ? '#e11d48' : 'inherit' }};">{{ $s['failed'] }}</td></tr>
      <tr><td style="padding:6px 0;">Udløbne/stoppede aftaler</td><td style="padding:6px 0; text-align:right; font-weight:700;">{{ $s['expired'] }}</td></tr>
    </table>

    @if (!empty($s['failures']))
      <div style="margin-top: 16px;">
        <div style="font-weight: 700; margin-bottom: 6px;">Detaljer</div>
        <ul style="margin: 0; padding-left: 18px; color: #65676b; font-size: 13px;">
          @foreach ($s['failures'] as $line)
            <li style="margin: 3px 0;">{{ $line }}</li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
  <div style="padding: 14px 22px; background: #fafbfc; color: #65676b; font-size: 12px; text-align: center;">
    Automatisk rapport fra betalingssystemet
  </div>
</div>
</body>
</html>
