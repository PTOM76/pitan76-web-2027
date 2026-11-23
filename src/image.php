<?php
declare(strict_types=1);

/** 画像を中央で切り抜いて指定サイズに縮小する。 */
function cover(GdImage $src, int $w, int $h): GdImage {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale); $ch = (int)round($h / $scale);
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w, $h, $cw, $ch);
    return $dst;
}

/** URL から画像を取得して GD 画像にする。取得や読み込みに失敗したら null。 */
function fetch_image(string $url): ?GdImage {
    $raw = http_get($url);
    $img = $raw === null ? false : @imagecreatefromstring($raw);
    return $img ?: null;
}

/** 透過を保ったまま縮小する。 */
function resize_png(string $src, string $dst, int $width): void {
    $img = imagecreatefrompng($src);
    $h = (int)round(imagesy($img) * $width / imagesx($img));
    $res = transparent_canvas($width, $h);
    imagecopyresampled($res, $img, 0, 0, 0, 0, $width, $h, imagesx($img), imagesy($img));
    imagepng($res, $dst, 9);
}

function transparent_canvas(int $w, int $h): GdImage {
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    return $img;
}

/**
 * サイトのアイコンを丸いドット絵にする。
 * 丸い元画像を 16x16 に縮めて色数を減らし、円の外を透明に、円周を焦げ茶の線にする。
 * 円の内側の左上は明るく、右下は暗くして立体に見せる。それを整数倍に拡大して出力する。
 */
function round_pixel_icon(string $src, string $dst, int $size): void {
    $img = imagecreatefrompng($src);
    $px = imagecreatetruecolor(16, 16);
    imagecopyresampled($px, $img, 0, 0, 0, 0, 16, 16, imagesx($img), imagesy($img));
    $res = transparent_canvas(16, 16);
    $ink = imagecolorallocate($res, 43, 23, 16);
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            $d = hypot($x - 7.5, $y - 7.5);
            if ($d > 7.9) continue;
            if ($d > 6.7) { imagesetpixel($res, $x, $y, $ink); continue; }
            $c = imagecolorat($px, $x, $y);
            $rgb = array_map(static fn($v) => (int)(round($v / 51) * 51), [($c >> 16) & 255, ($c >> 8) & 255, $c & 255]);
            if ($d > 5.6) {
                // 円周のすぐ内側。左上は明るく、右下は暗く
                $k = ($x + $y < 15) ? 1.3 : 0.7;
                $rgb = array_map(static fn($v) => max(0, min(255, (int)($v * $k + ($k > 1 ? 18 : 0)))), $rgb);
            }
            imagesetpixel($res, $x, $y, imagecolorallocate($res, ...$rgb));
        }
    }
    $out = transparent_canvas($size, $size);
    imagecopyresized($out, $res, 0, 0, 0, 0, $size, $size, 16, 16);   // 最近傍で拡大
    imagepng($out, $dst, 9);
}

/**
 * トップページの OGP 画像（1200x630）を作る。背景の方眼、名前と紹介文、キャラクター、足元のブロックを描く。
 * $font がなければ文字を描かない。
 */
function og_image(string $dst, string $sticker, ?string $font, string $name, string $title, string $lead): void {
    [$w, $h] = [1200, 630];
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    $rgb = static fn(string $hex) => imagecolorallocate($img, ...sscanf($hex, '#%02x%02x%02x'));
    $ink = $rgb('#161514');

    // 背景と 32px の方眼
    imagefill($img, 0, 0, $rgb('#dcecfa'));
    $grid = $rgb('#c8def2');
    for ($x = 0; $x < $w; $x += 32) imageline($img, $x, 0, $x, $h, $grid);
    for ($y = 0; $y < $h; $y += 32) imageline($img, 0, $y, $w, $y, $grid);

    // 足元の石ブロック
    $stone = [$rgb('#a7a39a'), $rgb('#b4b0a6'), $rgb('#9a968c')];
    for ($x = 0, $i = 0; $x < $w; $x += 64, $i++) {
        imagefilledrectangle($img, $x, $h - 64, $x + 63, $h - 1, $stone[$i % 3]);
        imagerectangle($img, $x, $h - 64, $x + 63, $h - 1, $ink);
        imagesetthickness($img, 1);
    }
    imagefilledrectangle($img, 0, $h - 66, $w, $h - 64, $ink);

    // キャラクター
    $src = imagecreatefrompng($sticker);
    $size = 470;
    imagecopyresampled($img, $src, $w - $size - 40, $h - 64 - $size + 30, 0, 0, $size, $size, imagesx($src), imagesy($src));

    if ($font !== null) {
        imagettftext($img, 104, 0, 72, 200, $ink, $font, $name);
        imagettftext($img, 30, 0, 78, 262, $rgb('#4a4640'), $font, $title);
        $y = 340;
        foreach (wrap_text($lead, $font, 26, 600) as $line) {
            imagettftext($img, 26, 0, 78, $y, $ink, $font, $line);
            $y += 44;
        }
    }
    imagepng($img, $dst, 9);
}

/** 文章を 1 行が $width px 以内になるように、1 文字ずつ測って折り返す。 */
function wrap_text(string $text, string $font, float $size, int $width): array {
    $lines = [''];
    foreach (mb_str_split($text) as $ch) {
        $next = end($lines) . $ch;
        $box = imagettfbbox($size, 0, $font, $next);
        if ($box[2] - $box[0] > $width && end($lines) !== '') $lines[] = $ch;
        else $lines[array_key_last($lines)] = $next;
    }
    return $lines;
}
