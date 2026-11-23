// 作品ブロックの 3D 空間。
// - 各ブロックの面には作品画像を貼る
// - 代表作のブロックは、スクロールで対応する枠（.work-slot）の位置へ移動する
// - クリックで壊すと 'block-break' イベントで作品を通知する
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
// 16x16 のドット絵テクスチャ。ore を渡すと石に鉱石の粒を混ぜる
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
      g.fillStyle = ore;
      g.fillRect(x, y, 2, 2);
      g.fillStyle = 'rgba(255,255,255,.7)';
      g.fillRect(x, y, 1, 1);
    }
  }
  const t = new THREE.CanvasTexture(c);
  t.magFilter = t.minFilter = THREE.NearestFilter;
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

// 飾りブロックは灰色の石。作品入りで画像がないものはカテゴリ色の鉱石にする
const stoneTex = stoneTexture(null);
const ORE = { 'Minecraft Mod': '#ffad0a', 'MC Plugin': '#ffad0a', PukiWiki: '#5fb4ff', Web: '#ff7cc8', Extension: '#ff7cc8', App: '#7be08f' };
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

// 作品画像を面に貼る。画像が複数あれば 6 面に順番に貼り、1 枚なら全面同じにする
function loadFaces(work, mats) {
  mats.forEach((m, k) => loader.load(work.thumb + (k ? `-${k + 1}` : '') + '.jpg', (t) => {
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 4;
    m.map = t;
    m.needsUpdate = true;
  }));
}

function makeBlock(work) {
  // 作品入りのブロックは、画像を読み込むまで鉱石の見た目にする
  const faces = work?.thumb ? Math.max(1, work.faces || 1) : 1;
  const map = work ? oreTexture(work.cat) : stoneTex;
  const mats = Array.from({ length: faces }, () => new THREE.MeshLambertMaterial({ map }));
  const mesh = new THREE.Mesh(geo, faces === 1 ? mats[0] : Array.from({ length: 6 }, (_, f) => mats[f % faces]));
  mesh.add(new THREE.LineSegments(edges, edgeMat));
  scene.add(mesh);
  const b = {
    mesh, work,
    // ページ上の位置（px）と奥行き
    px: Math.random(),
    py: Math.random(),
    z: -2 - Math.random() * 12,
    size: 0.6 + Math.random() * 0.9,
    rot: new THREE.Vector3(Math.random() * 6, Math.random() * 6, 0),
    dragX: 0, dragY: 0,     // 右ドラッグで回した角度
    moveX: 0, moveY: 0,     // Shift + ドラッグで動かした量（ワールド座標）
    spin: (Math.random() - 0.5) * 0.5,
    phase: Math.random() * 10,
    alive: 1,
    respawn: 0,
    hover: 0,
    // 画面に入ったときに一度だけ画像を読み込む
    loadFaces: work?.thumb && !lowData ? () => loadFaces(work, mats) : null,
  };
  mesh.userData.block = b;
  blocks.push(b);
  return b;
}

works.forEach((w) => {
  const b = makeBlock(w);
  if (w.featured !== null && w.featured !== undefined) {
    b.slot = document.querySelector(`[data-slot="${w.featured}"]`);
    if (b.slot) featured.push(b);
  }
});
const FILLER = narrow ? 20 : 50;
for (let i = 0; i < FILLER; i++) makeBlock(null);

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
const lastPt = { x: 0, y: 0 };

