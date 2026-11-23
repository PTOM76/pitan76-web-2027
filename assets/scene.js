// 作品ブロックの 3D 空間。
// - 各ブロックの面には作品画像を貼る
// - 代表作のブロックは、スクロールで対応する枠（.work-slot）の位置へ移動する
// - クリックで壊すと 'block-break' イベントで作品を通知する
// - 作品入り以外に、TNT・音符ブロック・ラッキーブロックと、中身を掘るまで決めない「何かの作品入り」がある
import * as THREE from 'three';

const canvas = document.getElementById('scene');
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const works = window.SITE?.works ?? [];
// 通信量を節約する設定や低速な回線では、作品画像を読み込まず鉱石の見た目のままにする
const lowData = navigator.connection?.saveData || /(^|-)2g$/.test(navigator.connection?.effectiveType ?? '');
const narrow = innerWidth < 700;

let renderer;
try {
  renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
} catch {
  canvas.remove();
  throw new Error('WebGL is not available');
}
let pixelRatio = Math.min(devicePixelRatio, narrow ? 1.5 : 2);
renderer.setPixelRatio(pixelRatio);
renderer.outputColorSpace = THREE.SRGBColorSpace;
document.body.classList.add('gl');

const scene = new THREE.Scene();
const FOV = 40;
const CAM_Z = 12;
const camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 100);
camera.position.z = CAM_Z;
// ブロックの見た目は昼と夜で変えない
scene.add(new THREE.HemisphereLight(0xffffff, 0xb9b2a0, 2.2));
const sun = new THREE.DirectionalLight(0xffffff, 1.4);
sun.position.set(4, 6, 8);
scene.add(sun);

const INK = 0x161514;
// 16x16 のドット絵テクスチャ。ore を渡すと石に鉱石の粒を混ぜる（配列なら粒ごとに色を選ぶ）
function stoneTexture(ore) {
  const c = document.createElement('canvas');
  c.width = c.height = 16;
  const g = c.getContext('2d');
  for (let y = 0; y < 16; y++) {
    for (let x = 0; x < 16; x++) {
      const v = 150 + Math.floor(Math.random() * 45);
      g.fillStyle = `rgb(${v},${v - 2},${v - 8})`;
      g.fillRect(x, y, 1, 1);
    }
  }
  if (ore) {
    // 2x2 前後の粒を散らし、縁を暗くして浮き出させる
    for (let n = 0; n < 7; n++) {
      const x = 1 + Math.floor(Math.random() * 13);
      const y = 1 + Math.floor(Math.random() * 13);
      g.fillStyle = 'rgba(22,21,20,.55)';
      g.fillRect(x + 1, y + 1, 2, 2);
      g.fillStyle = Array.isArray(ore) ? ore[n % ore.length] : ore;
      g.fillRect(x, y, 2, 2);
      g.fillStyle = 'rgba(255,255,255,.7)';
      g.fillRect(x, y, 1, 1);
    }
  }
  return pixelTexture(c);
}

