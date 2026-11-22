<?php
declare(strict_types=1);

/**
 * ターミナルの固定の出力（ビルド時に内容が決まるもの）を作る。値は HTML として表示されるため、ここでエスケープする。
 * 入力によって出力が変わるコマンド（find、curl など）は main.js で処理する。
 */
function terminal_text(array $site, array $projects): array {
    $commands = [
        'help', 'whoami', 'ls', 'open <n>', 'find <語>', 'skills', 'curl works/<作品>', 'ftp get <作品>',
        'smtp send <作品>', 'dex', 'theme <auto|light|dark>', 'goto <section>', 'bug', 'sudo', 'clear', 'exit',
    ];
    $groups = array_map(
        static fn($g) => str_pad($g['name'], 15) . implode(', ', $g['items']),
        $site['skills']
    );
    $ls = array_map(
        static fn($i, $p) => sprintf('[%d] %s — %s', $i + 1, $p['name'], $p['summary']),
        array_keys($projects), $projects
    );
    return array_map(h(...), [
        'help' => implode(', ', $commands),
        'whoami' => "{$site['author']} — {$site['lead']}",
        'ls' => implode("\n", $ls),
        'skills' => implode("\n", $groups),
    ]);
}