// 破片
const shardGeo = new THREE.BoxGeometry(0.14, 0.14, 0.14);
const shards = [];
function burst(point, color) {
  for (let i = 0; i < 18; i++) {
    const m = new THREE.Mesh(shardGeo, new THREE.MeshLambertMaterial({ color, transparent: true }));
    m.position.copy(point);
    m.userData.v = new THREE.Vector3((Math.random() - 0.5) * 7, Math.random() * 7, (Math.random() - 0.5) * 7);
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
// 要素の箱全体でブロックの操作を止める UI と、文字の上だけで止める文章を分ける。
// 見出しや段落は横幅いっぱいに広がるため、箱で判定すると文字のない余白でもブロックを触れなくなる
const UI = 'a,button,input,label,.sk,.work-text,.bar,.terminal,.loot,.chara,.dex,.courier,.toast,.spec,.inv,.filters';
const TEXT = 'p,h1,h2,h3,li,td,th,dl';
const range = document.createRange();
function overUI(e) {
  if (e.target.closest(UI)) return true;
  const el = e.target.closest(TEXT);
  if (!el) return false;
  range.selectNodeContents(el);
  return [...range.getClientRects()].some((r) =>
    e.clientX >= r.left - 4 && e.clientX <= r.right + 4 && e.clientY >= r.top && e.clientY <= r.bottom);
}
let hovered = null;
let broken = 0;

addEventListener('pointermove', (e) => {
  if (pointerHeld && grabbed) {
    const dx = e.clientX - lastPt.x, dy = e.clientY - lastPt.y;
    if (mode === 'rotate') {
      grabbed.dragY += dx * 0.012;
      grabbed.dragX += dy * 0.012;
    } else if (mode === 'move') {
      // カーソルと同じ速さで動くよう、ブロックの奥行きでの 1px の長さを掛ける
      const unit = viewHeightAt(grabbed.z) / innerHeight;
      grabbed.moveX += dx * unit;
      grabbed.moveY -= dy * unit;
    }
  }
  lastPt.x = e.clientX; lastPt.y = e.clientY;
  mouseTarget.set(e.clientX / innerWidth - 0.5, e.clientY / innerHeight - 0.5);
  if (overUI(e)) { ndc.set(9, 9); return; }
  ndc.set((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
});
addEventListener('pointerdown', (e) => {
  if ((e.button !== 0 && e.button !== 2) || overUI(e)) return;
  grabbed = null;
  // タッチでは pointermove が先に来ないため、ここでも位置を更新する
  ndc.set((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
  lastPt.x = e.clientX; lastPt.y = e.clientY;
  mode = e.shiftKey ? 'move' : e.button === 2 ? 'rotate' : 'mine';
  pointerHeld = true;
  // 長押ししている間に文字が選択されたり、スマホのメニューが開いたりしないようにする
  document.body.classList.add('holding');
});
const release = (e) => {
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

function stopMining() {
  if (!mining) return;
  mining.b.mesh.remove(crack);
  crack.visible = false;
  mining = null;
}
function breakBlock(b) {
  stopMining();
  b.alive = 0;
  b.respawn = 14;
  const c = new THREE.Color(b.work ? oreColor(b.work.cat) : '#9a968c');
  burst(b.mesh.getWorldPosition(new THREE.Vector3()), c);
  broken++;
  endNudge();
  dispatchEvent(new CustomEvent('block-break', { detail: { count: broken, work: b.work } }));
}
function updateMining(dt) {
  if (!pointerHeld || !hovered || mode !== 'mine') { stopMining(); return; }
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
    burst(b.mesh.getWorldPosition(new THREE.Vector3()), new THREE.Color(b.work ? oreColor(b.work.cat) : '#9a968c'));
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

  // ホバー判定
  ray.setFromCamera(ndc, camera);
  const hit = ray.intersectObjects(blocks.filter((b) => b.alive === 1).map((b) => b.mesh), false)[0];
  const prevHover = hovered;
  hovered = hit ? hit.object.userData.block : null;
  // 押した瞬間のブロックを掴む。掴んでいる間は、カーソルが外れてもそのブロックを掘り続ける
  if (pointerHeld && !grabbed && hovered) grabbed = hovered;
  if (grabbed && grabbed.alive !== 1) grabbed = null;
  if (grabbed) hovered = grabbed;
  if (hovered !== prevHover) dispatchEvent(new CustomEvent('block-hover', { detail: hovered ? { work: hovered.work } : null }));
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
      b.moveX -= b.moveX * back;
      b.moveY -= b.moveY * back;
      b.dragX -= b.dragX * back;
      b.dragY -= b.dragY * back;
    }
    b.mesh.position.copy(home);
    b.mesh.position.x += b.moveX;
    b.mesh.position.y += b.moveY;

    const sp = bug ? 10 : 1;
    const idleX = b.rot.x + bt * b.spin * sp;
    const idleY = b.rot.y + bt * b.spin * 0.8 * sp;
    // 代表作は枠に入ると正面寄りの角度でゆっくり回る
    const showX = -0.42 + mouse.y * 0.4;
    const showY = 0.7 + Math.sin(t * 0.5) * 0.25 + mouse.x * 0.6;
    b.mesh.rotation.set(
      THREE.MathUtils.lerp(idleX, showX, k) + (b.dragX ?? 0),
      THREE.MathUtils.lerp(idleY, showY, k) + (b.dragY ?? 0),
      THREE.MathUtils.lerp(0, 0.08, k) + (b === nudge && !reduced ? wobble(clock.elapsedTime) : 0),
    );

    b.hover += ((hovered === b ? 1 : 0) - b.hover) * 0.2;
    if (b.alive < 1) {
      if (b.alive === 0) {
        b.respawn -= dt;
        if (b.respawn <= 0) {
          // 復活したブロックは、動かした位置と回した角度を元に戻す
          b.alive = 0.01;
          b.moveX = b.moveY = b.dragX = b.dragY = 0;
        }
      } else {
        b.alive = Math.min(1, b.alive + dt * 2.5);
      }
    }
    b.mesh.scale.setScalar(scale * b.alive * (1 + b.hover * 0.12));
    b.mesh.visible = b.alive > 0;
  }

  for (let i = shards.length - 1; i >= 0; i--) {
    const s = shards[i];
    s.userData.v.y -= 14 * dt;
    s.position.addScaledVector(s.userData.v, dt);
    s.rotation.x += dt * 6;
    s.userData.life -= dt;
    s.material.opacity = Math.max(0, s.userData.life);
    if (s.userData.life <= 0) {
      scene.remove(s);
      s.material.dispose();
      shards.splice(i, 1);
    }
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
