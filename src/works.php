<?php
declare(strict_types=1);

/** works-info の作品一覧。開発時はキャッシュを優先し、取得に失敗した場合もキャッシュを利用する。 */
function fetch_works(string $url, string $cache, bool $dev): array {
    if ($dev && is_file($cache)) return json_data($cache);
    $raw = http_get($url);
    if ($raw === null) {
        warn('works-info could not be fetched');
        return is_file($cache) ? json_data($cache) : [];
    }
    write_file($cache, $raw);
    return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
}

/**
 * 作品画像を取得し、ブロック用（正方形）とカード用（16:9）のサムネイルを作る。
 * 2 枚目以降の画像（最大 6 枚目まで）はブロックの別の面に使うため、正方形のみ "ID-2.jpg" の形で保存する。
 * 生成済みならキャッシュを使う。返り値は 作品 ID => ['file' => ファイル名, 'faces' => 画像の枚数]。
 */
function work_thumbs(array $works, string $dir): array {
    ensure_dir($dir);
    $out = [];
    foreach ($works as $w) {
        $images = $w['custom']['images'] ?? [];
        $first = array_first($images);
        if ($first === null) continue;
        $id = slug($w['id']);
        if (!is_file("$dir/$id.jpg") || !is_file("$dir/$id-w.jpg")) {
            $img = fetch_image($first);
            if (!$img) {
                warn("image skipped: {$w['id']}");
                continue;
            }
            imagejpeg(cover($img, 256, 256), "$dir/$id.jpg", 82);
            imagejpeg(cover($img, 640, 360), "$dir/$id-w.jpg", 80);
        }
        $faces = 1;
        foreach (array_slice($images, 1, 5) as $url) {
            $file = "$dir/$id-" . ($faces + 1) . '.jpg';
            if (!is_file($file)) {
                $img = fetch_image($url);
                if (!$img) break;
                imagejpeg(cover($img, 256, 256), $file, 82);
            }
            $faces++;
        }
        $out[$w['id']] = ['file' => $id, 'faces' => $faces];
    }
    return $out;
}

/** 日付をそろえ、新しい順に並べる。 */
function normalize_works(array $works): array {
    $works = array_map(static fn(array $w) => [
        'createAt' => ymd($w['createAt'] ?? null),
        'updateAt' => ymd($w['updateAt'] ?? null),
    ] + $w, $works);
    usort($works, static fn($a, $b) => ($b['updateAt'] ?: $b['createAt']) <=> ($a['updateAt'] ?: $a['createAt']));
    return $works;
}

/**
 * テンプレートと JS で使う作品データを作る。
 * @param array $thumbs work_thumbs() の返り値
 * @param array $icons  work_icons() の返り値
 * @param array $notes  作品 ID => つくった理由
 */
function build_items(array $works, array $thumbs, array $icons, array $notes, array $config, array $featuredIds): array {
    return array_map(static function (array $w) use ($thumbs, $icons, $notes, $config, $featuredIds): array {
        $id = $w['id'];
        $thumb = $thumbs[$id] ?? null;
        $site = array_filter($w['site'] ?? [], static fn($u) => is_string($u) && str_starts_with($u, 'http'));
        $date = $w['updateAt'] ?: $w['createAt'];
        return [
            'id' => $id,
            'page' => 'works/' . slug($id) . '.html',
            'card' => 'works/' . slug($id) . '.card.html',
            'name' => $w['name'],
            'desc' => plain($w['description'] ?? ''),
            'cat' => $config['categories'][$w['category'] ?? 'misc'] ?? 'Misc',
            'year' => substr($date, 0, 4),
            'date' => $date,
            'created' => $w['createAt'],
            'updated' => $w['updateAt'] !== '' && $w['createAt'] !== $w['updateAt'],
            'url' => $site['modparks'] ?? $w['url'] ?? $site['github'] ?? '',
            'tags' => $w['tag'] ?? [],
            'thumb' => $thumb ? "assets/works/{$thumb['file']}" : null,
            'faces' => $thumb['faces'] ?? 0,
            'icon' => isset($icons[$id]) ? "assets/img/works/{$icons[$id]}" : null,
            'version' => $w['version'] ?? '',
            'license' => $w['license'] ?? '',
            'mc' => $w['custom']['mcversion'] ?? '',
            'links' => array_map(
                static fn($k, $u) => ['label' => $config['linkLabels'][$k] ?? ucfirst($k), 'url' => $u],
                array_keys($site), $site,
            ),
            'note' => $notes[$id] ?? '',
            'featured' => array_find_key($featuredIds, static fn($f) => $f === $id),
        ];
    }, $works);
}

