<?php
declare(strict_types=1);

/**
 * テンプレートの描画。すべてのテンプレートで共通の値（サイト情報など）と、ページの階層に合わせたパスの前置きを持つ。
 * 前置きはテンプレートの中で $p として使える（トップは ""、works/ 内は "../"）。
 */
final class View {
    /** 描画中の View。テンプレートから partial() と asset() で参照する */
    private static ?self $current = null;

    /**
     * @param array $shared   すべてのテンプレートに渡す値
     * @param array $versions アセットのパス => 内容のハッシュ
     */
    public function __construct(
        public readonly array $shared,
        public readonly array $versions = [],
        public readonly string $prefix = '',
    ) {}

    /** パスの前置きだけを変えた View を返す。 */
    public function at(string $prefix): self {
        return new self($this->shared, $this->versions, $prefix);
    }

    /** templates/$template.php を描画する。$vars は共通の値より優先する。 */
    public function render(string $template, array $vars = []): string {
        $file = TEMPLATE_DIR . "/$template.php";
        if (!is_file($file)) throw new RuntimeException("template not found: $template");
        [$outer, self::$current] = [self::$current, $this];
        try {
            return self::evaluate($file, $vars + ['p' => $this->prefix] + $this->shared);
        } finally {
            self::$current = $outer;
        }
    }

    /**
     * アセットの URL。更新時にブラウザのキャッシュが残らないよう、内容のハッシュを ?v= に付ける。
     */
    public function asset(string $path): string {
        $v = $this->versions[$path] ?? null;
        return $this->prefix . $path . ($v ? "?v=$v" : '');
    }

    public static function current(): self {
        return self::$current ?? throw new LogicException('テンプレートの外では使えません');
    }

    /** テンプレートを、$vars 以外の変数が見えない状態で実行する。 */
    private static function evaluate(string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

/** templates/partials/$name.php を描画する。テンプレートの中で使う。 */
function partial(string $name, array $vars = []): string {
    return View::current()->render("partials/$name", $vars);
}

/** アセットの URL。テンプレートの中で使う。 */
function asset(string $path): string {
    return View::current()->asset($path);
}
