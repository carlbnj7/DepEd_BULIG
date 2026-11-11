# Version 3 verification

## What was checked

- Reviewed the module's embedded-image contact sheets, activity prompts and source page mappings. Inspected the dog, picture-talk, outdoor-play and drawing-model pictures at full size.
- Audited all 225 activity IDs; a CSV lists assignments and source pages. Corrected 30 activities and made the image migration restore the complete default mapping for all 225 unedited activities.
- Ran the v2 baseline (1,084 HTTP checks), then v3 checks for every rendered pupil activity and every assigned image URL.
- Verified Grade 6 placement at Level 1, all eight level cards, future-level assignment persistence, direct-URL/POST restrictions, preservation of answers when reassigning, and cross-teacher denial.
- Verified the requested default pupil password and optional password change, including current-password and confirmation checks. Existing passwords are not reset.
- Repeated the image migration and verified existing users, password hashes, answers and XP were unchanged. Administrator-edited images were preserved; an older empty image assignment was repaired. Single-image fallback and byte-identical original-image fallback were exercised; traversal and unrelated-upload fallback were rejected.
- Imported the full fresh-install SQL into an empty database, with all eight level records. Also applied the migration runner twice to the previous schema.
- Chromium loaded 235 activity-image references across all 12 administrator audit pages. No assigned image failed to load.
- Browser checks covered widths 390, 768, 844 and 1440, pupil level cards, lesson path, profile, dog discussion and picture assessment. Checked for page-wide horizontal overflow.
- Verified the vertical mobile drawer, Escape dismissal, default-password account form, and eight starting-level options. Simulated an HTTP image failure and confirmed the pupil sees an explicit picture-unavailable message.
- PHP and JavaScript syntax checked. The original logo and PDF remain unchanged.

Screenshots in `docs/previews/v3-*.png` show the current sidebar, level cards, teacher form and restored dog activity.

## Remaining source inputs and live verification

Five source prompts refer to exact pictures not supplied among the module illustrations: IDs 48, 57, 58, 59 and 60. These are explicitly listed in the administrator image check, with upload/edit links. They must receive approved classroom pictures; this release does not fabricate official illustrations or claim those inputs are complete.

Other personal/oral/practical activities intentionally use the pupil, classmates or real objects and do not require a fixed picture. A blank image list alone is not considered proof of a bug; the activity's actual prompt was reviewed.

The supplied Hostinger installation has not been inspected directly because no live URL/access was supplied. Upload the complete image folders and run Admin → Official visuals → Run image check on the hosted copy. Local tests cannot prove which files were successfully uploaded to another server.

Actual microphone permissions, recognition quality, installed voices and physical touch drawing still require target-device checks.

## Reproduction

Use a disposable database only, PHP 8.1+, MariaDB 10.6+ or MySQL 8+, and a running app with `public/` as web root. Create test admin `adminqa` / `AdminTest42!`, then run from the parent of `bulig/`:

```text
python bulig/tests/integration_v3.py http://127.0.0.1:8080
php bulig/tests/visual_repair.php
```

The browser script `tests/browser_v3.cjs` uses Playwright, CHROME_BIN for the browser executable, PLAYWRIGHT_PATH if Playwright is outside the usual module path, and the temporary test-account JSON written by the HTTP suite. Tests create and edit disposable accounts and content. Never run against production.
