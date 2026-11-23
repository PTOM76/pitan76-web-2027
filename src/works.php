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
 * @param array $notes  作品 ID => つくった理由
 */
function build_items(array $works, array $thumbs, array $notes, array $config, array $featuredIds): array {
    return array_map(static function (array $w) use ($thumbs, $notes, $config, $featuredIds): array {
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
