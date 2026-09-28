# BULIG v11 — Level 1 pictures and lesson labels

For your existing working BULIG installation with Level 1, Level 2A/2B and Teacher Class Demo. This ZIP also includes the previous v10 Level 2 illustration fix, so you do not need to upload v10 separately.

## Upload this update on Hostinger

1. Download **BULIG-Pictures-and-Lessons-v11.zip** and back up your current website files.
2. Open Hostinger File Manager. Go to the running BULIG folder containing `app`, `config`, `database` and `public`—in your screenshot, this is `public_html`.
3. Upload the ZIP there. Extract into that same folder and allow replacement of matching files. Merge the folders; do not delete your current folders. Do not create an extra folder around the extracted files.
4. Confirm `app/presentation.php`, `database/level1-visuals-v11.json`, and `v11-...webp` pictures inside `public/assets/images/level1/lessonXX/` are present.
5. Reload BULIG. Check Level 1 → Lesson 5 → the ice-cream question; Lesson 6 → the book-on-table picture and drawing discussion; Lesson 12 → “Six snails slide slowly.” Check the same activities in Teacher Class Demo.
6. Open the lesson lists for Levels 1, 2A and 2B. They now say **Lesson 1, Lesson 2…**. Level 2 pre/post assessments are labelled separately so the first teaching lesson is Lesson 1.
7. Remove the uploaded ZIP from the website after extraction; keep your downloaded copy.

**No SQL import, database reset, CMD command, or database-password change is needed.** The update contains no connection configuration. Existing account records, answers, progress, grades, sections and starting-level assignments are preserved.

If you previously moved `public/assets` directly to `public_html/assets`, merge this ZIP's `public/assets` into your existing `assets` location instead. Keep the folder layout used by your working installation.

## What changed

- Reviewed all 225 Level 1 activity records and corrected the picture mappings for 70 activities.
- Corrected examples: ice cream instead of a picnic; forest instead of a festival; specific chair/door/book actions instead of the same six pictures everywhere; actual toys for toy discussions; a cat for cat word association; exactly six snails for the six-snails sentence.
- Added missing scenes including an apple tree, boy playing with a ball, book on a table, and rainbow over a lake.
- The picture revealed after the listening/drawing activity matches the red bird, yellow beak, green branch, yellow five-petal flower and blue sky. The picture stays hidden during the initial listening/drawing task.
- Original illustrations are retained where appropriate. Fourteen supplemental illustrations were created for missing or unsuitable scenes; they are not represented as original DepEd artwork.
- Several vocabulary/picture packs and story sequences now show the individual original illustrations rather than the full printed page.
- The pupil and teacher demo views share the corrected images.
- Lesson labels are consistent in lesson maps, activity headers, teacher demo, teaching-guide selector and Lesson Studio. References to the steps of a physical instruction remain unchanged.
- Available optional lessons now have an open-book icon instead of a misleading padlock.

## Notes

Personal, spoken and physical activities may intentionally have no fixed illustration. Previously displayed unrelated pictures have been removed from those tasks. Original PDF reference pages remain accessible.

Custom activity text or pictures saved through Lesson Studio are protected: the stock correction only applies to matching original prompts at revision 1. If you customized a question and still see a mismatch, update that question's image in Lesson Studio.

The update is tested locally, not deployed to your Hostinger account. If the old interface remains, check you replaced the files in the running project's folder; clear Hostinger's site cache if enabled. The stylesheet/JavaScript URLs now use `v=11`.

For maintainers: `docs/LEVEL1-V11-VISUAL-AUDIT.json` lists each reviewed activity; `database/level1-visuals-v11.json` records mappings, image sources, alt text and the built-in generation prompts. Activity IDs and database schema are unchanged.
