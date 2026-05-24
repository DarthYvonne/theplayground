<!DOCTYPE html>
<html lang="da">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1c1e21; background: #f0f2f5; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden;">
  <div style="background: #1877f2; padding: 16px 22px; color: #fff;">
    <div style="font-weight: 700; font-size: 16px;">The Playground</div>
    <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">
      Ny besked fra {{ $sender->name }}@if ($course) · {{ $course->title }}@endif
    </div>
  </div>
  <div style="padding: 22px;">
    <div style="white-space: pre-wrap; line-height: 1.55; color: #1c1e21;">{{ $bodyText }}</div>
    <div style="margin-top: 22px;">
      <a href="{{ $threadUrl }}" style="display: inline-block; background: #1877f2; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">Svar på The Playground</a>
    </div>
    <div style="margin-top: 22px; padding-top: 14px; border-top: 1px solid #f0f2f5; color: #65676b; font-size: 13px;">
      — {{ $sender->name }}
    </div>
  </div>
  <div style="padding: 14px 22px; background: #fafbfc; color: #65676b; font-size: 12px; text-align: center;">
    Du modtager denne mail, fordi du har slået mailbeskeder til. Du kan slå dem fra på
    <a href="{{ $settingsUrl }}" style="color: #1877f2;">Beskeder</a>.
  </div>
</div>
</body>
</html>
