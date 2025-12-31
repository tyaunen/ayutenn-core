---
description: URLルーティングを定義する
---

# ルーティングの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
ルーティングを追加する際は、以下の手順に従ってください。

## 手順

### 1. ルート定義ファイルを編集

`routes/` ディレクトリ内の PHP ファイルにルートを追加します。

```php
// routes/web.php
use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;

return [
    // ビュールート（HTMLページ表示）
    new Route(
        method: 'GET',
        path: '/top',
        routeAction: 'view',
        targetResourceName: '/pages/top'
    ),

    // コントローラールート（フォーム処理）
    new Route(
        method: 'POST',
        path: '/login',
        routeAction: 'controller',
        targetResourceName: '/auth/LoginController'
    ),

    // APIルート（JSON応答）
    new Route(
        method: 'POST',
        path: '/api/user',
        routeAction: 'api',
        targetResourceName: '/user/CreateUserApi'
    ),

    // リダイレクトルート
    new Route(
        method: 'GET',
        path: '/old-page',
        routeAction: 'redirect',
        targetResourceName: '/new-page'
    ),
];
```

## routeAction の種類

| 値 | 説明 |
|----|------|
| `view` | PHPビューファイルを表示 |
| `controller` | Controller を継承したクラスの `run()` を実行 |
| `api` | Api を継承したクラスの `run()` を実行 |
| `redirect` | 指定パスにリダイレクト（302） |

## ルートグループ（共通ミドルウェア）

```php
use ayutenn\core\routing\RouteGroup;
use ayutenn\core\routing\Route;

return [
    new RouteGroup(
        group: '/admin',
        middleware: [new AuthMiddleware()],
        routes: [
            new Route('GET', '/dashboard', 'view', '/admin/dashboard'),
            new Route('GET', '/users', 'view', '/admin/users'),
        ]
    ),
];
```

## ミドルウェアの実装

```php
use ayutenn\core\routing\Middleware;
use ayutenn\core\session\FlashMessage;

class AuthMiddleware extends Middleware
{
    public function __construct()
    {
        parent::__construct(
            routeAction: 'redirect',
            targetResourceName: '/login'
        );
    }

    public function handle(): void
    {
        if (!$this->isLoggedIn()) {
            FlashMessage::alert('ログインが必要です。');
        }
    }

    public function shouldOverride(): bool
    {
        return !$this->isLoggedIn();
    }

    private function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
```

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/routing.md` を参照してください。
