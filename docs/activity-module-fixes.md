# Module-verified activity corrections

Baseline: dev-branch a95994cdf525f17bea2108d0326831080a6b5287.

## Sources and decisions

- Level 5 Grade 1 original PDF, page 18: Nose, Head, Arm, Eye, Mouth, in that order. Replace pointing at oneself/typing with clickable body regions. Both eyes and both arms accept the corresponding answer. Existing chip handling saves the selected word. Server checks each answer.
- Level 3 original PDF pages 76–77 (printed pages 68–69): transcribe all 20 cr- words and 26 cl- words into a responsive text grid, still using the existing reading interaction. No invented/reduced word list.
- Level 2A original PDF page 34: the first mat is a worked example outside the nine-picture exercise. Keep its card index but mark it as an unscored example. The remaining exercise contains pan, bat (equipment), rat, lap, tap, bat (animal), cat, can, mat. Their middle vowel is /a/. Use five vowel choices, rather than trying to recognize isolated phonemes as whole words. Extract the actual rat illustration from the module because the generated replacement depicted a mouse. Update the raw card image reference so offline asset discovery includes it.
- Level 2A original PDF page 9: first five questions ask for the common beginning sound; final three ask for middle sounds. Show one specific direction per question, single-choice alphabet for Part A and vowels for Part B. Preserve existing question order and keys. The speaker names the pictured items to remove ambiguity.

## Scope and deployment

No SQL, schema migration, account changes, XP reset, or microphone implementation changes. Existing card indexes are retained; no bulk regrading is performed. Newly evaluated submissions use the corrected answer keys and nine-question middle-sound denominator.

Upload the app, database and public folders from the ZIP to the corresponding existing application folders. Upload app/activity_module_fixes.php together with app/level2a.php and app/scoring.php. Back up the replaced files first. Purge Hostinger cache, reload, and re-download offline lessons if applicable. The ZIP is a patch, not a complete application installation.

## Verification

Targeted PHP checks cover body-answer acceptance/rejection, scope by grade/page, stable card indexes, unscored example, vowel keys, pretest direction/choice counts, all 46 words reaching the existing matcher, and no inline script/style handlers. Existing audit-regressions.cjs was also run.

Full database integration and physical-device tests remain required. The local browser launch was blocked by the environment. Do not treat code-level checks as proof of live-device behavior.

## Manual acceptance checklist

1. Grade 1 Level 5 lesson 160: open the body activity; tap a wrong part, then the requested part. Test both eyes and arms. Verify saved response and completion after reload.
2. Level 3B lesson 75: check every chart word is readable on phone and laptop; read through the existing microphone flow and verify saved recording as before.
3. Level 2A lesson 15 activity 248: example first, then nine questions. Ensure only questions are scored. Select /i/ to check rejection, then /a/. Verify the rat picture is a rat.
4. Level 2A lesson 13 pretest: questions 1–5 show Part A; 6–8 show Part B. One tap replaces the previous answer. Submit and verify teacher results.
5. Repeat on Android Chrome, iPhone Safari/Home Screen and laptop. Check narrow-screen scrolling, keyboard focus, saved answers and offline re-download. Check an unrelated grade/lesson remains unchanged.

## Picture and underline follow-up
- Level 3B dr post-test card 2 now uses the existing child-drawing illustration instead of the sandcastle/play picture. Answer remains draw. The dragon card and other dr pictures were visually checked.
- Restored 57 explicit target words in Grade 5 Level 5 vocabulary cards (source pages 6, 7, 23, 25, 26, 36, 37), checked against module sentences and choices. Context-clue target underlines are visible in the source; pre/post-test targets are inferred from their sentence/answer pairs where the source also lacks the underline.
- Level 2B page 23 card 4 underlines toy, not gave, for its rhyming choice.
- Teacher Class Demo now retains target underlines through the same escaped formatter as pupil cards.
- Passed tests/picture-underline-fixes.php (including prior activity tests) and node tests/audit-regressions.cjs. PHP syntax checks passed. Full tests/run_tests.py blocked by missing app/bootstrap.php in the partial checkout; database/browser integration not verified here.
- No SQL migration. No microphone or recording changes.
