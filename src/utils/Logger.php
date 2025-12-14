<?php
namespace ayutenn\core\utils;

use ayutenn\core\config\Config;

/**
 * 【概要】
 * ロガークラス
 *
 * 【解説】
 * Monologっぽいカスタムロガークラスです。
 * 日ごとにファイルを分けてログを保存し、最大ファイル数を超えると古いファイルを削除します。
 * ログレベルはPSR-3に準拠した定数を利用しています。
 *
 * ログの保存先は、storage/logs{指定ファイルパス}です。
 *
 * 使用方法: Logger::$log->info('infoログ');
 *
 * 【無駄口】
 * 別にMonolog使えばよくね？
 *
 */
class Logger
{
    /** @var array<string, Logger> 複数ログインスタンスを管理 */
    private static array $instances = [];

    /** @var string ログ保存ディレクトリ */
    private string $logPath;

    /** @var int 保存する最大ファイル数 */
    private int $maxFiles = 1860; // 31 * 12 * 5 = 5年分

    /** @var int 出力する最低ログレベル */
    private int $minLevel = 100; // DEBUGレベル相当

    // ログレベル定数（PSR-3準拠）
    public const DEBUG     = 100;
    public const INFO      = 200;
    public const NOTICE    = 250;
    public const WARNING   = 300;
    public const ERROR     = 400;
    public const CRITICAL  = 500;
    public const ALERT     = 550;
    public const EMERGENCY = 600;

    /**
     * ログレベル名マッピング
     *
     * @var array<int, string>
     */
    private array $levelNames = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY'
    ];

    /**
     *
     * @param string $log_path storageディレクトリ内のログのパス
     */
    private function __construct(?string $log_path = null)
    {
        $this->logPath = $log_path;

        // 指定がファイルならエラー
        if (is_file($this->logPath)) {
            throw new \Exception("ログ保存パスはディレクトリである必要があります: {$this->logPath}");
        }

        $this->logPath = rtrim($log_path, '/') . '/';

        // ディレクトリがなければ作成
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        } else {
            // 書き込み権限がなければ例外
            if (!is_writable($this->logPath)) {
                throw new \Exception("ログ保存ディレクトリに書き込み権限がありません: {$this->logPath}");
            }
        }
    }

    /**
     * ログインスタンスを取得（複数インスタンスをサポート）
     *
     * @param string $log_name ログ名（省略時は "system"）
     * @return Logger
     */
    public static function setup(string $log_name = 'system'): Logger
    {
        if (!isset(self::$instances[$log_name])) {
            self::$instances[$log_name] = new self($log_name);
        }
        return self::$instances[$log_name];
    }

    /**
     * 現在の日付に基づいたログファイル名を取得
     *
     * @return string ログファイルのパス
     */
    private function getLogFilename(): string
    {
        $date = date('Y-m-d');
        return $this->logPath . 'log_' . $date . '.txt';
    }

    /**
     * ログローテーション処理（古いログを削除）
     *
     * @return void
     */
    private function rotateFiles(): void
    {
        $log_files = glob($this->logPath . 'log_*.txt') ?: [];

        if (count($log_files) > $this->maxFiles) {
            // ファイル名でソートして古い順に
            sort($log_files);

            // 超過分を削除
            $files_to_delete = count($log_files) - $this->maxFiles;
            for ($i = 0; $i < $files_to_delete; $i++) {
                @unlink($log_files[$i]);
            }
        }
    }

    /**
     * ログメッセージを書き込む
     *
     * @param int $level ログレベル
     * @param string $message メッセージ
     * @param array<string, mixed> $context 追加コンテキスト情報
     * @param bool $include_trace エラーレベルの場合にスタックトレースを含めるか
     * @return void
     */
    private function writeLog(int $level, string $message, array $context = [], bool $include_trace = false): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $log_file = $this->getLogFilename();
        $this->rotateFiles();

        $time_stamp = date('Y-m-d H:i:s');
        $level_name = $this->levelNames[$level] ?? 'UNKNOWN';

        // コンテキストを文字列化
        $context_str = empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE);

        $log_entry = "[{$time_stamp}][{$level_name}]> {$message} : {$context_str}\n";

        if ($include_trace) {
            $log_entry .= $this->formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)). "\n";
        }

        // ファイルに書き込み
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * スタックトレースを整形
     *
     * @param array<int, array<string, mixed>> $trace debug_backtrace() の結果
     * @return string 整形済みスタックトレース文字列
     */
    private function formatTrace(array $trace): string
    {
        $result = "Stack Trace:\n";

        foreach ($trace as $i => $t) {
            if ($i === 0) {
                continue; // 最初のエントリはこの関数自体なのでスキップ
            }

            $file = $t['file'] ?? '[internal function]';
            $line = $t['line'] ?? '';
            $class = $t['class'] ?? '';
            $type = $t['type'] ?? '';
            $function = $t['function'] ?? '';

            $result .= "#{$i} {$file}";
            if ($line) {
                $result .= "({$line})";
            }
            $result .= ": {$class}{$type}{$function}()\n";
        }

        return $result . "\n";
    }

    // --- 各ログレベルのメソッド ---

    /**
     * DEBUGログを出力
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->writeLog(self::DEBUG, $message, $context);
    }

    /**
     * INFOログを出力
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->writeLog(self::INFO, $message, $context);
    }

    /**
     * NOTICEログを出力
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->writeLog(self::NOTICE, $message, $context);
    }

    /**
     * WARNINGログを出力
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->writeLog(self::WARNING, $message, $context);
    }

    /**
     * ERRORログを出力（トレース含む）
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->writeLog(self::ERROR, $message, $context, true);
    }

    /**
     * CRITICALログを出力（トレース含む）
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->writeLog(self::CRITICAL, $message, $context, true);
    }

    /**
     * ALERTログを出力（トレース含む）
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->writeLog(self::ALERT, $message, $context, true);
    }

    /**
     * EMERGENCYログを出力（トレース含む）
     * システムが使用不能な状態
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->writeLog(self::EMERGENCY, $message, $context, true);
    }
}
