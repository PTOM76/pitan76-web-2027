// 背景のボクセル空間。クリックでブロックを壊せる。
import * as THREE from 'three';

const canvas = document.getElementById('scene');
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

let renderer;
try {
  renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
} catch {
  canvas.remove();
  throw new Error('WebGL is not available');
}
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));

const scene = new THREE.Scene();
scene.fog = new THREE.Fog(0x0a0d18, 14, 34);
const camera = new THREE.PerspectiveCamera(50, 1, 0.1, 100);
camera.position.set(0, 0, 16);

scene.add(new THREE.HemisphereLight(0xcfe8ff, 0x1a1030, 1.4));
const sun = new THREE.DirectionalLight(0xffffff, 1.6);
sun.position.set(5, 8, 6);
scene.add(sun);

// 16x16 のドット絵風テクスチャ
function pixelTexture() {
  const c = document.createElement('canvas');
  c.width = c.height = 16;
  const g = c.getContext('2d');
  for (let y = 0; y < 16; y++) {
    for (let x = 0; x < 16; x++) {
      const v = 200 + Math.floor(Math.random() * 55);
      g.fillStyle = `rgb(${v},${v},${v})`;
      g.fillRect(x, y, 1, 1);
    }
  }
  g.fillStyle = 'rgba(0,0,0,.35)';
  g.fillRect(0, 0, 16, 1); g.fillRect(0, 15, 16, 1); g.fillRect(0, 0, 1, 16); g.fillRect(15, 0, 1, 16);
  const t = new THREE.CanvasTexture(c);
  t.magFilter = THREE.NearestFilter;
  t.minFilter = THREE.NearestFilter;
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

const palette = ['#9fd3ff', '#59b8ff', '#ffc4ec', '#ffb627', '#b8f5c4', '#d6e4ff', '#6c7bb0'].map((c) => new THREE.Color(c));
const COUNT = innerWidth < 700 ? 90 : 180;
const geo = new THREE.BoxGeometry(1, 1, 1);
const mat = new THREE.MeshLambertMaterial({ map: pixelTexture() });
const mesh = new THREE.InstancedMesh(geo, mat, COUNT);
mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
scene.add(mesh);

const blocks = [];
for (let i = 0; i < COUNT; i++) {
  const r = 4 + Math.random() * 12;
  const a = Math.random() * Math.PI * 2;
  blocks.push({
    base: new THREE.Vector3(Math.cos(a) * r, (Math.random() - 0.5) * 44, Math.sin(a) * r - 6),
    rot: new THREE.Euler(Math.random() * 3, Math.random() * 3, 0),
    spin: (Math.random() - 0.5) * 0.6,
    phase: Math.random() * 10,
    size: 0.4 + Math.random() * 0.9,
    alive: 1,
    respawn: 0,
  });
  mesh.setColorAt(i, palette[i % palette.length]);
}

// 破片
const shardGeo = new THREE.BoxGeometry(0.16, 0.16, 0.16);
const shards = [];
function burst(pos, color) {
  for (let i = 0; i < 14; i++) {
    const m = new THREE.Mesh(shardGeo, new THREE.MeshLambertMaterial({ color, transparent: true }));
    m.position.copy(pos);
    m.userData.v = new THREE.Vector3((Math.random() - 0.5) * 6, Math.random() * 6, (Math.random() - 0.5) * 6);
    m.userData.life = 1.2;
    scene.add(m);
    shards.push(m);
  }
}

// 入力
const mouse = new THREE.Vector2();
const target = new THREE.Vector2();
addEventListener('pointermove', (e) => {
  target.set(e.clientX / innerWidth - 0.5, e.clientY / innerHeight - 0.5);
});
const ray = new THREE.Raycaster();
let broken = 0;
const IGNORE = 'a,button,input,label,.slot,.aw,.work,.stat,.inv-group,.unlock,.timeline,.term-win,.nav,.bubble,.marquee,p,h1,h2,h3';
addEventListener('pointerdown', (e) => {
  if (e.button !== 0 || e.target.closest(IGNORE)) return;
  const p = new THREE.Vector2((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
  ray.setFromCamera(p, camera);
  const hit = ray.intersectObject(mesh)[0];
  if (!hit || !blocks[hit.instanceId].alive) return;
  const b = blocks[hit.instanceId];
  b.alive = 0;
  b.respawn = 10;
  const c = new THREE.Color();
  mesh.getColorAt(hit.instanceId, c);
  burst(hit.point, c);
  broken++;
  dispatchEvent(new CustomEvent('block-break', { detail: { count: broken } }));
});

let bug = false;
addEventListener('bugmode', (e) => { bug = e.detail; });

function resize() {
  const w = innerWidth;
  const h = innerHeight;
  renderer.setSize(w, h, false);
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
}
addEventListener('resize', resize);
resize();

const dummy = new THREE.Object3D();
const clock = new THREE.Clock();
let frames = 0;
let fpsTime = 0;
let fps = 0;

function tick() {
  const dt = Math.min(clock.getDelta(), 0.05);
  const t = clock.elapsedTime;
  mouse.lerp(target, 0.05);

  const scroll = scrollY / Math.max(1, document.body.scrollHeight - innerHeight);
  camera.position.x = mouse.x * 3;
  camera.position.y = -mouse.y * 2 - scroll * 30 + 8;
  camera.lookAt(0, camera.position.y - mouse.y, -6);
  mesh.rotation.y = scroll * Math.PI * 0.8 + (reduced ? 0 : t * 0.02);

  const speed = bug ? 8 : 1;
  for (let i = 0; i < COUNT; i++) {
    const b = blocks[i];
    if (!b.alive) {
      b.respawn -= dt;
      if (b.respawn <= 0) b.alive = 0.01;
    } else if (b.alive < 1) {
      b.alive = Math.min(1, b.alive + dt * 2);
    }
    dummy.position.copy(b.base);
    if (!reduced) {
      dummy.position.y += Math.sin(t * 0.6 + b.phase) * 0.4;
      dummy.rotation.set(b.rot.x + t * b.spin * speed, b.rot.y + t * b.spin * 0.7 * speed, 0);
    } else {
      dummy.rotation.copy(b.rot);
    }
    dummy.scale.setScalar(b.size * b.alive);
    dummy.updateMatrix();
    mesh.setMatrixAt(i, dummy.matrix);
  }
  mesh.instanceMatrix.needsUpdate = true;

  for (let i = shards.length - 1; i >= 0; i--) {
    const s = shards[i];
    s.userData.v.y -= 12 * dt;
    s.position.addScaledVector(s.userData.v, dt);
    s.rotation.x += dt * 5;
    s.userData.life -= dt;
    s.material.opacity = Math.max(0, s.userData.life);
    if (s.userData.life <= 0) {
      scene.remove(s);
      s.material.dispose();
      shards.splice(i, 1);
    }
  }

  renderer.render(scene, camera);

  frames++;
  fpsTime += dt;
  if (fpsTime >= 0.5) {
    fps = Math.round(frames / fpsTime);
    frames = 0;
    fpsTime = 0;
    window.__sceneStats = { fps, blocks: COUNT, broken, shards: shards.length, calls: renderer.info.render.calls };
  }
  requestAnimationFrame(tick);
}
tick();

