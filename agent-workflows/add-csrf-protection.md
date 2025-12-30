---
description: CSRF保護を追加する
---

# CSRF保護の追加

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
フォームにCSRF保護を追加する際は、CsrfTokenManager クラスを使用してください。

## 手順

### 1. フォームにトークンを埋め込む

```php
<?php
use ayutenn\core\utils\CsrfTokenManager;

$csrf = new CsrfTokenManager();
?>

<form method="POST" action="/submit">
    <input type="hidden" name="csrf_token" value="<?= $csrf->getToken() ?>">

    <!-- 他のフォーム要素 -->
    <input type="text" name="username">
    <button type="submit">送信</button>
</form>
```

### 2. コントローラー/APIでトークンを検証

```php
use ayutenn\core\utils\CsrfTokenManager;
use ayutenn\core\session\FlashMessage;

class SubmitController extends Controller
{
    protected function main(): void
    {
        $csrf = new CsrfTokenManager();

        if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
            FlashMessage::error('不正なリクエストです。');
            $this->redirect('/form');
            return;
        }

        // 正常な処理を続行
    }
}
```

## API

| メソッド | 説明 |
|---------|------|
| `getToken(): string` | CSRFトークンを取得（未生成時は自動生成） |
| `validateToken(string $token): bool` | トークンを検証（有効期限: 12時間） |

## 注意事項

- セッションを使用するため、`session_start()` が必要です
- トークンの有効期限は12時間です
- 検証に失敗した場合は必ず処理を中断してください

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/utils.md` を参照してください。
