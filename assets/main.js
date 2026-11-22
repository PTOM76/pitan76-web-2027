(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const store = {
    get(k) { try { return sessionStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { sessionStorage.setItem(k, v); } catch { /* noop */ } },
  };

  /* ---------- 実績トースト ---------- */
  const unlocked = new Set();
  function achieve(id, title, icon = '🏆') {
    if (unlocked.has(id)) return;
    unlocked.add(id);
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `<span>${icon}</span><div><b>実績解除！</b>${title}</div>`;
    $('#toasts').append(el);
    setTimeout(() => el.remove(), 4600);
  }

  /* ---------- 起動画面 ---------- */
  const boot = $('#boot');
  const bootLines = [
    'BUG-CREATOR BIOS v20.27',
    'memory check ......... 76 KB OK',
    'loading kernel: pitan.ko',
    'mounting /works ...... ' + window.SITE.works + ' entries',
    'spawning voxels ...... done',
    'injecting bugs ....... done (intentional)',
    '',
    'welcome, visitor.',
  ];
  function endBoot() {
    boot.classList.add('done');
    store.set('booted', '1');
    startHero();
  }
  if (store.get('booted') || reduced) {
    boot.remove();
    startHero();
  } else {
    let i = 0;
    const log = $('#boot-log');
    const timer = setInterval(() => {
      log.textContent += bootLines[i++] + '\n';
      if (i >= bootLines.length) { clearInterval(timer); setTimeout(endBoot, 500); }
    }, 140);
    const skip = () => { clearInterval(timer); endBoot(); removeEventListener('keydown', skip); };
    boot.addEventListener('click', skip, { once: true });
    addEventListener('keydown', skip, { once: true });
  }

  /* ---------- タイトルのスクランブル ---------- */
  function scramble(el, text, dur = 900) {
    const chars = '!<>-_\\/[]{}=+*^?#01ABCDEFXZ';
    const start = performance.now();
    (function frame(now) {
      const p = Math.min(1, (now - start) / dur);
      const fixed = Math.floor(text.length * p);
      el.textContent = text.slice(0, fixed) + [...text.slice(fixed)].map(() => chars[Math.floor(Math.random() * chars.length)]).join('');
      if (p < 1) requestAnimationFrame(frame);
    })(start);
  }
  let heroStarted = false;
  function startHero() {
    if (heroStarted) return;
    heroStarted = true;
    const t = $('[data-scramble]');
    if (!reduced) scramble(t, t.dataset.scramble);
    t.addEventListener('mouseenter', () => scramble(t, t.dataset.scramble, 500));
  }

  /* ---------- スクロールで表示 ---------- */
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      e.target.classList.add('in');
      const n = $('[data-count]', e.target);
      if (n) countUp(n);
      io.unobserve(e.target);
    }
  }, { threshold: 0.15 });
  $$('.reveal').forEach((el) => io.observe(el));

  function countUp(el) {
    const raw = el.dataset.count;
    const m = raw.match(/^([\d,]+)(.*)$/);
    if (!m || reduced) return;
    const end = Number(m[1].replace(/,/g, ''));
    const start = performance.now();
    (function frame(now) {
      const p = Math.min(1, (now - start) / 1400);
      const v = Math.round(end * (1 - Math.pow(1 - p, 3)));
      el.textContent = v.toLocaleString('ja-JP') + m[2];
      if (p < 1) requestAnimationFrame(frame);
    })(start);
  }

  /* ---------- 3D チルト ---------- */
  $$('.tilt').forEach((el) => {
    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      el.style.transform = `perspective(900px) rotateY(${x * 10}deg) rotateX(${-y * 10}deg)`;
    });
    el.addEventListener('pointerleave', () => { el.style.transform = ''; });
  });

  /* ---------- ぴたん ---------- */
  const pitan = $('#pitan');
  const bubble = $('#pitan-bubble');
  const lines = ['にゃ？', 'なでなで…', 'バグじゃないです、仕様です', 'MOD作ってます', 'ブロック壊してみて', 'ビルド中…', 'あっ、そこは触らないで', 'ぴたんです'];
  let pets = 0;
  let bubbleTimer;
  function say(el, text) {
    el.textContent = text;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2200);
  }
  pitan.addEventListener('click', () => {
    pets++;
    pitan.classList.remove('boing');
    void pitan.offsetWidth;
    pitan.classList.add('boing');
    say(bubble, pets >= 10 ? 'もう十分なでたでしょ！' : lines[Math.floor(Math.random() * lines.length)]);
    if (pets === 10) achieve('pet', 'ぴたんを10回なでた', '🐾');
    clearTimeout(bubbleTimer);
  });
  // 目線がマウスを追う代わりに、アバター全体を少し傾ける
  addEventListener('pointermove', (e) => {
    if (reduced) return;
    const r = pitan.getBoundingClientRect();
    const x = (e.clientX - (r.left + r.width / 2)) / innerWidth;
    const y = (e.clientY - (r.top + r.height / 2)) / innerHeight;
    $('img', pitan).style.transform = `rotate(${x * 12}deg) translate(${x * 16}px, ${y * 12}px)`;
  });

  /* ---------- フレンズ ---------- */
  const friendLines = {
    ftp: ['ファイル送るよ〜', 'PORT 21 で待ってるね', 'パッシブモード派です', 'アップロード完了！'],
    http: ['200 OK！', '404… どこいったの？', 'ステートレスなので覚えてません', 'HTTPS になりたい'],
  };
  $$('.friend').forEach((f) => {
    const b = document.createElement('div');
    b.className = 'bubble';
    f.append(b);
    let n = 0;
    f.addEventListener('click', () => {
      const list = friendLines[f.dataset.friend];
      say(b, list[n++ % list.length]);
      if (n === 1) {
        f.dataset.met = '1';
        if ($$('.friend[data-met]').length === 2) achieve('friends', 'ふたりと話した', '💬');
      }
    });
  });

  // 画面端から覗く
  const peek = $('#peek');
  let lastPeek = 0;
  addEventListener('scroll', () => {
    const now = Date.now();
    if (now - lastPeek < 12000 || scrollY < innerHeight * 1.5 || reduced) return;
    lastPeek = now;
    const ftp = Math.random() < 0.5;
    peek.src = `assets/img/${ftp ? 'ftp' : 'http'}.png`;
    peek.classList.toggle('left', !ftp);
    peek.classList.add('show');
    setTimeout(() => peek.classList.remove('show'), 1800);
  }, { passive: true });

  /* ---------- アーカイブ ---------- */
  const q = $('#archive-q');
  if (q) {
    let cat = '';
    const items = $$('.aw');
    const filter = () => {
      const s = q.value.trim().toLowerCase();
      let shown = 0;
      for (const it of items) {
        const ok = (!cat || it.dataset.cat === cat) && (!s || it.dataset.q.includes(s));
        it.hidden = !ok;
        if (ok) shown++;
      }
      $('#archive-empty').hidden = shown > 0;
    };
    q.addEventListener('input', filter);
    $$('.chip').forEach((c) => c.addEventListener('click', () => {
      $$('.chip').forEach((x) => x.classList.toggle('is-on', x === c));
      cat = c.dataset.cat;
      filter();
    }));
  }

  /* ---------- スキルスロット ---------- */
  let slotHover = new Set();
  $$('.slot').forEach((s) => s.addEventListener('mouseenter', () => {
    slotHover.add(s.textContent);
    if (slotHover.size === $$('.slot').length) achieve('inventory', 'インベントリを全部チェックした', '🎒');
  }));

  /* ---------- ブロック破壊 ---------- */
  addEventListener('block-break', (e) => {
    const n = e.detail.count;
    if (n === 1) achieve('mine1', 'はじめてのブロック破壊', '⛏️');
    if (n === 20) achieve('mine20', 'ブロックを20個壊した', '💎');
    if (n === 76) achieve('mine76', '76個…これはぴたんの数字', '👑');
  });

  /* ---------- バグモード（Konami） ---------- */
  const konami = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
  let kpos = 0;
  let bugOn = false;
  function bugMode(on = !bugOn) {
    bugOn = on;
    document.body.classList.toggle('bug', on);
    dispatchEvent(new CustomEvent('bugmode', { detail: on }));
    if (on) {
      achieve('bug', 'バグモードを発見した', '🐛');
      rain();
    }
    return on;
  }
  function rain() {
    const imgs = ['pitan-cut', 'ftp', 'http'];
    for (let i = 0; i < 24; i++) {
      const el = document.createElement('img');
      el.className = 'rain';
      el.src = `assets/img/${imgs[i % 3]}.png`;
      el.style.left = Math.random() * 100 + 'vw';
      el.style.animationDuration = 2 + Math.random() * 3 + 's';
      el.style.animationDelay = Math.random() * 2 + 's';
      document.body.append(el);
      el.addEventListener('animationend', () => el.remove());
    }
  }

  /* ---------- ターミナル ---------- */
  const term = $('#terminal');
  const out = $('#term-out');
  const input = $('#term-input');
  const history = [];
  let hpos = 0;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function print(html, cls = '') {
    const d = document.createElement('div');
    if (cls) d.className = cls;
    d.innerHTML = html;
    out.append(d);
    out.scrollTop = out.scrollHeight;
  }
  const commands = {
    help: () => 'commands: help, whoami, ls, open &lt;n&gt;, skills, github, goto &lt;section&gt;, theme, bug, sudo, rm, clear, exit',
    whoami: () => 'pitan — Bug-Creator。MODやWebなどを開発しています。',
    ls: () => window.SITE.projects.map((p, i) => `[${i + 1}] ${esc(p.name)}  — ${esc(p.summary)}`).join('\n') + '\n\nopen &lt;番号&gt; で開きます',
    open: (n) => {
      const p = window.SITE.projects[Number(n) - 1];
      if (!p) return '<span class="err">usage: open &lt;1-' + window.SITE.projects.length + '&gt;</span>';
      window.open(p.url, '_blank', 'noopener');
      return `opening ${esc(p.name)} ...`;
    },
    skills: () => window.SITE.skills.map((g) => `${g.name.padEnd(15)} ${g.items.join(', ')}`).join('\n'),
    github: () => { window.open(window.SITE.github, '_blank', 'noopener'); return 'opening GitHub ...'; },
    goto: (s) => {
      const el = document.getElementById(s);
      if (!el) return '<span class="err">sections: works, archive, skills, log, friends</span>';
      closeTerm();
      el.scrollIntoView({ behavior: 'smooth' });
      return '';
    },
    bug: () => (bugMode() ? 'bug mode: ON 🐛' : 'bug mode: OFF'),
    theme: () => 'ダークモード以外は実装されていません（仕様です）',
    sudo: () => { achieve('sudo', 'sudo を試みた', '🔒'); return '<span class="err">visitor is not in the sudoers file. This incident will be reported.</span>'; },
    rm: (a) => (a && a.includes('-rf') ? '<span class="err">やめてください。</span>' : 'rm: missing operand'),
    pitan: () => 'にゃ。',
    hello: () => 'こんにちは！',
    clear: () => { out.innerHTML = ''; return ''; },
    exit: () => { closeTerm(); return ''; },
  };
  function openTerm() {
    term.hidden = false;
    if (!out.childElementCount) print('Pitan Terminal — "help" でコマンド一覧');
    input.focus();
    achieve('term', 'ターミナルを開いた', '💻');
  }
  function closeTerm() { term.hidden = true; }
  $$('[data-open-terminal]').forEach((b) => b.addEventListener('click', openTerm));
  $$('[data-close-terminal]').forEach((b) => b.addEventListener('click', closeTerm));
  term.addEventListener('click', (e) => { if (e.target === term) closeTerm(); });
  $('#term-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const line = input.value.trim();
    input.value = '';
    if (!line) return;
    history.push(line);
    hpos = history.length;
    print('$ ' + esc(line), 'cmd');
    const [cmd, ...args] = line.split(/\s+/);
    const fn = commands[cmd.toLowerCase()];
    const res = fn ? fn(args.join(' ')) : `<span class="err">command not found: ${esc(cmd)}</span>`;
    if (res) print(res);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp' && hpos > 0) { input.value = history[--hpos]; e.preventDefault(); }
    if (e.key === 'ArrowDown') { hpos = Math.min(history.length, hpos + 1); input.value = history[hpos] || ''; }
  });

  addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !term.hidden) return closeTerm();
    const typing = e.target.closest('input, textarea');
    if (!typing && (e.key === '`' || e.key === '@')) { e.preventDefault(); openTerm(); return; }
    if (typing) return;
    kpos = e.key === konami[kpos] ? kpos + 1 : (e.key === konami[0] ? 1 : 0);
    if (kpos === konami.length) { kpos = 0; bugMode(); }
  });

  /* ---------- #debug ---------- */
  const debug = $('#debug');
  function updateDebug() {
    const on = location.hash === '#debug';
    debug.hidden = !on;
    if (on) achieve('debug', 'デバッグパネルを見つけた', '🔧');
  }
  addEventListener('hashchange', updateDebug);
  updateDebug();
  setInterval(() => {
    if (debug.hidden) return;
    const s = window.__sceneStats || {};
    debug.textContent = `fps     ${s.fps ?? '-'}\nblocks  ${s.blocks ?? '-'}\nbroken  ${s.broken ?? 0}\nshards  ${s.shards ?? 0}\ncalls   ${s.calls ?? '-'}\nscroll  ${Math.round(scrollY)}\nbug     ${bugOn}`;
  }, 250);

  /* ---------- タブ離脱 ---------- */
  const title = document.title;
  document.addEventListener('visibilitychange', () => {
    document.title = document.hidden ? '戻ってきて… | Pitan' : title;
  });

  console.log('%cPITAN', 'font: 48px sans-serif; color: #9fd3ff; text-shadow: 3px 3px #ffc4ec');
  console.log('ソースを覗いているあなたへ: ↑↑↓↓←→←→BA を試してみてください。');
})();
