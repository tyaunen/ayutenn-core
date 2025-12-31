<?php
/**
 * マイグレーションCLIツール
 *
 * 使用方法:
 *   php bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations
 *   php bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --tables=./tables --output=./migrations
 */

namespace ayutenn\core\bin;

// Composerオートローダーを読み込み
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',       // フレームワーク単体で実行する場合
    __DIR__ . '/../../../autoload.php',        // composerパッケージとして利用する場合
];

$autoloaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, "Error: Composer autoload.php not found.\n");
    fwrite(STDERR, "Please run 'composer install' first.\n");
    exit(1);
}

use ayutenn\core\config\Config;
use ayutenn\core\database\DbConnector;
use ayutenn\core\migration\MigrationManager;
use PDO;

/**
 * ヘルプメッセージを表示
 */
function showHelp(): void
{
    $help = <<<HELP
Usage:
  php bin/migrate.php [options]

Options:
  --config=<path>       設定ファイルパス（PDO_DSN, PDO_USERNAME, PDO_PASSWORDを含む）
  --dsn=<dsn>           PDO DSN（--configがない場合は必須）
  --user=<user>         DBユーザー名（--configがない場合は必須）
  --password=<password> DBパスワード（省略時: 空文字）
  --tables=<dir>        テーブル定義JSONディレクトリ（必須）
  --output=<dir>        SQL出力ディレクトリ（必須）
  --rules=<dir>         ルールファイルディレクトリ（formatキー使用時に必須）
  --preview             プレビューのみ（ファイル出力しない）
  --drop-unknown        定義にないテーブルを削除対象に含める
  --help                このヘルプを表示

Examples:
  # 設定ファイルを使用（推奨）
  php bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations

  # ルールファイルを使用
  php bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --rules=./rules

  # DSN直接指定
  php bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --tables=./tables --output=./migrations

  # プレビューのみ
  php bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --preview

HELP;
    echo $help;
}

/**
 * エラーメッセージを表示して終了
 */
function exitWithError(string $message): void
{
    fwrite(STDERR, "Error: {$message}\n");
    fwrite(STDERR, "Use --help for usage information.\n");
    exit(1);
}

/**
 * 成功メッセージを表示
 */
function showSuccess(string $message): void
{
    echo "\033[32m✓ {$message}\033[0m\n";
}

/**
 * 情報メッセージを表示
 */
function showInfo(string $message): void
{
    echo "\033[36mℹ {$message}\033[0m\n";
}

// コマンドライン引数をパース
$options = getopt('', [
    'config:',
    'dsn:',
    'user:',
    'password:',
    'tables:',
    'output:',
    'rules:',
    'preview',
    'drop-unknown',
    'help',
]);

// ヘルプ表示
if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// 必須引数のチェック
if (!isset($options['tables'])) {
    exitWithError('--tables is required.');
}

if (!isset($options['output'])) {
    exitWithError('--output is required.');
}

$tablesDir = $options['tables'];
$outputDir = $options['output'];
$rulesDir = $options['rules'] ?? null;
$isPreview = isset($options['preview']);
$dropUnknown = isset($options['drop-unknown']);

// ディレクトリの存在確認
if (!is_dir($tablesDir)) {
    exitWithError("Tables directory not found: {$tablesDir}");
}

// PDO接続を取得
$pdo = null;

if (isset($options['config'])) {
    // 設定ファイルから読み込み
    $configPath = $options['config'];

    if (!file_exists($configPath)) {
        exitWithError("Config file not found: {$configPath}");
    }

    try {
        Config::loadFromJson($configPath);
        $pdo = DbConnector::connectWithPdo();
        showInfo("Config loaded from: {$configPath}");

        // 設定ファイルからMODEL_DIRECTORYを取得（CLIオプションが未指定の場合）
        if ($rulesDir === null) {
            $rulesDir = Config::get('MODEL_DIRECTORY');
            if ($rulesDir !== null) {
                showInfo("Rules directory from config: {$rulesDir}");
            }
        }
    } catch (\Exception $e) {
        exitWithError("Failed to connect using config: " . $e->getMessage());
    }
} elseif (isset($options['dsn']) && isset($options['user'])) {
    // DSN直接指定
    $dsn = $options['dsn'];
    $user = $options['user'];
    $password = $options['password'] ?? '';

    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        showInfo("Connected to: {$dsn}");
    } catch (\PDOException $e) {
        exitWithError("Failed to connect: " . $e->getMessage());
    }
} else {
    exitWithError('Either --config or both --dsn and --user are required.');
}

// マイグレーション実行
try {
    $manager = new MigrationManager($pdo, $tablesDir, $outputDir, $rulesDir);

    if ($isPreview) {
        // プレビューモード
        showInfo("Preview mode (no file output)");
        $result = $manager->preview($dropUnknown);

        if (empty($result['diffs'])) {
            showSuccess("No changes detected.");
        } else {
            echo "\n";
            echo "=== Generated SQL ===\n";
            echo $result['sql'];
            echo "\n";
            showInfo(count($result['diffs']) . " change(s) detected.");
        }
    } else {
        // 生成モード
        $filepath = $manager->generateMigration($dropUnknown);

        if ($filepath === null) {
            showSuccess("No changes detected. No migration file generated.");
        } else {
            showSuccess("Migration file generated: {$filepath}");
        }
    }
} catch (\Exception $e) {
    exitWithError("Migration failed: " . $e->getMessage());
}

exit(0);
