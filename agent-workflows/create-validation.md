---
description: バリデーションルールを定義する
---

# バリデーションの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
入力バリデーションを追加する際は、以下の手順に従ってください。

## 手順

### 1. バリデーションルールJSONを作成

`rules/` ディレクトリに JSON 形式でルールを作成します。

```json
// rules/password.json
{
    "type": "string",
    "min_length": 8,
    "max_length": 100,
    "max_line": 1
}
```

```json
// rules/email.json
{
    "type": "string",
    "max_length": 255,
    "conditions": ["email"]
}
```

### 2. フォーマット配列で使用

Controller や Api の `$RequestParameterFormat` で参照します。

```php
protected array $RequestParameterFormat = [
    'email' => [
        'name' => 'メールアドレス',
        'format' => 'email',  // rules/email.json を参照
        'require' => true,
    ],
    'password' => [
        'name' => 'パスワード',
        'format' => 'password',  // rules/password.json を参照
        'require' => true,
    ],
];
```

インライン定義も可能:

```php
'age' => [
    'name' => '年齢',
    'format' => [
        'type' => 'int',
        'min' => 0,
        'max' => 150,
    ],
    'require' => false,
],
```

## ルールオプション

| キー | 説明 |
|------|------|
| `type` | 型（string, int, number, boolean） |
| `min` | 数値の最小値 |
| `max` | 数値の最大値 |
| `min_length` | 文字列の最小長 |
| `max_length` | 文字列の最大長 |
| `min_line` | 文字列の最小行数 |
| `max_line` | 文字列の最大行数 |
| `conditions` | 追加条件の配列 |

## conditions

| 条件名 | 説明 |
|--------|------|
| `email` | メールアドレス形式 |
| `url` | URL形式 |
| `alphanumeric` | 英数字のみ |
| `alphabetic` | 英字のみ |
| `numeric` | 数字のみ |
| `datetime` | 日時形式（Y/m/d H:i:s） |
| `date` | 日付形式（Y/m/d） |
| `color_code` | カラーコード（#RRGGBB） |

## ネスト構造

### object型（オブジェクト）

```php
'user' => [
    'type' => 'object',
    'name' => 'ユーザー',
    'properties' => [
        'user_name' => [
            'name' => 'ユーザー名',
            'format' => 'user_name',
            'require' => true,
        ],
    ],
]
```

### list型（リスト）

```php
'tags' => [
    'type' => 'list',
    'name' => 'タグリスト',
    'items' => [
        'name' => 'タグ',
        'format' => 'tag',
        'require' => true,
    ],
]
```

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/validation.md` を参照してください。
