# Session（セッション管理）

セッションベースのフラッシュメッセージ管理を提供する。

---

## FlashMessage

フラッシュメッセージをセッションに格納・取得するクラス。
一度取得すると自動的に削除される一時的な通知メッセージを管理する。

### 基本的な使い方

```php
use ayutenn\core\session\FlashMessage;

// メッセージ設定
FlashMessage::info('登録が完了しました！');
FlashMessage::alert('入力内容を確認してください。');
FlashMessage::error('システムエラーが発生しました。');

// メッセージ取得（取得後に自動削除される）
$messages = FlashMessage::getMessages();
foreach ($messages as $msg) {
    echo "[{$msg['alert_type']}] {$msg['text']}";
}
```

### メッセージ種別

| 定数 | 用途 |
|------|------|
| `INFO` | 正常処理の完了通知（例：ログインに成功しました！） |
| `ALERT` | ユーザーへの注意喚起（例：未入力の欄があります！） |
| `ERROR` | 想定外のエラー通知（例：DB接続に失敗しました！） |

### メソッド

| メソッド | 説明 |
|----------|------|
| `info(string $text)` | INFO通知を作成 |
| `alert(string $text)` | ALERT通知を作成 |
| `error(string $text)` | ERROR通知を作成 |
| `getMessages(): array` | 全メッセージ取得（取得後削除） |

### メッセージ構造

```php
[
    'alert_type' => 'info',  // info, alert, error
    'alert_id' => '...',     // アクセスキー
    'text' => 'メッセージ本文'
]
```

### View側での使用例

```php
<?php
$messages = FlashMessage::getMessages();
foreach ($messages as $msg): ?>
    <div class="alert alert-<?= $msg['alert_type'] ?>">
        <?= htmlspecialchars($msg['text']) ?>
    </div>
<?php endforeach; ?>
```
