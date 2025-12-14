<?php
namespace ayutenn\core\utils;

use ayutenn\core\utils\Uuid;

/**
 * 【概要】
 * ファイル操作クラス
 *
 * 【解説】
 * ファイルのアップロード、削除、一覧取得などを行うクラスです。
 *
 * 【無駄口】
 * とくになし
 *
 */
class FileHandler
{
    /** @var string アップロード先ディレクトリ */
    private $uploadDirectory;

    /** @var int 最大ファイルサイズ（バイト単位） */
    private $maxFileSize; // バイト単位

    /** @var int ディレクトリの最大サイズ（バイト単位） */
    private $maxDirectorySize; // バイト単位

    /** @var array<int, string> 許可するファイル拡張子 */
    private $allowedExtensions;

    /** @var array<int, string> エラーメッセージ */
    private $errors = [];

    /**
     * FileHandlerクラスのコンストラクタ
     *
     * @param string $upload_directory アップロード先ディレクトリ、storageディレクトリからのパス
     * @param int $max_file_size 最大ファイルサイズ（バイト単位）
     * @param int $max_drectory_size ディレクトリの最大サイズ（バイト単位）
     * @param array $allowed_extensions 許可するファイル拡張子
     */
    public function __construct(
        string $upload_directory,
        int $max_file_size = 1000000, // デフォルト1MB
        int $max_drectory_size = 30000000, // デフォルト30MB
        array $allowed_extensions = []
    ) {
        $this->uploadDirectory = rtrim($upload_directory, '/') . '/';
        $this->maxFileSize = $max_file_size;
        $this->maxDirectorySize = $max_drectory_size;
        $this->allowedExtensions = $allowed_extensions;

        // アップロードディレクトリが存在しない場合は作成
        if (!file_exists($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0744, true);
        }
    }

    /**
     * ファイルをアップロードする
     *
     * @param array $file $_FILES['file']のような配列
     * @return bool|string 成功時はファイルパス、失敗時はfalse
     */
    public function uploadFile(array $file): bool|string
    {
        // 複数アップロード対応
        if (is_array($file['name'])) {
            // ファイル名の取得と検証
            $file_name = $file['name'][0];
            $file_tmp_path = $file['tmp_name'][0];
            $file_size = $file['size'][0];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_error = $file['error'][0];
        } else {
            // ファイル名の取得と検証
            $file_name = $file['name'];
            $file_tmp_path = $file['tmp_name'];
            $file_size = $file['size'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_error = $file['error'];
        }

        // エラーチェック
        if ($file_error !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file_error);
            return false;
        }

        // 拡張子チェック
        if (!empty($this->allowedExtensions) && !in_array($file_extension, $this->allowedExtensions)) {
            $this->errors[] = "ファイル拡張子 '{$file_extension}' は許可されていません。";
            return false;
        }

        // ファイルサイズチェック
        if ($file_size > $this->maxFileSize) {
            $this->errors[] = "ファイルサイズが大きすぎます。最大 " . $this->formatSize($this->maxFileSize) . " までです。";
            return false;
        }

        // ディレクトリサイズチェック
        $current_directory_size = $this->getDirectorySize($this->uploadDirectory);
        if (($current_directory_size + $file_size) > $this->maxDirectorySize) {
            $this->errors[] = "容量制限を超えています。残り容量は " . $this->formatSize($this->maxDirectorySize - $current_directory_size) . " です。";
            return false;
        }

        // ファイル名をUUIDで設定
        $file_name_uuid = Uuid::generateUuid7();
        $file_path = $this->uploadDirectory . $file_name_uuid . '.' . $file_extension;

        // ファイルの移動
        if (move_uploaded_file($file_tmp_path, $file_path)) {
            return $file_name_uuid . '.' . $file_extension;
        } else {
            $this->errors[] = "ファイルのアップロードに失敗しました。";
            return false;
        }
    }

