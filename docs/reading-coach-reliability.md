# Reading Coach reliability review

Reviewed dev-branch at `8d9b4141b49a4b61a4157b5be870ae541a3909a6`.
Line references below refer to that original commit, before this repair.

## Confirmed code defects

| Location | Defect and effect | Repair |
| --- | --- | --- |
| public/assets/coach.js:181–187 | Apple opens MediaRecorder before SpeechRecognition. The advertised separate recording is only a failure fallback. Competing microphone clients can interrupt each other. | Apple opens no capture stream during recognition; an explicit second recording follows. |
| public/assets/coach.js:106–112 | Every event replays cumulative interim transcripts; revised hypotheses become permanent green words. Network/audio errors immediately terminate; no watchdog when the engine hangs. | Consume final results once, discard stale callbacks, bounded transient-error retries, 20-second watchdog and automatic restarts. Permission denial stops with instructions. |
| public/assets/coach.js:204–215 | Manual and timed skips move the transcript base without restarting the cumulative recognizer; 12-second silence skips a word without pupil action. | Incremental transcript processing; restart after manual skip; quiet pauses only show a hint. |
| public/assets/coach.js:201,215; public/assets/sfx.js:52 | Correct-word audio chimes and tapped-word TTS play while speech recognition is active. The recognizer can hear app audio; Apple microphone sessions can be interrupted. | No correct-word sound during recognition; stop recognition before a spoken hint, resume afterward. |
| public/assets/coach.js:86 | Any one-letter change to a long word passes, even a changed vowel in a vowel lesson. | Edit-distance tolerance preserves the vowel sequence; existing teacher accent mode remains available. |
| public/assets/coach.js:29–36 | A 200-byte blob is treated as valid audio; recording/decode completion is not bounded. Headers and silence can masquerade as usable evidence. | Wait for final recorder data/stop, decode audio, require duration and nonzero signal, cap size/time, reject interrupted/silent/undecodable audio and offer retry. |
| public/assets/coach.js:295; app/bootstrap.php:11 | Preview uses a data URL but media-src permits only self/blob. Closing the coach does not stop preview audio. | Blob URL preview; stop playback and revoke the URL on close. CSP is unchanged. |
| public/assets/coach.js:195,322–330 | Restores green words after reload without retaining their recording, potentially submitting incomplete evidence. | A reload starts a complete new attempt. |
| public/assets/coach.js:152 | MutationObserver survives modal disposal. | Disconnect on close. |
| app/level1.php:91 | Only false is checked after writing audio, not a partial write. | Check exact byte count; delete an incomplete file and report failure. |

Other traced files: app/coach.php (Word Coach fields/scoring), app/scoring.php (say_match/say_sound), app/reading_support.php (teacher accent/support settings), app/reading_verification.php (rv_capture and historical audio), app/actions.php (transaction submission), app/level2a.php and public/assets/l1.js/l2a.js (activity integration), public/assets/theme.css, app/bootstrap.php, public/assets/sfx.js.

The server already accepts MP4/M4A and supports authorized range playback with appropriate audio MIME types. MP4 acceptance alone could not fix microphone contention. app/actions.php:227 clears old audio paths before rv_capture, so the suspected stale-path reuse through the normal submission route was ruled out. Existing verification/XP rules remain unchanged. No SQL migration is required.

## Design and limits

Apple Safari and the installed Home Screen app: (1) tap Start my reading, use only SpeechRecognition; (2) correct missed words; (3) tap Start recording and read the complete text again with only MediaRecorder; (4) finalize, decode-check and submit. MP4/AAC is preferred where supported, with WebM/Opus fallback. Other devices retain simultaneous checking/capture, with a separate recording retry if capture is interrupted.

Advantages: free browser APIs, no new service or library, no CSP relaxation, teacher gets a complete independent reading. Costs: Apple pupils read twice; the scored utterance and teacher recording are different readings. Native speech recognition can require a connection and is browser-dependent. It estimates transcript agreement, not phoneme-level pronunciation or speaker identity. A teacher still decides whose voice it is. Signal validation is not proof of speech or that every word was read. No browser can guarantee capture after a permission denial, interruption, crash, or storage failure; failed audio is rejected rather than represented as valid evidence.

Marks now wait for final recognition results, so green words can appear after a short phrase rather than immediately. Accents use the teacher's existing setting. Speech-support mode remains unscored by the coach. If recognition is unavailable, teacher-only review is explicitly unscored.

## Real-device release checklist

Repeat on iPhone/iPad Safari, installed Home Screen app, Android Chrome, and a laptop Chrome/Safari. Use a test pupil and an assigned teacher.

- Allow microphone/speech access; check a short word list and a long story. On Apple verify the two stages and that the recording stage does not run recognition.
- Pause for 15–25 seconds: no word is skipped or penalized solely for silence. Resume after an automatic restart.
- Repeat a word, skip one, tap Skip, and tap a word to hear Max. Only intended positions become green; Max's voice does not earn points.
- Test standard, accent-friendly and speech-support settings; verify vowel minimal pairs in vowel lessons.
- Complete two activities consecutively; close mid-reading and reopen; reread after a teacher rejection. Check fresh microphone acquisition and independent audio.
- Record and submit; teacher opens the exact attempt, seeks near the end and plays the complete clip on Safari and Chrome. Check both short and several-minute recordings.
- Deny permission, turn off connectivity, lock/background the phone, interrupt with a call, disconnect a headset, and stop immediately. Expect retry/unscored fallback, no false successful recording or invented score.
- Reload halfway: a complete new recording is required. Close during preview: playback and microphone stop.
- Reject an attempt as teacher: existing XP reversal/reread flow still applies; older attempt remains distinct.

Automation uses simulated speech events and real Chromium fake-microphone capture. It cannot certify physical iOS/WebKit behavior, children's recognition accuracy, production hosting limits, or Safari playback of every fallback codec. Real-device tests above remain necessary.
