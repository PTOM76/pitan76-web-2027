// トップページのブラウザテスト。これまでに見つかった不具合が再発しないかを確かめる
import { test, expect } from '@playwright/test';

/** ページの準備ができるまで待つ。gl が true なら、背景の 3D（CDN から読む Three.js）も待つ。 */
const ready = (page, gl = false) => page.waitForFunction((g) => window.SITE && document.readyState === 'complete' && (!g || window.__scene), gl);

/** トップページを開く。localStorage の初期値を渡せる。 */
async function open(page, storage = {}, gl = false) {
  await page.addInitScript((s) => {
    // 同じテストの中でのリロードでは初期値を入れ直さない（保存された値を確かめるため）
    if (sessionStorage.getItem('seeded')) return;
    sessionStorage.setItem('seeded', '1');
    for (const [k, v] of Object.entries(s)) localStorage.setItem(k, v);
  }, storage);
  await page.goto('index.html');
  await ready(page, gl);
}

const firstWorkId = (page) => page.evaluate(() => window.SITE.works[0].id);

test('読み込み時にエラーが出ない', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await open(page);
  await page.waitForTimeout(500);
  expect(errors).toEqual([]);
});

test('見出しの横の余白ではブロックを触れ、文字の上では触れない', async ({ page }) => {
  await open(page, {}, true);
  for (const selector of ['#works .sec-title', '#log .history li']) {
    const el = page.locator(selector).first();
    await el.scrollIntoViewIfNeeded();
    const box = await el.boundingBox();
    // 文字の上（左端の少し内側）
    expect(await page.evaluate(([x, y]) => window.__scene.overUI(x, y), [box.x + 8, box.y + box.height / 2]), `${selector} の文字の上`).toBe(true);
    // 文字のない右側の余白
    expect(await page.evaluate(([x, y]) => window.__scene.overUI(x, y), [box.x + box.width - 8, box.y + box.height / 2]), `${selector} の右の余白`).toBe(false);
  }
});

test('ボタンやリンクの上ではブロックを触れない', async ({ page }) => {
  await open(page, {}, true);
  const box = await page.locator('#dex-open').boundingBox();
  expect(await page.evaluate(([x, y]) => window.__scene.overUI(x, y), [box.x + box.width / 2, box.y + box.height / 2])).toBe(true);
});

test('Escape はいちばん手前の画面だけを閉じる', async ({ page }) => {
  await open(page);
  const id = await firstWorkId(page);
  await page.evaluate((i) => localStorage.setItem('dex', JSON.stringify([i])), id);
  await page.reload();
  await ready(page);
  await page.click('#dex-open');
  await page.click(`.dex-slot[data-id="${id}"]`);
  await expect(page.locator('#loot')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#loot')).toBeHidden();
  await expect(page.locator('#dex')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#dex')).toBeHidden();
});

test('図鑑をスクロールしても背景と枠が途切れない', async ({ page }) => {
  await page.setViewportSize({ width: 1000, height: 500 });
  await open(page);
  await page.click('#dex-open');
  // 開くときの拡大のアニメーションが終わってから測る（途中だと窓が大きく見える）
  await page.locator('.dex-win').evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished)));
  // 窓の中身がはみ出さず、窓そのものの高さに収まっている（背景の ::before が全体を覆う）
  const { scroll, client } = await page.locator('.dex-win').evaluate((el) => ({ scroll: el.scrollHeight, client: el.clientHeight }));
  expect(scroll).toBeLessThanOrEqual(client + 1);
  // 後ろの幕がスクロールでき、下まで送ると窓の下端が見える
  await page.locator('#dex').evaluate((el) => { el.scrollTop = el.scrollHeight; });
  const win = await page.locator('.dex-win').boundingBox();
  expect(win.y + win.height).toBeLessThanOrEqual(500 + 1);
});

test('実績はリロードしても残り、リセットで消える', async ({ page }) => {
  await open(page);
  await page.click('#dex-open');
  await page.reload();
  await ready(page);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('achievements') || '[]'))).toContain('dex');
  // 同じ実績は二度出さない
  await page.click('#dex-open');
  await expect(page.locator('.toast', { hasText: '図鑑を開いた' })).toHaveCount(0);
  page.once('dialog', (d) => d.accept());
  await page.click('#dex-reset');
  expect(await page.evaluate(() => localStorage.getItem('achievements'))).toBeNull();
  expect(await page.evaluate(() => localStorage.getItem('dex'))).toBeNull();
});

