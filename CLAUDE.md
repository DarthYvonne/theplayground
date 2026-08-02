# theplayground

## Testing policy

Anders tests everything himself. Do not verify his work for him.

**Do not run tests. At all.** Not the suite, not one file, not `--filter`, not
"just to check". Make the change, say what you changed, stop.

The only exception: Anders reports that something is broken. Then run the one
test that covers it — and nothing else.

Why: shelling out is what makes this slow for Anders — his console locks while
it runs and he cannot interrupt it. A session that only reads and edits files
feels instant to him; one that runs commands does not. The project lives in a
Dropbox folder, but that is NOT the cause — tested 2026-08-02, still in
Dropbox, fast. The cost is the shell call itself.

Do not assume a command will be quick because a previous one reported a small
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
