# BULIG performance patch — 7 November 2026

Baseline: dev-branch 1c9a80303d98438490df052fe400b519c7067b6c.

Confirmed code issues corrected:
- public/assets/app.js: matching-board MutationObserver rescanned the whole document after every child-list mutation, including timer text, counters and its own SVG paths. It now scans newly inserted element subtrees, ignores text and existing-board changes, and initializes directly inserted boards. Matching behavior and appearance remain unchanged.
- public/assets/offline.js: both pupil and teacher transaction helpers opened an IndexedDB connection per operation without closing it. Connections now close on completion, failure, abort and synchronous exceptions; rejected transactions settle rather than hanging. Answers are still acknowledged and deleted using the original rules.
- public/assets/bug-updates.js: while a popup/coach blocked notifications, repeated triggers could schedule overlapping retries. One pending retry is now retained and cleared when a request starts. The 45-second poll cadence and notification UI are unchanged.

Validation:
- node --check on all three changed scripts: PASS.
- node tests/performance-regressions.cjs: PASS. Tests text updates avoiding document rescans, direct/nested board insertion, SVG mutation filtering, both offline helpers on five transaction paths, and deduplicated poll timers.
- node tests/audit-regressions.cjs: PASS. Includes failed upload retention, offline download staging, draft timing and page readiness.
- python3 tests/run_tests.py: BLOCKED: no PHP executable in this environment. Partial checkout also cannot prove database integration.
- No authenticated live-page timings or physical-device benchmarks were obtained. These are confirmed inefficiencies, not proof of the sole cause of reported system-wide lag. Hosting screenshot did not establish overload. No quantified speedup is claimed.

Preserved: module text, questions, keys, grades, XP rules, original images, styling, microphone and recording implementation. No SQL migration. No service-worker caching changes or private-page caching.

Install:
1. Back up the three existing public/assets scripts.
2. Merge public/ from this patch into the existing deployment, preserving all other files. This is a performance-only patch; apply the earlier combined activity patch separately if needed.
3. Existing asset_url() generates new fingerprinted URLs automatically for changed scripts. Reload BULIG; purge hosting cache if old scripts persist. Do not delete saved offline data or pupil recordings.
4. Test dashboard, map, lesson opening/scrolling, matching cards, offline answer save/sync, teacher Class Demo and bug update popup. Compare the same device/account/network before and after. If slow server response remains, measure authenticated request timings and database queries before further changes.

Rollback: restore the three backed-up scripts and reload. No data conversion required.
