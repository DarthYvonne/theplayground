<!DOCTYPE html>
<html lang="da">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1c1e21; background: #f0f2f5; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden;">
  <div style="background: #1877f2; padding: 16px 22px; color: #fff;">
    <div style="font-weight: 700; font-size: 16px;">The Playground</div>
    <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">Nulstil din adgangskode</div>
  </div>
  <div style="padding: 22px;">
    <div style="line-height: 1.55;">
      Hej {{ $user->name }},<br><br>
      Vi har fået en anmodning om at nulstille adgangskoden til din konto. Tryk på knappen for at vælge en ny.
    </div>
    <div style="margin-top: 22px;">
      <a href="{{ $resetUrl }}" style="display: inline-block; background: #1877f2; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Vælg ny adgangskode</a>
    </div>
    <div style="margin-top: 22px; color: #65676b; font-size: 13px; line-height: 1.5;">
      Linket virker i {{ $expiresInMinutes }} minutter og kan kun bruges én gang.<br>
      Virker knappen ikke, kan du kopiere denne adresse ind i browseren:<br>
      <span style="color: #1877f2; word-break: break-all;">{{ $resetUrl }}</span>
    </div>
  </div>
  <div style="padding: 14px 22px; background: #fafbfc; color: #65676b; font-size: 12px; text-align: center;">
    Har du ikke bedt om at nulstille din adgangskode, kan du roligt ignorere denne mail — der sker ikke noget.
  </div>
</div>
</body>
</html>