function pixelTexture(c) {
  const t = new THREE.CanvasTexture(c);
  t.magFilter = t.minFilter = THREE.NearestFilter;
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

// 16x16 のキャンバスに描く。rows は 1 文字 1 ピクセルの図で、palette の文字の色で塗る（. は塗らない）
function drawPixels(g, rows, x0, y0, palette) {
  rows.forEach((row, y) => [...row].forEach((ch, x) => {
    if (ch === '.') return;
    g.fillStyle = palette[ch];
    g.fillRect(x0 + x, y0 + y, 1, 1);
  }));
}
function canvas16(fill) {
  const c = document.createElement('canvas');
  c.width = c.height = 16;
  const g = c.getContext('2d');
  fill(g);
  return pixelTexture(c);
}

// TNT。赤と暗い赤の縦じまに、白い帯と黒い文字
const tntTex = canvas16((g) => {
  for (let x = 0; x < 16; x++) {
    g.fillStyle = x % 4 < 2 ? '#d8402f' : '#b52d20';
    g.fillRect(x, 0, 1, 16);
  }
  g.fillStyle = '#efe9df';
  g.fillRect(0, 5, 16, 6);
  drawPixels(g, ['###.#..#.###', '.#..##.#..#.', '.#..#.##..#.', '.#..#..#..#.'], 2, 6, { '#': '#161514' });
});

// 音符ブロック。木の板に黒い音符
const noteTex = canvas16((g) => {
  for (let y = 0; y < 16; y++) {
    for (let x = 0; x < 16; x++) {
      const v = Math.floor(Math.random() * 14);
      g.fillStyle = y % 4 === 3 ? '#5a3a22' : `rgb(${132 + v},${88 + v},${52 + v})`;
      g.fillRect(x, y, 1, 1);
    }
  }
  drawPixels(g, ['..##.', '..#.#', '..#..', '..#..', '..#..', '###..', '###..'], 5, 4, { '#': '#161514' });
});

// ラッキーブロック。黄色い地に、縁取りと白い「?」
const luckyTex = canvas16((g) => {
  g.fillStyle = '#f7c62f';
  g.fillRect(0, 0, 16, 16);
  g.fillStyle = '#c98a12';
  g.fillRect(0, 15, 16, 1);
  g.fillRect(15, 0, 1, 16);
  g.fillStyle = '#ffe58a';
  g.fillRect(0, 0, 16, 1);
  g.fillRect(0, 0, 1, 16);
  drawPixels(g, ['.###.', '#...#', '....#', '..##.', '..#..', '.....', '..#..'], 6, 5, { '#': '#7a4d06' });
  drawPixels(g, ['.###.', '#...#', '....#', '..##.', '..#..', '.....', '..#..'], 5, 4, { '#': '#ffffff' });
});

// 何かの作品入り。いろいろな色の粒が混ざった鉱石
const mysteryTex = stoneTexture(['#ff7cc8', '#5fb4ff', '#7be08f', '#ffad0a', '#c58cff']);

// 飾りブロックは灰色の石。作品入りで画像がないものはカテゴリ色の鉱石にする
const stoneTex = stoneTexture(null);
// キーは data/config.json の categories の表示名
const ORE = {
  Minecraft: '#ffad0a', 'R.E.P.O Mod': '#ffad0a', PukiWiki: '#5fb4ff', Web: '#ff7cc8', Extension: '#ff7cc8',
  App: '#7be08f', 'MMD Plugin': '#c58cff', Misc: '#ffffff',
};
const oreTex = new Map();
const oreColor = (cat) => ORE[cat] || '#ffffff';
const oreTexture = (cat) => {
  const c = oreColor(cat);
  if (!oreTex.has(c)) oreTex.set(c, stoneTexture(c));
  return oreTex.get(c);
};

const loader = new THREE.TextureLoader();
const geo = new THREE.BoxGeometry(1, 1, 1);
const edges = new THREE.EdgesGeometry(geo);
const edgeMat = new THREE.LineBasicMaterial({ color: INK });

const blocks = [];
const featured = [];
// 特別なブロックの見た目と、壊したときの破片の色
const KIND = {
  tnt: { map: tntTex, color: '#d8402f' },
  note: { map: noteTex, color: '#8a5a34' },
  lucky: { map: luckyTex, color: '#f7c62f' },
  mystery: { map: mysteryTex, color: '#c58cff' },
};
const blockColor = (b) => new THREE.Color(KIND[b.kind]?.color ?? (b.work ? oreColor(b.work.cat) : '#9a968c'));

// 作品画像を面に貼る。画像が複数あれば 6 面に順番に貼り、1 枚なら全面同じにする
function loadFaces(work, mats) {
  mats.forEach((m, k) => loader.load(work.thumb + (k ? `-${k + 1}` : '') + '.jpg', (t) => {
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 4;
    m.map = t;
    m.needsUpdate = true;
  }));
}

// 作品のアイコンを、石のブロックの中央に貼った面にする
function loadIcon(work, mat) {
  const img = new Image();
  img.onload = () => {
    const c = document.createElement('canvas');
    c.width = c.height = 128;
    const g = c.getContext('2d');
    g.imageSmoothingEnabled = false;
    g.drawImage(mat.map.image, 0, 0, 128, 128);
    g.imageSmoothingEnabled = true;
    g.fillStyle = 'rgba(22,21,20,.55)';
    g.fillRect(24, 24, 84, 84);
    g.drawImage(img, 20, 20, 84, 84);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    mat.map = t;
    mat.needsUpdate = true;
  };
  img.src = work.icon;
}

// kind は 'work'（作品入り）、'stone'（飾り）、'mystery'（何かの作品入り）、'tnt'、'note'、'lucky'
function makeBlock(work, kind = work ? 'work' : 'stone') {
  // 作品入りのブロックは、画像を読み込むまで鉱石の見た目にする
  const faces = work?.thumb ? Math.max(1, work.faces || 1) : 1;
  const map = KIND[kind]?.map ?? (work ? oreTexture(work.cat) : stoneTex);
  const mats = Array.from({ length: faces }, () => new THREE.MeshLambertMaterial({ map }));
  const mesh = new THREE.Mesh(geo, faces === 1 ? mats[0] : Array.from({ length: 6 }, (_, f) => mats[f % faces]));
  mesh.add(new THREE.LineSegments(edges, edgeMat));
  scene.add(mesh);
  const b = {
    mesh, work, kind,
    // ページ上の位置（px）と奥行き
    px: Math.random(),
    py: Math.random(),
    z: -2 - Math.random() * 12,
    size: 0.6 + Math.random() * 0.9,
    rot: new THREE.Vector3(Math.random() * 6, Math.random() * 6, 0),
    dragX: 0, dragY: 0,     // 右ドラッグで回した角度
    moveX: 0, moveY: 0,     // Shift + ドラッグで動かした量（ワールド座標）
    velX: 0, velY: 0,       // 離したあとも滑り続ける速さ（ワールド座標 / 秒）
    spinX: 0, spinY: 0,     // 離したあとも回り続ける速さ（ラジアン / 秒）
    spin: (Math.random() - 0.5) * 0.5,
    phase: Math.random() * 10,
    alive: 1,
    respawn: 0,
    hover: 0,
    // 画面に入ったときに一度だけ画像を読み込む
    loadFaces: lowData ? null : work?.thumb ? () => loadFaces(work, mats) : work?.icon ? () => loadIcon(work, mats[0]) : null,
  };
  mesh.userData.block = b;
  blocks.push(b);
  return b;
}

// 画像もアイコンもない作品は専用のブロックを作らず、「何かの作品入り」のブロックを掘ったときに中から選ぶ
const isFeatured = (w) => w.featured !== null && w.featured !== undefined;
const plainWorks = works.filter((w) => !w.thumb && !w.icon && !isFeatured(w));
works.forEach((w) => {
  if (plainWorks.includes(w)) return;
  const b = makeBlock(w);
  if (isFeatured(w)) {
    b.slot = document.querySelector(`[data-slot="${w.featured}"]`);
    if (b.slot) featured.push(b);
  }
});
const count = (wide, small) => (narrow ? small : wide);
for (let i = 0; i < Math.min(plainWorks.length, count(12, 6)); i++) makeBlock(null, 'mystery');
for (let i = 0; i < count(3, 2); i++) makeBlock(null, 'tnt');
for (let i = 0; i < count(3, 2); i++) makeBlock(null, 'note');
for (let i = 0; i < count(3, 2); i++) makeBlock(null, 'lucky');
const FILLER = count(50, 20);
for (let i = 0; i < FILLER; i++) makeBlock(null);

// 「何かの作品入り」の中身。まだ図鑑にない作品を優先する
function pickMystery() {
  let found = new Set();
  try { found = new Set(JSON.parse(localStorage.getItem('dex') || '[]')); } catch { /* noop */ }
  const fresh = plainWorks.filter((w) => !found.has(w.id));
  const pool = fresh.length ? fresh : plainWorks;
  return pool[Math.floor(Math.random() * pool.length)] ?? null;
}

// 中央の本文を避けて左右に寄せる
for (const b of blocks) {
  const side = Math.random() < 0.5 ? -1 : 1;
  b.px = side * (0.35 + Math.random() * 0.75);
}

// 採掘中のひび割れ（10 段階）。ひびを描いた箱をブロックに重ねる
const crackTex = Array.from({ length: 10 }, (_, stage) => {
  const c = document.createElement('canvas');
  c.width = c.height = 16;
  const g = c.getContext('2d');
  g.fillStyle = 'rgba(22,21,20,.85)';
  let rnd = 76;
  const r = () => ((rnd = (rnd * 9301 + 49297) % 233280) / 233280);
  for (let line = 0; line <= stage; line++) {
    let x = 8, y = 8;
    const dx = r() < 0.5 ? -1 : 1, dy = r() < 0.5 ? -1 : 1;
    for (let k = 0; k < 3 + stage; k++) {
      g.fillRect(x, y, 1, 1);
      if (r() < 0.5) x += dx; else y += dy;
      x = Math.max(0, Math.min(15, x)); y = Math.max(0, Math.min(15, y));
    }
  }
  const t = new THREE.CanvasTexture(c);
  t.magFilter = t.minFilter = THREE.NearestFilter;
  return t;
});
const crack = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ transparent: true, depthWrite: false, map: crackTex[0] }));
crack.scale.setScalar(1.01);
crack.visible = false;
const MINE_TIME = 0.6;
let mining = null;        // { b, progress, stage }
let pointerHeld = false;
// 掴んでいるブロックと直前のカーソル位置
let grabbed = null;
// 押したときの操作。左の長押しで掘る、右ドラッグで回す、Shift を押しながらドラッグ（左右どちらでも）で動かす
let mode = 'mine';
const lastPt = { x: 0, y: 0, t: 0 };
const MAX_VEL = 30;
const towards = (v, goal, k) => v + (goal - v) * k;