    /**
     * ファイルを削除する
     *
     * @param string $filePath 削除するファイルのパス
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public function deleteFile(string $filePath): bool
    {
        // ディレクトリトラバーサル対策: 実パスを取得して検証
        $realPath = realpath($filePath);
        $realUploadDir = realpath($this->uploadDirectory);

        // ファイルが存在しない場合
        if ($realPath === false) {
            $this->errors[] = "指定されたファイルが存在しません。";
            return false;
        }

        // uploadDirectory外へのアクセスを防止
        if ($realUploadDir === false || strpos($realPath, $realUploadDir) !== 0) {
            $this->errors[] = "許可されていないパスへのアクセスです。";
            return false;
        }

        // ファイルの削除
        if (unlink($realPath)) {
            return true;
        } else {
            $this->errors[] = "ファイルの削除に失敗しました。";
            return false;
        }
    }

    /**
     * ディレクトリ内のファイル一覧を取得する
     *
     * name => ファイル名
     * path => ファイルフルパス
     * size => filesize($item_path)
     * formatted_size => MB,GBなど人間が読みやすい単位を付けたsize
     * modified => filemtime($item_path),
     * extension => pathinfo($item_name, PATHINFO_EXTENSION)
     *
     * @param string|null $directory 対象ディレクトリ（nullの場合はuploadDirectory）
     * @return array ファイル情報の配列
     */
    public function listFiles(?string $directory = null): array
    {
        $target_dir = $directory ?? $this->uploadDirectory;
        $files = [];

        if (is_dir($target_dir)) {
            $dir_contents = scandir($target_dir);

            foreach ($dir_contents as $item_name) {
                if ($item_name === '.' || $item_name === '..') {
                    continue;
                }

                $item_path = $target_dir . '/' . $item_name;

                if (is_file($item_path)) {
                    $files[] = [
                        'name' => $item_name,
                        'path' => $item_path,
                        'size' => filesize($item_path),
                        'formatted_size' => $this->formatSize(filesize($item_path)),
                        'modified' => filemtime($item_path),
                        'extension' => pathinfo($item_name, PATHINFO_EXTENSION)
                    ];
                }
            }
        }

        return $files;
    }

    /**
     * ディレクトリのサイズを計算する
     *
     * @param string $directory 対象ディレクトリ
     * @return int ディレクトリサイズ（バイト単位）
     */
    public function getDirectorySize(string $directory): int
    {
        $total_size = 0;

        if (is_dir($directory)) {
            $dir_objects = scandir($directory);

            foreach ($dir_objects as $object_name) {
                if ($object_name === '.' || $object_name === '..') {
                    continue;
                }

                $object_path = $directory . '/' . $object_name;

                if (is_file($object_path)) {
                    $total_size += filesize($object_path);
                } else if (is_dir($object_path)) {
                    $total_size += $this->getDirectorySize($object_path);
                }
            }
        }

        return $total_size;
    }

    /**
     * エラーメッセージを取得する
     *
     * @return array エラーメッセージの配列
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * アップロードエラーコードからメッセージを取得する
     *
     * @param int $error_code PHPのアップロードエラーコード
     * @return string エラーメッセージ
     */
    private function getUploadErrorMessage(int $error_code): string
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return "アップロードされたファイルがシステムの最大サイズ制限を超えています。";
            case UPLOAD_ERR_FORM_SIZE:
                return "アップロードされたファイルが最大サイズ制限を超えています。";
            case UPLOAD_ERR_PARTIAL:
                return "ファイルの一部のみがアップロードされました。";
            case UPLOAD_ERR_NO_FILE:
                return "ファイルがアップロードされませんでした。";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "一時フォルダがありません。";
            case UPLOAD_ERR_CANT_WRITE:
                return "ディスクへの書き込みに失敗しました。";
            case UPLOAD_ERR_EXTENSION:
                return "アップロードが停止されました。";
            default:
                return "不明なエラーが発生しました。";
        }
    }

    /**
     * サイズをフォーマットする（読みやすい形式に変換）
     *
     * @param int $byte_size バイト数
     * @return string フォーマットされたサイズ
     */
    private function formatSize(int $byte_size): string
    {
        $size_units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $byte_size = max($byte_size, 0);
        $power = floor(($byte_size ? log($byte_size) : 0) / log(1024));
        $power = min($power, count($size_units) - 1);

        $byte_size /= pow(1024, $power);

        return round($byte_size, 2) . ' ' . $size_units[$power];
    }
}