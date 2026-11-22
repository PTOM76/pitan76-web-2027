# Pitan Portfolio 2027

PHPでビルドする静的サイト。PHPはビルド時のみ実行し、公開するサイトにPHPの実行環境は含まない。背景の3D表現にはThree.jsを使用する。

## 必要環境

- PHP 8.3以上（GD拡張が必要）
- Node.js 20.11以上（開発サーバーを使用する場合のみ）

## 開発

```sh
npm run dev
```

`http://localhost:8076/` で確認できる。`assets/`、`data/`、`templates/`、`src/`、`build.php` の変更を検知して再ビルドし、ブラウザを自動で再読み込みする。CSSのみの変更は再読み込みせずに反映する。ポートは環境変数 `PORT` で変更できる。

開発時は作品データと画像を `.cache/` のキャッシュから読み込む。最新のデータを取得する場合は `.cache/` を削除する。

## ビルド

```sh
npm run build   # php build.php と同じ
```

`dist/` に出力する。ビルドの最初に `data/` の JSON を検証し、必要な項目が足りなければ内容を表示して終了する。

```sh
npm run check   # PHP・JavaScriptの構文チェックとビルド
```

| 環境変数 | 内容 |
| --- | --- |
| `PITAN_DEV` | `1` で開発用ビルド。作品データはキャッシュを優先し、`dist/` を削除せずに上書きする |
| `PITAN_BASE` | 公開先のパス（既定は `/`）。404ページのリンクと画像に使用する |
| `PITAN_URL` | 公開先のURL。指定すると `sitemap.xml`、`robots.txt`、canonicalを出力する |

GitHub Actionsでは `PITAN_BASE` と `PITAN_URL` をGitHub Pagesの設定から自動で渡す。

## 構成

| パス | 内容 |
| --- | --- |
| `data/site.json` | プロフィール、スキル、経歴、資格 |
| `data/projects.json` | 代表作。`work` は works-info の作品ID |
| `data/config.json` | 作品データの取得元、カテゴリ名、技術スタックのロゴ、画像サイズなど |
| `data/characters.json` | 作品を届けるキャラクター。キーと同名の画像を `assets/img/` に置く |
| `data/notes.json` | 作品ごとの「つくった理由」。作品ID => 文章 |
| `src/` | ビルド処理（作品データ、画像、ロゴ、検証、テンプレートの描画） |
| `templates/` | ページのテンプレート（トップの `index.php`、作品詳細の `work.php`、`404.php`） |
| `templates/partials/` | ページの部品（ヘッダー、各セクション、フッターなど） |
| `assets/style.css` | スタイル |
| `assets/main.js` | ページの演出（ターミナル、実績、作品索引など） |
| `assets/scene.js` | Three.jsによる作品ブロックの3D表現 |
| `assets/img/` | キャラクター画像（透過PNG） |
| `build.php` | ビルドの入口。データの読み込みから出力までの流れ |
| `scripts/check.mjs` | 構文チェック |
| `scripts/dev.mjs` | 開発サーバー |

作品ごとの詳細ページは `dist/works/作品ID.html` に出力する。ブロックを掘り当てたときのカードも同じ部品（`templates/partials/work-card.php`）から `dist/works/作品ID.card.html` に出力し、表示するときに1件ずつ読み込む。作品一覧はビルド時に [works-info](https://github.com/PTOM76/works-info) の `list.json` から取得し、作品画像をブロック用とカード用のサムネイルに変換して `dist/assets/works/` に出力する。

## 隠し要素

- `` ` `` キーでターミナルを開く（`help` でコマンド一覧）
- ↑↑↓↓←→←→BAでバグモード
- URLに `#debug` を付けるとデバッグ表示
- 背景のブロックを長押しすると作品が出現する。見つけた作品は図鑑に記録される
- ターミナルの `curl works/作品名`、`ftp get 作品名`、`smtp send 作品名`、`sudo rm -rf /`

## デプロイ

`main` へのpushでGitHub Actionsがビルドし、`dist/` をGitHub Pagesへデプロイする。リポジトリ設定のGitHub Pagesで、ソースに **GitHub Actions** を指定する。

公開するのはPitan名義の情報と作品のみとする。本名での活動、個人を特定できる情報、匿名と実名の活動を結び付けるリンクは追加しない。
