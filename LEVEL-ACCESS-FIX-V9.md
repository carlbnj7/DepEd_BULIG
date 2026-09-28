# BULIG v9 — assigned starting levels and optional skipped levels

## Corrected behaviour

For a pupil assigned to start at Level 2B:

- Level 2B opens immediately; the pupil does not have to finish Level 1 or 2A.
- Level 1 and Level 2A show **Skipped · optional practice**, with an **Explore skipped level** button.
- The pupil may open any lesson in those skipped levels. Activities within a chosen lesson still follow their normal sequence.
- Continue learning points to the assigned Level 2B path, not unfinished optional earlier levels.
- Skipping does not fabricate answers, lesson completions, XP or scores. Existing completed levels remain labelled Completed.
- Later levels still require the preceding levels from the assigned starting point. Levels 3–7 remain unavailable until their content exists.

The earlier code explicitly rejected levels below the teacher's assignment. That access bug is fixed. The old Coming soon label also depended on the database publication flag: an absent Level 2 import or unpublished installed content could still block an assigned level. The supplied repair SQL handles both installation states. Missing Level 2 setup is now labelled **Content setup needed**, rather than Coming soon.

## Install this fix on Hostinger

1. Back up your current website files and export your existing BULIG database in phpMyAdmin.
2. Download and extract **BULIG-Level-Access-Fix-v9.zip** on your computer.
3. Upload/merge its `app`, `public`, `database`, `storage`, `tools`, and `docs` folders into the corresponding folders in `public_html`. Replace matching files; do not create an extra nested project folder.
4. Keep your existing `config/database.php`, `public/assets/uploads`, and the separate root `public_html/index.php`. They are not included in this patch.
5. Open phpMyAdmin and select the same existing database used by your BULIG site.
6. Choose Import and select **`database/repair_level2_v9.sql`** from the extracted ZIP. Run this one import and wait for success.
7. Open the pupil dashboard again. A pupil already assigned Level 2B should now have Level 2B open and the earlier levels available as optional practice. You do not need to recreate the pupil or reassign the level.

**Import only `repair_level2_v9.sql` for this fix. Do not import `schema.sql` or any fresh-install file over your existing database.**

The combined repair installs missing Level 2 content using the tested additive import and enables Levels 2A/2B only when their expected published lessons and activities exist. It does not delete accounts, responses, uploads, or progress. Rerunning it does not duplicate the content. It deliberately restores availability for the supplied Level 2 content; custom unpublished/incomplete lessons are not forced to publish.

This package includes the Level 2 images, source files and the teacher Class Demo feature, so it also repairs missing files from those updates. It assumes the earlier v6 database structure (including pupil details migration 006) is installed, as in your previous successful import. No database credentials are included.

## Verified locally

Assigned Level 2B opened immediately. Level 1/2A cards showed Skipped and allowed optional lesson access, including later lessons. Continue learning stayed on 2B. Ordinary start-at-Level-1 pupils still could not jump ahead. No fake progress or XP was created. Both missing Level 2 content and unpublished Level 2 flags were repaired; repeated imports kept counts and accounts intact. Browser checks covered the dashboard at mobile and desktop sizes, and earlier account/avatar and grade/section regressions passed.

The package has not been uploaded to your live hosting. If a setup error remains after the import, copy the exact BULIG diagnostic line from that screen.
