# Level 3 (Word Recognition) content notes

Source: *LEVEL 3 WORD RECOGNITION Version 1.pdf* (144 pages, First Edition 2025), kept at `storage/level3-original.pdf`. All 144 pages are rendered in `storage/3/` for teacher/admin reference.

## What is in the app

27 learning steps, 172 activities, 58 picture/question decks (561 cards) and 484 pictures cut from the module.

| Step | Module part |
|---|---|
| Pre-assessment | Level 3 Pre-Assessment Toolkit (4 word-reading tasks) |
| Lessons 1–5 | CVC short a, e, i, o, u: teacher's activity, Build a Word, Odd Word Out, Tell Me the Word, Speed Read, assessment |
| Lessons 6–10 | Word families ea/ai, oa/oo, ack/eck, all/ell, -nk/-sk: teacher's activity, Stations 1–4, assessment |
| Lessons 11–20 | Consonant blends br-/bl- … tr-: teacher's activity, the module's activities, assessment, supplementary activity materials |
| Lessons 21–25 | Basic sight words (Fry lists 1–5): word list, game, reading phrases, reading sentences, assessment |
| Post-assessment | Level 3 Post-Assessment Toolkit (same 4 tasks) |

Wording, word lists, activity order and pictures follow the module. Supplementary activity materials come after each lesson's assessment, as in the module, marked “Extra practice”. Word charts on unlabeled pages become a “Read more blend words” step in the teacher's activity.

## Digital adaptation

- Paper tasks (circle, connect with a line, colour, write, cut and glue) become a typed or spoken answer on each card. Answers are saved for teacher review; they are not auto-marked.
- Word, phrase and sentence reading uses Speak Answer. Voice-to-text word matching is a guide only, not a pronunciation score. Teachers confirm the module's passing scores (8/10, 15/20) from observation.
- Group games (dart boards, word maps, baskets, clotheslines, fishing, snake and ladder, spin wheel, mystery box, word hunt) show the module picture and procedure; the pupil chooses Done after their turn.

## Build a Word (Lessons 1–5, Activity 1)

The module's two rows of letter boxes are real buttons. The pupil taps them in order; each letter flies onto the next
blank line. A full word is checked at once: right turns green, wrong shakes and clears for another try. Tapping a letter
on a line takes it back. The saved answer keeps earlier tries for the teacher, for example `car (tries: rat)`.

The picture is shown without the printed boxes (`l0N-build-NN-pic.webp`); the original pictures stay in the folder.
The boxes and the 50 answers were read from the module pages (PDF pages 14, 20, 26, 32 and 38). Every answer takes one
letter from each column. Notes: Lesson 1 card 1 is "car" (not a short "a" sound, kept as printed); Lesson 3 card 10 is
"sim" (SIM cards).

## Source inconsistencies (also listed in the admin Content issues page)

- Lesson 3 Speed Read (PDF p30) prints “sim”; the lesson uses “hid”. Kept as printed.
- Lesson 6 assessment (PDF p46) lists “head”; the stations use “hair”. Kept as printed.
- Lesson 9 Station 1 (PDF p54) lists “sell” twice and omits “well”. Kept as printed.
- Lesson 10 teacher step 2 (PDF p58) says “all” and “ell”; the intro here says -nk and -sk. Its assessment lists “flask” twice (kept).
- Lesson 11 supplementary materials are numbered 1 and 3 (no 2). Kept.
- Lesson 15 Supplementary Material 3 (PDF p102) says “cl- and cr-”; the pictures are gl-/gr-, so the direction here says gl- and gr-.
- Lesson 25 prints two “Activity 2: Reading Phrases” lists (PDF p139). Both are included.
- Level 3 is one level (only Level 2 has 2A and 2B). The module's toolkit page headings are shown as “Level 3”, and the four toolkit tasks are numbered 1–4.
- The module's References credit many pictures to AI image generators and Canva. Pictures are reused as supplied; ownership stays with their sources.

## Rebuilding

```
python3 tools/level3/extract_images.py storage/level3-original.pdf   # pictures + page renders
python3 tools/level3/build_content.py                                # cards JSON + SQL migration
python3 tools/level3/build_tiles.py                                  # Build a Word boxes, answers, pictures
```

Needs PyMuPDF, Pillow and NumPy. `database/level3-images.json` records the PDF page and position of every picture.
