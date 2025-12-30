---
description: Webフォーム処理用コントローラーを作成する
---

# コントローラーの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
Webフォーム処理（POST）を追加する際は、以下の手順に従ってください。

## 手順

### 1. Controllerクラスを作成

`controllers/` ディレクトリに Controller を継承したクラスを作成します。

```php
// controllers/auth/LoginController.php
<?php

use ayutenn\core\requests\Controller;
use ayutenn\core\session\FlashMessage;

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
        if (!$this->authenticate($email, $password)) {
            FlashMessage::alert('メールアドレスまたはパスワードが正しくありません。');
            $this->redirect('/login');
            return;
        }

        Controller::unsetRemain();  // 入力保存をクリア
        FlashMessage::info('ログインしました。');

        // PRGパターンを使用します。
        // ビューを表示する時でもrequireではなくredirectを行います。
        $this->redirect('/dashboard');
    }

    private function authenticate(string $email, string $password): bool
    {
        // 認証ロジック
        return true;
    }
}
```

### 2. ルートを追加

```php
// routes/web.php
new Route(
    method: 'POST',
    path: '/login',
    routeAction: 'controller',
    targetResourceName: '/auth/LoginController'
),
```

## プロパティ

| プロパティ | 型 | デフォルト | 説明 |
|-----------|-----|------------|------|
| `$RequestParameterFormat` | array | `[]` | バリデーションフォーマット |
| `$parameter` | array | `[]` | バリデーション済みパラメータ |
| `$redirectUrlWhenError` | string | `'/error'` | エラー時のリダイレクト先 |
| `$remainRequestParameter` | bool | `false` | 入力値をセッションに保存するか |
| `$keepGetParameter` | bool | `false` | リダイレクト時にGETパラメータを保持するか |

## メソッド

| メソッド | 説明 |
|---------|------|
| `main(): void` | メイン処理（抽象メソッド） |
| `redirect(string $path, array $parameter = []): void` | リダイレクト |
| `getRemainRequestParameter(): array` | セッション保存された入力値を取得 |
| `unsetRemain(): bool` | 入力保存をクリア |

## ビューでの入力値復元

```php
// views/login.php
$remain = LoginController::getRemainRequestParameter();
$email = $remain['email'] ?? '';
?>
<input type="email" name="email" value="<?= htmlspecialchars($email) ?>">
```

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/requests.md` を参照してください。