// PHP と JavaScript の構文チェック、data/ の検証を兼ねたビルドを行う
import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

const root = join(import.meta.dirname, '..');
const php = process.env.PHP || 'php';
const list = (dir, ext) => readdirSync(join(root, dir)).filter((f) => f.endsWith(ext)).map((f) => join(dir, f));
const run = (cmd, args) => execFileSync(cmd, args, { cwd: root, stdio: 'inherit' });

for (const f of ['build.php', ...list('src', '.php'), ...list('templates', '.php'), ...list('templates/partials', '.php')]) {
  run(php, ['-l', f]);
}
for (const f of list('assets', '.js')) run(process.execPath, ['--check', f]);
run(php, ['build.php']);
