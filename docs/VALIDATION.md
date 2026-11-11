> Version 3 results are in V3-VALIDATION.md. The results below describe the preceding version.

# Version 2 validation

Tested with PHP 8.3.6, MariaDB 10.11.14 and Chromium 153 on disposable databases.

## Completed checks

- **1,084 HTTP integration checks passed.** All 225 activities in all 12 lessons completed without teacher intervention. Coverage includes sequential locks, final Done, wrong/correct exact answers, open responses without invented correctness, blank content/practical submissions, duplicate XP prevention, transcript drafts, section filtering, teacher ownership, and CSRF rejection.
- Migrated a seeded v1 database, then ran the migration again. Existing accounts, passwords, responses, administrator-edited content and XP were preserved; former review queues were released without duplicate awards.
- Imported the single fresh-install SQL into a separate empty database and verified all 225 activities.
- PHP syntax and JavaScript syntax checks passed.
- Real browser checks covered login, account creation, section filtering, seven answer/feedback/Next flows, typing focus and playback controls.
- Browser layout checks covered pupil screens at 390×844, 768×1024, 844×390 and 1440×1000, plus teacher pages on a phone. No page errors, missing displayed images or page-wide horizontal overflow were found. Desktop and phone screenshots were visually inspected; examples are in `docs/previews/`.
- Original PDF and logo are retained unchanged. All referenced lesson/activity assets exist. Corrected image mappings are documented in `V2-CHANGES.md`.

## Device-dependent checks

Actual microphone permission, live speech recognition, installed narration voices, audio quality and touch drawing require checking on the school’s computers and phones. Browser testing exercised playback controls but does not establish audible voice quality. Typing remains available. A transcript match is not a pronunciation score.

The source’s Lesson 11/12 post-test heading reversal remains documented in `CONTENT-AUDIT.md`; interpretation by competency is retained.

## Reproduce HTTP checks

Use a disposable database only. Import `database/fresh_install_v2.sql` into an empty database, configure the application, create the test administrator `adminqa` with password `AdminTest42!`, and start PHP with `public/` as its web root. From the directory containing `bulig/`, run:

```text
python bulig/tests/integration_v2.py http://127.0.0.1:8080
```

The script creates and modifies test accounts and pupil work. Do not run against a production database. The old `integration.py` is a historical v1 suite and assumes the former teacher-review flow; use `integration_v2.py` for this release.

## Classroom acceptance

1. Create a teacher, section and pupil, and save their generated IDs.
2. Confirm the dashboard section filter shows only the selected section.
3. Complete a spoken or typed answer, then a content-only Next and practical Done.
4. Finish a lesson and confirm the next one opens without a teacher review.
5. Test microphone permissions, detected text, narration and drawing on the target devices.
6. For an upgrade, compare existing accounts and progress with the backup.
