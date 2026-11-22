<?php
declare(strict_types=1);

/**
 * テンプレートを描画して文字列で返す。$vars はテンプレート内で変数として使える。
 * テンプレートからは partial() で templates/partials/ の部品を読み込める。
 */
function render(string $template, array $vars): string {
    $file = TEMPLATE_DIR . "/$template.php";
    if (!is_file($file)) throw new RuntimeException("template not found: $template");
    return (static function () use ($file, $vars): string {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    })();
}

/** templates/partials/$name.php を描画する。$vars は呼び出し元の変数に追加される。 */
function partial(string $name, array $vars = []): string {
    return render("partials/$name", $vars + View::$shared);
}

/** すべてのテンプレートで共通の値（サイト情報、パスの前置き、アセットのバージョンなど）。 */
final class View {
    public static array $shared = [];
}

/**
 * アセットの URL。$p はページの階層に合わせた前置き（トップは ""、works/ 内は "../"）。
 * 更新時にブラウザのキャッシュが残らないよう、内容のハッシュを ?v= に付ける。
 */
function asset(string $path): string {
    $p = View::$shared['p'] ?? '';
    $v = View::$shared['versions'][$path] ?? null;
    return $p . $path . ($v ? "?v=$v" : '');
}