/** 作品の詳細項目（ラベル => 値）。値のない項目は含めない。 */
function work_spec(array $w): array {
    return array_filter([
        '公開' => $w['created'],
        '更新' => $w['updated'] ? $w['date'] : '',
        'バージョン' => $w['version'],
        '対応 MC' => $w['mc'],
        'ライセンス' => $w['license'],
    ], static fn($v) => $v !== '');
}

/** 最近の更新を、同じ日の同じ種類（公開 / 更新）ごとにまとめて最大 $limit 行にする。 */
function recent_log(array $items, int $limit): array {
    $log = [];
    foreach ($items as $w) {
        if ($w['date'] === '') continue;
        $key = $w['date'] . ($w['updated'] ? 'u' : 'n');
        if (!isset($log[$key])) {
            if (count($log) >= $limit) break;
            $log[$key] = ['date' => $w['date'], 'updated' => $w['updated'], 'works' => []];
        }
        $log[$key]['works'][] = $w;
    }
    return array_values($log);
}

/** カテゴリ => 件数（多い順）。 */
function category_counts(array $items): array {
    $cats = array_count_values(array_column($items, 'cat'));
    arsort($cats);
    return $cats;
}

/**
 * 作品のアイコンを取得し、64px の PNG にして $dir（リポジトリの assets/img/works/）に保存する。返り値は 作品 ID => ファイル名。
 * 図鑑ではアイコンを、ブロックの面では作品画像を優先して使う。
 * 一度保存したアイコンは取得し直さない。取得できなかった作品は $dir/missing.txt に記録し、次のビルドでは試さない
 * （取得し直すときは、該当の PNG や missing.txt の行を削除する）。
 */
