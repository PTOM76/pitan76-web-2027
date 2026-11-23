<?php
declare(strict_types=1);

namespace Minify;

/**
 * 配信用に CSS・JS を圧縮する。build.php が dist/ への出力時に呼ぶ。
 * 文字列・テンプレートリテラル・正規表現の中身は変更しない。
 * JS は改行を残す（自動セミコロン挿入の挙動を変えないため）。
 */

/** CSS のコメントと不要な空白を除去する。 */
function css(string $src): string {
    $out = '';
    $len = strlen($src);
    $space = false;
    for ($i = 0; $i < $len; $i++) {
        $c = $src[$i];
        if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
            $end = strpos($src, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }
        if ($c === '"' || $c === "'") {
            [$str, $i] = read_quoted($src, $i);
            if ($space && !str_contains('{};,>~+:(', substr($out, -1))) $out .= ' ';
            $out .= $str;
            $space = false;
            continue;
        }
        if (ctype_space($c)) {
            $space = $out !== '';
            continue;
        }
        if ($space) {
            $prev = substr($out, -1);
            // 記号の前後の空白は不要。ただしセレクタの結合子やメディアクエリの "and (" は残す
            if (!str_contains('{};,>~+:', $prev) && !str_contains('{};,>~+', $c)) $out .= ' ';
            elseif ($c === '+' && $prev !== '{' && $prev !== ';' && $prev !== '}') $out .= ' ';
            $space = false;
        }
        if ($c === '}' && str_ends_with($out, ';')) $out = substr($out, 0, -1);
        $out .= $c;
    }
    return $out;
}

/** JS のコメント・字下げ・空行を除去する。 */
function js(string $src): string {
    $out = '';
    $len = strlen($src);
    $braces = [];          // テンプレートリテラル内の ${ } の入れ子を追跡する
    $lastSig = '';         // 直前の意味のあるトークン（正規表現かどうかの判定に使う）
    $lineStart = true;
    for ($i = 0; $i < $len; $i++) {
        $c = $src[$i];
        $n = $src[$i + 1] ?? '';
        if ($c === "\n") {
            $out = rtrim($out, " \t");
            if ($out !== '' && !str_ends_with($out, "\n")) $out .= "\n";
            $lineStart = true;
            continue;
        }
        if ($c === ' ' || $c === "\t" || $c === "\r") {
            if (!$lineStart && !str_ends_with($out, ' ')) $out .= ' ';
            continue;
        }
        if ($c === '/' && $n === '/') {
            $end = strpos($src, "\n", $i);
            $i = ($end === false ? $len : $end) - 1;
            continue;
        }
        if ($c === '/' && $n === '*') {
            $end = strpos($src, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            if (!$lineStart && !str_ends_with($out, ' ')) $out .= ' ';
            continue;
        }
        $lineStart = false;
        if ($c === '"' || $c === "'") {
            [$str, $i] = read_quoted($src, $i);
            $out .= $str;
            $lastSig = 'x';
            continue;
        }
        if ($c === '`' || ($c === '}' && $braces && end($braces) === 0)) {
            if ($c === '}') array_pop($braces);
            // テンプレートリテラルを ` または ${ まで読む
            $j = $i + 1;
            while ($j < $len) {
                if ($src[$j] === '\\') { $j += 2; continue; }
                if ($src[$j] === '`') break;
                if ($src[$j] === '$' && ($src[$j + 1] ?? '') === '{') { $j++; $braces[] = 0; break; }
                $j++;
            }
            $out .= substr($src, $i, $j - $i + 1);
            $i = $j;
            $lastSig = $src[$j] === '`' ? 'x' : '{';
            continue;
        }
        if ($c === '/' && regex_allowed($lastSig, $out)) {
            $j = $i + 1;
            $class = false;
            while ($j < $len && $src[$j] !== "\n") {
                $d = $src[$j];
                if ($d === '\\') { $j += 2; continue; }
                if ($d === '[') $class = true;
                elseif ($d === ']') $class = false;
                elseif ($d === '/' && !$class) break;
                $j++;
            }
            $j++;
            while ($j < $len && ctype_alpha($src[$j])) $j++;   // フラグ
            $out .= substr($src, $i, $j - $i);
            $i = $j - 1;
            $lastSig = 'x';
            continue;
        }
        if ($braces) {
            $k = array_key_last($braces);
            if ($c === '{') $braces[$k]++;
            elseif ($c === '}') $braces[$k]--;
        }
        // 記号の前後の空白は不要（識別子・数値どうしの間だけ残す）
        if (str_ends_with($out, ' ') && !ctype_alnum($c) && !str_contains('_$\\', $c)) $out = substr($out, 0, -1);
        elseif (str_ends_with($out, ' ')) {
            $prev = substr($out, -2, 1);
            if (!ctype_alnum($prev) && !str_contains('_$', $prev)) $out = substr($out, 0, -1);
        }
        // "a - -b" や "a + +b" が "a--b" にならないようにする
        if (($c === '+' || $c === '-') && str_ends_with($out, $c) && ($src[$i - 1] ?? '') !== $c) $out .= ' ';
        $out .= $c;
        $lastSig = ctype_alnum($c) || str_contains('_$', $c) ? 'w' : $c;
    }
    return trim($out) . "\n";
}

/** 直前のトークンから、"/" が正規表現の始まりかどうかを判定する。 */
function regex_allowed(string $lastSig, string $out): bool {
    if ($lastSig === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $lastSig) || $lastSig === "\n") return true;
    if ($lastSig === 'w') {
        return (bool)preg_match('/(?:^|[^\w$])(?:return|typeof|instanceof|in|of|new|delete|void|throw|case|do|else|yield|await)\s*$/', $out);
    }
    return false;
}

/** $i から始まる引用符付き文字列を読み、[文字列, 終端の位置] を返す。 */
function read_quoted(string $src, int $i): array {
    $q = $src[$i];
    $j = $i + 1;
    $len = strlen($src);
    while ($j < $len && $src[$j] !== $q) {
        if ($src[$j] === '\\') $j++;
        $j++;
    }
    return [substr($src, $i, $j - $i + 1), $j];
}
