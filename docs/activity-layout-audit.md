# Activity layout audit — 7 November 2026

## Scope and evidence
Reviewed the shared activity renderers in app/level2a.php and app/level2_cards.php, the separate app/level1.php and app/level4.php renderers, and the accumulated public/assets/theme.css cascade. Reviewed reader_level.php for its role in navigation rather than treating it as an activity renderer.

Card-manifest inventory:
| Manifest | Sets | Cards | Single-picture cards | Cards with choices longer than 60 characters |
| --- | ---: | ---: | ---: | ---: |
| Level 2 (2A/2B) | 123 | 859 | 519 | 0 |
| Level 3 (3A/3B) | 58 | 561 | 386 | 0 |
| Level 5 | 320 | 1850 | 39 | 8 |
| Level 6 | 197 | 1095 | 156 | 18 |
| Level 7 | 154 | 852 | 35 | 1 |
| Total | 852 | 5217 | 1135 | 27 |

These are source-card counts, not individually visited screens or a count of visually proven defects. Level 1 and Level 4 use separate rendering paths and are not included in that manifest total.

## Findings and fixes
1. The v171 mobile single-image override explicitly used max-height:none!important and width:100%. Portrait images could grow to their full scaled height and push answers far below the prompt. A later, pupil-activity-only rule now caps image height to min(440px,55vh), and clamp(180px,45vh,320px) below 480px. Aspect ratio and full image contents are preserved with contain.
2. Shared answer chips lacked an explicit maximum width and emergency word wrapping. Long choices and narrow containers could overflow. Chips now stay within the container, wrap text, and long choices grow into full rows on narrow phones.
3. The shared navigation row was a non-wrapping flex row. Back plus longer Next labels could compress or extend beyond narrow cards. It now wraps with a 140px preferred button basis, maintains readable text, and keeps 48px minimum navigation height on narrow screens.
4. Crossword clue columns used a fixed 220px minimum even when their parent could be narrower. The minimum is now capped to 100% of available width. Existing puzzle scrolling is retained.
5. Picture-plus-speaker wrappers now occupy their grid cell consistently. Saved-audio controls are constrained to their parent width. Card and matching children can shrink within their container.

## Preserved
Existing colours, gradients, icons, fonts, button shapes, scoring, activity content, recognition and recording logic are unchanged. Class Demo is excluded from the new layout overrides. Body hotspot dimensions and positions are unchanged.

## Validation
- node tests/audit-regressions.cjs: PASS.
- node tests/body-hotspots.cjs: PASS (seven non-overlapping targets at five widths).
- ZIP CRC/integrity check: PASS during packaging.
- No new PHP changes in this layout follow-up.
- Browser rendering and click testing remain unverified: Chromium in this workspace was blocked from creating its process socket.
- Full PHP/MariaDB integration suite not run in this partial checkout.

## After upload: required visual checks
At 320, 375, 390, 768 and 1366 CSS pixels, open representative single-picture, multi-picture, long-choice, matching, crossword, story-reading and writing cards across grades. Check no page-level horizontal scrolling; pictures remain identifiable; all choice text is readable; Back/Next/Submit remain visible and usable; puzzle areas scroll internally; the microphone dock does not obscure answers; audio controls fit; the body mouth and both arms select correctly.
Also check portrait/landscape and increased text size. Text-heavy scanned charts may still need individually transcribed content; this audit does not claim to have repaired every chart or every unseen activity.

## Installation
Merge app/, database/ and public/ from the combined ZIP into the existing project. Keep existing files not present in the archive. No SQL migration. This is an update package, not a full installation. Refresh cached CSS after uploading.
