<?php
declare(strict_types=1);

/**
 * 技術スタックのロゴの指定。data/config.json の skillIcons に次の形で書く。
 *   "java/java-original"  Devicon のカラーロゴ
 *   "si:slug:色"          Simple Icons の単色ロゴを指定色で塗る
 *   "https://..."         画像の URL をそのまま取得する
 */
final readonly class IconRef {
    /** @param Closure(): ?string $fetch ロゴを取得する。失敗したら null */
    private function __construct(
        public string $file,
        public Closure $fetch,
    ) {}

    public static function parse(string $ref, array $src): self {
        return match (true) {
            str_starts_with($ref, 'https://') => new self(
                'url-' . basename(parse_url($ref, PHP_URL_PATH)),
                static fn() => ($raw = http_get($ref)) !== null && @imagecreatefromstring($raw) ? $raw : null,
            ),
            str_starts_with($ref, 'si:') => self::simpleIcon($ref, $src['simpleIcons']),
            default => new self(
                basename($ref) . '.svg',
                static fn() => http_get("{$src['devicon']}/$ref.svg"),
            ),
        };
    }

    private static function simpleIcon(string $ref, string $base): self {
        [, $slug, $hex] = explode(':', $ref);
        return new self("si-$slug.svg", static function () use ($base, $slug, $hex): ?string {
            $svg = http_get("$base/$slug.svg");
            return $svg === null ? null : str_replace('<path ', "<path fill=\"#$hex\" ", $svg);
        });
    }

    /** $dir に保存されていなければ取得する。保存できたら true。 */
    public function saveTo(string $dir): bool {
        $path = "$dir/$this->file";
        if (!is_file($path) && ($raw = ($this->fetch)()) !== null) file_put_contents($path, $raw);
        return is_file($path);
    }
}

/** 技術スタックのロゴを $dir に保存する。返り値は 名前 => [ファイル名, ...]。1 つも取得できなかった名前は含まない（文字のマークで表示する）。 */
function skill_icons(array $skills, array $config, string $dir): array {
    ensure_dir($dir);
    $out = [];
    foreach (array_merge(...array_column($skills, 'items')) as $name) {
        foreach ($config['skillIcons'][$name] ?? [] as $ref) {
            $icon = IconRef::parse($ref, $config['iconSources']);
            if ($icon->saveTo($dir)) $out[$name][] = $icon->file;
            else warn("icon skipped: $ref");
        }
    }
    return $out;
}
