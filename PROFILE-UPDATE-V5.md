# BULIG v5: pupil details and profile pictures

For a working BULIG v4 installation on Hostinger. Keep the existing logo and green design.

## Update your existing website

1. Back up your website files and export your current BULIG database in phpMyAdmin.
2. Extract `BULIG-Profile-Update-v5.zip` on your computer.
3. In Hostinger, open phpMyAdmin for the **same database used by your working BULIG website**. Select that database, choose Import, and import `database/migrations/006_pupil_details.sql`. This creates a new pupil-details table; it does not reset accounts, passwords, activity content, or progress. It is safe to import this migration again. Do not import a fresh-install SQL file into your existing database.
4. In File Manager, replace the three matching files inside your existing `app` folder: `actions.php`, `bootstrap.php`, and `views.php`.
5. Replace `public/assets/design.css` with the supplied file. Copy the entire supplied `public/assets/avatars` folder (all six PNG files) to the corresponding assets folder on your website. If your site's public files live directly in `public_html`, put these at `public_html/assets/design.css` and `public_html/assets/avatars/`. Follow your existing folder layout rather than adding another `public` folder.
6. Keep your current `config/database.php` and the contents of `assets/uploads`. No password or database-connection changes are required.
7. Reload the website. Teacher → Pupils: create or edit a pupil and choose Male/Female; enter their LRN when available. Pupil → My profile: select an avatar and press **Use this picture**, or choose a personal image and press **Save photo**.
8. Check the pupil dashboard: the selected picture should appear beside the name. If an avatar is missing, verify that all six files are in the matching `assets/avatars` directory with their original lowercase filenames.

## Behavior

- Male/Female is required when creating a pupil; existing records remain unset until supplied by the teacher.
- LRN is optional when unknown. A supplied value must contain exactly 12 digits and be unique. Leading zeros are retained. It does not replace the pupil's login ID.
- Three boy and three girl avatars are available to every pupil. Any character can be selected; the teacher's Male/Female field does not restrict the picker.
- Choosing an avatar or uploading a photo updates the same profile picture across the dashboard and sidebar. Upload supports PNG, JPEG, and WebP up to 4 MB.
- Default pupil password remains `12345678`; changing it remains optional. Teacher starting-level assignment and locked future levels remain available.

The LRN format follows [DepEd Order No. 22, s. 2012](https://www.deped.gov.ph/wp-content/uploads/2012/03/DO_s2012_22.pdf).

The patch contains only the changed application files, avatars, migration, and these instructions. Use the full project ZIP only for a separate new installation.
