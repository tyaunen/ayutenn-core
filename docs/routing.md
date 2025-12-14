# Routing（ルーティング）

リクエストURLに基づいて適切なコントローラー、ビュー、APIを実行するルーティングシステム。

---

## 基本的な使い方

### ルーターの初期化

```php
use ayutenn\core\routing\Router;

// アプリケーションのエントリーポイントで初期化
$router = new Router(
    __DIR__ . '/routes',   // ルート定義ファイルのディレクトリ
    '/myapp'               // URLプレフィックス
);

// リクエストをディスパッチ
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
```

### ルート定義ファイル

`routes/` ディレクトリ内の `.php` ファイルは自動的に読み込まれる。

```php
// routes/web.php
use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;

return [
    // シンプルなビュールート
    new Route(
        method: 'GET',
        path: '/top',
        routeAction: 'view',
        targetResourceName: '/pages/top'
    ),

    // コントローラールート
    new Route(
        method: 'POST',
        path: '/login',
        routeAction: 'controller',
        targetResourceName: '/auth/LoginController'
    ),

    // APIルート
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

---

## Route クラス

### コンストラクタ引数

| 引数 | 型 | 説明 |
|------|-----|------|
| `$method` | string | HTTPメソッド（GET, POST など） |
| `$path` | string | URLパス |
| `$routeAction` | string | アクションタイプ |
| `$targetResourceName` | string | 対象リソース名 |
| `$middleware` | array | ミドルウェア配列 |

### routeAction の種類

| 値 | 説明 |
|----|------|
| `view` | PHPビューファイルを表示 |
| `controller` | Controller を継承したクラスの `run()` を実行 |
| `api` | Api を継承したクラスの `run()` を実行 |
| `redirect` | 指定パスにリダイレクト（302） |

---

## RouteGroup クラス

ルートをグループ化し、共通のミドルウェアやパスプレフィックスを適用する。

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

---

## Middleware クラス

ルート実行前に処理を行う抽象クラス。

### 実装例

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

    /**
     * 副作用処理（フラッシュメッセージ等）
     */
    public function handle(): void
    {
        if (!$this->isLoggedIn()) {
            FlashMessage::alert('ログインが必要です。');
        }
    }

    /**
     * ルート上書き判定
     */
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

### メソッド

| メソッド | 説明 |
|----------|------|
| `handle(): void` | 副作用処理を実行（オーバーライド可能） |
| `shouldOverride(): bool` | trueを返すとルート設定を上書き（必須） |

---

## 必要な設定（Config）

```php
// アプリケーション設定
Config::get('CONTROLLER_DIR', '/controllers');
Config::get('VIEW_DIR', '/views');
Config::get('API_DIR', '/api');
Config::get('PATH_ROOT', '/myapp');
Config::get('404_VIEW_FILE', '/errors/404.php');
```

---

## 404エラー処理

マッチするルートがない場合、`Route::showNotFoundPage()` が呼ばれる。
`404_VIEW_FILE` で指定したビューファイルが表示される。

```php
// 手動で404を表示する場合
Route::showNotFoundPage();
```
