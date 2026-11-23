// README に載せるブロックの画像を docs/blocks/ に作る。npm run block-images で実行する。
// サイトを開いて scene.js のテクスチャを取り出し、CSS の 3D で立方体に貼って撮影する
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';

const root = join(import.meta.dirname, '..');
const php = process.env.PHP || 'php';
const PORT = 8097;
const outDir = join(root, 'docs/blocks');

execFileSync(php, ['build.php'], { cwd: root, env: { ...process.env, PITAN_DEV: '1' }, stdio: 'inherit' });
const server = spawn(php, ['-S', `127.0.0.1:${PORT}`, '-t', 'dist'], { cwd: root, stdio: 'ignore' });
const browser = await chromium.launch({ channel: 'chrome', args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
try {
  const page = await browser.newPage();
  for (let i = 0; ; i++) {
    try { await page.goto(`http://127.0.0.1:${PORT}/index.html`); break; } catch (e) { if (i > 20) throw e; await new Promise((r) => setTimeout(r, 250)); }
  }
  await page.waitForFunction(() => window.__scene?.textures);
  const textures = await page.evaluate(() => window.__scene.textures());

  // 斜め上から見た立方体。上面・左面・右面の明るさを変えて立体に見せる
  const cube = (src) => `
    <div class="cube"><div class="face top" style="background-image:url(${src})"></div>
    <div class="face left" style="background-image:url(${src})"></div>
    <div class="face right" style="background-image:url(${src})"></div></div>`;
  await page.setContent(`<!doctype html><style>
    body { margin: 0; background: transparent; }
    .shot { width: 160px; height: 160px; display: grid; place-items: center; perspective: none; }
    .cube { position: relative; width: 80px; height: 80px; transform-style: preserve-3d; transform: rotateX(-30deg) rotateY(-45deg); }
    .face { position: absolute; inset: 0; background-size: cover; image-rendering: pixelated; outline: 2px solid #161514; outline-offset: -1px; }
    .top { transform: rotateX(90deg) translateZ(40px); }
    .left { transform: translateZ(40px); filter: brightness(.82); }
    .right { transform: rotateY(90deg) translateZ(40px); filter: brightness(.64); }
  </style>${Object.entries(textures).map(([k, src]) => `<div class="shot" id="${k}">${cube(src)}</div>`).join('')}`);

  mkdirSync(outDir, { recursive: true });
  for (const k of Object.keys(textures)) {
    await page.locator(`#${k}`).screenshot({ path: join(outDir, `${k}.png`), omitBackground: true });
    console.log(`docs/blocks/${k}.png`);
  }
} finally {
  await browser.close();
  server.kill();
}
