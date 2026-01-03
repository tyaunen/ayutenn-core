<?php
namespace ayutenn\core\tests\integration\migration;

use ayutenn\core\migration\DatabaseInspector;
use ayutenn\core\migration\MigrationManager;
use ayutenn\core\migration\SchemaDiffer;
use ayutenn\core\migration\TableDefinition;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * カラム追加時に関係のないカラムにも変更が生じる問題の再現テスト
 *
 * 問題: カラムを1つ追加しただけなのに、他のカラムにまで変更が及ぶ
 * 例:
 * - messages.content (TEXT) - 定義を変更していないのにMODIFY COLUMNが生成される
 * - users.updated_at (DATETIME) - 定義を変更していないのにMODIFY COLUMNが生成される
 */
class ColumnModificationBugTest extends TestCase
{
    private static ?PDO $pdo = null;
    private string $tempDir;
    private string $outputDir;
    private const TABLE_PREFIX = 'bug_test_';
    private const TEST_DATABASE = 'framework_migration_test';
    private static bool $databaseCreated = false;

    public static function setUpBeforeClass(): void
    {
        $dsn = 'mysql:host=localhost;charset=utf8mb4';
        $username = 'root';
        $password = '';

        try {
            $tempPdo = new PDO($dsn, $username, $password);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $tempPdo->query("SHOW DATABASES LIKE '" . self::TEST_DATABASE . "'");
            if ($stmt->rowCount() === 0) {
                $tempPdo->exec("CREATE DATABASE `" . self::TEST_DATABASE . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$databaseCreated = true;
            }

            self::$pdo = new PDO(
                'mysql:host=localhost;dbname=' . self::TEST_DATABASE . ';charset=utf8mb4',
                $username,
                $password
            );
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\Exception $e) {
            self::markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped('Database connection not available');
        }

        $this->tempDir = sys_get_temp_dir() . '/bug_migration_' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/bug_output_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->outputDir);
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTables();
        $this->removeDirectory($this->tempDir);
        $this->removeDirectory($this->outputDir);
    }

