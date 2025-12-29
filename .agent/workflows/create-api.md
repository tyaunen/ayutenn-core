---
description: JSON APIエンドポイントを作成する
---

# APIの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
JSON APIエンドポイントを追加する際は、以下の手順に従ってください。

## 手順

### 1. Apiクラスを作成

`api/` ディレクトリに Api を継承したクラスを作成します。

```php
// api/user/CreateUserApi.php
<?php

use ayutenn\core\requests\Api;

class CreateUserApi extends Api
{
    protected array $RequestParameterFormat = [
        'name' => [
            'name' => '名前',
            'format' => [
                'type' => 'string',
                'max_length' => 50,
            ],
            'require' => true,
        ],
        'email' => [
            'name' => 'メールアドレス',
            'format' => [
                'type' => 'string',
                'conditions' => ['email'],
            ],
            'require' => true,
        ],
        'age' => [
            'name' => '年齢',
            'format' => [
                'type' => 'int',
                'min' => 0,
                'max' => 150,
            ],
            'require' => false,
        ],
    ];

    public function main(): array
    {
        // バリデーション済みパラメータは $this->parameter で取得
        $name = $this->parameter['name'];
        $email = $this->parameter['email'];

        // ユーザー作成処理...
        $userId = $this->createUser($name, $email);

        return $this->createResponse(true, [
            'user_id' => $userId,
            'message' => "ユーザー {$name} を作成しました。",
        ]);
    }

    private function createUser(string $name, string $email): int
    {
        // DB登録ロジック
        return 1;
    }
}
```

### 2. ルートを追加

```php
// routes/api.php
new Route(
    method: 'POST',
    path: '/api/user',
    routeAction: 'api',
    targetResourceName: '/user/CreateUserApi'
),
```

## レスポンス形式

```php
// 成功時
$this->createResponse(true, ['user_id' => 123]);
// => { "status": 0, "payload": { "user_id": 123 } }

// 失敗時
$this->createResponse(false, ['message' => 'エラーが発生しました']);
// => { "status": 9, "payload": { "message": "エラーが発生しました" } }
```

## バリデーションエラー時

バリデーションエラーは自動的にJSONで返却されます:

```json
{
    "status": 9,
    "payload": {
        "errors": {
            "email": "メールアドレスはメールアドレス形式である必要があります。"
        }
    }
}
```

## プロパティ

| プロパティ | 型 | 説明 |
|-----------|-----|------|
| `$RequestParameterFormat` | array | バリデーションフォーマット |
| `$parameter` | array | バリデーション・型変換済みのパラメータ |

## メソッド

| メソッド | 説明 |
|---------|------|
| `main(): array` | メイン処理（抽象メソッド） |
| `createResponse(bool $succeed, array $payload = []): array` | レスポンス生成 |

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/requests.md` を参照してください。
