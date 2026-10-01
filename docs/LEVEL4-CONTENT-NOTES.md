# Level 4 (Fluency) content notes

Source: the six *LEVEL 4 FLUENCY* modules, one per grade (First Edition 2025). They are kept at `storage/level4/grade-1.pdf` … `grade-6.pdf`. Every page is rendered in `storage/level4/g1/` … `g6/` for teachers.

## One module per grade

Levels 1–3 use one module for every grade. Level 4 has a separate module for each grade:

- Each grade's module is its own row in `modules`, with `grade_level` set to 1–6.
- A pupil sees only the Level 4 module for their own grade (`pupils.grade_level`). The pupil's lesson list, availability, next lesson and source pages all apply this filter (`GRADE_SQL` in `app/bootstrap.php`).
- Levels with `grade_level` NULL are shared by all grades, so nothing changes for Levels 1–3.
- **Class Demo:** the teacher picks a grade after opening Level 4.
- **Teaching guide, lesson studio, source pages and PDF download:** these show the grade, for example "Level 4 · Grade 3".

Levels 5–7 can use the same approach: give each grade's module its `grade_level`.

## What is in the app

| Grade | Lessons | Passages |
|---|---|---|
| 1 | 12 | Pre-test *The Cat*, 10 practice passages, post-test |
| 2 | 12 | Pre-test *The Picnic*, 10 practice passages, post-test |
| 3 | 12 | Pre-test *The Ant and The Fly*, 10 practice passages, post-test |
| 4 | 12 | Pre-test *My First Baseball Game*, 10 practice passages, post-test |
| 5 | 10 | Pre-test *The Crocodile and the Monkey*, 8 practice passages, post-test |
| 6 | 11 | Pre-test *"Bad Girl"*, 9 practice passages, post-test |

That is 69 lessons, 81 activities and 80 pictures cut from the modules.

Each passage is one lesson, laid out like the module page: its picture, title, author and text, plus the module's "Number of Words in the Passage".

- **Pupils:** they tap **Start reading**, read aloud, then tap **I'm done**. The reading time and words per minute are saved with their response. Speak Answer listens continuously and shows a word-match guide only.
- **Teachers:** the teaching guide for every passage includes the module's Phil-IRI procedure from pages 6–8: marking miscues, the Oral Reading Score and the Reading Speed.
- **Class Demo:** it adds a scoring helper where the teacher enters miscues and seconds. It shows the score, the reading level (Independent, Instructional or Frustration) and words per minute. Nothing from the helper is saved.

## Source inconsistencies (also listed in the admin Content issues page)

- **Grades 1–2 word counts:** the "Number of Words" includes the title words. The module's numbers are kept for scoring.
- **Grade 2 grey boxes:** PDF p12, p16, p23 and p26 have stray grey drop-shadow boxes. They are left out of the pictures.
- **Grade 3 "A Robot Dog" (p20):** it starts mid-story ("walked and walked…"). Kept as printed.
- **Grade 4 baseball word count:** "My First Baseball Game" prints "(251 Words)" in the text, but its table says 255. The table is used.
- **Grade 4 p10 and p17:** p10 repeats the baseball continuation. The table of contents lists p17, which holds that same continuation, as "Songs of the Witches II". p10 is not used, and p17 is shown as "My First Baseball Game II".
- **Grade 5 "Jimmy Jet":** the table says 150 words; the poem has 144.
- **Grade 6 "The Crocodile and the Monkey":** the table says 435 words; the story has about 490.
- **Grade 6 "Be Glad Your Nose…":** the table says 140 words; the poem has 132.
- **Grade 6 "Bad Girl" and song lyrics:** "Bad Girl" mentions smoking and gambling. Several passages are song lyrics (Fight Song, You Learn, I Wanna Do Right, Verb To Be). They are included as in the module, and ownership stays with their holders.

## Text accuracy

- **Grades 1–2:** text comes from the PDF text layer and is exact.
- **Grades 3–6:** text comes from the text layer where it is complete, and from OCR otherwise.
- **Checking:** each passage was checked against its page image. Hand corrections are in `FIXES` and `typed.json` in `tools/level4/`.

## Rebuilding

```
python3 tools/level4/extract_passages.py   # passage text -> database/level4-passages.json
python3 tools/level4/extract_images.py     # pictures + page renders -> database/level4-images.json
python3 tools/level4/build_content.py      # database/level4-meta.json + migrations/010_level4_content.sql
```

These need PyMuPDF, Pillow, NumPy and tesseract-ocr.
