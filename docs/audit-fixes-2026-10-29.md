# Audit fixes awaiting integration validation

Based on dev-branch bf568b33616aea8f76bd880fd50a8f9fabea2ebc.

Changes:
- Pupil offline submissions remain queued after HTTP errors or malformed acknowledgments. Lesson completion returns a JSON acknowledgment for JSON callers while normal forms retain their redirect.
- Offline level downloads stage and validate pages/assets before copying into the saved cache. Network failures and cancellation keep the previous download. The bulk downloader offers retry instead of reporting failed downloads as ready.
- Level 3B practice and cycle checks follow LEVEL_ORDER rather than numeric IDs.
- Typing uses the intended 650 ms draft debounce; drawing retains its 15 second throttle.
- My Lessons reuses availability and next-lesson results within the map render. Activity pages reuse the activity list already queried.
- Map pages omit four activity-only scripts. Final styles and map markup are unchanged.
- An early, external script hides only unfinished fallback lists and the activity form until deferred setup completes. No overlay or minimum wait is added. DOM-ready, page-show and a four-second failsafe reveal the content. This addresses intermediate unenhanced text; it does not remove network latency or guarantee image decoding is instant.

Passed locally: `node tests/audit-regressions.cjs` and syntax checks on app.js, offline.js and lesson-ready.js. Tests cover HTTP 403/429/500/503, invalid success bodies, acknowledged retry answers, failed page/image downloads, login redirects, cancellation, retained old downloads, staging cleanup, typing/drawing delays and reveal fallbacks.

Release gate NOT completed: PHP/MySQL are unavailable in the editing environment. Run the repository's `python3 tests/run_tests.py` in its configured test environment. PHP lint and database integration remain pending. Do not deploy or build an upload ZIP until the required suite passes.

Also verify on a signed-in test pupil: starting at Level 3B versus Level 4, future cycle dates, earlier-level free practice, successful and failed offline synchronization, and map/activity appearance on a phone. Compare cold/warm navigation timings before claiming a speed improvement. Test offline download updates with storage pressure; staging requires temporary additional storage.

After validation, refresh checker fingerprints and use the documented update builder with the actual live deployment's base commit. No database migration or credentials change is required.
