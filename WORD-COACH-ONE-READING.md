# One-reading Word Coach update

## Install on Hostinger
Back up app/level1.php and public/assets/coach.js first. Extract the deployment ZIP into the existing project root (the folder containing app and public), allowing replacement. Upload the COMPLETE public/assets/one-reading folder, including its .htaccess, model and WASM files. Do not import SQL: this update uses the existing reading-verification tables. Existing teacher review must already be configured.

Purge the Hostinger/CDN cache once and reload the app. On iPhone Home Screen, close and reopen the app. This is an update ZIP, not a fresh installation. No configuration, database, pupil uploads, lesson content or images are included or replaced. To undo, restore the two backed-up files.

For a Git checkout instead of the ZIP, run python3 tools/fetch_one_reading.py before deployment. Large pinned vendor/model binaries are distributed in the ZIP, not Git. The downloader verifies size and SHA-256. No build step; browsers use self-hosted assets only. Worker-scoped CSP permits WASM without allowing inline scripts or inline styles. Keep the included .htaccess; servers ignoring it need an equivalent worker response policy.

## Pupil flow
Start opens the guide every time. Let’s begin lets Max demonstrate. Start my reading opens ONE microphone stream. The stream supplies local word recognition and teacher audio. Finish reading finalizes the same recording, then checks the words. Optional missed-word practice preserves the original audio. Listen to my reading previews it. Send to my teacher submits through the existing activity form. Manual sending is now the default (the existing ?wcsend=auto option retains its countdown).

There is no mandatory second full reading just to make a recording. A genuinely failed/empty recording still needs a retry; a teacher can still request a rereading. If recognition fails after recording, keep audio for teacher review and do not assign an automatic word score. No background or hidden recording occurs.

## Cost and limits
No per-minute speech API charges. Model runs on the device, using the site's hosting/bandwidth. Initial download is about 53 MB; browser caching may be cleared by app updates, device storage pressure or browser settings. Short pauses before word highlights are expected; local checking is not instant. Maximum capture: five minutes when compressed recording works, three minutes for WAV fallback. The model matches recognized words, not a validated phoneme-level pronunciation assessment. Teachers remain the final reviewers.

## Validation performed
- Shared capture engine tests: same PCM for checking, compressed audio and WAV fallback, second attempt, permission cleanup, recording retained when model fails.
- DOM flow tests: guide on each Start, single capture, teacher hidden field/event, normal score and no-score fallback.
- PHP WAV validation: valid mono 16 kHz PCM; rejected silent, truncated and wrong-format WAV.
- Existing coach matching/finalization/audio-session regressions and audit-regressions.cjs pass.
- Python full suite: PHP syntax checks pass; database portion blocked here because mysql/MariaDB test runtime is unavailable. This is not a full database/end-to-end pass.
- Physical-device integrated testing is still required. The earlier standalone prototype was user-tested on iPhone; this integrated release has not been physically tested here.

## Device checklist (Safari, iPhone Home Screen, Android Chrome, laptop Chrome)
1. Open a reading activity, press Start: guide appears. First model download completes. No microphone before Start my reading.
2. Allow microphone, read a short passage once, Finish; blue draft highlights update, then word results appear.
3. Play the recording: beginning and last word are audible. Send; teacher sees the passage and can play the complete recording.
4. Try a second activity and repeat the same activity: guide appears and microphone works again.
5. Try missed-word practice; original recording remains playable and can still be sent.
6. Deny permission: readable error, no false recording/score. Allow permission and retry.
7. Interrupt with a phone call or lock screen: do not submit a false complete score. Check any retained audio before sending.
8. Test a longer story and slow connection. After assets are cached, airplane mode can test local capture/checking; reconnect before sending the activity.
9. Teacher approves/rejects using the existing review flow. Check score/XP handling in the existing system.

Tests: node tests/one-reading-engine.mjs; php tests/one-reading-wav.php; node tests/audit-regressions.cjs. The optional DOM test requires jsdom 26.1.0 available via Node resolution (development only): node tests/one-reading-ui.cjs. Run python3 tests/run_tests.py against a separate test MariaDB before production rollout.
