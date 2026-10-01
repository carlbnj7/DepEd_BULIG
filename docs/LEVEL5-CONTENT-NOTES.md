# Level 5 (Listening Comprehension and Vocabulary Development) content notes

Source: the six *LEVEL 5 – LISTENING COMPREHENSION AND VOCABULARY DEVELOPMENT* modules, one per grade (First Edition 2025), kept at `storage/level5/grade-1.pdf` … `grade-6.pdf`. Every page is rendered in `storage/level5/g1/` … `g6/` for teachers.

## One module per grade

Like Level 4, each grade has its own module (`modules.grade_level` 1–6, bulig_levels id 6). A pupil sees only the module for their grade. Teachers choose the grade in Class Demo; the teaching guide, source pages and PDF download are per grade.

## How the pages are used

Every pupil page is rebuilt as native content, like the other levels:

- **Text:** it is real text. Lines that the module printed as pictures of text are read by OCR and checked against the page.
- **Pictures:** each is cut out separately and placed in the same row as its item. For example, "1. Ball [ball] a. A toy with wheels…" stays on one row, so matching still works.
- **Captions:** labels printed under a picture become its caption.
- **Layout:** the row and column layout comes from the page (`tools/level5/layout.py`) and is saved in `database/level5-pages.json`. If a teacher edits an activity's text, the plain edited text is shown instead.
- **Question pages:** pupils type or say numbered answers. Group games just need Done.

Each grade's lessons follow the module order:

| Grade | Structure |
|---|---|
| 1 | Pre-assessment, 21 topic lessons (My Body … Buildings Around Me), post-assessment |
| 2 | 2 pre-tests (Lessons 1–10 and 11–20), 20 topic lessons, 2 post-tests |
| 3 | Pre-test, story activities with questions, post-test |
| 4 | Pre-assessments 1–10 and 11–20, Activities 1–10, post-assessment 1–10, Activities 11–20, post-assessment 11–20 |
| 5 | Pre-test, Activities 1–20, post-test, second set of Activities 1–20 |
| 6 | Pre-test, Activities 1–20, post-test |

Answer keys (Grade 2 p7, p10, p14, p15, p67, p68, p73; Grade 3 p13, p43–47, p53, p58 — and the key box under questions 46–50 on p20; Grade 4 p59–69; Grade 5 p63–69) and the Grade 4 lesson guide (p50–58) are teacher-only. On Grade 2 pages where a key shares the page with test questions, the pupil image is cropped to show only the questions.

## Cards (what pupils and Class Demo show)

Every pupil page is a deck of cards in `database/level5-cards.json` (one item per card, the module order kept: story first, then the questions):

- **Story and vocabulary pages** (Grade 1 and 2, `tools/level5/storycards.py`): the poem is real text (typed from the page) and every picture is cut out on its own with the word printed under it. The page lettering is never left inside a picture, so nothing shows twice. Grade 1 pages are one flat picture, so each picture's box is set from the page; Grade 2 photos are the module's own embedded pictures.
- **Worksheets** (Grades 4–6, `tools/level5/worksheets.py`): word searches and crosswords are rebuilt as tappable letter grids from the PDF text; word banks are word chips; antonym/synonym tables are draw-a-line matching. Puzzles that cannot be retyped (linked-letter boxes, the spin wheel, crossword frames) are one worksheet picture without the page heading.
- **Stories and directions** read at normal size (a passage box); the section heading and directions sit on a small line above the card, so the activity stays in focus.
- **Questions split over two pages** are made whole on the first page.

## Source inconsistencies

- **Grade 1 and 2 cover pages:** they say "Level 4 – Vocabulary Development".
- **Grade 1 contents page:** the page numbers stop matching the PDF after "In the Garden".
- **Grade 3 activity numbering:** it skips Activity 8 and has two Activity 14 pages (Set A and Set B).
- **Grade 5 second set:** it has a second set of Activities 1–20 after the post-test.
- **No answer key:** the Grade 1 and Grade 6 modules have none.

## Rebuilding

```
python3 tools/level5/build_content.py
python3 tools/level5/storycards.py
python3 tools/level5/build_cards.py
```

It writes the page images, `database/level5-meta.json` and `database/migrations/011_level5_content.sql`. OCR results are cached in `tools/level5/ocr-cache.json`.
