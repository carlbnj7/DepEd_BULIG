> Superseded findings: version 3 restores the module dog photo (image-210.jpeg), repairs picture assessments and fixes spider-poem order. The v2 claim that no suitable dog photo existed was incorrect. Use ACTIVITY-VISUAL-AUDIT.csv for current assignments.

# V2 change and content audit

## Root causes fixed

1. `phase_available()` used teacher-reviewed assessment status, and `refresh_progress()` required every response to be approved. These gates are replaced with sequential completed-activity checks. An explicit Finish Lesson endpoint verifies completion and unlocks the next lesson.
2. All pages used a required response field. `activities.response_mode` now distinguishes answer, informational, practical, and drawing interactions. The server enforces the mode rather than trusting a client flag.
3. Open personal answers were treated as awaiting correctness review. They now become recorded completions without a numeric correctness claim. Exact answers are validated immediately, with unsuccessful attempts retained and no XP granted until correct.
4. Sections were unstructured text. Teacher-owned section records and pupil membership now support creation, renaming, reassignment, and SQL filtering, with server-side ownership checks.
5. PDF image filters excluded images extending past arbitrary crop boundaries. Lesson 3 pictures ended below the old cutoff; the second Lesson 10 image ended at about 771 PDF points and was omitted by a 770-point limit. Both are restored.
6. The PDF’s internal image enumeration differed from visual reading order. Lesson 2 now includes the full six-panel action sequence in visual order. Lesson 5 no longer displays an unrelated child illustration for the dog prompt. Lesson 12 tongue-twister illustrations are matched individually to Peter, Betty, and snails.
7. Fixed-height image covers cropped official visual content. Lesson imagery now uses proportional containment, with larger images available by tapping.
8. Drafts did not retain speech transcripts separately. Typed and recognized responses now restore together.

## Lesson-by-lesson checks

| Lesson | Verification / correction |
|---|---|
| 1 | Original family picture and all four sentence starters preserved; personal answers complete immediately. |
| 2 | Full action-panel sequence restored in visual order; practical steps offer Done without typing. |
| 3 | Missing messy-room/scenario illustrations restored; original scenario wording preserved. |
| 4 | Toolkit pages preserved; five pre-test answers explicitly given by the prompts are checked as red, big, round, tall, short. |
| 5 | Picture-card pages preserved; unrelated dog-prompt image removed and the required teacher-supplied example noted. |
| 6 | Drawing controls retained; model image stays hidden during the listening/drawing instruction and appears for comparison afterward. |
| 7 | Story-chain visuals and all group source pages preserved; illustrative reading pages offer Next. |
| 8 | Both picture-talk scenes preserved, with original prompts and personal connections. |
| 9 | Poem images and recitation text preserved; speech matching remains transcription feedback rather than pronunciation grading. |
| 10 | Both original scene illustrations are present; practical station activities offer Done. |
| 11 | Original Word Association visuals and connections preserved; personal associations are not restricted to invented answer keys. |
| 12 | Peter/peppers, Betty/butter, and snails images matched to their respective prompts; source post-test heading mismatch remains explicitly flagged. |

The original 95-page PDF and original BULIG logo are unchanged. The established 225 activity IDs and 24 assessments are preserved. The module’s text, teacher-led source checklists, supplementary material, and documented ambiguities remain available; this update does not invent content for later levels.

## Responsive changes

Larger touch controls, more readable labels, a highlighted current lesson, a focused mobile learning path, wrapped forms, contained tables, proportional module images, visible answer feedback, and distinct Listen / Speak Answer / Type Answer controls. Historical source-page graphics remain untouched; interface icons use the existing SVG icon set.

## Limits

The module does not supply every real object or example image mentioned in its lesson plans. Those props still come from the classroom. Personal, physical, and group tasks are recorded as participation; no automated claim of correctness or observed performance is made.
