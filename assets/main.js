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
    let ctx = null;
    let on = true;
    try { on = localStorage.getItem('sound') !== 'off'; } catch { /* noop */ }
    const ac = () => {
      if (!on) return null;
      // ページを一度も操作していない間はブラウザが再生を許可しないため、何もしない
      if (navigator.userActivation && !navigator.userActivation.hasBeenActive) return null;
      ctx ??= new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.state === 'suspended') ctx.resume();
      return ctx;
    };
    function noise(dur, freq, gain) {
      const a = ac(); if (!a) return;
      const buf = a.createBuffer(1, Math.ceil(a.sampleRate * dur), a.sampleRate);
      const d = buf.getChannelData(0);
      for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / d.length) ** 2;
      const src = a.createBufferSource();
      const f = a.createBiquadFilter();
      const g = a.createGain();
      src.buffer = buf; f.type = 'lowpass'; f.frequency.value = freq; g.gain.value = gain;
      src.connect(f).connect(g).connect(a.destination);
      src.start();
    }
    function tone(freqs, step = 0.07, type = 'square', gain = 0.05) {
      const a = ac(); if (!a) return;
      freqs.forEach((fq, i) => {
        const o = a.createOscillator();
        const g = a.createGain();
        const t0 = a.currentTime + i * step;
        o.type = type; o.frequency.value = fq;
        g.gain.setValueAtTime(gain, t0);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + step * 1.6);
        o.connect(g).connect(a.destination);
        o.start(t0); o.stop(t0 + step * 1.7);
      });
    }
    return {
      get on() { return on; },
      toggle() {
        on = !on;
        try { localStorage.setItem('sound', on ? 'on' : 'off'); } catch { /* noop */ }
        return on;
      },
      hit: () => noise(0.06, 900 + Math.random() * 500, 0.35),
      breaks: () => { noise(0.22, 1600, 0.5); noise(0.12, 300, 0.6); },
      blip: () => tone([660, 880], 0.05, 'square', 0.03),
      transfer: () => tone([523, 659, 784, 1047], 0.06, 'square', 0.035),
      achieve: () => tone([784, 988, 1319], 0.09, 'triangle', 0.08),
      boing: () => tone([320, 480], 0.06, 'sine', 0.12),
    };
  })();
  const soundBtn = $('#sound');
  const paintSound = () => { soundBtn.textContent = sound.on ? '♪ ON' : '♪ OFF'; soundBtn.setAttribute('aria-pressed', String(sound.on)); };
  paintSound();
  soundBtn.addEventListener('click', () => { sound.toggle(); paintSound(); sound.blip(); });
  addEventListener('block-hit', () => sound.hit());

  /* ---------- 実績 ---------- */
  const unlocked = new Set();
  function achieve(id, title, icon = '★') {
    if (unlocked.has(id)) return;
    unlocked.add(id);
    sound.achieve();
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `<i>${icon}</i><div><b>実績解除</b>${esc(title)}</div>`;
    $('#toasts').append(el);
    setTimeout(() => el.remove(), 4400);
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
  const lines = ['にゃ？', 'なでなで…', 'バグじゃないです、仕様です', 'ブロック壊してみて', 'ビルド中…', 'MOD作ってます', 'あっ、そこは触らないで'];
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
      say(pitanSay, '剥がさないで〜');
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
    say(pitanSay, pets >= 10 ? 'もう十分なでたでしょ！' : lines[(Math.random() * lines.length) | 0]);
    if (pets === 10) achieve('pet', 'ぴたんを10回なでた', '♥');
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
  // Web 系の作品は HTTPちゃん、それ以外はランダム
  const couriers = {
    ftp: { name: 'FTPプロトコルさん', proto: 'FTP / File Transfer Protocol', desc: 'ファイルの転送を担当する。サーバーへのアップロードやダウンロードで作品を届ける。', img: 'assets/img/ftp.png', start: (f) => `STOR ${f}.dat`, done: '226 Transfer complete' },
    http: { name: 'HTTPプロトコルさん', proto: 'HTTP / Hypertext Transfer Protocol', desc: 'Webページの配送を担当する。リクエストを受け取り、レスポンスとして作品を届ける。見つからないときは 404 を返す。', img: 'assets/img/http.png', start: (f) => `GET /works/${f} HTTP/1.1`, done: 'HTTP/1.1 200 OK' },
  };
  const courier = $('#courier');
  const courierImg = $('img', courier);
  const ccmd = $('#courier-cmd');
  const cbar = $('#courier-bar');
  const loot = $('#loot');
  let delivering = false;
  let current = couriers.ftp;
  let pending = null;
  async function deliver(w) {
    if (delivering) { pending = w; return; }
    delivering = true;
    const file = (w.id || 'item').replace(/[^a-z0-9_-]/gi, '-');
    const c = couriers[w.cat === 'Web' || w.cat === 'Extension' || Math.random() < 0.5 ? 'http' : 'ftp'];
    courierImg.src = c.img;
    current = c;
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
    showLoot(w);
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
    achieve('chara-' + (current === couriers.http ? 'http' : 'ftp'), current.name + 'に会った', '✉');
  });
  $('#chara-close').addEventListener('click', () => { chara.hidden = true; });
  chara.addEventListener('click', (e) => { if (e.target === chara) chara.hidden = true; });
  addEventListener('keydown', (e) => { if (e.key === 'Escape') chara.hidden = true; });

  function showLoot(w) {
    const img = $('#loot-img');
    // 画像のない作品で前の作品の画像が残らないよう、毎回 src を消す
    img.removeAttribute('src');
    img.hidden = !w.thumb;
    if (w.thumb) img.src = w.thumb + '-w.jpg';
    $('#loot-name').textContent = w.name;
    $('#loot-meta').textContent = [w.cat, w.year].filter(Boolean).join(' · ');
    $('#loot-desc').textContent = w.desc;
    const note = $('#loot-note');
    note.hidden = !w.note;
    $('p', note).textContent = w.note || '';
    // 詳細。値のない項目は出さない
    const fmt = (d) => d ? d.replace(/\//g, '-') : '';
    const spec = [
      ['公開', fmt(w.created)], ['更新', w.updated ? fmt(w.date) : ''], ['バージョン', w.version],
      ['対応 MC', w.mc], ['ライセンス', w.license],
    ].filter(([, v]) => v);
    $('#loot-spec').replaceChildren(...spec.flatMap(([k, v]) => {
      const dt = document.createElement('dt'); dt.textContent = k;
      const dd = document.createElement('dd'); dd.textContent = v;
      return [dt, dd];
    }));
    $('#loot-tags').textContent = (w.tags || []).map((x) => '#' + x).join(' ');
    const links = w.links?.length ? w.links : [{ label: '開く', url: w.url || SITE.github }];
    $('#loot-links').replaceChildren(...links.map((l) => {
      const a = document.createElement('a');
      a.href = l.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = l.label;
      return a;
    }));
    loot.hidden = false;
  }
  loot.addEventListener('click', (e) => { if (e.target === loot) loot.hidden = true; });
  $('#loot-close').addEventListener('click', () => { loot.hidden = true; });
  addEventListener('keydown', (e) => { if (e.key === 'Escape') loot.hidden = true; });

  const found = new Set();
  addEventListener('block-break', (e) => {
    const { count, work } = e.detail;
    sound.breaks();
    if (work) {
      found.add(work.id);
      deliver(work);
    }
    if (count === 1) achieve('mine1', 'はじめてのブロック破壊', '⛏');
    if (count === 20) achieve('mine20', 'ブロックを20個壊した', '◆');
    if (found.size === 10) achieve('found10', '作品を10個発掘した', '◎');
  });

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
    help: () => 'help, whoami, ls, open <n>, find <語>, skills, curl <url>, goto <section>, bug, sudo, clear, exit'.replace(/</g, '&lt;'),
    whoami: () => 'pitan — Bug-Creator。MODやWebなどを開発しています。',
    ls: () => SITE.projects.map((p, i) => `[${i + 1}] ${esc(p.name)} — ${esc(p.summary)}`).join('\n'),
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
    skills: () => SITE.skills.map((g) => `${g.name.padEnd(15)} ${g.items.join(', ')}`).join('\n'),
    curl: (u) => {
      const r = !u ? '400 Bad Request' : /pitan/i.test(u) ? '200 OK' : '418 I\'m a teapot';
      return `HTTP/1.1 ${r}\nX-Served-By: HTTP-chan`;
    },
    goto: (s) => {
      const el = document.getElementById(s);
      if (!el) return '<span class="err">sections: works, index, inventory, log</span>';
      term.hidden = true;
      el.scrollIntoView({ behavior: 'smooth' });
      return '';
    },
    bug: () => (bugMode() ? 'bug mode: ON' : 'bug mode: OFF'),
    sudo: () => { achieve('sudo', 'sudo を試みた', '#'); return '<span class="err">visitor is not in the sudoers file. This incident will be reported.</span>'; },
    rm: (a) => (a.includes('-rf') ? '<span class="err">やめてください。</span>' : 'rm: missing operand'),
    ftp: () => '220 FTP-chan ready.\n530 Login incorrect.',
    pitan: () => 'にゃ。',
    clear: () => { out.innerHTML = ''; return ''; },
    exit: () => { term.hidden = true; return ''; },
  };
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
    if (e.key === 'Escape') { term.hidden = true; loot.hidden = true; return; }
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
