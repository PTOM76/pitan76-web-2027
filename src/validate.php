<?php
declare(strict_types=1);

/** data/ の JSON の項目の型。各要素が決まった形の配列は、型の代わりに ['キー' => 型, ...] で書く。 */
enum Field {
    case Text;
    case List;

    public function accepts(mixed $v): bool {
        return match ($this) {
            self::Text => is_string($v),
            self::List => is_array($v),
        };
    }

    public function label(): string {
        return match ($this) {
            self::Text => '文字列',
            self::List => '配列',
        };
    }
}

/** data/ の JSON に必要な項目があるかを確かめる。問題を「ファイル: パス」の形で返す。 */
function validate_data(array $data, array $schema, string $where): array {
    $errors = [];
    foreach ($schema as $key => $type) {
        $path = "$where.$key";
        if (!array_key_exists($key, $data)) {
            $errors[] = "$path がありません";
            continue;
        }
        $v = $data[$key];
        if (is_array($type)) {
            $errors = [...$errors, ...validate_rows($v, $type, $path)];
        } elseif (!$type->accepts($v)) {
            $errors[] = "$path は{$type->label()}にしてください";
        }
    }
    return $errors;
}

/** 各要素が $schema の形の配列かを確かめる。 */
function validate_rows(mixed $rows, array $schema, string $path): array {
    if (!is_array($rows)) return ["$path は配列にしてください"];
    $errors = [];
    foreach (array_values($rows) as $i => $row) {
        $errors = [...$errors, ...is_array($row)
            ? validate_data($row, $schema, "{$path}[$i]")
            : ["{$path}[$i] の形が違います"]];
    }
    return $errors;
}

const SITE_SCHEMA = [
    'title' => Field::Text, 'author' => Field::Text, 'description' => Field::Text, 'lead' => Field::Text, 'heroFacts' => Field::Text,
    'links' => Field::List,
    'skills' => ['name' => Field::Text, 'items' => Field::List],
    'history' => ['period' => Field::Text, 'items' => Field::Text],
    'qualifications' => ['name' => Field::Text],
    'achievements' => ['id' => Field::Text, 'label' => Field::Text, 'value' => Field::Text],
    'activities' => Field::List,
];

const PROJECT_SCHEMA = [
    'work' => Field::Text, 'name' => Field::Text, 'category' => Field::Text, 'summary' => Field::Text,
    'detail' => Field::Text, 'tags' => Field::List, 'url' => Field::Text,
];

const CHARACTER_SCHEMA = [
    'name' => Field::Text, 'proto' => Field::Text, 'desc' => Field::Text, 'start' => Field::Text, 'done' => Field::Text,
];
