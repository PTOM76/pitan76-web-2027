// ブラウザの自動テスト（tests/e2e/）の設定。npm run test:e2e で実行する。
// 開発用のビルドで dist/ を作り、PHP の内蔵サーバーで配信して、手元の Chrome で開く
import { defineConfig } from '@playwright/test';

const PORT = 8098;

export default defineConfig({
  testDir: 'tests/e2e',
  // PHP の内蔵サーバーは Windows ではリクエストを 1 本ずつしか処理しないため、同時に動かすテストを絞る
  workers: 2,
  timeout: 60_000,
  reporter: 'list',
  use: {
    baseURL: `http://127.0.0.1:${PORT}/`,
    channel: 'chrome',
    viewport: { width: 1280, height: 800 },
    // GPU のない環境でも背景の 3D（WebGL）が動くようにする
    launchOptions: { args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] },
  },
  webServer: {
    command: `php build.php && php -S 127.0.0.1:${PORT} -t dist`,
    env: { PITAN_DEV: '1' },
    url: `http://127.0.0.1:${PORT}/index.html`,
    reuseExistingServer: false,
    stdout: 'ignore',
    stderr: 'pipe',
    timeout: 120_000,
  },
});
