// サイトで使う Font Awesome のアイコンを、SVG のデータとして src/fa-icons.json に書き出す。npm run fa-icons で実行する。
// テンプレート・src・main.js の中の '名前' の形の文字列のうち、Font Awesome Free にある名前をすべて拾う
// （達成のアイコンのように icon() を通さず名前だけ渡すものも拾うため）。使っていない名前が少し混ざっても害はない。
// アイコンを増やしたら、このスクリプトを実行して src/fa-icons.json をコミットする。
import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = join(import.meta.dirname, '..');
const fa = join(root, 'node_modules/@fortawesome/fontawesome-free');
const pkg = JSON.parse(readFileSync(join(fa, 'package.json'), 'utf8'));

const files = [
  ...['templates', 'templates/partials', 'src'].flatMap((d) => readdirSync(join(root, d)).filter((f) => f.endsWith('.php')).map((f) => join(d, f))),
  'assets/main.js',
];
const names = new Set();
for (const f of files) {
  // 1 文字の名前（a、0 など）はただの文字列と区別できないため除く
  for (const m of readFileSync(join(root, f), 'utf8').matchAll(/'([a-z0-9]+(?:-[a-z0-9]+)*)'/g)) if (m[1].length > 1) names.add(m[1]);
}

const out = {
  _license: `Font Awesome Free ${pkg.version} by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0)`,
};
for (const style of ['solid', 'brands']) {
  out[style] = {};
  for (const name of [...names].sort()) {
    const file = join(fa, 'svgs', style, `${name}.svg`);
    if (!existsSync(file)) continue;
    const svg = readFileSync(file, 'utf8');
    const [, , , w, h] = svg.match(/viewBox="(\d+) (\d+) (\d+) (\d+)"/).map(Number);
    out[style][name] = [w, h, svg.match(/<path d="([^"]+)"/)[1]];
  }
}
writeFileSync(join(root, 'src/fa-icons.json'), JSON.stringify(out, null, 1) + '\n');
console.log(`src/fa-icons.json: solid ${Object.keys(out.solid).length}, brands ${Object.keys(out.brands).length}`);
