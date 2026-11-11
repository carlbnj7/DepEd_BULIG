# Official Level 1 content audit

Current per-activity image mapping: `ACTIVITY-VISUAL-AUDIT.csv`. See `V3-VALIDATION.md` for repaired picture assignments and the five exact compositions still requiring approved classroom pictures.

Version 2 progression note: teacher observation and rubric feedback are optional and never block learning. Open responses record completion; content pages use Next and practical tasks use Done. See `V2-CHANGES.md` for corrected visual mappings.

Source: supplied 95-page PDF, **Level 1 – Oral Language**, First Edition 2025, Department of Education – Division of Bukidnon. The original file is included unchanged. PDF page numbers below count the file pages, not the printed page numbers.

| Lesson | Official topic | Pre-test PDF page | Main visual pages | Lesson-plan pages | Post-test PDF page |
|---|---|---:|---|---|---:|
| 1 | Talking About One’s Self and One’s Family | 7 | 16 | 47–49 | 87 |
| 2 | Follow One-to-Two-Step Physical Directions | 8 | 16–17 | 50–52 | 88 |
| 3 | Give and Follow One-to-Two-Step Verbal Directions | 9 | 17 | 52–55 | 89 |
| 4 | Recognize Describing Words | 10 | 18–22 | 56–59 | 90 |
| 5 | Talk About Topics of Interest | 11 | 23–25 | 60–62 | 91 |
| 6 | Describe and Draw | 12 | 25–26 | 63–65 | 92 |
| 7 | Using Story Chain | 13 | 26, 67–70 | 66–71 | 93 |
| 8 | Using Picture Talk | 13 | 27 | 72–74 | 93 |
| 9 | Using Poems | 13 | 28–31 | 75–77 | 93 |
| 10 | Talk, Play and Share | 13 | 32 | 78–80 | 93 |
| 11 | Using Word Association | 14 | 33 | 81–83 | 95, by competency |
| 12 | Using Tongue Twister | 14 | 33–34 | 83–85 | 94, by competency |

## Source inconsistencies recorded in the admin interface

1. Final post-tests reverse the Lesson 11 and 12 headings. The import associates **Word Association with Lesson 11** and **Tongue Twisters with Lesson 12**, matching the lesson plans, visuals, and pre-tests. Original page headings are preserved. This interpretation needs the content owner’s confirmation.
2. Lesson 2 pre-test labels “Stand up” as a two-step task. The source wording is retained.
3. The additional asking-questions activity describes both a 20-question and a 10-question stopping point. Both remain in the source checklist.
4. Personal responses and oral/group/physical performance have no fixed answer key. No invented correct answers are seeded.
5. Some lesson plans ask for real props or example pictures without supplying a separate image for that specific prompt. Teachers provide them. The original pages are retained rather than substituting unrelated artwork.

## Preservation method

- 95 pages were rendered at 1.25× PDF resolution for exact layout reference, including text embedded in illustrations.
- All 210 distinct embedded images were extracted with their original encoded bytes and dimensions. `database/source-pages.json` records page association, image path, source object, dimensions, and original placement rectangle.
- Full page text, image metadata, original lesson-plan text, objectives, assessment text, activity prompts, image paths, narration, and source-page references are imported into MySQL.
- Bitmap-only text remains visible in the source image; no unreliable OCR output is used as an answer key. Narration reads the structured prompt, not every word embedded in a bitmap.
- Original branding and source pages retain their original graphics. New application controls use SVG icons instead of emojis. The original PDF may itself contain emoji symbols in teacher checklists, which have not been erased from the source.
- The supplied activity screenshots guide the green header, cream canvas, spacious text, outlined answer card, and green Next button. They are not substituted for educational content.

## Digital adaptation boundary

The application offers interactive text responses, sentence starters, microphone transcription, drawing, saved submissions, and teacher review. It preserves complete classroom procedures through teacher-guided source checklists. Those checklists include activities requiring peers, movement, props, and homework; they are not falsely presented as autonomous computer-marked exercises.

A teacher should review each lesson before use, confirm the supplied visuals and requested props, and record decisions for the documented source ambiguities. Browser word matching does not assess eye contact, physical actions, collaboration, or pronunciation.

Original PDF SHA-256: `6f9987e7226af75e10503c258b3327923bf8a415a9971f9ba8bd18105a5cdf98`
