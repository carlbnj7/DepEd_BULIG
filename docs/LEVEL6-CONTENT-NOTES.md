# Level 6 (Graded Reading Comprehension) content notes

Source: the six *BULIG Level 6 – Graded Reading Comprehension* modules (V2), one per grade, kept at `storage/level6/grade-1.pdf` … `grade-6.pdf` (bulig_levels id 7). Every page is rendered in `storage/level6/g1` … `g6` for teachers.

| Grade | Lessons |
|---|---|
| 1 | Pre-test (4 sections), 20 stories (noting details, reality/fantasy, sequencing, cause and effect, conclusions), post-test |
| 2 | Pretest, Activities 1.1–5.4, post test (same items as the pretest); lesson guides are teacher-only |
| 3 | Pre-test (4 stories), 20 days (Days 6–20 have two stories each), post-test |
| 4 | Pre-assessment (5 stories), Activities 1–20, post-assessment |
| 5 | Pre-assessment (6 selections), Activities 1–10, formative assessment, Activities 11–20, formative assessment |
| 6 | Pre-assessment (5 stories), Activities 1–20, post-assessment |

Each story is one activity: a story card (text, its pictures cut out without page text, key words) and then one card per question. Speed-reading stories carry a timer (words from the module, or counted). Matching (Grade 1 cause and effect) uses the draw-a-line board.

## Rebuilding

```
python3 tools/level6/build.py
```

The card text comes from `tools/level6/script/gN.txt`: drafted once from the PDF text (`draft.py`, `cleanup.py`) and then corrected by hand against the pages. Do not re-run the draft over the scripts. `compact.py N` prints a one-line-per-card proof.

## Source notes

- Printed typos are kept as in the module (for example Grade 6 “Daphne”: “she looked back with threw herself”).
- Grade 1 post-test numbers its first items 1, 6, 7, 8, 9; they are shown as 1–5.
