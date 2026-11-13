/* BULIG Defense Panel. A panel asks a question (spoken with the browser's voice), the student answers out loud,
   the browser's speech recognition writes down the words, and the answer is checked against the key points of the
   question: key points said and missed, filler words, pace and length, then a better answer is shown.
   Everything stays on this device (localStorage). No server is needed. */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const store = {
    get(k, d) { try { const v = localStorage.getItem('dp_' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('dp_' + k, JSON.stringify(v)); } catch (e) { /* private mode */ } }
  };
  const CATS = window.DP_CATS || {};
  const PANEL = [
    { id: 'chair', name: 'Panel Chair', img: 'assets/img/teacher-male.webp', pitch: 0.9 },
    { id: 'tech', name: 'Technical Panelist', img: 'assets/img/teacher-female.webp', pitch: 1.15 },
    { id: 'content', name: 'Content Panelist', img: 'assets/img/admin-m-tip.webp', pitch: 1.0 }
  ];
  const WHO_FOR = { literature: 'content', diagrams: 'tech', tech: 'tech', system: 'tech', security: 'tech', results: 'content', method: 'content', objectives: 'content', overview: 'chair', future: 'chair', random: 'chair' };
  const FILLERS = ['um', 'umm', 'uh', 'uhh', 'ah', 'ahh', 'er', 'erm', 'hmm', 'like', 'basically', 'actually', 'literally', 'you know', 'i mean', 'kind of', 'sort of', 'ano', 'kuan', 'bale', 'parang', 'kasi'];
  const HEDGES = ['i think', 'i guess', 'maybe', 'probably', 'i\'m not sure', 'im not sure', 'not sure', 'i don\'t know', 'i dont know', 'perhaps'];
  const STOP = new Set('a an the and or but of to in on at for from by with as is are was were be been being it its this that these those we our us i you your they their them he she his her which who whom what when where why how so than then there here also not no do does did can could will would should may might must into about over under up down out more most very just only each every all any some such both own same other our'.split(' '));

  const settings = Object.assign({ voice: true, rate: 1, lang: 'en-PH', count: '8', limit: '120', peek: false }, store.get('settings', {}));
  let custom = store.get('custom', []);
  let history = store.get('history', []);

  /* ---------- questions ---------- */
  function allQuestions() {
    const base = (window.DP_QUESTIONS || []).map((q, i) => Object.assign({ id: 'b' + i }, q));
    return base.concat(custom.map(q => Object.assign({ mine: true }, q)));
  }
  function shuffle(a) { a = a.slice(); for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; }
  function pickMock(n) {
    /* a real defense moves from the overview to the details: one or two from each topic, in topic order, then a random one */
    const qs = allQuestions(), order = ['overview', 'objectives', 'literature', 'method', 'diagrams', 'system', 'tech', 'results', 'security', 'future'];
    const byCat = {}; qs.forEach(q => (byCat[q.cat] = byCat[q.cat] || []).push(q));
    Object.keys(byCat).forEach(k => byCat[k] = shuffle(byCat[k]));
    const out = []; let round = 0;
    while (out.length < n - 1 && round < 4) { order.forEach(c => { if (out.length < n - 1 && byCat[c] && byCat[c][round]) out.push(byCat[c][round]); }); round++; }
    const rnd = shuffle(byCat.random || []);
    if (rnd.length) out.push(rnd[0]);
    const sorted = order.concat(['random']);
    const intro = qs.find(q => /introduce your study/i.test(q.q));
    let list = out.slice(0, n).sort((a, b) => sorted.indexOf(a.cat) - sorted.indexOf(b.cat));
    if (intro) list = [intro].concat(list.filter(q => q !== intro)).slice(0, n);   // a defense always opens with the overview
    return list;
  }

  /* ---------- screens ---------- */
  function go(name) {
    stopAll();
    $$('.screen').forEach(s => s.hidden = s.id !== 's-' + name);
    $$('.topnav a, .tabbar a').forEach(a => a.classList.toggle('on', a.dataset.go === name));
    document.body.dataset.screen = name;
    if (name === 'home') renderHome();
    if (name === 'bank') renderBank();
    if (name === 'history') renderHistory();
    if (name === 'settings') renderSettings();
    window.scrollTo(0, 0);
  }
  document.addEventListener('click', e => {
    const g = e.target.closest('[data-go]'); if (g) { e.preventDefault(); go(g.dataset.go); return; }
    const s = e.target.closest('[data-start]'); if (s) { e.preventDefault(); start(s.dataset.start, s.dataset.cat); }
  });
  window.addEventListener('hashchange', () => { const h = location.hash.slice(1); if (['home', 'bank', 'history', 'settings'].includes(h)) go(h); });

  function renderHome() {
    const done = history.length, avg = done ? Math.round(history.reduce((t, h) => t + h.avg, 0) / done) : 0;
    const answered = history.reduce((t, h) => t + h.items.length, 0);
    $('#home-stats').innerHTML = stat('i-users', done, done === 1 ? 'practice session' : 'practice sessions') + stat('i-mic', answered, 'answers given') + stat('i-star', done ? avg + '%' : '–', 'average score') + stat('i-book', allQuestions().length, 'questions in the bank');
    const counts = {}; allQuestions().forEach(q => counts[q.cat] = (counts[q.cat] || 0) + 1);
    $('#topics').innerHTML = Object.keys(CATS).map((c, i) => `<button class="topic t${i % 5}" data-start="topic" data-cat="${c}"><b>${esc(CATS[c])}</b><span>${counts[c] || 0} questions</span><svg class="ic"><use href="#i-next"/></svg></button>`).join('');
    const note = $('#mic-note');
    if (!Rec) { note.hidden = false; note.textContent = 'This browser cannot turn speech into text. Use Chrome or Edge to answer by voice, or type your answers.'; }
  }
  const stat = (ic, n, l) => `<div class="stat"><svg class="ic"><use href="#${ic}"/></svg><b>${n}</b><span>${esc(l)}</span></div>`;

  /* ---------- session ---------- */
  let S = null; // {mode, list, i, results:[]}
  function start(mode, cat) {
    let list;
    if (mode === 'mock') list = pickMock(parseInt(settings.count, 10) || 8);
    else if (mode === 'topic') list = shuffle(allQuestions().filter(q => q.cat === cat)).slice(0, Math.max(5, parseInt(settings.count, 10) || 8));
    else list = shuffle(allQuestions()).slice(0, 1);
    if (!list.length) return;
    S = { mode, cat, list, i: 0, results: [], started: Date.now() };
    $$('.screen').forEach(s => s.hidden = s.id !== 's-room');
    $$('.topnav a, .tabbar a').forEach(a => a.classList.remove('on'));
    document.body.dataset.screen = 'room';
    $('#room-topic').textContent = mode === 'mock' ? 'Mock defense' : mode === 'topic' ? CATS[cat] : 'Random question';
    ask();
  }
  function current() { return S && S.list[S.i]; }
  function ask() {
    const q = current(); if (!q) return finish();
    resetAnswer();
    const who = PANEL.find(p => p.id === (WHO_FOR[q.cat] || 'chair')) || PANEL[0];
    $('#panel-row').innerHTML = PANEL.map(p => `<figure class="pm${p === who ? ' on' : ''}"><img src="${p.img}" alt="${esc(p.name)}"><figcaption>${esc(p.name)}</figcaption>${p === who ? '<span class="talk" aria-hidden="true"><i></i><i></i><i></i></span>' : ''}</figure>`).join('');
    $('#q-who').textContent = who.name.toUpperCase() + ' ASKS · ' + (CATS[q.cat] || 'My question').toUpperCase();
    $('#q-text').textContent = q.q;
    $('#room-count').textContent = S.list.length > 1 ? `Question ${S.i + 1} of ${S.list.length}` : 'One question';
    $('#room-bar').style.width = (100 * S.i / S.list.length) + '%';
    $('#feedback').hidden = true; $('#answer').hidden = false;
    if (settings.peek) { $('#feedback').hidden = false; $('#feedback').innerHTML = `<div class="card peek"><span class="eyebrow">STUDY FIRST</span><p>${esc(q.model)}</p></div>`; }
    S.who = who; speak(q.q, who);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  $('#btn-repeat').addEventListener('click', () => { const q = current(); if (q) speak(q.q, S.who); });
  $('#btn-skip').addEventListener('click', () => { if (!S) return; stopAll(); S.results.push({ q: current(), skipped: true, score: 0 }); next(); });

  /* ---------- voice of the panel ---------- */
  let voices = [];
  function loadVoices() { voices = (window.speechSynthesis && speechSynthesis.getVoices()) || []; }
  if (window.speechSynthesis) { loadVoices(); speechSynthesis.onvoiceschanged = loadVoices; }
  function speak(text, who) {
    if (!settings.voice || !window.speechSynthesis) return;
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    const en = voices.filter(v => /^en/i.test(v.lang));
    const pick = en.length ? en[PANEL.indexOf(who) % en.length] : null;
    if (pick) u.voice = pick;
    u.lang = (pick && pick.lang) || 'en-US'; u.rate = parseFloat(settings.rate) || 1; u.pitch = (who && who.pitch) || 1;
    const fig = $('.pm.on'); if (fig) fig.classList.add('speaking');
    u.onend = u.onerror = () => { const f = $('.pm.on'); if (f) f.classList.remove('speaking'); };
    speechSynthesis.speak(u);
  }

  /* ---------- recording and speech to text ---------- */
  const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
  let rec = null, recOn = false, finalText = '', interim = '', t0 = 0, tick = null, media = null, chunks = [], audioURL = null, stream = null, ctx = null, raf = null, typedMode = false;
  function resetAnswer() {
    stopAll(); finalText = ''; interim = ''; chunks = []; if (audioURL) URL.revokeObjectURL(audioURL); audioURL = null; typedMode = false;
    $('#live').innerHTML = '<span class="ph">Your words will appear here as you speak…</span>'; $('#live').hidden = false;
    $('#typed').value = ''; $('#typed').hidden = true; $('#btn-type').innerHTML = '<svg class="ic"><use href="#i-pen"/></svg>Type instead';
    $('#timer').textContent = '0:00'; $('#btn-check').disabled = true; setMic(false);
    $('#ans-title').textContent = Rec ? 'Tap the microphone and answer out loud' : 'Type your answer below';
    $('#ans-sub').textContent = Rec ? 'Take a breath. Answer like you are facing the real panel.' : 'This browser cannot turn speech into text. Chrome or Edge can.';
    if (!Rec) toggleTyped(true);
  }
  function setMic(on) {
    const b = $('#btn-mic'); b.classList.toggle('rec', on);
    b.innerHTML = `<svg class="ic"><use href="#${on ? 'i-stop' : 'i-mic'}"/></svg>`; b.setAttribute('aria-label', on ? 'Stop' : 'Start answering');
    $('#answer').classList.toggle('recording', on);
  }
  $('#btn-mic').addEventListener('click', () => { recOn ? stopRec() : startRec(); });
  async function startRec() {
    if (!Rec) { toggleTyped(true); return; }
    if (window.speechSynthesis) speechSynthesis.cancel();
    toggleTyped(false);
    try { stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }); }
    catch (e) { $('#ans-title').textContent = 'The microphone is blocked'; $('#ans-sub').textContent = 'Allow the microphone in the address bar, or type your answer.'; toggleTyped(true); return; }
    recOn = true; setMic(true); t0 = Date.now(); buzz(30);
    $('#ans-title').textContent = 'Listening… answer now'; $('#ans-sub').textContent = 'Tap the red button when you finish.';
    if (!finalText) $('#live').innerHTML = '';
    try { media = new MediaRecorder(stream); chunks = []; media.ondataavailable = e => { if (e.data.size) chunks.push(e.data); }; media.onstop = () => { if (chunks.length) { if (audioURL) URL.revokeObjectURL(audioURL); audioURL = URL.createObjectURL(new Blob(chunks, { type: media.mimeType || 'audio/webm' })); } }; media.start(); } catch (e) { media = null; }
    meter(stream);
    rec = new Rec(); rec.lang = settings.lang || 'en-PH'; rec.continuous = true; rec.interimResults = true; rec.maxAlternatives = 1;
    rec.onresult = ev => { interim = ''; for (let i = ev.resultIndex; i < ev.results.length; i++) { const r = ev.results[i]; if (r.isFinal) finalText += (finalText ? ' ' : '') + r[0].transcript.trim(); else interim += r[0].transcript; } showLive(); };
    rec.onerror = ev => { if (ev.error === 'not-allowed' || ev.error === 'service-not-allowed') { $('#ans-sub').textContent = 'Speech to text is not allowed here. Type your answer instead.'; stopRec(); toggleTyped(true); } };
    rec.onend = () => { if (recOn) { try { rec.start(); } catch (e) { /* already started */ } } };   // Chrome stops after a pause: keep listening
    try { rec.start(); } catch (e) { /* ignore */ }
    const limit = parseInt(settings.limit, 10) || 120;
    tick = setInterval(() => { const s = Math.floor((Date.now() - t0) / 1000); $('#timer').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); $('#timer').classList.toggle('warn', s >= limit - 10); if (s >= limit) stopRec(); }, 250);
  }
  function showLive() {
    const t = (finalText + ' ' + interim).trim();
    $('#live').innerHTML = t ? esc(finalText) + (interim ? ' <span class="interim">' + esc(interim) + '</span>' : '') : '<span class="ph">Listening…</span>';
    $('#btn-check').disabled = !t;
  }
  function stopRec() {
    if (!recOn) return; recOn = false; S && (S.secs = (S.secs || 0) + (Date.now() - t0) / 1000);
    clearInterval(tick); tick = null;
    try { rec && rec.stop(); } catch (e) { /* ignore */ }
    try { media && media.state !== 'inactive' && media.stop(); } catch (e) { /* ignore */ }
    if (stream) stream.getTracks().forEach(t => t.stop()); stream = null;
    if (raf) cancelAnimationFrame(raf); raf = null; if (ctx) { ctx.close().catch(() => {}); ctx = null; }
    $$('.meter i').forEach(i => i.style.transform = '');
    setMic(false); buzz([20, 40, 20]);
    if (interim) { finalText += (finalText ? ' ' : '') + interim.trim(); interim = ''; }
    showLive();
    $('#ans-title').textContent = finalText ? 'Got it! Check your answer or tap the microphone to add more' : 'I did not hear anything';
    $('#ans-sub').textContent = finalText ? 'You can also fix a wrong word by typing.' : 'Speak closer to the microphone, or type your answer.';
  }
  function stopAll() { stopRec(); if (window.speechSynthesis) speechSynthesis.cancel(); }
  function meter(st) {
    try {
      ctx = new (window.AudioContext || window.webkitAudioContext)(); const src = ctx.createMediaStreamSource(st); const an = ctx.createAnalyser(); an.fftSize = 64; src.connect(an);
      const d = new Uint8Array(an.frequencyBinCount), bars = $$('.meter i');
      const draw = () => { an.getByteFrequencyData(d); bars.forEach((b, i) => { const v = d[i + 1] / 255; b.style.transform = 'scaleY(' + (0.15 + v * 1.1).toFixed(2) + ')'; }); raf = requestAnimationFrame(draw); };
      draw();
    } catch (e) { /* no meter */ }
  }
  function toggleTyped(on) {
    typedMode = on === undefined ? !typedMode : on;
    $('#typed').hidden = !typedMode;
    if (typedMode) { $('#typed').value = (finalText + ' ' + interim).trim() || $('#typed').value; $('#typed').focus(); }
    $('#btn-type').innerHTML = typedMode ? '<svg class="ic"><use href="#i-mic"/></svg>Show what I said' : '<svg class="ic"><use href="#i-pen"/></svg>Type instead';
    $('#live').hidden = typedMode; $('#btn-check').disabled = !(typedMode ? $('#typed').value.trim() : finalText.trim());
  }
  $('#btn-type').addEventListener('click', () => { if (recOn) stopRec(); toggleTyped(); });
  $('#typed').addEventListener('input', () => { $('#btn-check').disabled = !$('#typed').value.trim(); });
  $('#btn-check').addEventListener('click', () => {
    if (recOn) stopRec();
    const text = (typedMode ? $('#typed').value : finalText).trim(); if (!text) return;
    if (typedMode) finalText = text;
    setTimeout(() => showFeedback(text), 150);   // let the recorder finish so the recording can be played
  });

  /* ---------- checking the answer ---------- */
  const norm = s => ' ' + String(s).toLowerCase().replace(/[’‘]/g, "'").replace(/[^a-z0-9'.\s-]/g, ' ').replace(/(\D)\.|\.(\D|$)/g, '$1 $2').replace(/\s+/g, ' ').trim() + ' ';
  function has(n, w) {
    w = w.toLowerCase().trim();
    if (w.endsWith('*')) { const stem = w.slice(0, -1); return new RegExp('(^|\\s)' + stem.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + "[a-z'-]*(\\s|$)").test(n); }
    return n.includes(' ' + w + ' ');
  }
  /* the word or phrase as it was said ("gamified" for "gamif*") */
  function saidWord(n, w) {
    w = w.toLowerCase().trim(); if (!w.endsWith('*')) return w;
    const m = n.match(new RegExp('(?:^|\\s)(' + w.slice(0, -1).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + "[a-z'-]*)(?=\\s|$)"));
    return m ? m[1] : w.slice(0, -1);
  }
  function analyse(q, text, secs) {
    const n = norm(text);
    const words = n.trim() ? n.trim().split(' ') : [];
    const keys = (q.keys || []).map(([label, ws]) => { const hit = ws.filter(w => has(n, w)); return { label, ws, ok: hit.length > 0, hit, said: Array.from(new Set(hit.map(w => saidWord(n, w)))) }; });
    const covered = keys.filter(k => k.ok).length, cov = keys.length ? covered / keys.length : 0;
    /* similarity: shared content words with the model answer */
    const content = s => new Set(norm(s).trim().split(' ').filter(w => w.length > 2 && !STOP.has(w)).map(w => w.replace(/(ing|ed|es|s)$/, '')));
    const mw = content(q.model), aw = content(text); let common = 0; aw.forEach(w => { if (mw.has(w)) common++; });
    const sim = mw.size ? Math.min(1, common / Math.min(mw.size, 25)) : 0;
    const fill = {}; FILLERS.forEach(f => { const c = (n.match(new RegExp('(^|\\s)' + f + '(?=\\s)', 'g')) || []).length; if (c) fill[f] = c; });
    const fillers = Object.values(fill).reduce((a, b) => a + b, 0);
    const hedges = HEDGES.filter(h => n.includes(' ' + h + ' '));
    const wpm = secs > 5 ? Math.round(words.length / (secs / 60)) : 0;
    let score = 100 * (keys.length ? 0.65 * cov + 0.35 * sim : sim);
    if (words.length < 25) score *= 0.75;
    score -= Math.min(10, Math.max(0, fillers - 2) * 2);
    score = Math.max(0, Math.min(100, Math.round(score)));
    const stars = score >= 80 ? 3 : score >= 60 ? 2 : score >= 35 ? 1 : 0;
    return { n, words: words.length, keys, covered, cov, sim, fill, fillers, hedges, wpm, secs, score, stars };
  }
  /* the transcript with the key words in green and the filler words in orange */
  function marked(text, a) {
    let h = esc(text);
    const hits = []; a.keys.forEach(k => k.hit.forEach(w => hits.push(w)));
    hits.sort((x, y) => y.length - x.length).forEach(w => {
      const stem = w.replace(/\*$/, ''), re = new RegExp('(^|[^\\w>])(' + stem.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + (w.endsWith('*') ? "[\\w'-]*" : '') + ')(?![\\w<])', 'gi');
      h = h.replace(re, '$1<mark class="ok">$2</mark>');
    });
    Object.keys(a.fill).forEach(f => { h = h.replace(new RegExp('(^|[\\s,.])(' + f + ')(?=[\\s,.]|$)', 'gi'), '$1<mark class="fill">$2</mark>'); });
    return h;
  }
  /* a sentence of the model answer that covers a missed key point */
  function exampleFor(q, k) {
    const sents = q.model.split(/(?<=[.!?])\s+/);
    return sents.find(s => k.ws.some(w => has(norm(s), w))) || '';
  }
  function showFeedback(text) {
    const q = current(); const secs = S.secs || 0; S.secs = 0;
    const a = analyse(q, text, secs);
    S.results[S.i] = { q, text, score: a.score, stars: a.stars, covered: a.covered, total: a.keys.length, fillers: a.fillers };
    const missed = a.keys.filter(k => !k.ok);
    const starsHtml = [0, 1, 2].map(i => `<svg class="ic st${i < a.stars ? ' on' : ''}"><use href="#i-star"/></svg>`).join('');
    const verdict = a.stars === 3 ? 'Excellent answer!' : a.stars === 2 ? 'Good answer. Add the missing points.' : a.stars === 1 ? 'A start. Cover more key points.' : 'Let’s try that again.';
    const coach = a.stars >= 2 ? 'jarvis-thumbs' : a.stars === 1 ? 'jarvis-idea' : 'jarvis-hello';
    const last = S.i >= S.list.length - 1;
    $('#answer').hidden = true;
    const f = $('#feedback'); f.hidden = false;
    f.innerHTML = `
      <div class="card fb-score s${a.stars}">
        <img class="coach" src="assets/img/${coach}.webp" alt="Jarvis the coach">
        <div class="ring" style="--p:${a.score}"><b>${a.score}</b><small>score</small></div>
        <div class="fb-head"><span class="eyebrow">YOUR RESULT</span><h2>${verdict}</h2><div class="stars">${starsHtml}</div></div>
        <div class="chips">
          <span class="chip ${a.cov >= .7 ? 'good' : a.cov >= .4 ? 'mid' : 'bad'}"><b>${a.covered}/${a.keys.length}</b> key points</span>
          <span class="chip"><b>${a.words}</b> words</span>
          ${a.wpm ? `<span class="chip ${a.wpm > 175 || a.wpm < 90 ? 'mid' : 'good'}"><b>${a.wpm}</b> words a minute</span>` : ''}
          <span class="chip ${a.fillers > 2 ? 'bad' : 'good'}"><b>${a.fillers}</b> filler words</span>
          ${secs ? `<span class="chip"><b>${Math.floor(secs / 60)}:${String(Math.round(secs % 60)).padStart(2, '0')}</b> long</span>` : ''}
        </div>
      </div>
      <div class="fb-grid">
        <div class="card">
          <h3><svg class="ic"><use href="#i-mic"/></svg>What I heard</h3>
          <p class="heard">${marked(text, a)}</p>
          <p class="legend"><mark class="ok">key word</mark> <mark class="fill">filler word</mark></p>
          ${audioURL ? `<audio controls src="${audioURL}"></audio>` : ''}
        </div>
        <div class="card">
          <h3><svg class="ic"><use href="#i-check"/></svg>Key points</h3>
          <ul class="keys">${a.keys.map(k => `<li class="${k.ok ? 'ok' : 'no'}"><svg class="ic"><use href="#${k.ok ? 'i-check' : 'i-x'}"/></svg><span><b>${esc(k.label)}</b>${k.ok ? '<small>You said: “' + esc(k.said.join('”, “')) + '”</small>' : '<small>Missing</small>'}</span></li>`).join('')}</ul>
        </div>
      </div>
      ${missed.length ? `<div class="card add"><h3><svg class="ic"><use href="#i-plus"/></svg>Add these to your answer</h3><ul>${missed.map(k => { const ex = exampleFor(q, k); return `<li><b>${esc(k.label)}.</b> ${ex ? 'Say something like: “' + esc(ex) + '”' : ''}</li>`; }).join('')}</ul></div>` : ''}
      <div class="card better"><h3><svg class="ic"><use href="#i-bulb"/></svg>A better answer</h3><p>${betterHtml(q, a)}</p><button class="btn quiet small" id="btn-hear"><svg class="ic"><use href="#i-sound"/></svg>Hear it</button></div>
      <div class="card tips"><h3><svg class="ic"><use href="#i-star"/></svg>Tips</h3><ul>${tipsFor(q, a, text).map(([t, d]) => `<li><b>${esc(t)}.</b> ${esc(d)}</li>`).join('')}</ul></div>
      <div class="fb-btns"><button class="btn secondary" id="btn-again"><svg class="ic"><use href="#i-retry"/></svg>Try this question again</button><button class="btn primary" id="btn-next">${last ? '<svg class="ic"><use href="#i-check"/></svg>See my results' : 'Next question<svg class="ic"><use href="#i-next"/></svg>'}</button></div>`;
    $('#btn-again').onclick = () => { S.results.length = S.i; ask(); };
    $('#btn-next').onclick = next;
    $('#btn-hear').onclick = () => speak(q.model, PANEL[1]);
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function tipsFor(q, a, text) {
    const tips = [];
    if (a.words < 25) tips.push(['Say more', 'Your answer was short. Give the main point, then one or two supporting details or numbers.']);
    if (a.secs > 150) tips.push(['Be more direct', 'Your answer ran long. Lead with the main point in the first sentence, then stop after the key details.']);
    if (a.fillers > 2) tips.push(['Fewer filler words', 'You said ' + Object.entries(a.fill).map(([w, c]) => `“${w}” ×${c}`).join(', ') + '. Pause and breathe instead. Silence sounds more confident.']);
    if (a.hedges.length) tips.push(['Sound sure', 'You said “' + a.hedges.join('”, “') + '”. Say what you know plainly: “Our results show…”, “We used…”.']);
    if (a.wpm > 175) tips.push(['Slow down', 'About ' + a.wpm + ' words a minute. Aim for 120 to 160 so the panel can follow.']);
    if (a.wpm && a.wpm < 90 && a.words >= 25) tips.push(['Keep the flow', 'About ' + a.wpm + ' words a minute. Practice the answer a few times so it flows more smoothly.']);
    if (q.cat === 'results' && !/\d/.test(text) && !/(fifteen|fifty|sixty|seventy|eighty|ninety)/i.test(text)) tips.push(['Use the numbers', 'Results questions expect exact figures, like 65.67, 68, 15 teachers and 50 pupils.']);
    if (S && S.i === 0 && S.mode === 'mock' && !/^\s*(good|thank|ma'?am|sir|yes)/i.test(text)) tips.push(['Greet the panel', 'Start your first answer with “Good morning, panel” or “Thank you for the question.”']);
    tips.push(['Structure', 'Answer first, then explain, then end with the result or why it matters. One idea per sentence.']);
    if (a.stars >= 2 && a.fillers <= 2) tips.unshift(['Well done', 'Clear and complete. Practice it once more so it sounds natural, not memorised.']);
    return tips.slice(0, 4);
  }
  /* the model answer, with the parts that cover the points you missed in bold */
  function betterHtml(q, a) {
    const missed = a.keys.filter(k => !k.ok);
    return q.model.split(/(?<=[.!?])\s+/).map(s => missed.some(k => k.ws.some(w => has(norm(s), w))) ? '<b class="miss">' + esc(s) + '</b>' : esc(s)).join(' ');
  }
  function next() { if (!S) return; S.i++; if (S.i >= S.list.length) finish(); else ask(); }

  /* ---------- results of the session ---------- */
  function finish() {
    stopAll();
    const res = S.results.filter(Boolean), answered = res.filter(r => !r.skipped);
    const avg = answered.length ? Math.round(answered.reduce((t, r) => t + r.score, 0) / answered.length) : 0;   // skipped questions are listed, not averaged
    const entry = { at: Date.now(), mode: S.mode, title: $('#room-topic').textContent, avg, items: res.map(r => ({ q: r.q.q, cat: r.q.cat, score: r.score, stars: r.stars || 0, skipped: !!r.skipped, text: r.text || '' })) };
    if (res.length) { history.unshift(entry); history = history.slice(0, 50); store.set('history', history); }
    const byCat = {}; answered.forEach(r => { (byCat[r.q.cat] = byCat[r.q.cat] || []).push(r.score); });
    const weak = Object.entries(byCat).map(([c, s]) => [c, s.reduce((a, b) => a + b, 0) / s.length]).sort((a, b) => a[1] - b[1]).filter(x => x[1] < 70).slice(0, 2);
    const stars = avg >= 80 ? 3 : avg >= 60 ? 2 : avg >= 35 ? 1 : 0;
    const el = $('#s-summary');
    el.innerHTML = `
      <div class="card sum-top s${stars}">
        <img class="coach" src="assets/img/${stars >= 2 ? 'jarvis-cheer' : 'jarvis-idea'}.webp" alt="Jarvis the coach">
        <span class="eyebrow">${esc(entry.title.toUpperCase())} · DONE</span>
        <h1>${stars === 3 ? 'You are ready for the panel!' : stars === 2 ? 'Great practice!' : 'Keep practicing. You are getting there!'}</h1>
        <div class="ring big" style="--p:${avg}"><b>${avg}</b><small>average</small></div>
        <p>${answered.length} answered${res.length - answered.length ? ' · ' + (res.length - answered.length) + ' skipped' : ''}${weak.length ? ' · Practice more on <b>' + weak.map(w => esc(CATS[w[0]] || w[0])).join('</b> and <b>') + '</b>' : ''}</p>
        <div class="fb-btns">${weak.length ? `<button class="btn secondary" data-start="topic" data-cat="${weak[0][0]}"><svg class="ic"><use href="#i-retry"/></svg>Practice ${esc(CATS[weak[0][0]] || '')}</button>` : ''}<button class="btn primary" data-start="mock"><svg class="ic"><use href="#i-users"/></svg>Another mock defense</button><button class="btn quiet" data-go="home">Home</button></div>
      </div>
      <div class="card"><h3>Your answers</h3><ol class="sum-list">${res.map(r => `<li><span class="sl-q">${esc(r.q.q)}</span><span class="sl-s ${r.skipped ? 'skip' : 's' + (r.stars || 0)}">${r.skipped ? 'Skipped' : r.score + '%'}</span></li>`).join('')}</ol></div>`;
    $$('.screen').forEach(s => s.hidden = s.id !== 's-summary');
    S = null; window.scrollTo(0, 0);
  }

  /* ---------- question bank ---------- */
  function renderBank() {
    const sel = $('#bank-cat'), add = $('#add-cat');
    if (!sel.options.length) { sel.innerHTML = '<option value="">All topics</option>' + Object.entries(CATS).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join(''); add.innerHTML = Object.entries(CATS).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join(''); }
    const term = $('#bank-search').value.trim().toLowerCase(), cat = sel.value;
    const list = allQuestions().filter(q => (!cat || q.cat === cat) && (!term || (q.q + ' ' + q.model).toLowerCase().includes(term)));
    $('#bank-list').innerHTML = list.length ? list.map(q => `<details class="card bq"><summary><span class="tag">${esc(CATS[q.cat] || 'Mine')}</span>${esc(q.q)}${q.mine ? '<span class="mine">My question</span>' : ''}</summary>
      <p class="bq-model">${esc(q.model)}</p>
      ${q.keys && q.keys.length ? '<p class="bq-keys">' + q.keys.map(k => `<span>${esc(k[0])}</span>`).join('') + '</p>' : ''}
      <div class="bq-btns"><button class="btn primary small" data-practice="${q.id}"><svg class="ic"><use href="#i-mic"/></svg>Practice this</button>${q.mine ? `<button class="btn quiet small" data-del="${q.id}"><svg class="ic"><use href="#i-trash"/></svg>Delete</button>` : ''}</div></details>`).join('') : '<p class="empty">No questions match.</p>';
  }
  $('#bank-search').addEventListener('input', renderBank);
  $('#bank-cat').addEventListener('change', renderBank);
  $('#bank-list').addEventListener('click', e => {
    const p = e.target.closest('[data-practice]'), d = e.target.closest('[data-del]');
    if (p) { const q = allQuestions().find(x => x.id === p.dataset.practice); if (q) { S = { mode: 'one', list: [q], i: 0, results: [] }; $$('.screen').forEach(s => s.hidden = s.id !== 's-room'); $('#room-topic').textContent = CATS[q.cat] || 'My question'; ask(); } }
    if (d && confirm('Delete this question?')) { custom = custom.filter(q => q.id !== d.dataset.del); store.set('custom', custom); renderBank(); }
  });
  $('#form-add').addEventListener('submit', e => {
    e.preventDefault(); const f = e.target;
    const keys = f.keys.value.split('\n').map(l => l.trim()).filter(Boolean).map(l => { const [label, rest] = l.includes(':') ? [l.slice(0, l.indexOf(':')), l.slice(l.indexOf(':') + 1)] : [l, l]; return [label.trim(), rest.split(',').map(w => w.trim().toLowerCase()).filter(Boolean)]; });
    custom.push({ id: 'm' + Date.now(), cat: f.cat.value, q: f.q.value.trim(), model: f.model.value.trim(), keys });
    store.set('custom', custom); f.reset(); $('#add-q').open = false; renderBank();
  });

  /* ---------- history ---------- */
  function renderHistory() {
    $('#hist-list').innerHTML = history.length ? history.map(h => `<details class="card hist"><summary><span class="h-date">${new Date(h.at).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}</span><b>${esc(h.title)}</b><span class="h-n">${h.items.length} ${h.items.length === 1 ? 'question' : 'questions'}</span><span class="sl-s s${h.avg >= 80 ? 3 : h.avg >= 60 ? 2 : h.avg >= 35 ? 1 : 0}">${h.avg}%</span></summary>
      <ol class="sum-list">${h.items.map(i => `<li><span class="sl-q">${esc(i.q)}${i.text ? '<small>' + esc(i.text) + '</small>' : ''}</span><span class="sl-s ${i.skipped ? 'skip' : 's' + i.stars}">${i.skipped ? 'Skipped' : i.score + '%'}</span></li>`).join('')}</ol></details>`).join('') : '<p class="empty">No practice yet. Start a mock defense from Home.</p>';
  }

  /* ---------- settings ---------- */
  function renderSettings() {
    const f = $('#form-set');
    f.voice.checked = !!settings.voice; f.rate.value = settings.rate; f.lang.value = settings.lang; f.count.value = settings.count; f.limit.value = settings.limit; f.peek.checked = !!settings.peek;
    $('#rate-val').textContent = Number(settings.rate).toFixed(2) + '×';
  }
  $('#form-set').addEventListener('change', e => {
    const f = e.currentTarget;
    Object.assign(settings, { voice: f.voice.checked, rate: f.rate.value, lang: f.lang.value, count: f.count.value, limit: f.limit.value, peek: f.peek.checked });
    store.set('settings', settings); $('#rate-val').textContent = Number(settings.rate).toFixed(2) + '×';
  });
  $('#btn-clear').addEventListener('click', () => { if (confirm('Clear all practice history on this device?')) { history = []; store.set('history', history); renderHistory(); } });

  function buzz(p) { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { /* no vibration */ } }

  /* ---------- on the phone: install like an app, open offline ---------- */
  if ('serviceWorker' in navigator && location.protocol !== 'file:') navigator.serviceWorker.register('sw.js').catch(() => {});
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  let installEvt = null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); installEvt = e; if (!standalone) { $('#install').hidden = false; $('#btn-install').hidden = false; } });
  if (ios && !standalone) {   // Safari has no install button: say how
    $('#install').hidden = false; $('#btn-install').hidden = true;
    $('#install-text').innerHTML = '<b>Put it on your iPhone</b>Tap the Share button, then <b>Add to Home Screen</b>.';
  }
  $('#btn-install').addEventListener('click', async () => { if (!installEvt) return; installEvt.prompt(); try { await installEvt.userChoice; } catch (e) { /* closed */ } installEvt = null; $('#install').hidden = true; });
  window.addEventListener('appinstalled', () => { $('#install').hidden = true; });

  go(['bank', 'history', 'settings'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'home');
})();
