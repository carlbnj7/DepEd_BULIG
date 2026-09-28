# BULIG v8 — Teacher Class Demo

This update adds a teacher-only slideshow using the same published content and image files as the pupil activities. It does not copy the lessons into a separate editable content set. Later published activity edits therefore appear in both modes.

## Install on your existing Hostinger site

1. Back up the matching website files.
2. If you have not installed the Level 2A/2B v7 update yet, install that update and import its `007_level2_content.sql` first. Class Demo itself needs **no additional SQL import**.
3. Download and extract `BULIG-Teacher-Class-Demo-v8.zip`.
4. Merge its folders into `public_html`, using the same layout shown in your screenshots. Replace matching files:
   - `app/views.php`
   - `app/teacher_demo.php` (new file)
   - `public/index.php`
   - `public/assets/app.js`
   - `public/assets/design.css`
5. Keep your existing `config/database.php`, database, original image folders, and pupil uploads. The patch does not include or replace them.
6. Open the website and sign in as teacher. The dashboard now has an **Open Class Demo** button, and the sidebar has **Class Demo**.

The patch is for v7. If v7 is already installed, upload the files only. Do not reimport a fresh-install SQL file. Uploading the earlier v7 application files after this patch would overwrite the new demo feature; apply v8 last.

## Present to the class

1. Connect your computer to the TV or projector and mirror or extend its screen.
2. In the teacher dashboard, choose **Open Class Demo**.
3. Choose Level 1, Level 2A, or Level 2B.
4. Choose **Full screen**. If using an extended display, first move the browser window onto the TV.
5. Choose **Listen** for text-to-speech; Pause, Replay, Stop, Mute, and the voice selector remain available.
6. Let pupils answer aloud or discuss as a class. The teacher decides whether an answer is right.
7. Choose **Next** or **Previous**, or use the right/left arrow keys. No answer entry, microphone recognition, grading, or submission is required.

Use **Learning step** or **Jump to slide** to open any published activity, including pre/post assessments. There are no pupil progression locks in Class Demo. Home/End move to the first/last slide; F toggles fullscreen when a dropdown is not focused. Moving slides stops the previous narration and retains fullscreen. Refreshing keeps the selected slide in the URL.

Use **Enlarge pictures** when the original page has small text; scroll through the enlarged picture and choose **Fit pictures** to return. Original worksheet pages retain their original layout, so long pages or long prompts may need scrolling. Worksheet directions can be expanded separately.

Choose **Exit demo** to return to the level menu. Levels 3–7 show Coming soon until their content is published. Nothing is fabricated for those levels.

## What changes — and what does not

- Level 1: 225 slides. Level 2A: 103 slides. Level 2B: 66 slides.
- Slides use the pupil activity's title, question/prompt, original images, answer choices where applicable, and narration. The generic instruction to type/submit is replaced by a classroom discussion instruction where applicable; no correct-answer key is exposed as an answer selection.
- Existing pupil lesson screens, answers, locks, XP, streaks, and teacher assignment rules continue normally. Demo does not write to those records.
- Only a signed-in teacher can access the demo route. Pupils cannot use its unlocked navigation.
- Narration remains the installed pupil narration, not newly recorded audio or OCR narration of all words printed inside worksheet pictures. Available voices and sound output depend on the presenting computer. Check that audio plays through the TV before class.

## Validation

All 394 slide titles, prompts, and narration strings were compared against the live test database. All 243 distinct resolved image paths loaded in Chromium. Previous/Next, unrestricted jumps, boundaries, fullscreen retention, keyboard navigation, enlargement, and refresh links were exercised. Checksums of 12 pupil-learning tables stayed unchanged after demo use. Teacher-only access and invalid level/slide rejection were tested, along with existing account/avatar and grade/section dashboard regressions. See `docs/V8-VALIDATION.md`.

This was tested locally. The files still need to be uploaded to your Hostinger site.
