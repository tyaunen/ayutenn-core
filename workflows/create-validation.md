---
description: バリデーションルールを定義する
---

# バリデーションの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
入力バリデーションを追加する際は、以下の手順に従ってください。

## バリデーションルールの作成基準
プロジェクト中で繰り返し登場する値かつ、バリデーションが組み合わせになる場合はルールファイルに定義してください。
例えば、ユーザーIDはユーザー名は繰り返し登場するかつ「n文字以上」と「n文字以上」「使える記号は……」などルールが複数あるため、ルールファイルを作成するべきです。
一方で、フラグ系の値は単にboolであり、「削除フラグ」と「登録済フラグ」など全てにルールファイルを作成するのは冗長です。そのため、インラインで定義すべきです。

## 手順

### 1. バリデーションルールJSONを作成

`models/` ディレクトリに JSON 形式でルールを作成します。

```json
// models/password.json
{
    "type": "string",
    "min_length": 8,
    "max_length": 100,
    "max_line": 1
}
```

```json
// models/email.json
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
        'format' => 'email',  // models/email.json を参照
        'require' => true,
    ],
    'password' => [
        'name' => 'パスワード',
        'format' => 'password',  // models/password.json を参照
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

## マイグレーションとの連携（dbセクション）

ルールファイルに `db` セクションを追加すると、テーブル定義でこのルールを参照できます。
**これにより、バリデーションとテーブル定義の齟齬を防ぐことができます。**

```json
// models/user_id.json
{
    "type": "string",
    "min_length": 16,
    "max_length": 16,
    "conditions": ["alphanumeric"],
    "db": {
        "type": "char",
        "length": 16
    }
}
```

テーブル定義で参照:

```json
// tables/users.json
{
  "name": "users",
  "columns": {
    "user_id": {
      "format": "user_id",
      "nullable": false,
      "comment": "ユーザーID"
    }
  }
}
```

### dbセクションの属性

| 属性 | 説明 |
|------|------|
| `type` | DBカラム型（varchar, char, int等） |
| `length` | カラム長（省略時はmax_lengthから導出） |
| `unsigned` | 符号なし |
| `precision` | DECIMALの精度 |
| `scale` | DECIMALのスケール |

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