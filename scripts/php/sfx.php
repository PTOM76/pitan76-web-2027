<?php
declare(strict_types=1);

namespace Sfx;

/**
 * 効果音を物理モデルで合成し、assets/sfx/ に ogg（Opus）と m4a（AAC）で書き出す。
 * 使い方: php scripts/php/sfx.php（ffmpeg が必要）。build.php は assets/sfx/sfx.json がないときだけ呼ぶ
 *
 * 参考にした手法
 *  - モーダル合成: K. van den Doel, P. G. Kry, D. K. Pai, "FoleyAutomatic", SIGGRAPH 2001
 *  - Rayleigh 減衰 d = α + β f^2: R. L. Klatzky, D. K. Pai, E. P. Krotkov, 2000
 *  - 固有振動以外の成分（residual）: Z. Ren, H. Yeh, M. C. Lin, "Example-Guided Physically Based Modal Sound Synthesis", 2013
 *  - 破断音: C. Zheng, D. L. James, "Rigid-Body Fracture Sound with Precomputed Soundbanks", SIGGRAPH 2010
 *  - 棒の固有振動比: 両端自由の梁 1 : 2.756 : 5.404 : 8.933、片持ち梁 1 : 6.267 : 17.55
 */

const SR = 48000;

function rnd(float $a, float $b): float {
    return $a + mt_rand() / mt_getrandmax() * ($b - $a);
}

function silence(float $sec): array {
    return array_fill(0, (int)ceil($sec * SR), 0.0);
}

/** $src を $dst の $at 秒の位置に $gain 倍で足す */
function mix(array &$dst, array $src, float $at = 0.0, float $gain = 1.0): void {
    $o = (int)round($at * SR);
    $n = min(count($src), count($dst) - $o);
    for ($i = max(0, -$o); $i < $n; $i++) $dst[$o + $i] += $src[$i] * $gain;
}

/**
 * 物体を叩いたときの音。各固有振動を減衰する正弦波で表し、接触時間 $contact 秒の
 * 半正弦波の力で叩く（Hertz 接触の近似）。接触が短いほど高い固有振動まで鳴る。
 *
 * @param array $modes [[周波数 Hz, 振幅], ...]
 * @param float $alpha 周波数によらない減衰（1/s）
 * @param float $beta  周波数の 2 乗に比例する減衰。大きいほど高音が早く消える
 */
function strike(array $modes, float $alpha, float $beta, float $contact, float $sec): array {
    $n = (int)ceil($sec * SR);
    $ir = array_fill(0, $n, 0.0);
    foreach ($modes as [$f, $amp]) {
        if ($f >= SR * 0.45) continue;
        $d = $alpha + $beta * $f * $f;
        $w = 2 * M_PI * $f / SR;
        // 減衰する正弦波を漸化式で計算する（sin を毎回呼ばない）
        $r = exp(-$d / SR);
        $c = 2 * $r * cos($w);
        $y1 = 0.0; $y2 = -$amp * $r * sin($w);
        $ph = rnd(0, 0.3);
        for ($i = 0; $i < $n; $i++) {
            $y = $c * $y1 - $r * $r * $y2;
            if ($i === 0) $y = $amp * sin($ph);
            if ($i === 1) $y = $amp * $r * sin($w + $ph);
            $ir[$i] += $y;
            $y2 = $y1; $y1 = $y;
            if ($i > 2000 && abs($y) < 1e-6 && abs($y2) < 1e-6) break;
        }
    }
    // 半正弦波の力と畳み込む
    $len = max(1, (int)round($contact * SR));
    $pulse = [];
    for ($k = 0; $k < $len; $k++) $pulse[] = sin(M_PI * ($k + 0.5) / $len);
    $out = array_fill(0, $n, 0.0);
    for ($i = 0; $i < $n; $i++) {
        if ($ir[$i] == 0.0) continue;
        for ($k = 0; $k < $len && $i + $k < $n; $k++) $out[$i + $k] += $ir[$i] * $pulse[$k];
    }
    return $out;
}