function work_icons(array $works, string $dir): array {
    ensure_dir($dir);
    $missingFile = "$dir/missing.txt";
    $missing = is_file($missingFile) ? array_flip(file($missingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    $out = [];
    foreach ($works as $w) {
        $id = slug($w['id']);
        $file = "$dir/$id.png";
        if (!is_file($file) && !isset($missing[$id])) {
            $img = array_find_value(icon_sources($w), static fn(string $url) => $url === '' ? null : decode_icon(http_get($url)));
            if ($img) {
                $res = transparent_canvas(64, 64);
                imagecopyresampled($res, $img, 0, 0, 0, 0, 64, 64, imagesx($img), imagesy($img));
                imagepng($res, $file, 9);
            } else {
                $missing[$id] = true;
                warn("icon not found: {$w['id']}");
            }
        }
        if (is_file($file)) $out[$w['id']] = "$id.png";
    }
    // 内容が変わったときだけ書き込む。毎回書くと、開発サーバーが変更を検知して再ビルドを繰り返す
    $ids = array_keys($missing);
    sort($ids);
    $list = implode("
", $ids) . "
";
    if (!is_file($missingFile) || file_get_contents($missingFile) !== $list) write_file($missingFile, $list);
    return $out;
}

/** $items から $fn が null 以外を返した最初の値。 */
function array_find_value(iterable $items, Closure $fn): mixed {
    foreach ($items as $item) {
        if (($v = $fn($item)) !== null) return $v;
    }
    return null;
}

/**
 * 作品のアイコンの候補 URL を、優先する順に返す。必要になるまで取得しないよう、ジェネレーターにする。
 * Mod は Modrinth、CurseForge（CFWidget）、ModParks の順。サイトは favicon、Chrome 拡張はストアの画像。
 */
function icon_sources(array $w): Generator {
    $site = $w['site'] ?? [];
    if (preg_match('~modrinth\.com/\w+/([^/?#]+)~', $site['modrinth'] ?? '', $m)) {
        yield json_decode(http_get("https://api.modrinth.com/v2/project/$m[1]") ?? 'null', true)['icon_url'] ?? '';
    }
    if ($cf = $w['custom']['cf_projectid'] ?? null) {
        yield json_decode(http_get("https://api.cfwidget.com/$cf") ?? 'null', true)['thumbnail'] ?? '';
    }
    foreach ([$site['modparks'] ?? null, $site['chromewebstore'] ?? null] as $page) {
        if ($page) yield page_image($page, 'og:image') ?? '';
    }
    if (($home = $site['website'] ?? null) && preg_match('~^https?://~i', $home)) {
        yield page_image($home, 'icon') ?? '';
        yield manifest_icon($home) ?? '';
        yield url_origin($home) . '/favicon.ico';
    }
}

/**
 * ページの HTML から画像の URL を探す。$kind が 'og:image' なら OGP 画像、'icon' なら
 * apple-touch-icon を優先し、次にサイズの大きいアイコン。SVG は GD で読めないため除く。
 */
function page_image(string $page, string $kind): ?string {
    $html = http_get($page);
    if ($html === null) return null;
    if ($kind === 'og:image') {
        return preg_match('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)~i', $html, $m)
            ? resolve_url($page, html_entity_decode($m[1])) : null;
    }
    preg_match_all('~<link\b[^>]*>~i', $html, $tags);
    $best = null;
    $bestScore = -1;
    foreach ($tags[0] as $tag) {
        if (!preg_match('~rel=["\']([^"\']*icon[^"\']*)["\']~i', $tag, $rel)) continue;
        if (!preg_match('~href=["\']([^"\']+)["\']~i', $tag, $href) || str_ends_with(strtolower(parse_url($href[1], PHP_URL_PATH) ?? ''), '.svg')) continue;
        $size = preg_match('~sizes=["\'](\d+)x~i', $tag, $s) ? (int)$s[1] : 16;
        $score = (str_contains(strtolower($rel[1]), 'apple-touch-icon') ? 1000 : 0) + $size;
        if ($score > $bestScore) [$best, $bestScore] = [resolve_url($page, html_entity_decode($href[1])), $score];
    }
    return $best;
}

/** ページの Web アプリ マニフェストから、いちばん大きい PNG のアイコン（purpose が any のもの）を探す。 */
function manifest_icon(string $page): ?string {
    $html = http_get($page) ?? '';
    if (!preg_match('~<link\b[^>]*rel=["\']manifest["\'][^>]*href=["\']([^"\']+)~i', $html, $m)
        && !preg_match('~<link\b[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']manifest["\']~i', $html, $m)) return null;
    $url = resolve_url($page, html_entity_decode($m[1]));
    $icons = json_decode(http_get($url) ?? 'null', true)['icons'] ?? [];
    $png = array_filter($icons, static fn($i) => is_array($i) && ($i['type'] ?? '') === 'image/png'
        && !str_contains($i['purpose'] ?? 'any', 'maskable'));
    usort($png, static fn($a, $b) => (int)($b['sizes'] ?? 0) <=> (int)($a['sizes'] ?? 0));
    return $png ? resolve_url($url, $png[0]['src']) : null;
}

/** 取得した画像を GD 画像にする。ICO は中に PNG が入っていれば読む。読めなければ null。 */
function decode_icon(?string $raw): ?GdImage {
    if ($raw === null || $raw === '') return null;
    $png = strpos($raw, "\x89PNG");
    if (str_starts_with($raw, "\0\0\1\0") && $png !== false) $raw = substr($raw, $png);
    return @imagecreatefromstring($raw) ?: null;
}

function url_origin(string $url): string {
    $u = parse_url($url);
    return "{$u['scheme']}://{$u['host']}" . (isset($u['port']) ? ":{$u['port']}" : '');
}

/** ページ内の相対 URL を絶対 URL にする。 */
function resolve_url(string $base, string $href): string {
    return match (true) {
        (bool)preg_match('~^https?://~i', $href) => $href,
        str_starts_with($href, '//') => parse_url($base, PHP_URL_SCHEME) . ':' . $href,
        str_starts_with($href, '/') => url_origin($base) . $href,
        default => url_origin($base) . preg_replace('~[^/]*$~', '', parse_url($base, PHP_URL_PATH) ?: '/') . $href,
    };
}
