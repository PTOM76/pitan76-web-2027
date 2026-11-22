// 開発サーバー: ファイル変更を監視して PHP で再ビルドし、ブラウザを自動リロードする。
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { watch } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const dist = join(root, 'dist');
const port = Number(process.env.PORT) || 8076;
const php = process.env.PHP || 'php';
const WATCH = ['assets', 'data', 'templates', 'build.php'];
const TYPES = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.ico': 'image/x-icon',
};
const RELOAD = `<script>new EventSource('/__reload').onmessage=(e)=>{if(e.data==='css'){for(const l of document.querySelectorAll('link[rel=stylesheet]')){const u=new URL(l.href);if(u.origin===location.origin){u.searchParams.set('t',Date.now());l.href=u;}}}else location.reload();};</script>`;

const clients = new Set();
let building = null;
let queued = false;

function build() {
  if (building) { queued = true; return building; }
  const started = Date.now();
  building = new Promise((done) => {
    const p = spawn(php, ['build.php'], { cwd: root, env: { ...process.env, PITAN_DEV: '1' }, stdio: ['ignore', 'pipe', 'inherit'] });
    p.stdout.on('data', (d) => process.stdout.write(`[build] ${d}`));
    p.on('error', (e) => { console.error(`[build] php を起動できません: ${e.message}`); done(false); });
    p.on('close', (code) => {
      console.log(code === 0 ? `[build] ok (${Date.now() - started} ms)` : `[build] failed (exit ${code})`);
      done(code === 0);
    });
  }).finally(() => {
    building = null;
    if (queued) { queued = false; build(); }
  });
  return building;
}

let timer;
let cssOnly = true;
function onChange(file) {
  if (!file.endsWith('.css')) cssOnly = false;
  clearTimeout(timer);
  timer = setTimeout(async () => {
    const msg = cssOnly ? 'css' : 'reload';
    cssOnly = true;
    if (await build()) for (const c of clients) c.write(`data: ${msg}\n\n`);
  }, 120);
}
for (const w of WATCH) {
  watch(join(root, w), { recursive: true }, (_, f) => onChange(String(f ?? w)));
}

createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (url.pathname === '/__reload') {
    res.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache', Connection: 'keep-alive' });
    res.write(': connected\n\n');
    clients.add(res);
    req.on('close', () => clients.delete(res));
    return;
  }
  let path = normalize(join(dist, decodeURIComponent(url.pathname)));
  if (!path.startsWith(dist)) { res.writeHead(403).end(); return; }
  try {
    if ((await stat(path)).isDirectory()) path = join(path, 'index.html');
  } catch {
    path = join(dist, '404.html');
    res.statusCode = 404;
  }
  try {
    let body = await readFile(path);
    const type = TYPES[extname(path)] || 'application/octet-stream';
    if (type.startsWith('text/html')) body = body.toString().replace('</body>', `${RELOAD}</body>`);
    res.setHeader('Content-Type', type);
    res.setHeader('Cache-Control', 'no-store');
    res.end(body);
  } catch {
    res.writeHead(404).end('not found');
  }
}).listen(port, async () => {
  await build();
  console.log(`\n  ▶ http://localhost:${port}/\n`);
});
