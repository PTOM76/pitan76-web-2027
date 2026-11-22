<?php
declare(strict_types=1);

/** JSON ファイルを連想配列として読む。壊れていれば例外を投げる。 */
function json_data(string $file): array {
    $raw = @file_get_contents($file);
    if ($raw === false) throw new RuntimeException("cannot read: $file");
    return json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);
}

/** HTML に埋め込む値をエスケープする。 */
function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    if (!$d || !preg_match('/^(\d{4})\D?(\d{1,2})\D?(\d{1,2})/', $d, $m)) return '';
    return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
}

/** 説明文の HTML を除去して 1 行のテキストにする。 */
function plain(string $html): string {
    $t = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/** ファイル名や URL に使える ID にする。 */
function slug(string $id): string {
    return preg_replace('/[^a-z0-9_-]/i', '_', $id);
}

function http_get(string $url): ?string {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: pitan76-web-build\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw === false ? null : $raw;
}

/** 親フォルダを作ってから書き込む。 */
function write_file(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($path, $content);
}

function copy_file(string $src, string $dst): void {
    $dir = dirname($dst);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    copy($src, $dst);
}

/** $cached が $sources のどれよりも古い（またはない）ときに true。 */
function is_stale(string $cached, string ...$sources): bool {
    if (!is_file($cached)) return true;
    $t = filemtime($cached);
    foreach ($sources as $s) {
        if (filemtime($s) > $t) return true;
    }
    return false;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
