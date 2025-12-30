---
description: フラッシュメッセージを追加する
---

# フラッシュメッセージの追加

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
一時的な通知メッセージを表示する際は、FlashMessage クラスを使用してください。

## 基本的な使い方

### コントローラー/API側でメッセージを設定

```php
use ayutenn\core\session\FlashMessage;

// 成功通知
FlashMessage::info('登録が完了しました！');

// 警告・注意喚起
FlashMessage::alert('入力内容を確認してください。');

// エラー通知
FlashMessage::error('システムエラーが発生しました。');
```

### ビュー側でメッセージを表示

```php
<?php
use ayutenn\core\session\FlashMessage;

$messages = FlashMessage::getMessages();
foreach ($messages as $msg): ?>
    <div class="alert alert-<?= $msg['alert_type'] ?>">
        <?= htmlspecialchars($msg['text']) ?>
    </div>
<?php endforeach; ?>
```

## メッセージ種別

| メソッド | 用途 | 例 |
|---------|------|-----|
| `info()` | 正常処理の完了通知 | ログインに成功しました！ |
| `alert()` | ユーザーへの注意喚起 | 未入力の欄があります！ |
| `error()` | 想定外のエラー通知 | DB接続に失敗しました！ |

## メッセージ構造

```php
[
    'alert_type' => 'info',    // info, alert, error
    'alert_id' => '...',       // ユニークID
    'text' => 'メッセージ本文'
]
```

## 注意事項

- メッセージは `getMessages()` で取得すると自動的に削除されます
- セッションを使用するため、`session_start()` が必要です

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/session.md` を参照してください。