test('技術スタックの欄をすべて見ると実績が出る（図鑑のマスは数えない）', async ({ page }) => {
  await open(page);
  const slots = page.locator('#inventory .slot');
  const n = await slots.count();
  for (let i = 0; i < n; i++) await slots.nth(i).dispatchEvent('pointerenter');
  await expect(page.locator('.toast', { hasText: '持ち物を全部確認した' })).toHaveCount(1);
});

test('ページの最下部では、ナビの最後の項目が今のセクションになる', async ({ page }) => {
  await open(page);
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  await expect(page.locator('.bar-nav a[data-section="log"]')).toHaveAttribute('aria-current', '');
  await page.evaluate(() => window.scrollTo(0, 0));
  await expect(page.locator('.bar-nav a[aria-current]')).toHaveCount(0);
});

test('Caps Lock 中でもコナミコマンドでバグモードになる', async ({ page }) => {
  await open(page);
  for (const k of ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'B', 'A']) {
    await page.keyboard.press(k);
  }
  await expect(page.locator('body')).toHaveClass(/\bbug\b/);
});

test('ターミナルの find で、日本語の作品名でも列がそろう', async ({ page }) => {
  await open(page);
  await page.keyboard.press('`');
  await page.fill('#term-input', 'find a');
  await page.press('#term-input', 'Enter');
  const text = await page.locator('#term-out > div').last().innerText();
  const cats = await page.evaluate(() => [...new Set(window.SITE.works.map((w) => w.cat))]);
  const width = (s) => [...s].reduce((n, c) => n + (/[ᄀ-ᅟ⺀-꓏가-힣豈-﫿︰-﹏＀-｠￠-￦]/.test(c) ? 2 : 1), 0);
  // 各行は「名前（表示幅 30）+ 空白 + カテゴリ」。カテゴリには空白が入ることがあるため、一覧と照らして取り除く
  const lines = text.split('\n').filter((l) => !l.startsWith('…'));
  expect(lines.length).toBeGreaterThan(1);
  for (const l of lines) {
    const cat = cats.filter((c) => l.endsWith(' ' + c)).sort((a, b) => b.length - a.length)[0];
    expect(cat, l).toBeTruthy();
    expect(width(l.slice(0, -(cat.length + 1))), l).toBe(30);
  }
});

test('特別なブロックがあり、画像もアイコンもない作品は専用のブロックを持たない', async ({ page }) => {
  await open(page, {}, true);
  const { kinds, plain, works } = await page.evaluate(() => ({
    kinds: window.__scene.kinds(), plain: window.__scene.plainWorks(), works: window.SITE.works,
  }));
  for (const k of ['tnt', 'note', 'lucky']) expect(kinds[k], k).toBeGreaterThan(0);
  expect(kinds.work).toBe(works.length - plain.length);
  if (plain.length) expect(kinds.mystery).toBeGreaterThan(0);
  const plainSet = new Set(plain);
  expect(works.filter((w) => plainSet.has(w.id)).every((w) => !w.thumb && !w.icon)).toBe(true);
});

test('ラッキーブロックを壊すと何かが起きる', async ({ page }) => {
  await open(page);
  await page.evaluate(() => dispatchEvent(new CustomEvent('block-break', { detail: { count: 1, work: null, kind: 'lucky', chain: false } })));
  await expect(page.locator('.toast', { hasText: 'ラッキー！' })).toHaveCount(1);
  await expect(page.locator('.toast', { hasText: 'ラッキーブロックを開けた' })).toHaveCount(1);
});

test('音符ブロックでドまで鳴らすと実績、TNT の爆発でも実績が出る', async ({ page }) => {
  await open(page);
  await page.evaluate(() => {
    for (let p = 0; p < 8; p++) dispatchEvent(new CustomEvent('note-play', { detail: { pitch: p } }));
    dispatchEvent(new CustomEvent('block-explode', { detail: { count: 6 } }));
  });
  await expect(page.locator('.toast', { hasText: 'ドレミファソラシドを鳴らした' })).toHaveCount(1);
  await expect(page.locator('.toast', { hasText: 'TNT で爆破した' })).toHaveCount(1);
  await expect(page.locator('.toast', { hasText: '一度に 5 個のブロックを吹き飛ばした' })).toHaveCount(1);
});