/**
 * 固体の固有振動を作る。3 次元の物体では高い周波数ほど固有振動が密になるため、
 * 周波数比を k^0.7 程度で増やし、少し揺らして非整数にする。振幅もばらつかせる。
 */
function solidModes(float $f0, int $count, float $spread = 1.0): array {
    $modes = [];
    for ($k = 0; $k < $count; $k++) {
        $ratio = 1 + $spread * (($k + 1) ** 0.7 - 1) * rnd(0.92, 1.08);
        $modes[] = [$f0 * $ratio, rnd(0.3, 1.0) / sqrt($k + 1)];
    }
    return $modes;
}

/** 理論上の比を持つ棒の固有振動 */
function barModes(float $f0, array $ratios, array $amps): array {
    return array_map(fn($r, $a) => [$f0 * $r, $a], $ratios, $amps);
}

/** 白色ノイズに一次のフィルタと指数減衰をかけたもの。固有振動以外の擦れや砕ける成分 */
function residual(float $sec, float $decay, float $lp, float $hp): array {
    $n = (int)ceil($sec * SR);
    $out = [];
    $al = 1 - exp(-2 * M_PI * $lp / SR);
    $ah = exp(-2 * M_PI * $hp / SR);
    $l = 0.0; $hPrev = 0.0; $hOut = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $x = rnd(-1, 1) * exp(-$decay * $i / SR);
        $l += $al * ($x - $l);
        $hOut = $ah * ($hOut + $l - $hPrev);
        $hPrev = $l;
        $out[] = $hOut;
    }
    return $out;
}

/**
 * 小さな部屋の響き。初期反射を数本足し、その後に 4 本の遅延線のフィードバック網
 * （FDN）で短い残響を作る。乾いたままの合成音は作り物に聞こえやすいため。
 */
function room(array $x, float $wet = 0.12, float $t60 = 0.35): array {
    $n = count($x) + (int)(SR * $t60);
    $y = array_pad($x, $n, 0.0);
    foreach ([[0.0043, 0.32], [0.0071, 0.25], [0.0113, 0.2], [0.0167, 0.14]] as [$t, $g]) {
        mix($y, $x, $t, $g * $wet * 2);
    }
    $lens = [1427, 1789, 2083, 2399];
    $bufs = array_map(fn($l) => array_fill(0, $l, 0.0), $lens);
    $pos = [0, 0, 0, 0];
    $gains = array_map(fn($l) => 10 ** (-3 * $l / SR / $t60), $lens);
    $lp = [0.0, 0.0, 0.0, 0.0];
    for ($i = 0; $i < $n; $i++) {
        $in = $x[$i] ?? 0.0;
        $o = [];
        for ($j = 0; $j < 4; $j++) $o[$j] = $bufs[$j][$pos[$j]];
        // Hadamard 行列で 4 本を混ぜる
        $h = [
            $o[0] + $o[1] + $o[2] + $o[3], $o[0] - $o[1] + $o[2] - $o[3],
            $o[0] + $o[1] - $o[2] - $o[3], $o[0] - $o[1] - $o[2] + $o[3],
        ];
        for ($j = 0; $j < 4; $j++) {
            $v = $in + 0.5 * $h[$j] * $gains[$j];
            $lp[$j] += 0.45 * ($v - $lp[$j]);   // 高音ほど早く消えるよう、帰還路に低域通過をかける
            $bufs[$j][$pos[$j]] = $lp[$j];
            $pos[$j] = ($pos[$j] + 1) % $lens[$j];
        }
        $y[$i] += ($o[0] + $o[1] + $o[2] + $o[3]) * 0.25 * $wet;
    }
    return $y;
}

