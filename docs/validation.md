# Validation（バリデーション）

リクエストパラメータのバリデーションとキャストを行うクラス群。

## 基本的な使い方

```php
use ayutenn\core\validation\Validator;

$format = [
    'old-password' => [
        'name' => '旧パスワード',
        'format' => 'password',
        'require' => true,
    ],
    'new-password' => [
        'name' => '新パスワード',
        'format' => 'password',
        'require' => true,
    ],
];

$validator = new Validator($format, __DIR__ . '/rules');
$result = $validator->validate($_POST);

if ($result->hasErrors()) {
    $errors = $result->getErrors();
    // エラー処理
} else {
    $cleanData = $result->getCastedValues();
    // 正常処理
}
```

---

## エラーの取得

### ValidationResult API

| メソッド | 説明 |
|---------|------|
| `hasErrors(): bool` | エラーがあるか |
| `getErrors(): array` | 全エラーを取得 |
| `getError(string $key): ?string` | 特定パラメータのエラーを取得 |
| `getCastedValues(): array` | キャスト済みの値を取得 |
| `getValue(string $key): mixed` | 特定パラメータの値を取得 |

### エラーメッセージの形式

```php
$result = $validator->validate($_POST);

// 全エラーを取得
$errors = $result->getErrors();
// [
//     'username' => 'ユーザー名は必須です。',
//     'email' => 'メールアドレスはメールアドレス形式である必要があります。',
// ]

// 特定パラメータのエラー
$error = $result->getError('username');
// 'ユーザー名は必須です。'
// エラーがない場合は null
```

### エラーメッセージの種類

| 状況 | エラーメッセージ例 |
|------|-----------------|
| 必須項目が空 | `{name}は必須です。` |
| 文字数超過 | `{name}は16文字以下である必要があります。（現在: 20文字）` |
| 文字数不足 | `{name}は8文字以上である必要があります。（現在: 5文字）` |
| 行数超過 | `{name}は5行以下である必要があります。（現在: 10行）` |
| 数値超過 | `{name}は100以下である必要があります。` |
| 数値不足 | `{name}は0以上である必要があります。` |
| 形式不正（email） | `{name}はメールアドレス形式である必要があります。` |
| 形式不正（alphanumeric） | `{name}は英数字のみである必要があります。` |
| 型不正 | `{name}は文字列である必要があります。` |

### ネスト構造のエラー

object型やlist型のネスト内でエラーが発生した場合、子要素のエラーが連結されます。

```php
// object型のエラー
$result->getError('user');
// 'ユーザー名は必須です。 メールアドレスはメールアドレス形式である必要があります。'

// list型のエラー
$result->getError('tags');
// '[0]: タグは英数字のみである必要があります。 [2]: タグは必須です。'
```

---

## フォーマット配列

### item型（単一値）

```php
'username' => [
    'type' => 'item',          // 省略可能（デフォルト）
    'name' => 'ユーザー名',
    'format' => 'username',    // ファイル名 or インライン配列
    'require' => true,         // 省略可能（デフォルト: true）
]
```

### object型（オブジェクト）

```php
'user' => [
    'type' => 'object',        // 必須
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
'icon_list' => [
    'type' => 'list',          // 必須
    'name' => 'アイコンリスト',
    'items' => [
        'name' => 'アイコン',
        'format' => 'icon',
        'require' => true,
    ],
]
```

---

## format の指定方法

### 文字列（ファイル名）

```php
'format' => 'password'  // rules/password.json を読み込む
```

### インライン配列

```php
'format' => [
    'type' => 'string',
    'min_length' => 8,
    'max_length' => 100,
]
```

---

## ルールファイル（JSON）

```json
{
    "type": "string",
    "min_length": 8,
    "max_length": 100,
    "max_line": 1
}
```

### オプション

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

### conditions

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
