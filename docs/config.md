# Config（設定管理）

JSONファイルから設定を読み込み、キーでアクセスを提供する静的クラス。
JSONはネストしないフラットな構造を前提とする。

## 基本的な使い方

```php
use ayutenn\core\config\Config;

// JSONファイルから設定を読み込み
Config::loadFromJson(__DIR__ . '/config/app.json');

// 環境依存の設定を上書きマージ
Config::loadFromJson(__DIR__ . '/config/env.json');

// 設定値を取得
$appName = Config::get('app_name');
$dbHost = Config::get('db_host');
```

## JSONファイル例

### app.json（アプリ共通設定、Git管理）

```json
{
    "app_name": "My Application",
    "app_version": "1.0.0"
}
```

### env.json（環境依存設定、.gitignoreに追加）

```json
{
    "app_debug": true,
    "db_host": "localhost",
    "db_name": "myapp",
    "db_user": "root",
    "db_password": "secret"
}
```

## APIリファレンス

### `loadFromJson(string $path): void`

JSONファイルから設定を読み込む。複数回呼び出した場合、後から読み込んだ設定が既存設定に上書きマージされる。

```php
Config::loadFromJson('/path/to/app.json');
Config::loadFromJson('/path/to/env.json'); // app.jsonの設定に上書きマージ
```

**例外:**
- `InvalidArgumentException`: ファイルが存在しない場合
- `RuntimeException`: JSONのパースに失敗した場合

### `get(string $key): mixed`

設定値を取得する。キーが存在しない場合は例外をスローする。

```php
Config::get('app_name');    // 'My Application'
Config::get('db_host');     // 'localhost'
Config::get('nonexistent'); // InvalidArgumentException
```

**例外:**
- `InvalidArgumentException`: キーが存在しない場合

### `set(string $key, mixed $value): void`

設定値を動的にセットする。主にテスト用。

```php
Config::set('app_debug', true);
```

### `reset(): void`

全ての設定をクリアする。主にテスト用。

```php
Config::reset();
```

## テストでの使い方

```php
use ayutenn\core\config\Config;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset(); // 他のテストの影響を排除
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testSomething(): void
    {
        // テスト用の設定を直接セット
        Config::set('db_host', 'test-db');

        // テスト対象を実行
        $result = $this->service->connect();

        $this->assertTrue($result);
    }
}
```