/** 直流分と超低域を除き、ピークを -1 dBFS にそろえ、末尾を短くフェードする */
function finish(array $x, float $peakDb = -1.0): array {
    $a = exp(-2 * M_PI * 30 / SR);
    $prevIn = 0.0; $prevOut = 0.0;
    foreach ($x as $i => $v) {
        $prevOut = $a * ($prevOut + $v - $prevIn);
        $prevIn = $v;
        $x[$i] = $prevOut;
    }
    // 末尾の無音を削る
    $last = count($x) - 1;
    $peak = max(array_map('abs', $x)) ?: 1.0;
    while ($last > 0 && abs($x[$last]) < $peak * 1e-4) $last--;
    $x = array_slice($x, 0, $last + 1);
    $g = 10 ** ($peakDb / 20) / $peak;
    $fade = min(count($x), (int)(0.01 * SR));
    $n = count($x);
    foreach ($x as $i => $v) {
        $f = $i >= $n - $fade ? ($n - $i) / $fade : 1.0;
        $x[$i] = $v * $g * $f;
    }
    return $x;
}

function writeWav(string $path, array $x): void {
    $data = '';
    foreach ($x as $v) $data .= pack('v', (int)round(max(-1, min(1, $v)) * 32767) & 0xffff);
    $h = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, SR, SR * 2, 2, 16)
        . 'data' . pack('V', strlen($data));
    file_put_contents($path, $h . $data);
}

// ---------- 各効果音 ----------

/** ツルハシで石を叩く。硬い物同士なので接触は短く、石は高音の減衰が速い */
function hitStone(): array {
    $f0 = rnd(330, 480);
    $body = strike(solidModes($f0, 36, 2.2), 25, 2.2e-6, rnd(0.00025, 0.0004), 0.35);
    $x = silence(0.4);
    mix($x, $body, 0, 1.0);
    mix($x, residual(0.08, 90, 7000, 900), 0, 0.5);   // 砕けた粒の擦れ
    mix($x, residual(0.03, 160, 12000, 3000), 0, 0.25);  // 金属の先端が当たる瞬間
    return finish(room($x, 0.1, 0.3));
}

/** 石のブロックが砕ける。ひびの衝撃、ブロック本体、大きさの違う破片の順に鳴る */
/**
 * 砕け方の性格。候補ごとにこの中から 1 つを選び、さらに数値を揺らす。
 *  crack: ひびの衝撃の強さ / body: 本体の基準周波数 [下, 上] / heavy: 本体の重さ（接触時間と音量）
 *  pieces: 破片の数 [下, 上] / gap: 破片の平均間隔 s / small: 破片の大きさ [下, 上]（小さいほど高い）
 *  dust: 崩れる砂の量 / bounce: 破片が床で跳ねる回数の上限
 */
const BREAK_STYLES = [
    'normal' => ['crack' => 0.7, 'body' => [180, 240], 'heavy' => 1.0, 'pieces' => [20, 24], 'gap' => 0.014, 'small' => [0.15, 1.0], 'frag' => [480, 720], 'dust' => 0.9, 'bounce' => 0],
    'low'    => ['crack' => 0.35, 'body' => [80, 110], 'heavy' => 1.4, 'pieces' => [9, 13], 'gap' => 0.018, 'small' => [0.6, 1.0], 'frag' => [280, 420], 'dust' => 1.0, 'bounce' => 1],
    'lower'  => ['crack' => 0.25, 'body' => [55, 75], 'heavy' => 1.6, 'pieces' => [7, 10], 'gap' => 0.022, 'small' => [0.7, 1.0], 'frag' => [200, 300], 'dust' => 1.1, 'bounce' => 1],
];