    private function cleanupTestTables(): void
    {
        if (self::$pdo === null) {
            return;
        }

        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $stmt = self::$pdo->query("SHOW TABLES LIKE '" . self::TABLE_PREFIX . "%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            self::$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function prefixTable(string $name): string
    {
        return self::TABLE_PREFIX . $name;
    }

    /**
     * TEXT型カラムを持つテーブルにカラムを追加した場合の不具合を再現
     *
     * 期待: 新しいカラム (user_name) のみADD COLUMNが生成される
     * 問題: content (TEXT) カラムにもMODIFY COLUMNが生成されてしまう
     */
    public function test_TEXT型カラムを持つテーブルにカラム追加時に不要なMODIFYが生成されない(): void
    {
        $tableName = $this->prefixTable('messages');

        // 既存テーブルを作成（TEXT型のcontentカラムを含む）
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `content` TEXT NOT NULL COMMENT 'メッセージ内容',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 新しいカラム (user_name) を追加する定義
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'content' => ['type' => 'text', 'nullable' => false, 'comment' => 'メッセージ内容'],
                'user_name' => ['type' => 'varchar', 'length' => 255, 'nullable' => false, 'comment' => 'ユーザー名'],  // 新規
                'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/messages.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        // 期待: user_name の ADD COLUMN のみ
        $this->assertStringContainsString('ADD COLUMN', $preview['sql'], 'user_name のADD COLUMNが含まれるべき');
        $this->assertStringContainsString('user_name', $preview['sql']);

        // 問題の検証: content に対する MODIFY COLUMN が生成されていないか
        // この行は現在失敗することを期待（バグを再現）
        $this->assertStringNotContainsString('MODIFY COLUMN `content`', $preview['sql'],
            'content カラムは変更していないので MODIFY COLUMN が生成されるべきではない');
    }

    /**
     * DATETIME型のupdated_atカラムを持つテーブルにカラムを追加した場合の不具合を再現
     *
     * 期待: 新しいカラム (user_name) のみADD COLUMNが生成される
     * 問題: updated_at (DATETIME with ON UPDATE) カラムにもMODIFY COLUMNが生成されてしまう
     */
    public function test_DATETIME型のON_UPDATEカラムを持つテーブルにカラム追加時に不要なMODIFYが生成されない(): void
    {
        $tableName = $this->prefixTable('users');

        // 既存テーブルを作成（ON UPDATE CURRENT_TIMESTAMPを含む）
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 新しいカラム (user_name) を追加する定義
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
                'user_name' => ['type' => 'varchar', 'length' => 255, 'nullable' => false, 'comment' => 'ユーザー名'],  // 新規
                'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '作成日時'],
                'updated_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP', 'comment' => '更新日時'],
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/users.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        // 期待: user_name の ADD COLUMN のみ
        $this->assertStringContainsString('ADD COLUMN', $preview['sql'], 'user_name のADD COLUMNが含まれるべき');
        $this->assertStringContainsString('user_name', $preview['sql']);

        // 問題の検証: updated_at に対する MODIFY COLUMN が生成されていないか
        // この行は現在失敗することを期待（バグを再現）
        $this->assertStringNotContainsString('MODIFY COLUMN `updated_at`', $preview['sql'],
            'updated_at カラムは変更していないので MODIFY COLUMN が生成されるべきではない');
    }

    /**
     * 問題のデバッグ用: JSONからの定義とDBからの定義を比較
     */
    public function test_デバッグ用_TEXT型カラムの定義比較(): void
    {
        $tableName = $this->prefixTable('debug_text');

        // DBにテーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `content` TEXT NOT NULL COMMENT 'テスト用コンテンツ',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // DBから定義を取得
        $inspector = new DatabaseInspector(self::$pdo);
        $dbTableDef = $inspector->getTableDefinition($tableName);
        $dbColumn = $dbTableDef->getColumn('content');

        // JSONから定義を作成
        $jsonTableDef = TableDefinition::fromArray([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'content' => ['type' => 'text', 'nullable' => false, 'comment' => 'テスト用コンテンツ'],
            ],
            'primaryKey' => ['id'],
        ]);
        $jsonColumn = $jsonTableDef->getColumn('content');

        // デバッグ情報を出力
        $dbArray = $dbColumn->toArray();
        $jsonArray = $jsonColumn->toArray();

        // 問題の確認: DB側でlengthが設定されている可能性
        $this->assertEquals($jsonArray['type'], $dbArray['type'], 'type が一致するべき');#
        $this->assertEquals($jsonArray['nullable'], $dbArray['nullable'], 'nullable が一致するべき');
        $this->assertEquals($jsonArray['comment'], $dbArray['comment'], 'comment が一致するべき');

        // 核心: length の比較（ここで差異が生じる可能性）
        $this->assertEquals($jsonArray['length'], $dbArray['length'],
            sprintf('length が一致するべき: JSON=%s, DB=%s',
                json_encode($jsonArray['length']), json_encode($dbArray['length'])));

        // 最終確認: equals メソッドでの比較
        $this->assertTrue($jsonColumn->equals($dbColumn),
            sprintf("カラム定義が一致するべき\nJSON: %s\nDB: %s",
                json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                json_encode($dbArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 問題のデバッグ用: DATETIME + ON UPDATE の定義比較
     */
    public function test_デバッグ用_DATETIME型のON_UPDATEカラムの定義比較(): void
    {
        $tableName = $this->prefixTable('debug_datetime');

        // DBにテーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // DBから定義を取得
        $inspector = new DatabaseInspector(self::$pdo);
        $dbTableDef = $inspector->getTableDefinition($tableName);
        $dbColumn = $dbTableDef->getColumn('updated_at');

        // JSONから定義を作成
        $jsonTableDef = TableDefinition::fromArray([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'updated_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP', 'comment' => '更新日時'],
            ],
            'primaryKey' => ['id'],
        ]);
        $jsonColumn = $jsonTableDef->getColumn('updated_at');

        $dbArray = $dbColumn->toArray();
        $jsonArray = $jsonColumn->toArray();

        // 各属性の比較
        $this->assertEquals($jsonArray['type'], $dbArray['type'], 'type が一致するべき');
        $this->assertEquals($jsonArray['nullable'], $dbArray['nullable'], 'nullable が一致するべき');
        $this->assertEquals($jsonArray['default'], $dbArray['default'],
            sprintf('default が一致するべき: JSON=%s, DB=%s',
                json_encode($jsonArray['default']), json_encode($dbArray['default'])));
        $this->assertEquals($jsonArray['onUpdate'], $dbArray['onUpdate'],
            sprintf('onUpdate が一致するべき: JSON=%s, DB=%s',
                json_encode($jsonArray['onUpdate']), json_encode($dbArray['onUpdate'])));
        $this->assertEquals($jsonArray['comment'], $dbArray['comment'], 'comment が一致するべき');

        // 最終確認
        $this->assertTrue($jsonColumn->equals($dbColumn),
            sprintf("カラム定義が一致するべき\nJSON: %s\nDB: %s",
                json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                json_encode($dbArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
    }
}
