<!DOCTYPE html>
<html lang="da">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1c1e21; background: #f0f2f5; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden;">
  <div style="background: #e11d48; padding: 16px 22px; color: #fff;">
    <div style="font-weight: 700; font-size: 16px;">The Playground</div>
    <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">Din betaling fejlede</div>
  </div>
  <div style="padding: 22px; line-height: 1.55;">
    <p>Hej {{ explode(' ', trim($user->name))[0] }},</p>
    <p>Vi kunne ikke trække betalingen for dit hold <strong>{{ $course->title }}</strong>. Det skyldes typisk et udløbet kort eller manglende dækning.</p>
    <p>Hvis du opdaterer dit kort i løbet af de næste dage, fortsætter din tilmelding uden afbrydelse.</p>
    <div style="margin-top: 18px;">
      <a href="{{ $billingUrl }}" style="display: inline-block; background: #1877f2; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Opdater dit kort</a>
    </div>
  </div>
  <div style="padding: 14px 22px; background: #fafbfc; color: #65676b; font-size: 12px; text-align: center;">
    The Playground
  </div>
</div>
</body>
</html>