/** 石のブロックが砕ける。ひびの衝撃、ブロック本体、大きさの違う破片の順に鳴る */
function breakStone(string $style): array {
    $s = BREAK_STYLES[$style];
    $x = silence(1.6);
    // ひびが走る瞬間。1〜3 回に分けて走ることがある
    $cracks = mt_rand(1, 3);
    for ($c = 0; $c < $cracks; $c++) {
        mix($x, residual(0.012, rnd(300, 500), 16000, rnd(1500, 3000)), $c * rnd(0.004, 0.012), $s['crack'] * 0.7 / ($c + 1));
    }
    // 本体: 重いほど接触が長く、低い音が中心になる
    $f0 = rnd(...$s['body']);
    mix($x, strike(solidModes($f0, 30, rnd(2.2, 3.0)), 18, 3.5e-6, 0.0008 * $s['heavy'], 0.6), 0.002, 0.8 * $s['heavy']);
    mix($x, residual(rnd(0.2, 0.35), rnd(12, 22), rnd(2000, 3500), 120), 0.002, 0.7 * $s['dust']);
    // 破片: 小さいほど固有振動が高い（周波数は大きさに反比例）。間隔は指数分布で、だんだん間遠くなる
    $t = 0.01;
    $count = mt_rand(...$s['pieces']);
    for ($i = 0; $i < $count; $i++) {
        $t += -log(1 - rnd(0, 0.999)) * $s['gap'] * (1 + $i * 0.1);
        $size = rnd(...$s['small']);
        $modes = solidModes(rnd(...$s['frag']) / $size, 14, 2.0);   // 同じ破片が跳ねても同じ音色になるよう固定する
        $amp = 0.28 * (1 - $i / ($count + 4)) * $size ** 0.5;
        $contact = 0.0003 * $size;
        mix($x, strike($modes, 40, 3e-6, $contact, 0.15), $t, $amp);
        if (mt_rand(0, 2) === 0) mix($x, residual(0.03, 120, 9000, 1500), $t, 0.15 * $s['dust']);
        // 床で跳ねる: 跳ね返り係数 0.4〜0.6 で、間隔と強さが縮まっていく
        $bt = $t; $gapB = rnd(0.05, 0.1) * $size; $ab = $amp;
        for ($b = 0; $b < mt_rand(0, $s['bounce']); $b++) {
            $e = rnd(0.4, 0.6);
            $bt += $gapB; $gapB *= $e; $ab *= $e;
            mix($x, strike($modes, 40, 3e-6, $contact, 0.12), $bt, $ab);
        }
    }
    return finish(room($x, 0.14, 0.45));
}

/** 操作音。小さな木片を爪で弾いた程度の、短く乾いた音 */
function blip(): array {
    $x = silence(0.15);
    mix($x, strike(solidModes(1650, 18, 1.6), 60, 6e-6, 0.00035, 0.12), 0, 1.0);
    mix($x, residual(0.015, 300, 9000, 2500), 0, 0.15);
    return finish(room($x, 0.06, 0.2), -3.0);
}

const FREE_BAR = [[1, 2.756, 5.404, 8.933], [1, 0.42, 0.22, 0.1]];

/** 金属の棒（鉄琴）を柔らかいマレットで叩く */
function barNote(float $f, float $sec, float $alpha): array {
    $modes = barModes($f, FREE_BAR[0], FREE_BAR[1]);
    // 実物の鉄琴は 1 本の棒でもわずかにずれた振動を持つので、うなりを少し加える
    $modes[] = [$f * 1.0017, 0.35];
    return strike($modes, $alpha, 1.2e-9, 0.0009, $sec);
}

/**
 * 作品が届いたときの短いフレーズ。和音を下から順に積む分散和音は定番すぎるため、
 * 5 度跳ねて 1 音下がり、オクターブ上へ抜けて 6 度を重ねた響きで終える（E5 B5 A5 → E6 + F#6）。
 * 手で叩いたように、間隔と強さを少し不揃いにする。
 */
function transfer(): array {
    $x = silence(1.4);
    // [開始 s, 周波数 Hz, 強さ, 余韻 s]
    $notes = [
        [0.000, 659.26, 0.55, 0.35],    // E5
        [0.072, 987.77, 0.75, 0.40],    // B5
        [0.150, 880.00, 0.50, 0.50],    // A5
        [0.262, 1318.51, 0.85, 1.10],   // E6
        [0.268, 1479.98, 0.35, 1.00],   // F#6（わずかに遅らせて重ねる）
    ];
    foreach ($notes as [$t, $f, $g, $len]) {
        mix($x, barNote($f, $len, 3.2 / $len), $t + rnd(-0.004, 0.004), $g * rnd(0.92, 1.05));
    }
    return finish(room($x, 0.16, 0.6), -2.0);
}

