<?php
declare(strict_types=1);

/**
 * data/ の JSON に必要な項目があるかを確かめる。足りない項目を「ファイル: パス」の形で返す。
 * 型の指定: 'string'、'array'（配列ならなんでも）、['キー' => 型, ...]（各要素がその形の配列）。
 */
function validate_data(array $data, array $schema, string $where): array {
    $errors = [];
    foreach ($schema as $key => $type) {
        $path = "$where.$key";
        if (!array_key_exists($key, $data)) {
            $errors[] = "$path がありません";
            continue;
        }
        $v = $data[$key];
        if ($type === 'string' && !is_string($v)) $errors[] = "$path は文字列にしてください";
        elseif ($type === 'array' && !is_array($v)) $errors[] = "$path は配列にしてください";
        elseif (is_array($type)) {
            if (!is_array($v)) { $errors[] = "$path は配列にしてください"; continue; }
            foreach (array_values($v) as $i => $row) {
                $errors = array_merge($errors, is_array($row) ? validate_data($row, $type, "{$path}[$i]") : ["{$path}[$i] の形が違います"]);
            }
        }
    }
    return $errors;
}

const SITE_SCHEMA = [
    'title' => 'string', 'author' => 'string', 'description' => 'string', 'lead' => 'string', 'heroFacts' => 'string',
    'links' => 'array',
    'skills' => ['name' => 'string', 'items' => 'array'],
    'history' => ['period' => 'string', 'items' => 'string'],
    'qualifications' => ['name' => 'string'],
    'achievements' => ['id' => 'string', 'label' => 'string', 'value' => 'string'],
    'activities' => 'array',
];

const PROJECT_SCHEMA = [
    'work' => 'string', 'name' => 'string', 'category' => 'string', 'summary' => 'string',
    'detail' => 'string', 'tags' => 'array', 'url' => 'string',
];

const CHARACTER_SCHEMA = ['name' => 'string', 'proto' => 'string', 'desc' => 'string', 'start' => 'string', 'done' => 'string'];
