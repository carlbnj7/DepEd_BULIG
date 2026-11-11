# Reading Coach microphone repair

Baseline: dev-branch f94fdb9ce2d3548f9be98033e2b419a311b5c9a3.

## Findings

1. `public/assets/sfx.js:15` forced `navigator.audioSession.type='ambient'` globally for pupils. This is a playback-oriented session, incompatible with microphone capture in implementations enforcing Audio Session rules. Change it to `auto`. Coach also resets this before each native speech or recorder request, so an older cached effects script cannot keep it in ambient mode. This is an identified iOS capture blocker, not proof that it explains every reported device failure.
2. The baseline Coach's finish path aborted the active speech session. Abort discards pending final results. Long-reading and single-word finish now request stop, accept late results, and bound the wait before cancellation. New-file references: `coach.js:113`, `:126`, `:233`.
3. Desktop capture and recognition started automatically after narration, and phone missed-word checks started from timers or narration callbacks. The first check now uses an explicit Start tap everywhere; phone word retries also use a tap. Mobile start executes synchronously within that tap. `coach.js:212`, `:275`.
4. The fallback paused the recorder, which does not release its microphone track. It now cancels the recorder and stops recognition before offering a retry. The desktop first-attempt recording is finalized before missed-word correction starts. `coach.js:288`, `:233`.
5. A request for microphone permission could remain pending indefinitely. A bounded request now releases any stream granted after timeout; unsupported constraints receive a plain-audio retry. Bitrate options also have a fallback.
6. Single-word transcript handling assumed iterable result objects. It now reads array-like results by index, and waits for final results after its timeout.
7. The deployed `public/assets/microphone-check.js` returned HTTP 404 during this investigation. The visible diagnostic page therefore could show an inert Start button without testing the microphone at all. Both diagnostic files are included, with a visible script-loaded status and separate speech and recording/playback checks.

## What this patch changes

Only Coach JavaScript, sound-effects JavaScript, and the diagnostic HTML/JavaScript change in production. No CSS, artwork, PHP routes, database schema, scoring formula, or teacher dashboard layout changes. Sound effects are quiet while Coach is open. Blue interim marks remain provisional; confirmed results alone affect progress. A session with no final transcript offers retry/teacher review rather than treating missing speech-service output as incorrect pronunciation.

Recording waits for final recorder chunks, checks decoded duration and audio energy, and releases tracks and contexts. Empty, silent, corrupt, or interrupted audio is not submitted. AAC/MP4 remains preferred where supported, followed by browser-supported WebM/Opus. This does not guarantee every older browser can decode every other browser's codec. The existing PHP endpoint already accepts MP4/WebM and serves audio with byte ranges; no server change is included.

## Design and remaining limits

This patch retains the existing two-stage flow on Android and Apple browsers: check words, then record the reading for the teacher. Desktop non-Safari browsers retain concurrent capture. The two-stage flow avoids competition between the native speech service and the recording stream, but requires an additional reading. This patch does not claim to implement reliable simultaneous capture on every phone.

A one-reading design across devices would capture one stream and send that same audio to a transcription service, storing it for teacher review as well. That avoids competing browser microphone clients, but requires a configured service, network/upload handling, cost assessment, and appropriate handling of pupil audio. It is not silently introduced here.

Browser speech recognition supplies a transcript; matching it to the passage is not a validated phonetic pronunciation assessment. Existing accent modes and bounded skip/repeat handling remain in place. No speech service, permission, device microphone, or network can be guaranteed by a JavaScript patch.

## Verification

- PASS: `node --check` for all three changed JavaScript files.
- PASS: `node tests/coach-regressions.cjs` — incremental finals, provisional highlights, stale callbacks, restarts/watchdog, permission errors, second attempt, accent/vowel matching, skips/repeats, gesture sequencing, final recording chunks, silence/corruption rejection and track cleanup.
- PASS: `node tests/coach-finalization.cjs` — late final result, stalled engine, cancellation and no restart while finishing.
- PASS: `node tests/coach-audio-session.cjs` — automatic audio session, quiet effects, array-like results and single-word late final result.
- PASS: `node tests/audit-regressions.cjs` — existing upload/offline/readiness regression checks.
- BLOCKED: `python3 tests/run_tests.py` was executed but stopped immediately because the `php` executable is absent. No PHP/database integration pass is claimed.
- Physical iPhone/iPad, Android, laptop microphone and teacher playback tests were not performed here. Automated audio tests use mocks.

## Deployment

No SQL is needed. Back up the four matching production files, then upload the ZIP's `public/` contents into the existing server `public/` folder. Do not create a nested `public/public/` folder. This is a patch, not a complete site. Upload BOTH coach.js and sfx.js, plus BOTH diagnostic files. The app generates new hashed asset URLs from source modification time and size. Purge Hostinger page/CDN cache, reopen the app, and refresh previously downloaded offline lessons if testing those. Do not delete saved pupil drafts or recordings.

Open `/public/microphone-check.html?v=mic-repair-2`. It must say “Check script loaded. Ready.” Run speech and recording checks separately. The diagnostic does not upload recordings. A repository commit is not proof of Hostinger deployment.

## Device checklist

Repeat on iPhone Safari, iPhone Home Screen app, iPad Safari, Android Chrome, and laptop Chrome. Also check desktop Safari if used.

1. Allow the microphone. Run diagnostic speech twice and record/play a short sentence twice. Confirm the recorded voice is audible, not merely that a file has bytes.
2. Open Coach, finish or skip Max's narration, tap Start, and read a short passage. Observe blue provisional and green final words.
3. Read a long story, pause for 10–20 seconds, then continue. Confirm automatic restart and no duplicate credit from old events.
4. Repeat a word; skip a word; use Hear it; finish immediately after the last word. Confirm the last final result is retained and missed-word practice still works.
5. On phones, tap Say it for each missed word. Complete the separate teacher recording. On desktop, confirm the first-attempt audio ends before corrections and contains no Max narration.
6. Close Coach mid-reading, reopen, and complete two readings consecutively. No old attempt may mark words or save into the new attempt.
7. Deny access, interrupt with another app, and simulate connection loss. Confirm an actionable retry or teacher-review path rather than false pronunciation scores or empty submissions.
8. Submit and sign in as the assigned teacher. Check the correct pupil, passage reference, audible recording, playback/seeking and review status. Play an iPhone recording in Chrome and an Android recording in Safari.

If diagnostic speech fails but diagnostic recording plays, capture its exact speech error and device/browser version: this isolates native speech-service failure from recording. If both work but Coach fails, report the activity ID, grade/level and step. If neither works, the diagnostic reports microphone/API errors without scoring the pupil.

References: https://www.w3.org/TR/audio-session/#microphone-mediastreamtrack ; https://developer.mozilla.org/en-US/docs/Web/API/SpeechRecognition/stop ; https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder/stop .