// 破片
const shardGeo = new THREE.BoxGeometry(0.14, 0.14, 0.14);
const shards = [];
function burst(point, color, n = 18, power = 7) {
  for (let i = 0; i < n; i++) {
    const m = new THREE.Mesh(shardGeo, new THREE.MeshLambertMaterial({ color, transparent: true }));
    m.position.copy(point);
    m.userData.v = new THREE.Vector3((Math.random() - 0.5) * power, Math.random() * power, (Math.random() - 0.5) * power);
    m.userData.life = 1.1;
    scene.add(m);
    shards.push(m);
  }
}

// 画面座標（px）→ 奥行き z のワールド座標
const vh = () => innerHeight;
function viewHeightAt(z) {
  return 2 * Math.tan(THREE.MathUtils.degToRad(FOV / 2)) * (CAM_Z - z);
}
function screenToWorld(x, y, z, out) {
  const h = viewHeightAt(z);
  const w = h * camera.aspect;
  return out.set((x / innerWidth - 0.5) * w, -(y / innerHeight - 0.5) * h, z);
}

// 入力
const mouse = new THREE.Vector2();
const mouseTarget = new THREE.Vector2();
const ndc = new THREE.Vector2(9, 9);
const ray = new THREE.Raycaster();
const targets = [];   // レイキャストの対象。毎フレーム配列を作らないよう使い回す
// 要素の箱全体でブロックの操作を止める UI と、文字の上だけで止める文章を分ける。
// 見出しや段落は横幅いっぱいに広がるため、箱で判定すると文字のない余白でもブロックを触れなくなる
const UI = 'a,button,input,label,.sk,.work-text,.bar,.terminal,.loot,.chara,.dex,.courier,.toast,.spec,.inv,.filters';
const TEXT = 'p,h1,h2,h3,li,td,th,dl';
// 文字の判定をしない入れ物。中の文章を全部調べることになり重いうえ、余白で触れなくなるため
const CONTAINER = 'html,body,main,section,canvas';
const range = document.createRange();
function overUI(e) {
  if (e.target.closest(UI)) return true;
  const el = e.target.closest(TEXT) ?? e.target;
  if (el.matches(CONTAINER)) return false;
  // テキストノードだけを測る。要素ごと範囲に入れると、display: block の span や b の箱（横幅いっぱい）まで含まれてしまう
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    if (!n.data.trim()) continue;
    range.selectNodeContents(n);
    for (const r of range.getClientRects()) {
      if (e.clientX >= r.left - 4 && e.clientX <= r.right + 4 && e.clientY >= r.top && e.clientY <= r.bottom) return true;
    }
  }
  return false;
}
let hovered = null;
let broken = 0;