function achieve(): array {
    $x = silence(2.2);
    foreach ([783.99, 987.77, 1318.5] as $i => $f) mix($x, barNote($f, $i === 2 ? 1.9 : 0.9, $i === 2 ? 2.2 : 4.5), $i * 0.095, 0.8);
    mix($x, barNote(1318.5 * 2, 1.2, 3), 0.19, 0.18);   // 最後の音にオクターブ上を薄く重ねてきらめきを出す
    return finish(room($x, 0.18, 0.8), -1.5);
}

/**
 * 机の端に押さえた定規を弾く。片持ち梁の固有振動を使い、振幅が大きい間は
 * 張力が増えて音程が上がる（振幅に依存する非線形性）ため、減衰とともに音程を下げる。
 */
function boing(): array {
    $sec = 0.55;
    $n = (int)($sec * SR);
    $x = [];
    $ph = [0.0, 0.0, 0.0];
    $ratios = [1, 6.267, 17.55];
    $amps = [1.0, 0.18, 0.04];
    $f = 185.0;
    for ($i = 0; $i < $n; $i++) {
        $t = $i / SR;
        $e = exp(-7 * $t);
        $bend = 1 + 0.32 * $e * $e;   // 振幅が大きいほど音程が高い
        $v = 0.0;
        foreach ($ratios as $k => $r) {
            $ph[$k] += 2 * M_PI * $f * $r * $bend / SR;
            $v += $amps[$k] * sin($ph[$k]) * exp(-(7 + 9 * $k * $k) * $t);
        }
        // 机に当たってバタつく音: 1 周期ごとに短いクリックが入る
        $v += 0.25 * $e * (sin($ph[0]) > 0.97 ? rnd(-1, 1) : 0);
        $x[] = $v * min(1, $t / 0.001);
    }
    return finish(room($x, 0.1, 0.3), -2.0);
}

// ---------- 書き出し ----------

/** 全効果音を $out に書き出し、名前ごとの候補一覧を sfx.json に保存する */
function generate(string $out): void {
    mt_srand(76);
    $sounds = [
        'hit' => [4, 'hitStone'],
        // 普通の音を 2 つ、低い音を 1 つ、さらに低い音を 2 つ作る
        'break' => [5, fn(int $v) => breakStone(['normal', 'normal', 'low', 'lower', 'lower'][$v - 1])],
        'blip' => [1, 'blip'],
        'transfer' => [1, 'transfer'],
        'achieve' => [1, 'achieve'],
        'boing' => [1, 'boing'],
    ];
    if (!is_dir($out)) mkdir($out, 0777, true);
    // 候補の数を減らしたときに古いファイルが残らないよう、先に消す
    foreach (glob("$out/*.{ogg,m4a,json}", GLOB_BRACE) as $f) unlink($f);
    $tmp = sys_get_temp_dir();
    $manifest = [];
    foreach ($sounds as $name => [$count, $fn]) {
        for ($v = 1; $v <= $count; $v++) {
            $base = $count > 1 ? "$name-$v" : $name;
            $wav = "$tmp/sfx-" . getmypid() . "-$base.wav";   // ビルドが同時に走っても衝突しないよう PID を付ける
            writeWav($wav, is_string($fn) ? (__NAMESPACE__ . '\\' . $fn)() : $fn($v));
            foreach (['ogg' => '-c:a libopus -b:a 64k', 'm4a' => '-c:a aac -b:a 96k'] as $ext => $codec) {
                exec(sprintf('ffmpeg -y -loglevel error -i %s %s %s', escapeshellarg($wav), $codec, escapeshellarg($out . "/$base.$ext")), $o, $code);
                if ($code !== 0) throw new \RuntimeException("ffmpeg に失敗しました: $base.$ext");
            }
            unlink($wav);
            $manifest[$name][] = $base;
        }
    }
    file_put_contents($out . '/sfx.json', json_encode($manifest, JSON_PRETTY_PRINT) . "\n");
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) generate(__DIR__ . '/../../assets/sfx');
