---
description: プロジェクトの初期設定（Bootstrap）を行う
---

# プロジェクトの初期設定

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
新規プロジェクトのセットアップ時は、以下の手順に従ってください。

## 手順

### 1. bootstrap.php を作成

プロジェクトルートに `bootstrap.php` を作成し、フレームワークを初期化します。

```php
<?php
// bootstrap.php

require_once __DIR__ . '/vendor/autoload.php';

use ayutenn\core\FrameworkPaths;
use ayutenn\core\config\Config;

// パス設定（必須）
FrameworkPaths::init([
    'controllerDir' => __DIR__ . '/controllers',
    'viewDir' => __DIR__ . '/views',
    'apiDir' => __DIR__ . '/api',
    'pathRoot' => '/myapp',  // URLのベースパス
    'validationRulesDir' => __DIR__ . '/rules',
    'notFoundView' => '404.php', // オプション
]);

// 環境依存設定（DB接続など）
Config::loadFromJson(__DIR__ . '/config/env.json');
```

### 2. 必要なディレクトリを作成

```
my-project/
├── bootstrap.php
├── public/
│   └── index.php
├── controllers/       # コントローラー
├── views/             # ビュー
├── api/               # API
├── rules/             # バリデーションルール
├── routes/            # ルート定義
├── tables/            # テーブル定義（マイグレーション用）
├── migrations/        # 生成されたSQLファイル
├── config/
│   ├── app.json       # アプリ共通設定（Git管理）
│   └── env.json       # 環境依存設定（.gitignoreに追加）
└── vendor/
```

### 3. public/index.php を作成

```php
<?php
// public/index.php

require_once __DIR__ . '/../bootstrap.php';

use ayutenn\core\routing\Router;

session_start();

$router = new Router(__DIR__ . '/../routes', '/myapp');
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
```

### 4. 設定ファイルを作成

#### config/app.json（Git管理）
```json
{
    "app_name": "My Application",
    "app_version": "1.0.0"
}
```

#### config/env.json（.gitignoreに追加）
```json
{
    "PDO_DSN": "mysql:host=localhost;dbname=myapp;charset=utf8mb4",
    "PDO_USERNAME": "root",
    "PDO_PASSWORD": "",
    "app_debug": true
}
```

## FrameworkPaths 設定キー

| キー | 説明 | 必須 |
|:-----|:-----|:----:|
| `controllerDir` | コントローラーファイルのディレクトリ | ✓ |
| `viewDir` | ビューファイルのディレクトリ | ✓ |
| `apiDir` | APIファイルのディレクトリ | ✓ |
| `pathRoot` | URLのベースパス（例: `/myapp`） | ✓ |
| `validationRulesDir` | バリデーションルールファイルのディレクトリ | ✓ |
| `notFoundView` | 404ページのビューファイル名 | - |

## 詳細ドキュメント

- パス設定: `vendor/tyaunen/ayutenn-core/docs/framework_paths.md`
- Config設定: `vendor/tyaunen/ayutenn-core/docs/config.md`
