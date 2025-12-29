---
description: ファイルアップロード機能を追加する
---

# ファイルアップロードの追加

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
ファイルアップロード機能を追加する際は、FileHandler クラスを使用してください。

## 手順

### 1. アップロードフォームを作成

```php
<form method="POST" action="/upload" enctype="multipart/form-data">
    <input type="file" name="file">
    <button type="submit">アップロード</button>
</form>
```

### 2. コントローラーでアップロード処理

```php
use ayutenn\core\utils\FileHandler;
use ayutenn\core\session\FlashMessage;

class UploadController extends Controller
{
    protected function main(): void
    {
        $handler = new FileHandler(
            __DIR__ . '/../uploads/',  // アップロード先ディレクトリ
            1000000,                   // 最大ファイルサイズ（1MB）
            30000000,                  // ディレクトリ最大サイズ（30MB）
            ['jpg', 'png', 'gif', 'pdf'] // 許可する拡張子
        );

        $filename = $handler->uploadFile($_FILES['file']);

        if ($filename === false) {
            $errors = $handler->getErrors();
            FlashMessage::alert(implode(' ', $errors));
            $this->redirect('/upload');
            return;
        }

        FlashMessage::info('ファイルをアップロードしました: ' . $filename);
        $this->redirect('/files');
    }
}
```

## FileHandler コンストラクタ

```php
new FileHandler(
    string $uploadDirectory,    // アップロード先ディレクトリ（絶対パス）
    int $maxFileSize,           // 最大ファイルサイズ（バイト）
    int $maxDirectorySize,      // ディレクトリ最大サイズ（バイト）
    array $allowedExtensions    // 許可する拡張子の配列
)
```

## API

| メソッド | 説明 |
|---------|------|
| `uploadFile(array $file): string\|false` | アップロード（成功時はUUID形式のファイル名） |
| `deleteFile(string $filePath): bool` | ファイル削除（ディレクトリトラバーサル対策済み） |
| `listFiles(?string $directory = null): array` | ファイル一覧取得 |
| `getDirectorySize(string $directory): int` | ディレクトリサイズ取得 |
| `getErrors(): array` | エラーメッセージ取得 |

## ファイル一覧・削除の例

```php
$handler = new FileHandler(__DIR__ . '/../uploads/', 1000000, 30000000, []);

// ファイル一覧
$files = $handler->listFiles();
foreach ($files as $file) {
    echo $file . "\n";
}

// ファイル削除
$handler->deleteFile(__DIR__ . '/../uploads/uuid-filename.jpg');
```

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/utils.md` を参照してください。
