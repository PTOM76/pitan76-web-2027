(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const SITE = window.SITE;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));

  /* ---------- 効果音（Web Audio で合成） ---------- */
  const sound = (() => {
    // 音は scripts/php/sfx.php で合成した assets/sfx/ のファイルを鳴らす。
    // 同じ名前に複数の候補があるときは毎回ランダムに選び、同じ音の繰り返しを避ける
    const base = new URL('sfx/', document.currentScript.src);
    let ctx = null, out = null, bank = null;
    let on = true;
    try { on = localStorage.getItem('sound') !== 'off'; } catch { /* noop */ }
    const ac = () => {
      if (!on) return null;
      // ページを一度も操作していない間はブラウザが再生を許可しないため、何もしない
      if (navigator.userActivation && !navigator.userActivation.hasBeenActive) return null;
      if (!ctx) {
        try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch { on = false; return null; }
        out = ctx.createGain();
        out.gain.value = 0.5;
        out.connect(ctx.destination);
        bank = load(ctx);
      }
      if (ctx.state === 'suspended') ctx.resume();
      return ctx;
    };
    async function load(a) {
      const list = await (await fetch(new URL('sfx.json', base))).json();
      const entries = await Promise.all(Object.entries(list).map(async ([name, files]) => [name,
        await Promise.all(files.map(async (f) => a.decodeAudioData(await (await fetch(new URL(`${f}.m4a`, base))).arrayBuffer()))),
      ]));
      return Object.fromEntries(entries);
    }
    // 読み込み中に鳴らした音は、読み込みが終わり次第鳴らす（初回の 1 回だけ少し遅れる）
    function play(name, gain = 1) {
      const a = ac(); if (!a) return;
      bank.then((b) => {
        const list = b[name]; if (!list) return;
        const src = a.createBufferSource();
        const g = a.createGain();
        src.buffer = list[Math.floor(Math.random() * list.length)];
        g.gain.value = gain;
        src.connect(g).connect(out);
        src.start();
      }).catch(() => { /* 読み込めなければ無音のまま */ });
    }
    return {
      get on() { return on; },
      toggle() {
        on = !on;
        try { localStorage.setItem('sound', on ? 'on' : 'off'); } catch { /* noop */ }
        return on;
      },
      hit: () => play('hit', 0.7),
      breaks: () => play('break'),
      blip: () => play('blip', 0.6),
      transfer: () => play('transfer', 0.6),
      achieve: () => play('achieve', 0.7),
      boing: () => play('boing', 0.8),
    };
  })();
  const soundBtn = $('#sound');
  const paintSound = () => { soundBtn.textContent = sound.on ? '♪ ON' : '♪ OFF'; soundBtn.setAttribute('aria-pressed', String(sound.on)); };
  paintSound();
  soundBtn.addEventListener('click', () => { sound.toggle(); paintSound(); sound.blip(); });
  addEventListener('block-hit', () => sound.hit());

  /* ---------- 実績 ---------- */
  const unlocked = new Set();
  function toast(label, title, icon) {
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `<i>${icon}</i><div><b>${esc(label)}</b>${esc(title)}</div>`;
    $('#toasts').append(el);
    setTimeout(() => el.remove(), 4400);
  }
  function achieve(id, title, icon = '★') {
    if (unlocked.has(id)) return;
    unlocked.add(id);
    sound.achieve();
    toast('実績解除', title, icon);
  }

  /* ---------- 座標表示（背景の方眼 32px を 1 ブロックとして、ページ上端からの深さ） ---------- */
  const cy = $('#coord-y');
  const onScroll = () => {
    cy.textContent = String(-Math.round(scrollY / 32));
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- ぴたん ---------- */
  const pitan = $('#pitan');
  const pitanSay = $('#pitan-say');
  const lines = ['にゃ？', 'これは…バグですえ', 'バグじゃない、仕様ですえ', 'ブロック壊してみてな〜', 'ビルド中…', 'MOD作ってまし'];
  let pets = 0;
  function say(el, text, ms = 2200) {
    el.textContent = text;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), ms);
  }
  let drag = null;
  let dragged = false;
  pitan.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    e.preventDefault();   // 文字選択と画像の標準ドラッグを止める
    drag = { x: e.clientX, y: e.clientY };
    dragged = false;
    pitan.setPointerCapture(e.pointerId);
    pitan.classList.add('dragging');
  });
  pitan.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const dx = e.clientX - drag.x;
    const dy = e.clientY - drag.y;
    if (Math.hypot(dx, dy) > 6) dragged = true;
    pitan.style.setProperty('--dx', dx + 'px');
    pitan.style.setProperty('--dy', dy + 'px');
    pitan.style.setProperty('--tilt', (dx * 0.05) + 'deg');
  });
  const drop = () => {
    if (!drag) return;
    drag = null;
    pitan.classList.remove('dragging');
    pitan.style.setProperty('--dx', '0px');
    pitan.style.setProperty('--dy', '0px');
    pitan.style.setProperty('--tilt', '0deg');
    if (dragged) {
      sound.boing();
      say(pitanSay, '剥がさんといて〜');
      achieve('peel', 'ステッカーを剥がそうとした', '✋');
    }
  };
  pitan.addEventListener('pointerup', drop);
  pitan.addEventListener('pointercancel', drop);
  pitan.addEventListener('click', () => {
    if (dragged) return;
    sound.boing();
    pets++;
    pitan.classList.remove('boing');
    void pitan.offsetWidth;
    pitan.classList.add('boing');
    say(pitanSay, pets >= 10 ? 'もう十分なでたやろが！' : lines[(Math.random() * lines.length) | 0]);
    if (pets === 10) achieve('pet', '代理くんを10回なでた', '♥');
  });

  /* ---------- 作品索引 ---------- */
  const q = $('#index-q');
  if (q) {
    const rows = $$('#index-list li');
    const empty = $('#index-empty');
    const list = $('#index-list');
    const more = $('#index-more');
    let cat = '';
    let expanded = false;
    const filter = () => {
      const s = q.value.trim().toLowerCase();
      const all = expanded || !!s || !!cat;
      list.classList.toggle('is-all', all);
      if (more) more.hidden = all;
      let shown = 0;
      for (const r of rows) {
        const ok = (!cat || r.dataset.cat === cat) && (!s || r.dataset.q.includes(s));
        r.hidden = !ok;
        if (ok) shown++;
      }
      const wasHidden = empty.hidden;
      empty.hidden = shown > 0;
      if (wasHidden && !empty.hidden) achieve('404', 'HTTPちゃんに 404 を返された', '?');
    };
    q.addEventListener('input', filter);
    more?.addEventListener('click', () => { expanded = true; filter(); });
    $$('.filter').forEach((c) => c.addEventListener('click', () => {
      $$('.filter').forEach((x) => x.classList.toggle('is-on', x === c));
      cat = c.dataset.cat;
      filter();
    }));

    // ホバーで画像プレビュー
    const pv = $('#index-preview');
    for (const r of rows) {
      const id = r.dataset.id;
      r.addEventListener('pointerenter', () => {
        if (document.body.classList.contains('gl')) {
          dispatchEvent(new CustomEvent('summon', { detail: { id, el: r } }));
          return;
        }
        if (!r.dataset.thumb) return;
        pv.src = r.dataset.thumb;
        pv.classList.add('show');
      });
      r.addEventListener('pointerleave', () => {
        pv.classList.remove('show');
        dispatchEvent(new CustomEvent('summon', { detail: null }));
      });
    }
    addEventListener('pointermove', (e) => {
      pv.style.left = e.clientX + 24 + 'px';
      pv.style.top = Math.min(e.clientY - 40, innerHeight - 180) + 'px';
    }, { passive: true });
  }

  /* ---------- インベントリ ---------- */
  const seen = new Set();
  const slots = $$('.slot');
  slots.forEach((s) => s.addEventListener('pointerenter', () => {
    seen.add(s);
    if (seen.size === slots.length) achieve('inv', '持ち物を全部確認した', '▣');
  }));


  /* ---------- ブロック破壊 → FTPちゃん / HTTPちゃんが作品を届ける ---------- */
  // キャラクターの設定は data/characters.json。{file} は作品のファイル名に置き換える
  const couriers = Object.fromEntries(Object.entries(SITE.characters).map(([key, c]) => [key, {
    ...c, key, img: `assets/img/${key}.png`, start: (f) => c.start.replace('{file}', f),
  }]));
  const keys = Object.keys(couriers);
  // 作品のカテゴリを担当するキャラ（characters.json の categories）。いなければ全員からランダム
  const courierFor = (w) => keys.find((k) => couriers[k].categories?.includes(w.cat)) || keys[(Math.random() * keys.length) | 0];
  const courier = $('#courier');
  const courierImg = $('img', courier);
  const ccmd = $('#courier-cmd');
  const cbar = $('#courier-bar');
  const loot = $('#loot');
  let delivering = false;
  let current = couriers[keys[0]];
  let pending = null;
  async function deliver(w, kind) {
    if (delivering) { pending = w; return; }
    delivering = true;
    const file = (w.id || 'item').replace(/[^a-z0-9_-]/gi, '-');
    const c = couriers[kind || courierFor(w)];
    courierImg.src = c.img;
    current = c;
    loadCard(w).catch(() => {});   // 配達の演出の間に読み込んでおく
    ccmd.textContent = c.start(file);
    cbar.style.transition = 'none';
    cbar.style.width = '0';
    courier.classList.add('show');
    await wait(reduced ? 0 : 380);
    cbar.style.transition = 'width .7s steps(12)';
    cbar.style.width = '100%';
    await wait(reduced ? 0 : 750);
    ccmd.textContent = c.done;
    sound.transfer();
    await showLoot(w);
    // カードを閉じるまで（最長 8 秒）その場に残る
    const t0 = performance.now();
    while ((!loot.hidden && performance.now() - t0 < 8000) || !chara.hidden) await wait(200);
    await wait(1200);
    courier.classList.remove('show');
    delivering = false;
    if (pending) {
      const next = pending;
      pending = null;
      await wait(500);
      deliver(next);
    }
  }
  // 配達中のキャラをタップすると紹介カードを開く
  const chara = $('#chara');
  $('#courier-btn').addEventListener('click', () => {
    $('#chara-img').src = current.img;
    $('#chara-name').textContent = current.name;
    $('#chara-proto').textContent = current.proto;
    $('#chara-desc').textContent = current.desc;
    chara.hidden = false;
    sound.blip();
    achieve('chara-' + current.key, current.name + 'に会った', '✉');
  });
  $('#chara-close').addEventListener('click', () => { chara.hidden = true; });
  chara.addEventListener('click', (e) => { if (e.target === chara) chara.hidden = true; });
  addEventListener('keydown', (e) => { if (e.key === 'Escape') chara.hidden = true; });

  // カードの中身は PHP が作品ごとに works/ID.card.html として出力している。必要になった 1 件だけ読み込む
  const cards = new Map();
  function loadCard(w) {
    if (!cards.has(w.id)) {
      const req = fetch(w.card).then((r) => (r.ok ? r.text() : Promise.reject(r.status)));
      req.catch(() => cards.delete(w.id));   // 失敗したら次回また読み込む
      cards.set(w.id, req);
    }
    return cards.get(w.id);
  }
  async function showLoot(w) {
    let html;
    try {
      html = await loadCard(w);
    } catch {
      // 読み込めなければ詳細ページへの案内だけ出す
      const a = document.createElement('a');
      a.href = w.page; a.textContent = `${w.name} の詳細ページを開く`;
      $('#loot-body').replaceChildren(a);
      loot.hidden = false;
      return;
    }
    // 中身はビルド時に PHP がエスケープ済みの、同じサイト内の HTML
    $('#loot-body').innerHTML = html;
    loot.hidden = false;
  }
  loot.addEventListener('click', (e) => { if (e.target === loot) loot.hidden = true; });
  $('#loot-close').addEventListener('click', () => { loot.hidden = true; });
  addEventListener('keydown', (e) => { if (e.key === 'Escape') loot.hidden = true; });

  /* ---------- 作品図鑑（掘り当てた作品をブラウザに記録する） ---------- */
  const found = new Set();
  try { for (const id of JSON.parse(localStorage.getItem('dex') || '[]')) found.add(id); } catch { /* noop */ }
  const dex = $('#dex');
  // マス目は PHP が全作品分を出力している。見つけた作品のマスを有効にし、画像を読み込む
  const dexSlots = new Map($$('.dex-slot').map((b) => [b.dataset.id, b]));
  const byId = new Map(SITE.works.map((w) => [w.id, w]));
  function renderDex() {
    let n = 0;
    for (const [id, b] of dexSlots) {
      if (!found.has(id)) continue;
      n++;
      if (b.classList.contains('is-found')) continue;
      b.classList.add('is-found');
      b.disabled = false;
      b.title = b.dataset.name;
      b.setAttribute('aria-label', b.dataset.name);
      const img = $('img[data-src]', b);
      if (img) img.src = img.dataset.src;
    }
    $('#dex-found').textContent = n;
  }
  $('#dex-grid').addEventListener('click', (e) => {
    const b = e.target.closest('.dex-slot.is-found');
    if (b && byId.has(b.dataset.id)) showLoot(byId.get(b.dataset.id));
  });
  function openDex() {
    renderDex();
    dex.hidden = false;
    achieve('dex', '図鑑を開いた', '▤');
  }
  $('#dex-open').addEventListener('click', openDex);
  $('#dex-close').addEventListener('click', () => { dex.hidden = true; });
  dex.addEventListener('click', (e) => { if (e.target === dex) dex.hidden = true; });
  addEventListener('keydown', (e) => { if (e.key === 'Escape' && loot.hidden && chara.hidden) dex.hidden = true; });
  function register(w) {
    if (found.has(w.id)) return;
    found.add(w.id);
    try { localStorage.setItem('dex', JSON.stringify([...found])); } catch { /* noop */ }
    toast('図鑑に登録', w.name, '▤');
    if (!dex.hidden) renderDex();
    if (found.size === 10) achieve('found10', '作品を10個発掘した', '◎');
    if (found.size === 50) achieve('found50', '作品を50個発掘した', '◆');
    if (SITE.works.every((x) => found.has(x.id))) achieve('foundAll', '図鑑をすべて埋めた', '★');
  }

  addEventListener('block-break', (e) => {
    const { count, work } = e.detail;
    sound.breaks();
    if (work) {
      register(work);
      deliver(work);
    }
    if (count === 1) achieve('mine1', 'はじめてのブロック破壊', '⛏');
    if (count === 20) achieve('mine20', 'ブロックを20個壊した', '◆');
  });

  /* ---------- 昼と夜（自動 / 昼 / 夜） ---------- */
  const themeBtn = $('#theme');
  const THEME_LABEL = { auto: '◐', light: '☀', dark: '☾' };
  let themeMode = document.documentElement.dataset.themeMode || 'auto';
  function applyTheme() {
    const h = new Date().getHours();
    const dark = themeMode === 'auto' ? !(h >= 6 && h < 18) : themeMode === 'dark';
    const root = document.documentElement;
    const changed = root.dataset.theme !== (dark ? 'dark' : 'light');
    root.dataset.theme = dark ? 'dark' : 'light';
    themeBtn.textContent = THEME_LABEL[themeMode];
    themeBtn.title = `昼と夜の切り替え（今: ${THEME_LABEL[themeMode].slice(2)}）`;
    $('meta[name="theme-color"]').content = dark ? '#121829' : '#dcecfa';
    if (changed) dispatchEvent(new CustomEvent('themechange', { detail: { dark } }));
  }
  themeBtn.addEventListener('click', () => {
    themeMode = { auto: 'light', light: 'dark', dark: 'auto' }[themeMode];
    try { localStorage.setItem('theme', themeMode); } catch { /* noop */ }
    sound.blip();
    applyTheme();
    if (themeMode === 'dark') achieve('night', '夜にした', '☾');
  });
  setInterval(applyTheme, 60000);   // 自動のとき、時刻が変われば切り替える
  applyTheme();

  /* ---------- ブロックのホバー表示 ---------- */
  const tip = $('#block-tip');
  addEventListener('block-hover', (e) => {
    const d = e.detail;
    if (!d) { tip.classList.remove('show'); return; }
    tip.classList.toggle('has-work', !!d.work);
    tip.innerHTML = d.work
      ? `<b>◆ 作品入り</b><span>${esc(d.work.cat)} · 長押しで採掘</span>`
      : '<b>ただのブロック</b><span>長押しで採掘</span>';
    tip.classList.add('show');
  });
  addEventListener('pointermove', (e) => {
    tip.style.transform = `translate(${e.clientX + 18}px, ${e.clientY + 18}px)`;
  }, { passive: true });

  /* ---------- バグモード ---------- */
  const konami = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
  let kpos = 0;
  let bugOn = false;
  function bugMode(on = !bugOn) {
    bugOn = on;
    document.body.classList.toggle('bug', on);
    dispatchEvent(new CustomEvent('bugmode', { detail: on }));
    if (on) {
      achieve('bug', 'バグモードを発見した', '!');
      for (let i = 0; i < 24; i++) {
        const el = document.createElement('img');
        el.className = 'rain';
        el.src = `assets/img/${['pitan', 'ftp', 'http'][i % 3]}.png`;
        el.style.left = Math.random() * 100 + 'vw';
        el.style.animationDuration = 2 + Math.random() * 3 + 's';
        el.style.animationDelay = Math.random() * 2 + 's';
        el.addEventListener('animationend', () => el.remove());
        document.body.append(el);
      }
    }
    return on;
  }

  /* ---------- ターミナル ---------- */
  const term = $('#terminal');
  const out = $('#term-out');
  const input = $('#term-input');
  const hist = [];
  let hpos = 0;
  const print = (html, cls = '') => {
    const d = document.createElement('div');
    if (cls) d.className = cls;
    d.innerHTML = html;
    out.append(d);
    out.scrollTop = out.scrollHeight;
  };
  const commands = {
    // 固定の出力は PHP の terminal_text() が作る
    help: () => SITE.term.help,
    whoami: () => SITE.term.whoami,
    ls: () => SITE.term.ls,
    open: (n) => {
      const p = SITE.projects[Number(n) - 1];
      if (!p) return `<span class="err">usage: open 1-${SITE.projects.length}</span>`;
      window.open(p.url, '_blank', 'noopener');
      return `opening ${esc(p.name)} …`;
    },
    find: (s) => {
      const k = s.toLowerCase();
      if (!k) return '<span class="err">usage: find &lt;語&gt;</span>';
      const hits = SITE.works.filter((w) => (w.name + ' ' + w.desc + ' ' + w.tags.join(' ')).toLowerCase().includes(k));
      return hits.length ? hits.slice(0, 12).map((w) => `${esc(w.name.padEnd(28))} ${esc(w.cat)}`).join('\n') + (hits.length > 12 ? `\n… ほか ${hits.length - 12} 件` : '') : '0 件';
    },
    skills: () => SITE.term.skills,
    curl: (u) => {
      const m = u.match(/(?:^|\/)works\/(.+)$/);
      if (m) {
        const w = findWork(m[1]);
        if (!w) return 'HTTP/1.1 404 Not Found\nX-Served-By: HTTPプロトコルさん';
        term.hidden = true;
        showLoot(w);
        return `HTTP/1.1 200 OK\nContent-Type: application/x-pitan-work\nX-Served-By: HTTPプロトコルさん\n\n${esc(w.name)}`;
      }
      const r = !u ? '400 Bad Request' : /pitan/i.test(u) ? '200 OK' : '418 I\'m a teapot';
      return `HTTP/1.1 ${r}\nX-Served-By: HTTPプロトコルさん`;
    },
    goto: (s) => {
      const el = document.getElementById(s);
      if (!el) return '<span class="err">sections: works, index, inventory, log</span>';
      term.hidden = true;
      el.scrollIntoView({ behavior: 'smooth' });
      return '';
    },
    bug: () => (bugMode() ? 'bug mode: ON' : 'bug mode: OFF'),
    sudo: (a) => {
      if (/^rm\s+-(rf|fr)\s+\/\*?$/.test(a)) {
        term.hidden = true;
        collapse();
        achieve('rmrf', 'sudo rm -rf / を実行した', '☠');
        return '<span class="err">removing / …</span>';
      }
      achieve('sudo', 'sudo を試みた', '#');
      return '<span class="err">visitor is not in the sudoers file. This incident will be reported.</span>';
    },
    smtp: (a) => {
      const m = a.match(/^send\s+(.+)$/);
      if (!m) return '220 SMTPプロトコルさん ESMTP ready.\nusage: smtp send &lt;作品名&gt;';
      const w = findWork(m[1]);
      if (!w) return `550 ${esc(m[1])}: Mailbox unavailable`;
      term.hidden = true;
      deliver(w, 'smtp');
      return '250 OK\n354 Start mail input; end with &lt;CRLF&gt;.&lt;CRLF&gt;';
    },
    dex: () => { term.hidden = true; openDex(); return ''; },
    theme: (a) => {
      if (!['auto', 'light', 'dark'].includes(a)) return `theme: ${themeMode}\nusage: theme auto|light|dark`;
      themeMode = a;
      try { localStorage.setItem('theme', a); } catch { /* noop */ }
      applyTheme();
      return `theme: ${a}`;
    },
    rm: (a) => (a.includes('-rf') ? '<span class="err">やめてください。</span>' : 'rm: missing operand'),
    ftp: (a) => {
      const m = a.match(/^get\s+(.+)$/);
      if (!m) return '220 FTPプロトコルさん ready.\nusage: ftp get &lt;作品名&gt;';
      const w = findWork(m[1]);
      if (!w) return `550 ${esc(m[1])}: No such file or directory`;
      term.hidden = true;
      deliver(w, 'ftp');
      return `200 PORT command successful.\n150 Opening BINARY mode data connection for ${esc(w.id)}`;
    },
    pitan: () => 'にゃ。',
    clear: () => { out.innerHTML = ''; return ''; },
    exit: () => { term.hidden = true; return ''; },
  };
  // 作品を ID か名前の一部で探す
  function findWork(s) {
    const k = decodeURIComponent(s).trim().toLowerCase();
    return SITE.works.find((w) => w.id.toLowerCase() === k)
      || SITE.works.find((w) => w.name.toLowerCase() === k)
      || SITE.works.find((w) => w.name.toLowerCase().includes(k) || w.id.toLowerCase().includes(k));
  }

  // sudo rm -rf / : ページの部品が崩れ落ち、しばらくして元に戻る
  let collapsing = false;
  function collapse() {
    if (collapsing) return;
    collapsing = true;
    sound.breaks();
    dispatchEvent(new CustomEvent('collapse'));
    const parts = $$('main > *, .footer');
    parts.forEach((el) => {
      el.style.setProperty('--fall-r', ((Math.random() - 0.5) * 70).toFixed(1) + 'deg');
      el.style.setProperty('--fall-x', ((Math.random() - 0.5) * 30).toFixed(1) + 'vw');
      el.style.setProperty('--fall-d', (Math.random() * 0.5).toFixed(2) + 's');
    });
    document.body.classList.add('collapsed');
    setTimeout(() => {
      document.body.classList.remove('collapsed');
      parts.forEach((el) => ['--fall-r', '--fall-x', '--fall-d'].forEach((p) => el.style.removeProperty(p)));
      collapsing = false;
      toast('復旧', 'バックアップから復元しました', '↺');
    }, 4200);
  }

  function openTerm() {
    term.hidden = false;
    if (!out.childElementCount) print('pitan shell — "help" でコマンド一覧');
    input.focus();
    achieve('term', 'ターミナルを開いた', '>');
  }
  $$('[data-open-terminal]').forEach((b) => b.addEventListener('click', openTerm));
  $$('[data-close-terminal]').forEach((b) => b.addEventListener('click', () => { term.hidden = true; }));
  term.addEventListener('click', (e) => { if (e.target === term) term.hidden = true; });
  $('#term-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const line = input.value.trim();
    input.value = '';
    if (!line) return;
    hist.push(line);
    hpos = hist.length;
    print('$ ' + esc(line), 'cmd');
    const [cmd, ...args] = line.split(/\s+/);
    const fn = commands[cmd.toLowerCase()];
    const res = fn ? fn(args.join(' ')) : `<span class="err">command not found: ${esc(cmd)}</span>`;
    if (res) print(res);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp' && hpos > 0) { input.value = hist[--hpos]; e.preventDefault(); }
    if (e.key === 'ArrowDown') { hpos = Math.min(hist.length, hpos + 1); input.value = hist[hpos] || ''; }
  });

  addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { term.hidden = true; return; }
    if (e.target.closest('input, textarea')) return;
    if (e.key === '`') { e.preventDefault(); openTerm(); return; }
    kpos = e.key === konami[kpos] ? kpos + 1 : (e.key === konami[0] ? 1 : 0);
    if (kpos === konami.length) { kpos = 0; bugMode(); }
  });

  /* ---------- #debug ---------- */
  const debug = $('#debug');
  const updateDebug = () => {
    debug.hidden = location.hash !== '#debug';
    if (!debug.hidden) achieve('debug', 'デバッグ表示を見つけた', '%');
  };
  addEventListener('hashchange', updateDebug);
  updateDebug();
  setInterval(() => {
    if (debug.hidden) return;
    const s = window.__sceneStats || {};
    debug.textContent = `fps    ${s.fps ?? '-'}\nblocks ${s.blocks ?? '-'}\nbroken ${s.broken ?? 0}\nshards ${s.shards ?? 0}\ncalls  ${s.calls ?? '-'}\nbug    ${bugOn}`;
  }, 250);

  /* ---------- その他 ---------- */
  const title = document.title;
  document.addEventListener('visibilitychange', () => {
    document.title = document.hidden ? "Connection: keep-alive… | " + title : title;
  });
  console.log('%cPITAN.76', 'font: 40px monospace; background: #161514; color: #ffad0a; padding: 4px 12px');
  console.log('↑↑↓↓←→←→BA');
})();