// タッチ中の指。2 本目の指を置くと、掘るのをやめて回す操作に切り替える（2 本指のドラッグで回す）
const touches = new Map();   // pointerId => { x, y }
const clampVel = (v) => THREE.MathUtils.clamp(v, -MAX_VEL, MAX_VEL);
function rotateBy(b, dx, dy, sec) {
  b.dragY += dx * 0.012;
  b.dragX += dy * 0.012;
  b.spinY = towards(b.spinY, clampVel(dx * 0.012 / sec), 0.4);
  b.spinX = towards(b.spinX, clampVel(dy * 0.012 / sec), 0.4);
}

addEventListener('pointermove', (e) => {
  const t = touches.get(e.pointerId);
  if (t && touches.size >= 2) {
    // 2 本指: 指の動きの平均でブロックを回す
    const dx = (e.clientX - t.x) / touches.size, dy = (e.clientY - t.y) / touches.size;
    t.x = e.clientX; t.y = e.clientY;
    if (pointerHeld && grabbed) rotateBy(grabbed, dx, dy, Math.max(0.008, (e.timeStamp - lastPt.t) / 1000));
    lastPt.t = e.timeStamp;
    return;
  }
  if (t) { t.x = e.clientX; t.y = e.clientY; }
  if (pointerHeld && grabbed) {
    const dx = e.clientX - lastPt.x, dy = e.clientY - lastPt.y;
    // 直前の動きの速さをなめらかに記録し、離したときの勢いにする
    const sec = Math.max(0.008, (e.timeStamp - lastPt.t) / 1000);
    if (mode === 'rotate') {
      rotateBy(grabbed, dx, dy, sec);
    } else if (mode === 'move') {
      // カーソルと同じ速さで動くよう、ブロックの奥行きでの 1px の長さを掛ける
      const unit = viewHeightAt(grabbed.z) / innerHeight;
      grabbed.moveX += dx * unit;
      grabbed.moveY -= dy * unit;
      grabbed.velX = towards(grabbed.velX, clampVel(dx * unit / sec), 0.4);
      grabbed.velY = towards(grabbed.velY, clampVel(-dy * unit / sec), 0.4);
    }
  }
  lastPt.x = e.clientX; lastPt.y = e.clientY; lastPt.t = e.timeStamp;
  mouseTarget.set(e.clientX / innerWidth - 0.5, e.clientY / innerHeight - 0.5);
  if (overUI(e)) { ndc.set(9, 9); return; }
  ndc.set((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
});
// 音符ブロックを短く押したら（長押しで掘る前に離したら）音を鳴らす
const TAP_MS = 250;
let downAt = 0;
addEventListener('pointerdown', (e) => {
  if (e.pointerType === 'touch') {
    touches.set(e.pointerId, { x: e.clientX, y: e.clientY });
    // 1 本目でブロックを押さえている間に 2 本目を置いたら、回す操作に切り替える
    if (touches.size >= 2) {
      if (pointerHeld && mode === 'mine') {
        mode = 'rotate';
        stopMining();
        if (grabbed) dispatchEvent(new CustomEvent('block-grab', { detail: { mode } }));
      }
      return;
    }
  }
  if ((e.button !== 0 && e.button !== 2) || overUI(e)) return;
  grabbed = null;
  downAt = performance.now();
  lastPt.t = e.timeStamp;
  // タッチでは pointermove が先に来ないため、ここでも位置を更新する
  ndc.set((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
  lastPt.x = e.clientX; lastPt.y = e.clientY;
  mode = e.shiftKey ? 'move' : e.button === 2 ? 'rotate' : 'mine';
  pointerHeld = true;
  // 長押ししている間に文字が選択されたり、スマホのメニューが開いたりしないようにする
  document.body.classList.add('holding');
});
const release = (e) => {
  if (e?.pointerId !== undefined) touches.delete(e.pointerId);
  const tapped = grabbed ?? hovered;
  if (pointerHeld && mode === 'mine' && tapped?.kind === 'note' && tapped.alive === 1 && performance.now() - downAt < TAP_MS) playNote(tapped);
  pointerHeld = false;
  document.body.classList.remove('holding');
  grabbed = null;
  stopMining();
  if (e?.pointerType && e.pointerType !== 'mouse') ndc.set(9, 9);
};
addEventListener('pointerup', release);
addEventListener('pointercancel', release);
addEventListener('blur', release);
// ブロックの上ではメニューを開かない（右ドラッグで回すため）
addEventListener('contextmenu', (e) => { if (grabbed || hovered) e.preventDefault(); });

// 初回の案内。まだ 1 つも掘ったことがなければ、少し待ってから画面内のブロックを 1 つ揺らし、吹き出しを付ける
const nudgeEl = document.getElementById('nudge');
const MINED_KEY = 'mined';
let mined = false;
try { mined = localStorage.getItem(MINED_KEY) === '1'; } catch { /* noop */ }
let nudgeReady = false;
let nudge = null;
if (!mined && nudgeEl) setTimeout(() => { nudgeReady = true; }, 2500);
const projected = new THREE.Vector3();
function toScreen(b) {
  b.mesh.getWorldPosition(projected).project(camera);
  return { x: (projected.x + 1) / 2 * innerWidth, y: (1 - projected.y) / 2 * innerHeight };
}
const nudgeable = (b, p) => b.alive === 1 && !b.slot && !b.summonEl
  && p.x > innerWidth * 0.08 && p.x < innerWidth * 0.92 && p.y > innerHeight * 0.25 && p.y < innerHeight * 0.85;
function updateNudge() {
  if (!nudgeReady || mined) return;
  let p = nudge && toScreen(nudge);
  if (!nudge || !nudgeable(nudge, p)) {
    // 手前にある（大きく見える）ブロックを選ぶ
    nudge = null;
    for (const b of blocks) {
      const q = toScreen(b);
      if (nudgeable(b, q) && (!nudge || b.z > nudge.z)) { nudge = b; p = q; }
    }
  }
  nudgeEl.hidden = !nudge;
  if (nudge) nudgeEl.style.transform = `translate(${Math.round(p.x)}px, ${Math.round(p.y)}px)`;
}
function endNudge() {
  mined = true;
  nudge = null;
  if (nudgeEl) nudgeEl.hidden = true;
  try { localStorage.setItem(MINED_KEY, '1'); } catch { /* noop */ }
}

function playNote(b) {
  b.pitch = ((b.pitch ?? -1) + 1) % 8;   // ドレミファソラシド
  b.pop = 1;
  burst(b.mesh.getWorldPosition(new THREE.Vector3()), new THREE.Color().setHSL(b.pitch / 8, 0.8, 0.6), 5, 4);
  dispatchEvent(new CustomEvent('note-play', { detail: { pitch: b.pitch } }));
}

// TNT。掘ると点火し、少しして爆発する。周りのブロックを壊し、近くの TNT にも火を付ける
const FUSE = 1.2;
const BLAST = 3.2;   // 爆発が届く距離（ワールド座標）
let shake = 0;
function prime(b, fuse = FUSE) {
  if (b.fuse > 0 || b.alive !== 1) return;
  b.fuse = fuse;
  if (mining?.b === b) stopMining();
  dispatchEvent(new CustomEvent('tnt-prime'));
}
function explode(b) {
  const center = b.mesh.getWorldPosition(new THREE.Vector3());
  b.fuse = 0;
  b.mesh.material.emissive?.setScalar(0);
  breakBlock(b, { force: true, chain: true });
  burst(center, new THREE.Color('#ffb21e'), 40, 14);
  burst(center, new THREE.Color('#efe9df'), 20, 10);
  let n = 0;
  const p = new THREE.Vector3();
  for (const o of blocks) {
    if (o === b || o.alive !== 1 || o.fuse > 0) continue;
    if (o.mesh.getWorldPosition(p).distanceTo(center) > BLAST) continue;
    if (o.kind === 'tnt') prime(o, 0.3);
    else { breakBlock(o, { chain: true }); n++; }
  }
  if (!reduced) shake = 0.45;
  dispatchEvent(new CustomEvent('block-explode', { detail: { count: n } }));
}

// ラッキーブロックで起きる「ブロックの雨」。画面の上から石や鉱石の小さなブロックが降る
const rainGeo = new THREE.BoxGeometry(0.45, 0.45, 0.45);
addEventListener('block-rain', () => {
  const z = 0;
  const h = viewHeightAt(z);
  const w = h * camera.aspect;
  const maps = [stoneTex, mysteryTex, tntTex, noteTex, luckyTex];
  for (let i = 0; i < 40; i++) {
    const m = new THREE.Mesh(rainGeo, new THREE.MeshLambertMaterial({ map: maps[i % maps.length], transparent: true }));
    m.add(new THREE.LineSegments(new THREE.EdgesGeometry(rainGeo), edgeMat));
    m.position.set((Math.random() - 0.5) * w, h / 2 + 1 + Math.random() * h, z - Math.random() * 3);
    m.userData.v = new THREE.Vector3((Math.random() - 0.5) * 1.5, -2 - Math.random() * 3, 0);
    m.userData.life = 3.5;
    scene.add(m);
    shards.push(m);
  }
});

function stopMining() {
  if (!mining) return;
  mining.b.mesh.remove(crack);
  crack.visible = false;
  mining = null;
}
// chain は爆発に巻き込まれたとき（main.js は音を 1 回にまとめる）。force は TNT を点火せずに壊すとき
function breakBlock(b, { chain = false, force = false } = {}) {
  if (b.kind === 'tnt' && !force) { prime(b); return; }
  if (mining?.b === b) stopMining();
  b.alive = 0;
  b.respawn = 14;
  burst(b.mesh.getWorldPosition(new THREE.Vector3()), blockColor(b));
  broken++;
  endNudge();
  const work = b.kind === 'mystery' ? pickMystery() : b.work;
  dispatchEvent(new CustomEvent('block-break', { detail: { count: broken, work, kind: b.kind, chain } }));
}
function updateMining(dt) {
  if (!pointerHeld || !hovered || mode !== 'mine' || hovered.fuse > 0) { stopMining(); return; }
  if (!mining || mining.b !== hovered) {
    stopMining();
    mining = { b: hovered, progress: 0, stage: -1 };
    hovered.mesh.add(crack);
    crack.visible = true;
  }
  mining.progress += dt / MINE_TIME;
  const stage = Math.min(9, Math.floor(mining.progress * 10));
  if (stage !== mining.stage) {
    mining.stage = stage;
    crack.material.map = crackTex[stage];
    crack.material.needsUpdate = true;
    if (stage % 3 === 0) dispatchEvent(new CustomEvent('block-hit'));
  }
  if (mining.progress >= 1) breakBlock(mining.b);
}

// 索引の行にホバーすると、その作品のブロックを行の横へ呼び出す
const byId = new Map(blocks.filter((b) => b.work).map((b) => [b.work.id, b]));
let summoned = null;     // { b, el }
addEventListener('summon', (e) => {
  const b = e.detail ? byId.get(e.detail.id) : null;
  if (summoned && summoned.b !== b) summoned.b.summonEl = null;
  summoned = b ? { b, el: e.detail.el } : null;
  if (b) b.summonEl = e.detail.el;
});

let bug = false;

// sudo rm -rf / : すべてのブロックが一度に砕け、少しずつ戻る
addEventListener('collapse', () => {
  stopMining();
  for (const b of blocks) {
    if (b.alive !== 1) continue;
    burst(b.mesh.getWorldPosition(new THREE.Vector3()), blockColor(b));
    b.fuse = 0;
    b.alive = 0;
    b.respawn = 4 + Math.random() * 4;
  }
});addEventListener('bugmode', (e) => { bug = e.detail; });

function resize() {
  renderer.setSize(innerWidth, innerHeight, false);
  camera.aspect = innerWidth / innerHeight;
  camera.updateProjectionMatrix();
}
addEventListener('resize', resize);
resize();

const home = new THREE.Vector3();
const slotPos = new THREE.Vector3();
const clock = new THREE.Clock();
const smooth = (t) => t * t * (3 - 2 * t);
// 2.4 秒ごとに 0.5 秒だけ小刻みに揺れる
const wobble = (t) => (t % 2.4 < 0.5 ? Math.sin(t * 40) * 0.12 : 0);
let frames = 0;
let slow = 0;
let fpsTime = 0;

function tick() {
  const dt = Math.min(clock.getDelta(), 0.05);
  const t = reduced ? 0 : clock.elapsedTime;
  mouse.lerp(mouseTarget, 0.06);
  const docH = Math.max(document.documentElement.scrollHeight, innerHeight);

  // ホバー判定。カーソルが UI の上や画面外にある（ndc が範囲外）ときは判定しない
  let hit = null;
  if (Math.abs(ndc.x) <= 1 && Math.abs(ndc.y) <= 1) {
    ray.setFromCamera(ndc, camera);
    targets.length = 0;
    for (const b of blocks) if (b.alive === 1) targets.push(b.mesh);
    hit = ray.intersectObjects(targets, false)[0] ?? null;
  }
  const prevHover = hovered;
  hovered = hit ? hit.object.userData.block : null;
  // 押した瞬間のブロックを掴む。掴んでいる間は、カーソルが外れてもそのブロックを掘り続ける
  if (pointerHeld && !grabbed && hovered) {
    grabbed = hovered;
    // 回す・動かす操作を覚えたことを main.js に伝える（掘る操作は block-break で伝わる）
    if (mode !== 'mine') dispatchEvent(new CustomEvent('block-grab', { detail: { mode } }));
  }
  if (grabbed && grabbed.alive !== 1) grabbed = null;
  if (grabbed) hovered = grabbed;
  if (hovered !== prevHover) dispatchEvent(new CustomEvent('block-hover', { detail: hovered ? { work: hovered.work, kind: hovered.kind } : null }));
  document.body.classList.toggle('on-block', !!hovered && (!grabbed || mode === 'mine'));
  document.body.classList.toggle('grabbing', !!grabbed && mode === 'rotate');
  document.body.classList.toggle('moving', !!grabbed && mode === 'move');
  if (grabbed) getSelection()?.removeAllRanges();   // 掴んで動かしたときに文字が選択されないようにする
  updateMining(dt);

  for (const b of blocks) {
    // ブロックごとの時間。カーソルが乗っている間は止めて、掘りやすくする
    if (hovered !== b && !reduced) b.t = (b.t ?? 0) + dt;
    const bt = b.t ?? 0;

    // 視差付きでページと一緒に流れる（マウスの位置では動かさない）
    const depth = (CAM_Z - b.z) / CAM_Z;        // 1〜2
    const parallax = 0.25 + 0.55 / depth;
    const span = docH * parallax + innerHeight * 1.4;
    let sy = b.py * span - scrollY * parallax - innerHeight * 0.2;
    sy = ((sy % span) + span) % span - innerHeight * 0.2;
    const hz = viewHeightAt(b.z);
    home.set(b.px * hz * camera.aspect * 0.5, -(sy / vh() - 0.5) * hz, b.z);
    home.y += Math.sin(bt * 0.7 + b.phase) * 0.25;
    // 代表作の枠や索引の横に呼び出されたブロックは、元の位置が画面外でも読み込む
    if (b.loadFaces && (b.slot || b.summonEl || (sy > -innerHeight * 0.5 && sy < innerHeight * 1.5))) {
      b.loadFaces();
      b.loadFaces = null;
    }

    const base = b.size * Math.min(1, camera.aspect * 0.9);   // 縦長画面では小さくする
    let scale = base;
    let k = 0;
    b.summonK = THREE.MathUtils.clamp((b.summonK ?? 0) + (b.summonEl ? dt : -dt) * 4, 0, 1);
    if (b.summonK > 0 && b.summonEl) b.lastSummonEl = b.summonEl;
    if (b.summonK > 0 && b.lastSummonEl) {
      const r = b.lastSummonEl.getBoundingClientRect();
      k = smooth(b.summonK);
      const z = 3;
      // 索引の右の余白に置く（余白がない幅では行の右端）
      const sx = innerWidth >= 1100 ? Math.min(r.right + 130, innerWidth - 110) : innerWidth - 70;
      screenToWorld(sx, r.top + r.height / 2, z, slotPos);
      home.lerp(slotPos, k);
      scale = THREE.MathUtils.lerp(base, innerWidth >= 1100 ? 1.5 : 0.7, k);
    } else if (b.slot) {
      const r = b.slot.getBoundingClientRect();
      const cy = r.top + r.height / 2;
      // 枠の中心が画面中央から ±35% 以内なら枠に留まり、±80% までに徐々に戻る
      const d = Math.abs(cy - innerHeight / 2) / innerHeight;
      const goal = smooth(THREE.MathUtils.clamp((0.8 - d) / 0.45, 0, 1));
      // 急に飛び去らないよう、時間方向にもなめらかに追従させる
      b.slotK = (b.slotK ?? 0) + (goal - (b.slotK ?? 0)) * Math.min(1, dt * 5);
      k = b.slotK;
      if (k > 0.001) {
        const z = 2;
        screenToWorld(r.left + r.width / 2, cy, z, slotPos);
        const target = (r.width / innerHeight) * viewHeightAt(z) * 0.62;
        home.lerp(slotPos, k);
        scale = THREE.MathUtils.lerp(base, target, k);
      }
    }
    // 代表作の枠や索引の横にいる間は、動かした位置と回した角度を元に戻していく（掴んでいる間は除く）
    if (k > 0.5 && grabbed !== b) {
      const back = Math.min(1, dt * 6);
      b.velX = b.velY = b.spinX = b.spinY = 0;
      b.moveX -= b.moveX * back;
      b.moveY -= b.moveY * back;
      b.dragX -= b.dragX * back;
      b.dragY -= b.dragY * back;
    }
    // 慣性。掴んでいる間は、止めたまま離したときに飛ばないよう勢いを早めに落とす
    if (grabbed === b) {
      const hold = Math.exp(-dt * 8);
      b.velX *= hold; b.velY *= hold; b.spinX *= hold; b.spinY *= hold;
    } else if (b.velX || b.velY || b.spinX || b.spinY) {
      b.moveX += b.velX * dt;
      b.moveY += b.velY * dt;
      b.dragX += b.spinX * dt;
      b.dragY += b.spinY * dt;
      const friction = Math.exp(-dt * 3);
      b.velX *= friction; b.velY *= friction; b.spinX *= friction; b.spinY *= friction;
      if (Math.hypot(b.velX, b.velY, b.spinX, b.spinY) < 0.01) b.velX = b.velY = b.spinX = b.spinY = 0;
    }
    b.mesh.position.copy(home);
    b.mesh.position.x += b.moveX;
    b.mesh.position.y += b.moveY;
    // 投げたブロックが画面の外へ消えないよう、左右の端で跳ね返す。
    // 元から端にはみ出して置かれているブロックは押し戻さない（動かした分だけを戻す）
    if (grabbed !== b && b.moveX) {
      const limit = Math.max(viewHeightAt(home.z) * camera.aspect / 2 - scale * 0.5, Math.abs(home.x));
      const x = b.mesh.position.x;
      if (Math.abs(x) > limit && Math.sign(b.moveX) === Math.sign(x)) {
        const over = x - Math.sign(x) * limit;
        b.moveX -= over;
        b.mesh.position.x -= over;
        if (Math.sign(b.velX) === Math.sign(x)) b.velX *= -0.5;
      }
    }

    const sp = bug ? 10 : 1;
    const idleX = b.rot.x + bt * b.spin * sp;
    const idleY = b.rot.y + bt * b.spin * 0.8 * sp;
    // 代表作は枠に入ると正面寄りの角度でゆっくり回る
    const showX = -0.42 + mouse.y * 0.4;
    const showY = 0.7 + Math.sin(t * 0.5) * 0.25 + mouse.x * 0.6;
    b.mesh.rotation.set(
      THREE.MathUtils.lerp(idleX, showX, k) + (b.dragX ?? 0),
      THREE.MathUtils.lerp(idleY, showY, k) + (b.dragY ?? 0),
      THREE.MathUtils.lerp(0, 0.08, k) + (b === nudge && !reduced ? wobble(clock.elapsedTime) : 0)
        - THREE.MathUtils.clamp(b.velX * 0.03, -0.35, 0.35),   // 動いている方向へ少し傾ける
    );

    b.hover += ((hovered === b ? 1 : 0) - b.hover) * 0.2;
    if (b.alive < 1) {
      if (b.alive === 0) {
        b.respawn -= dt;
        if (b.respawn <= 0) {
          // 復活したブロックは、動かした位置と回した角度を元に戻す
          b.alive = 0.01;
          b.moveX = b.moveY = b.dragX = b.dragY = 0;
          b.velX = b.velY = b.spinX = b.spinY = 0;
        }
      } else {
        b.alive = Math.min(1, b.alive + dt * 2.5);
      }
    }
    if (b.fuse > 0) {
      b.fuse -= dt;
      b.mesh.material.emissive?.setScalar(Math.floor(b.fuse * 8) % 2 ? 0.7 : 0);   // 白く点滅する
      if (b.fuse <= 0) explode(b);
    }
    b.pop = Math.max(0, (b.pop ?? 0) - dt * 5);
    const swell = b.fuse > 0 ? (1 - b.fuse / FUSE) * 0.18 : 0;
    b.mesh.scale.setScalar(scale * b.alive * (1 + b.hover * 0.12 + swell + Math.sin(b.pop * Math.PI) * 0.2));
    b.mesh.visible = b.alive > 0;
  }

  for (let i = shards.length - 1; i >= 0; i--) {
    const s = shards[i];
    s.userData.v.y -= 14 * dt;
    s.position.addScaledVector(s.userData.v, dt);
    s.rotation.x += dt * 6;
    s.userData.life -= dt;
    s.material.opacity = Math.min(1, Math.max(0, s.userData.life * 2));
    if (s.userData.life <= 0) {
      scene.remove(s);
      s.material.dispose();
      shards.splice(i, 1);
    }
  }

  // 爆発で画面を揺らす
  if (shake > 0) {
    shake = Math.max(0, shake - dt);
    camera.position.set((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake, CAM_Z);
  } else if (camera.position.x || camera.position.y) {
    camera.position.set(0, 0, CAM_Z);
  }

  updateNudge();
  renderer.render(scene, camera);

  frames++;
  fpsTime += dt;
  if (fpsTime >= 0.5) {
    // 描画が追いつかない端末では、一度だけ解像度を等倍まで下げる
    slow = frames / fpsTime < 35 ? slow + 1 : 0;
    if (slow >= 4 && pixelRatio > 1) {
      pixelRatio = 1;
      renderer.setPixelRatio(1);
      resize();
    }
    window.__sceneStats = { fps: Math.round(frames / fpsTime), blocks: blocks.length, broken, shards: shards.length, calls: renderer.info.render.calls };
    frames = 0;
    fpsTime = 0;
  }
  requestAnimationFrame(tick);
}
tick();

// 自動テスト（tests/e2e/）用の入口。画面上の (x, y) がブロックの操作を止める UI や文字の上かを返す
window.__scene = {
  overUI: (x, y) => overUI({ target: document.elementFromPoint(x, y) ?? document.body, clientX: x, clientY: y }),
  // ブロックのテクスチャ（data URL）。README の画像を作るのに使う（scripts/block-images.mjs）
  textures: () => Object.fromEntries(Object.entries({
    ore: oreTexture('Minecraft'), mystery: mysteryTex, tnt: tntTex, note: noteTex, lucky: luckyTex, stone: stoneTex,
  }).map(([k, t]) => [k, t.image.toDataURL()])),
  // ブロックの種類ごとの数
  kinds: () => blocks.reduce((o, b) => ({ ...o, [b.kind]: (o[b.kind] ?? 0) + 1 }), {}),
  // 画像もアイコンもない作品の ID（「何かの作品入り」の中身の候補）
  plainWorks: () => plainWorks.map((w) => w.id),
};
