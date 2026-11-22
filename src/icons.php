<?php
declare(strict_types=1);

/**
 * 技術スタックのロゴを取得して $dir に保存する。ロゴの指定は data/config.json の skillIcons。
 *   "java/java-original"  Devicon のカラーロゴ
 *   "si:slug:色"          Simple Icons の単色ロゴを指定色で塗る
 *   "https://..."         画像の URL をそのまま取得する
 * 返り値は 名前 => [ファイル名, ...]。1 つも取得できなかった名前は含まない（文字のマークで表示する）。
 */
function skill_icons(array $skills, array $config, string $dir): array {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $src = $config['iconSources'];
    $out = [];
    foreach ($skills as $g) {
        foreach ($g['items'] as $name) {
            foreach ($config['skillIcons'][$name] ?? [] as $ref) {
                [$file, $fetch] = icon_ref($ref, $src);
                if (!is_file("$dir/$file")) {
                    $raw = $fetch();
                    if ($raw !== null) file_put_contents("$dir/$file", $raw);
                }
                if (is_file("$dir/$file")) $out[$name][] = $file;
                else warn("icon skipped: $ref");
            }
        }
    }
    return $out;
}

/** ロゴの指定を [保存するファイル名, 取得する関数] にする。 */
function icon_ref(string $ref, array $src): array {
    if (str_starts_with($ref, 'https://')) {
        return ['url-' . basename(parse_url($ref, PHP_URL_PATH)), static function () use ($ref): ?string {
            $raw = http_get($ref);
            return $raw !== null && @imagecreatefromstring($raw) ? $raw : null;
        }];
    }
    if (str_starts_with($ref, 'si:')) {
        [, $slug, $hex] = explode(':', $ref);
        return ["si-$slug.svg", static function () use ($src, $slug, $hex): ?string {
            $raw = http_get("{$src['simpleIcons']}/$slug.svg");
            return $raw === null ? null : str_replace('<path ', "<path fill=\"#$hex\" ", $raw);
        }];
    }
    return [basename($ref) . '.svg', static fn(): ?string => http_get("{$src['devicon']}/$ref.svg")];
}
