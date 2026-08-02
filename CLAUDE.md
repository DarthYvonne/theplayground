# theplayground

## Testing policy

Anders tests the UI himself. Keep verification cheap and scoped.

**Run only what the change touched.** One file, or one filter:

```
php artisan test tests/Feature/CourseScheduleTest.php
php artisan test --filter=test_name
```

Do not run the full suite unless explicitly asked. (For reference: the whole
suite is ~8s and the Unit suite ~2.4s — speed is not the problem, scope is.)

**Never verify UI in the browser.** No dev server, no `vite build`, no Chrome
automation, no screenshots to confirm layout, styling, copy, or page rendering.
Anders checks that himself. Report the change and let him look.

**Do not write new tests unless asked.** Adjust an existing test when a change
breaks it; otherwise leave the suite alone.

PHP is Herd: `C:\Users\kanka\.config\herd\bin\php.bat` (on PATH in PowerShell,
not in the Bash tool).
