# Level 2 v10 content and verification notes

## Digital presentation

The original module illustrations are reused. Printed worksheets are decomposed into question cards rather than displayed as full-page screenshots. Paper actions such as circling, connecting and writing become a typed or spoken response; oral/performance activities remain teacher reviewed. This is a digital adaptation, not a formally reviewed replacement for the official modules. The source PDF links remain available.

123 activity groups correspond to 122 distinct PDF pages; Level 2B page 42 spans two lessons and has a separate continuation treatment. There are 861 cards and 769 referenced art files. Introductions bring the demo totals to 460 slides (2A) and 447 slides (2B). The existing 169 Level 2 activity records are preserved. Level 1 presentation is unchanged.

`database/level2-cards.json` is the shared source for native questions, narration, choices and image paths. `database/level2-art-provenance.json` records the original PDF/page and extraction details. `tools/content/build_level2_cards.py` rebuilds these assets from the supplied PDFs. Editing an old worksheet prompt in Lesson Studio does not rewrite this shared manifest; edit and review the relevant native card content when changing Level 2 questions.

## Source inconsistencies handled

PDF page numbers below are physical PDF pages. These are editorial adaptations for readable question/image alignment; responses remain teacher reviewed, not automatically scored.

- 2A p8: ending blanks are titled Ending sounds despite the printed middle-sound instruction.
- 2A p40: inconsistent phoneme-count wording is omitted; the ending-sound task remains.
- 2A p48: inconsistent cup/mug initial-sound wording is reframed as identifying matching initial sounds.
- 2B p14: the comb illustration uses `___omb`.
- 2B p15: sleeping uses `___eep`; the cat item uses the original cat illustration from p37.
- 2B p16: `weg` is corrected to `wig`.
- 2B p18: the shirt illustration uses `___irt` with st/sh choices.
- 2B p41: girl and ice letter alternatives are adapted to match their pictures.
- 2B pp43/58: the shrug illustration uses `___ug` with shr among its choices.
- 2B p44: the glass blank becomes `___ass`.
- 2B p48: sentence wording follows the segmented version: “Catch, go on the ship, and we got a fish!”
- 2B p50: where a printed item has no rhyming option, pupils may respond “no rhyme” and suggest a word.
- 2B p52: instructions permit more than one non-rhyming word where the printed group needs this.
- 2B p57: the globe illustration uses `___obe`.
- 2B p59: the three illustration uses `___ee` with thr/br/sh choices.
- 2B p61: pupils can explain when a pair such as foot/boat does not rhyme.

Other ambiguous source items remain open for teacher judgment. Individual image alt text and labels should not be treated as an automatic answer key. Original module ownership and source attribution remain with their authors.

## Verification

Local PHP/MySQL checks covered the existing account/profile, grade/section, starting-level, skipped-level and demo access behavior. v10 browser checks cover all 169 Level 2 activity routes, all 123 native groups, exact shared image/narration parity in both demos, all 769 image files decoded by Chromium, pupil draft restore and final submission/review, mobile/desktop widths, demo refresh deep links, fullscreen and unrestricted Next. No JavaScript errors were observed. Actual pupil and demo screenshots were inspected.

The draft restore check exposed browser CRLF normalization; the parser now normalizes line endings and retains earlier worksheet responses separately from card answers. Saved progress uses the existing parent activity IDs and schema; no v10 SQL migration is required.
