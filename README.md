# Pitan Portfolio 2027

PHPでビルドする静的サイト。PHPはビルド時のみ実行し、公開するサイトにPHPの実行環境は含まない。背景の3D表現にはThree.jsを使用する。

## 必要環境

- PHP 8.3以上（GD拡張が必要）
- Node.js 20.11以上（開発サーバーを使用する場合のみ）

## 開発

```sh
npm run dev
```

`http://localhost:8076/` で確認できる。`assets/`、`data/`、`templates/`、`build.php` の変更を検知して再ビルドし、ブラウザを自動で再読み込みする。CSSのみの変更は再読み込みせずに反映する。ポートは環境変数 `PORT` で変更できる。

開発時は作品データと画像を `.cache/` のキャッシュから読み込む。最新のデータを取得する場合は `.cache/` を削除する。

## ビルド

```sh
npm run build   # php build.php と同じ
```

`dist/` に出力する。

## 構成

| パス | 内容 |
| --- | --- |
| `data/site.json` | プロフィール、スキル、経歴、資格 |
| `data/projects.json` | 代表作。`work` は works-info の作品ID |
| `templates/` | ページのテンプレート（`index.php`、`404.php`） |
| `assets/style.css` | スタイル |
| `assets/main.js` | ページの演出（ターミナル、実績、作品索引など） |
| `assets/scene.js` | Three.jsによる作品ブロックの3D表現 |
| `assets/img/` | キャラクター画像（透過PNG） |
| `build.php` | 静的サイトジェネレーター |
| `scripts/dev.mjs` | 開発サーバー |

作品一覧はビルド時に [works-info](https://github.com/PTOM76/works-info) の `list.json` から取得し、作品画像をブロック用とカード用のサムネイルに変換して `dist/assets/works/` に出力する。

## 隠し要素

- `` ` `` キーでターミナルを開く（`help` でコマンド一覧）
- ↑↑↓↓←→←→BAでバグモード
- URLに `#debug` を付けるとデバッグ表示
- 背景のブロックをクリックすると作品が出現する

## デプロイ

`main` へのpushでGitHub Actionsがビルドし、`dist/` をGitHub Pagesへデプロイする。リポジトリ設定のGitHub Pagesで、ソースに **GitHub Actions** を指定する。

公開するのはPitan名義の情報と作品のみとする。本名での活動、個人を特定できる情報、匿名と実名の活動を結び付けるリンクは追加しない。
