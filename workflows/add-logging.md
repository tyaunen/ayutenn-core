---
description: ログ出力機能を追加する
---

# ログ出力の追加

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
ログ出力を追加する際は、Logger クラスを使用してください。

## 基本的な使い方

```php
use ayutenn\core\utils\Logger;

// ロガーを初期化
$log = Logger::setup(__DIR__ . '/../logs/');

// 各レベルでログ出力
$log->debug('デバッグ情報');
$log->info('通常ログ');
$log->warning('警告');
$log->error('エラー発生');       // スタックトレース付き
$log->critical('致命的エラー');  // スタックトレース付き
$log->emergency('システム停止'); // スタックトレース付き

// コンテキスト情報を追加
$log->info('ユーザーログイン', ['user_id' => 123, 'ip' => $_SERVER['REMOTE_ADDR']]);
```

## ログレベル

| レベル | 用途 |
|--------|------|
| `debug` | デバッグ情報 |
| `info` | 一般情報 |
| `notice` | 注意情報 |
| `warning` | 警告 |
| `error` | エラー（スタックトレース付き） |
| `critical` | 致命的エラー（スタックトレース付き） |
| `alert` | 即時対応が必要 |
| `emergency` | システム使用不能（スタックトレース付き） |

## ログファイル形式

- ファイル名: `YYYY-MM-DD.log`（日付ごとに分割）
- 出力形式:
```
[2024-01-15 10:30:45][INFO]> ユーザーログイン : {"user_id":123,"ip":"192.168.1.1"}
```

## 推奨使用パターン

```php
// bootstrap.php でロガーを初期化
$logger = Logger::setup(__DIR__ . '/logs/');

// コントローラーやAPIで使用
class UserController extends Controller
{
    protected function main(): void
    {
        $log = Logger::setup(__DIR__ . '/../logs/');

        try {
            // 処理
            $log->info('ユーザー作成成功', ['user_id' => $userId]);
        } catch (Exception $e) {
            $log->error('ユーザー作成失敗: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/utils.md` を参照してください。
