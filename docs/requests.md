# Requests（リクエスト処理）

APIとWebフォームのリクエスト処理を担う抽象クラス群。バリデーション、リダイレクト、セッション管理を提供する。

---

## Api

APIエンドポイントの抽象基底クラス。JSONレスポンスを返すAPIを実装する際に継承する。

### 基本的な使い方

```php
use ayutenn\core\requests\Api;

class UserApi extends Api
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

        return $this->createResponse(true, ['message' => "Hello, {$name}!"]);
    }
}

// 実行
$api = new UserApi();
$api->run();
```

### プロパティ

| プロパティ | 型 | 説明 |
|-----------|-----|------|
| `$RequestParameterFormat` | array | バリデーションフォーマット（docs/validation.md参照） |
| `$parameter` | array | バリデーション・型変換済みのパラメータ |

### メソッド

#### `run(): void`

API実行のエントリーポイント。以下の処理を行う：
1. GET/POSTパラメータを取得
2. バリデーションを実行
3. エラー時はエラーレスポンスを返却
4. 成功時は`main()`を実行

#### `main(): array`（abstract）

APIのメイン処理。`createResponse()`で戻り値を生成する。

#### `createResponse(bool $succeed, array $payload = []): array`

レスポンス配列を生成する。

| 引数 | 説明 |
|------|------|
| `$succeed` | 成功時 `true`、失敗時 `false` |
| `$payload` | レスポンスデータ |

**戻り値の構造:**
```php
[
    'status' => 0,      // 成功時: 0, 失敗時: 9
    'payload' => [...]  // データ
]
```

### 必要な設定（Config）

```php
Config::set('VALIDATION_RULES_DIR', '/path/to/rules');
```

---

## Controller

Webフォーム処理の抽象基底クラス。フォームからのPOSTを処理し、リダイレクトで結果を通知する。

### 基本的な使い方

```php
use ayutenn\core\requests\Controller;

class LoginController extends Controller
{
    protected array $RequestParameterFormat = [
        'email' => [
            'name' => 'メールアドレス',
            'format' => ['type' => 'string', 'conditions' => ['email']],
            'require' => true,
        ],
        'password' => [
            'name' => 'パスワード',
            'format' => ['type' => 'string', 'min_length' => 8],
            'require' => true,
        ],
    ];

    protected string $redirectUrlWhenError = '/login';
    protected bool $remainRequestParameter = true;

    protected function main(): void
    {
        $email = $this->parameter['email'];
        $password = $this->parameter['password'];

        // ログイン処理...

        Controller::unsetRemain();  // 入力保存をクリア
        $this->redirect('/dashboard');
    }
}

// 実行
$controller = new LoginController();
$controller->run();
```

### プロパティ

| プロパティ | 型 | デフォルト | 説明 |
|-----------|-----|------------|------|
| `$RequestParameterFormat` | array | `[]` | バリデーションフォーマット |
| `$parameter` | array | `[]` | バリデーション済みパラメータ |
| `$redirectUrlWhenError` | string | `'/error'` | エラー時のリダイレクト先 |
| `$remainRequestParameter` | bool | `false` | 入力値をセッションに保存するか |
| `$keepGetParameter` | bool | `false` | リダイレクト時にGETパラメータを保持するか |

### メソッド

#### `run(): void`

コントローラ実行のエントリーポイント。

#### `main(): void`（abstract）

メイン処理。バリデーション成功後に実行される。

#### `redirect(string $path, array $parameter = []): void`

指定パスにリダイレクトする。`PATH_ROOT`設定を基にフルURLを構築。

#### `getRemainRequestParameter(): array`（static）

セッションに保存された入力値を取得する。フォームの再表示時に使用。

```php
// view側での使用例
$remain = LoginController::getRemainRequestParameter();
$email = $remain['email'] ?? '';
```

#### `unsetRemain(): bool`（static）

セッション保存された入力値を削除する。処理成功後に呼び出す。

### 必要な設定（Config）

```php
Config::set('VALIDATION_RULES_DIR', '/path/to/rules');
Config::set('PATH_ROOT', '/myapp');  // アプリケーションルート
```

---

## 関連ドキュメント

- [Validation（バリデーション）](validation.md) - `RequestParameterFormat`の書式
- [Session（セッション管理）](session.md) - `FlashMessage`クラス
