# Teacher Class Demo v8 validation

Test environment: isolated PHP 8.3 / MariaDB 10.11 and Chromium. The live Hostinger site was not accessed.

- All PHP files pass syntax checks; JavaScript passes Node syntax checking.
- The demo pulls published activities directly from the pupil content tables, excluding unpublished lessons and activities in its query.
- Browser comparison of all 394 slides against database title, prompt, and narration values: 225 Level 1, 103 Level 2A, 66 Level 2B.
- All 243 distinct resolved image paths decoded successfully in the browser.
- Classroom slides contain no answer forms, textareas, speech-recognition controls, submission controls, or XP widgets.
- Teachers can jump directly to all slides without pupil completion records. Pupils and administrators receive 403 on this teacher-only route. Unpublished future levels and cross-level slide IDs return 404.
- Previous/Next, first/last boundaries, left/right keyboard navigation, picture enlargement, deep-link refresh, and fullscreen retention across slide changes passed browser checks.
- Narration controls invoke the exact slide narration, and slide changes cancel earlier speech. Speech synthesis calls were captured in the test; actual voices, speaker output and TV audio must be checked on the presentation device.
- Screenshots at 390, 1366 and 1920 pixels; representative mobile and TV layouts inspected. No horizontal page overflow or JavaScript page errors in the tested flows.
- Checksums of activity_completion, activity_drafts, pupil_progress, xp_transactions, pupil_xp, learning_days, pupil_streaks, pupil_badges, pupil_rewards, assessment_attempts, response_history and reading_assessments remained unchanged after demo navigation.
- The 38 existing account/profile/avatar HTTP checks and existing grade/section dashboard and pupil browser regression checks passed.

Original worksheet images remain whole pages. Use Enlarge pictures and scroll for small print or long pages. The demo does not introduce new content for Levels 3–7, grade answers, or change the existing content limitations documented in the v7 audit.
