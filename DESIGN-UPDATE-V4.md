# BULIG soft-green design update

For the existing Hostinger v3 installation from this conversation. No SQL import is needed for this design update. If v3 is not installed yet, install that update first (including migrations 004 and 005).

## Install

1. Back up your current application files.
2. Extract BULIG-Design-Update-v4.zip on your computer.
3. Upload the contents of `app/` into your existing `app/` folder; replace matching files.
4. Upload the contents of `public/` into the same folder where your working public `index.php` and `assets/` live. Merge folders and replace matching files. If you placed the public files directly in `public_html`, upload the public folder's CONTENTS there, not an extra nested `public` folder.
5. Keep your existing `config/database.php`, database and `assets/uploads/` files. This update ZIP contains no credentials, schema, account data or replacement user photos.
6. Refresh with Ctrl+F5. Clear your site's asset cache if your hosting/CDN still serves the previous design.
7. Sign in and choose My profile → Choose image → Save photo. The uploaded picture now appears beside the name on the home/dashboard and other signed-in pages. Pupils see their real grade and section. Teachers see their own sections; administrators see their role.
8. On a phone, use the menu button at the upper left. It opens a vertical sidebar. The logo above the page and in the sidebar is the unchanged official BULIG logo.

The login screen cannot show a personal photo before someone signs in. It displays the official logo and learning visual instead. Accounts without a photo receive a neutral green profile illustration until they upload one.

## Design

Soft sage and off-white surfaces, forest-green actions, circular profile photos, measured XP and streak cards, a prominent Level 1 card, quiet locked future levels, a learning trail, refreshed activities, achievements, profiles, teacher/admin workspaces and login. No flashing decorations or automatic animation. No external fonts or image services are required.

## Verification

Browser screenshots were produced for pupil, teacher and administrator screens. The browser script exercises actual profile-photo upload, grade/section labels, five viewport sizes (320, 390, 768, 844 and 1440 pixels), pupil home/path/profile/achievements/activities and teacher/admin home/accounts/profile. It checks image loading and page overflow before screenshots. The retained final screenshots were visually reviewed. The earlier run's temporary final console log was not retained after the workspace resumed, so this document does not assert a retained final zero-console-error result.

The original logo hash was verified unchanged. JavaScript syntax and the new SVG assets were checked. Previous learning content, five documented source-picture gaps, passwords, XP, assignments and database structure are unchanged by this design update. Live Hostinger rendering still depends on uploading the files into the existing paths.

The screenshots use disposable test accounts and a sample portrait from the module to exercise photo upload. Those accounts and uploaded test files are not included in the update.
