# theplayground

## Testing policy

Anders tests everything himself. Do not verify his work for him.

**Do not run tests. At all.** Not the suite, not one file, not `--filter`, not
"just to check". Make the change, say what you changed, stop.

The only exception: Anders reports that something is broken. Then run the one
test that covers it — and nothing else.

Why: running PHP in this project hangs his console for a long time, and he
cannot approve or interrupt anything while it does. The cause is not confirmed
(Dropbox sync and Defender scanning Laravel's autoload are both suspects), so
do not assume a command will be quick because a previous one reported a small
number. `php artisan test` reports its own internal duration, which excludes
PHP startup, bootstrapping and migrations — it is not what Anders waits
through. Never quote it to him as the cost.

The same caution applies to any `php artisan` command, not just tests.

**Never verify UI in the browser.** No dev server, no `vite build`, no Chrome
automation, no screenshots to confirm layout, styling, copy, or page rendering.
Anders checks that himself. Report the change and let him look.

**Do not write new tests unless asked.** Adjust an existing test when a change
breaks it; otherwise leave the suite alone.

PHP is Herd: `C:\Users\kanka\.config\herd\bin\php.bat` (on PATH in PowerShell,
not in the Bash tool).
