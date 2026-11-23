<?php
declare(strict_types=1);

/** JSON ファイルを連想配列として読む。読めない、または壊れていれば例外を投げる。 */
function json_data(string $file): array {
    $raw = @file_get_contents($file);
    if ($raw === false) throw new RuntimeException("cannot read: $file");
    return json_decode($raw ?: '[]', true, flags: JSON_THROW_ON_ERROR);
}

/** HTML に埋め込む値をエスケープする。 */
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** スクリプト内に埋め込む JSON。</script> などで閉じられないようにする。 */
function js_json(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
}

function warn(string $message): void {
    fwrite(STDERR, "warning: $message\n");
}

/** works-info の日付（2026/08/24、20260926 など）を Y-m-d にそろえる。読めなければ空文字。 */
function ymd(?string $d): string {
    return preg_match('/^(\d{4})\D?(\d{1,2})\D?(\d{1,2})/', $d ?? '', $m)
        ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3])
        : '';
}

/** 説明文の HTML を除去して 1 行のテキストにする。 */
function plain(string $html): string {
    $text = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

/** ファイル名や URL に使える ID にする。 */
function slug(string $id): string {
    return preg_replace('/[^a-z0-9_-]/i', '_', $id);
}

function http_get(string $url): ?string {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: pitan76-web-build\r\n"]]);
    return @file_get_contents($url, context: $ctx) ?: null;
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

/** 親フォルダを作ってから書き込む。 */
function write_file(string $path, string $content): void {
    ensure_dir(dirname($path));
    file_put_contents($path, $content);
}

function copy_file(string $src, string $dst): void {
    ensure_dir(dirname($dst));
    copy($src, $dst);
}

/** $cached がないか、$sources のどれかより古ければ true。 */
function is_stale(string $cached, string ...$sources): bool {
    if (!is_file($cached)) return true;
    $t = filemtime($cached);
    return array_any($sources, static fn($s) => filemtime($s) > $t);
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/**
 * 文章中の {id} を値に置き換え、HTML として返す。値は <b class="num"> で囲む。
 * 存在しない id があれば例外を投げる。
 */
function fill_placeholders(string $text, array $values): string {
    return preg_replace_callback('/\{(\w+)\}/', static fn(array $m) => array_key_exists($m[1], $values)
        ? '<b class="num">' . h($values[$m[1]]) . '</b>'
        : throw new RuntimeException("プレースホルダ {{$m[1]}} に対応する値がありません"),
    h($text));
}
