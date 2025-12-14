# FrameworkPaths（パス設定）

フレームワークが使用するディレクトリパスを一元管理する静的クラス。
このフレームワークを利用する親プロジェクトは、bootstrap 処理で `FrameworkPaths::init()` を呼び出してパスを設定する必要がある。

## 基本的な使い方

```php
use ayutenn\core\FrameworkPaths;

// bootstrap.php（親プロジェクト）
FrameworkPaths::init([
    'controllerDir' => __DIR__ . '/controllers',
    'viewDir' => __DIR__ . '/views',
    'apiDir' => __DIR__ . '/api',
    'pathRoot' => '/myapp',
    'validationRulesDir' => __DIR__ . '/rules',
    'notFoundView' => '404.php', // オプション
]);

// フレームワーク内部で使用
$viewDir = FrameworkPaths::getViewDir();
```

## 設定キー

| キー | 説明 | 必須 |
|:-----|:-----|:----:|
| `controllerDir` | コントローラーファイルのディレクトリ絶対パス | ✓ |
| `viewDir` | ビューファイルのディレクトリ絶対パス | ✓ |
| `apiDir` | APIファイルのディレクトリ絶対パス | ✓ |
| `pathRoot` | URLのベースパス（例: `/myapp`） | ✓ |
| `validationRulesDir` | バリデーションルールファイルのディレクトリ絶対パス | ✓ |
| `notFoundView` | 404ページのビューファイル名 | - |

## APIリファレンス

### `init(array $config): void`

パス設定を初期化する。必須キーが不足している場合は例外をスローする。

```php
FrameworkPaths::init([
    'controllerDir' => __DIR__ . '/controllers',
    'viewDir' => __DIR__ . '/views',
    'apiDir' => __DIR__ . '/api',
    'pathRoot' => '/myapp',
    'validationRulesDir' => __DIR__ . '/rules',
]);
```

**例外:**
- `InvalidArgumentException`: 必須キーが見つからない場合

### `isInitialized(): bool`

初期化済みかどうかを確認する。

```php
if (!FrameworkPaths::isInitialized()) {
    // 初期化処理
}
```

### Getter メソッド

各パスを取得する。未初期化の場合は例外をスローする。

```php
FrameworkPaths::getControllerDir();     // '/path/to/controllers'
FrameworkPaths::getViewDir();           // '/path/to/views'
FrameworkPaths::getApiDir();            // '/path/to/api'
FrameworkPaths::getPathRoot();          // '/myapp'
FrameworkPaths::getValidationRulesDir(); // '/path/to/rules'
FrameworkPaths::getNotFoundView();      // '404.php' または null
```

**例外:**
- `RuntimeException`: 未初期化の場合

### `reset(): void`

設定をクリアする（テスト用）。

```php
FrameworkPaths::reset();
```

## 推奨プロジェクト構成

```
my-project/
├── bootstrap.php      # FrameworkPaths::init() をここで呼び出す
├── public/
│   └── index.php      # require __DIR__ . '/../bootstrap.php';
├── controllers/       # controllerDir
├── views/             # viewDir
├── api/               # apiDir
├── rules/             # validationRulesDir
└── vendor/
```

## テストでの使い方

```php
use ayutenn\core\FrameworkPaths;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        FrameworkPaths::reset();
        FrameworkPaths::init([
            'controllerDir' => __DIR__ . '/fixtures/controllers',
            'viewDir' => __DIR__ . '/fixtures/views',
            'apiDir' => __DIR__ . '/fixtures/api',
            'pathRoot' => '/test',
            'validationRulesDir' => __DIR__ . '/fixtures/rules',
        ]);
    }

    protected function tearDown(): void
    {
        FrameworkPaths::reset();
    }
}
```

## 環境依存設定について

> [!NOTE]
> DB接続情報（PDO_DSN等）やWebhook設定など、環境によって異なる値は引き続き `Config` クラスで管理してください。`FrameworkPaths` はディレクトリパスの設定のみを扱います。

```php
// bootstrap.php
use ayutenn\core\FrameworkPaths;
use ayutenn\core\config\Config;

// パス設定
FrameworkPaths::init([...]);

// 環境依存設定（DB接続など）
Config::loadFromJson(__DIR__ . '/config/env.json');
```
