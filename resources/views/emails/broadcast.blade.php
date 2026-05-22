<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1c1e21; background: #f0f2f5; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden;">
  <div style="background: #1877f2; padding: 16px 22px; color: #fff;">
    <div style="font-weight: 700; font-size: 16px;">The Playground</div>
    <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">{{ $course->title }}</div>
  </div>
  <div style="padding: 22px;">
    <div style="white-space: pre-wrap; line-height: 1.55; color: #1c1e21;">{{ $bodyText }}</div>
    <div style="margin-top: 22px; padding-top: 14px; border-top: 1px solid #f0f2f5; color: #65676b; font-size: 13px;">
      — {{ $sender->name }}, {{ $course->title }} trainer
    </div>
  </div>
  <div style="padding: 14px 22px; background: #fafbfc; color: #65676b; font-size: 12px; text-align: center;">
    You're receiving this because you're enrolled in {{ $course->title }} at The Playground.
  </div>
</div>
</body>
</html>
