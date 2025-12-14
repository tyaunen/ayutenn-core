# Utils（ユーティリティ）

汎用的なユーティリティクラス群。CSRFトークン管理、ファイル操作、ロギング、リダイレクト等を提供する。

---

## CsrfTokenManager

セッションベースのCSRFトークン管理クラス。フォームのCSRF対策に使用する。

### 基本的な使い方

```php
use ayutenn\core\utils\CsrfTokenManager;

$manager = new CsrfTokenManager();

// トークンを取得（フォームに埋め込む）
$token = $manager->getToken();

// POSTデータのトークンを検証
if ($manager->validateToken($_POST['csrf_token'])) {
    // 正常なリクエスト
}
```

### APIリファレンス

#### `getToken(): string`

CSRFトークンを取得する。トークンが未生成または期限切れの場合は新規生成される。

#### `validateToken(string $token): bool`

トークンの検証を行う。有効期限は12時間。

---

## DiscordWebhook

Discord Webhook送信用クラス。

### 基本的な使い方

```php
use ayutenn\core\utils\DiscordWebhook;

// embedsデータを作成（discohook.appで生成可能）
$embeds = [
    [
        'title' => '通知タイトル',
        'description' => 'メッセージ本文',
        'color' => 5814783
    ]
];

$webhook = new DiscordWebhook($embeds);
$result = $webhook->sendWebhook('https://discord.com/api/webhooks/...');

if ($result['success']) {
    echo '送信成功';
} else {
    echo 'エラー: ' . $result['error'];
}
```

### 必要な設定（env.json）

```json
{
    "DISCORD_WEBHOOK_USER_NAME": "BotName",
    "DISCORD_WEBHOOK_AVATAR_ICON": "https://example.com/avatar.png"
}
```

### APIリファレンス

#### `__construct(array $embeds)`

embedsデータを受け取りインスタンスを生成する。

#### `sendWebhook(string $webhookurl): array`

Webhookを送信する。戻り値は以下の構造：

| キー | 型 | 説明 |
|------|-----|------|
| `success` | bool | 送信成功かどうか |
| `response` | string\|false | サーバーからの応答 |
| `http_code` | int | HTTPステータスコード |
| `error` | string | cURLエラーメッセージ（エラー時のみ） |

---

## FileHandler

ファイルアップロード・削除・一覧取得を行うクラス。

### 基本的な使い方

```php
use ayutenn\core\utils\FileHandler;

$handler = new FileHandler(
    '/path/to/uploads/',  // アップロード先ディレクトリ
    1000000,              // 最大ファイルサイズ（1MB）
    30000000,             // ディレクトリ最大サイズ（30MB）
    ['jpg', 'png', 'pdf'] // 許可する拡張子
);

// ファイルアップロード
$filename = $handler->uploadFile($_FILES['file']);
if ($filename === false) {
    $errors = $handler->getErrors();
}

// ファイル一覧取得
$files = $handler->listFiles();

// ファイル削除
$handler->deleteFile('/path/to/uploads/file.jpg');
```

### APIリファレンス

#### `uploadFile(array $file): string|false`

ファイルをアップロードする。成功時はUUID形式のファイル名を返す。

#### `deleteFile(string $filePath): bool`

ファイルを削除する。ディレクトリトラバーサル対策済み。

#### `listFiles(?string $directory = null): array`

ディレクトリ内のファイル一覧を取得する。

#### `getDirectorySize(string $directory): int`

ディレクトリの合計サイズ（バイト）を取得する。

#### `getErrors(): array`

エラーメッセージの配列を取得する。

---

## Logger

PSR-3準拠のカスタムロガー。日付ごとにファイルを分けて保存する。

### 基本的な使い方

```php
use ayutenn\core\utils\Logger;

// ロガーを初期化（パスごとに異なるインスタンスを取得可能）
$log = Logger::setup('/path/to/logs/');

// 各レベルでログ出力
$log->debug('デバッグ情報');
$log->info('通常ログ');
$log->warning('警告');
$log->error('エラー発生');       // スタックトレース付き
$log->critical('致命的エラー');  // スタックトレース付き
$log->emergency('システム停止'); // スタックトレース付き

// コンテキスト情報を追加
$log->info('ユーザーログイン', ['user_id' => 123]);
```

### ログレベル定数

| 定数 | 値 | 用途 |
|------|-----|------|
| `DEBUG` | 100 | デバッグ情報 |
| `INFO` | 200 | 一般情報 |
| `NOTICE` | 250 | 注意情報 |
| `WARNING` | 300 | 警告 |
| `ERROR` | 400 | エラー |
| `CRITICAL` | 500 | 致命的エラー |
| `ALERT` | 550 | 即時対応が必要 |
| `EMERGENCY` | 600 | システム使用不能 |

### ログファイル形式

```
[2024-01-15 10:30:45][INFO]> ユーザーログイン : {"user_id":123}
```

---

## Redirect

リダイレクト・レスポンス処理クラス。テストモード対応。

### 基本的な使い方

```php
use ayutenn\core\utils\Redirect;

// 通常のリダイレクト
Redirect::redirect('/dashboard');

// GETパラメータ付きリダイレクト
Redirect::redirect('/search', ['q' => 'keyword', 'page' => 1]);

// JSONレスポンス（API用）
Redirect::apiResponse(['status' => 1, 'data' => $result]);

// PHPファイルを読み込んで終了
Redirect::show('/path/to/template.php');
```

### テストでの使い方

```php
Redirect::$isTest = true;

// リダイレクト実行後
Redirect::redirect('/dashboard');
$this->assertEquals('/dashboard', Redirect::$lastRedirectUrl);

// APIレスポンス実行後
Redirect::apiResponse(['status' => 1]);
$this->assertEquals(['status' => 1], Redirect::$lastApiResponse);
```

---

## Uuid

UUIDv7生成クラス。タイムスタンプベースでソート可能。

### 基本的な使い方

```php
use ayutenn\core\utils\Uuid;

$uuid = Uuid::generateUuid7();
// 例: "01934abc-def0-7123-8456-789abcdef012"
```

### 特徴

- **タイムスタンプベース**: 生成順にソート可能
- **RFC 9562準拠**: バージョン7、バリアントビット正しく設定
- **用途**: URLに公開するID、インサート前にPKが必要な場合など
