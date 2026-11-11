# BULIG v7 validation — 18 September 2026

Tested on an isolated PHP 8.3 / MariaDB 10.11 database and Chromium browser. No live Hostinger database was accessed.

- PHP syntax checked across the project.
- v6 database imported, then migration 007 applied and reapplied. Counts remained unchanged: 394 activities and 58 total learning steps (12 original, 22 Level 2A, 24 Level 2B).
- All 169 new activity screens exercised through authenticated HTTP requests: intro navigation, worksheet responses, required nonempty answer/drawing, draft saving/restoration, sequential access, finishing each learning step, and duplicate-submission XP protection.
- Level 1 completion unlocks 2A; finishing 2A unlocks 2B. Teacher-assigned starts at 2A and 2B bypass earlier levels. Uninstalled Levels 3–7 remain locked. Grade and starting level remain independent.
- Four new pre/post worksheet attempts record completion with no fabricated correctness score. Level 2 work does not award the Level 1 completion badge.
- Reimport after an activity edit and pupil submissions preserved both the edit and saved responses.
- Pupil requests for oral answer-key pages and PDF downloads rejected; teacher access to the correct Level 2 source and guide succeeded.
- Every database activity image path exists, including existing Level 1 assets. All 329 new protected source renders exist.
- Existing 38 account/profile/avatar HTTP regression checks passed.
- Existing browser checks passed: grade-separated rosters, dependent section dropdowns, invalid grade/section combinations rejected, pupil/login/teacher layouts at 320, 390 and 1440 pixels.
- New browser checks passed at 320, 390 and 1440 pixels: worksheet image loading, no horizontal page overflow, enlargement, scroll/pen switch, typed draft restoration, drawing draft restoration and submission, and teacher worksheet/pen overlay.
- No JavaScript page errors or PHP warning/fatal/500 responses in the final test run. Representative mobile and desktop screenshots inspected.

## Practical limits

These are faithful original-page worksheet activities with text/voice-to-text and drawing responses, not individually auto-graded questions or drag-and-drop games. The app checks that a response exists; it does not establish that every printed item has been answered correctly. Original teacher-led oral assessments are available separately. Browser microphone services and installed TTS voices vary by device; real-device microphone accuracy and Hostinger deployment require testing there. Existing Level 1 teacher-supplied picture/prop requirements remain documented in its earlier audit.
