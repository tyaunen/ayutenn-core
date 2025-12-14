<?php
namespace ayutenn\core\tests\integration\migration;

use ayutenn\core\migration\DatabaseInspector;
use ayutenn\core\migration\TableDefinition;
use ayutenn\core\migration\MigrationManager;
use ayutenn\core\database\DbConnector;
use ayutenn\core\config\Config;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * マイグレーション機能のインテグレーションテスト
 *
 * 実際のMySQLデータベースを使用してテストを行う。
 * テスト用のテーブルを作成・削除するため、テスト用DBを使用すること。
 */
class MigrationIntegrationTest extends TestCase
{
    private static ?PDO $pdo = null;
    private string $tempDir;
    private string $outputDir;

    /**
     * テスト用のテーブルプレフィックス
     */
    private const TABLE_PREFIX = 'migration_test_';

    /**
     * テスト用データベース名
     */
    private const TEST_DATABASE = 'framework_migration_test';

    /**
     * データベースが作成されたかどうか
     */
    private static bool $databaseCreated = false;

    public static function setUpBeforeClass(): void
    {
        // XAMPPのデフォルト設定で接続（データベース指定なし）
        $dsn = 'mysql:host=localhost;charset=utf8mb4';
        $username = 'root';
        $password = '';

        try {
            $tempPdo = new PDO($dsn, $username, $password);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // データベースが存在しなければ作成
            $stmt = $tempPdo->query("SHOW DATABASES LIKE '" . self::TEST_DATABASE . "'");
            if ($stmt->rowCount() === 0) {
                $tempPdo->exec("CREATE DATABASE `" . self::TEST_DATABASE . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$databaseCreated = true;
            }

            // テスト用データベースに接続
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

        // 一時ディレクトリを作成
        $this->tempDir = sys_get_temp_dir() . '/migration_integration_' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/migration_output_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->outputDir);

        // 既存のテスト用テーブルをクリーンアップ
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        // テスト用テーブルをクリーンアップ
        $this->cleanupTestTables();

        // 一時ディレクトリを削除
        $this->removeDirectory($this->tempDir);
        $this->removeDirectory($this->outputDir);
    }

    public static function tearDownAfterClass(): void
    {
        // このテストで作成したデータベースであれば削除
        if (self::$databaseCreated && self::$pdo !== null) {
            try {
                $dsn = 'mysql:host=localhost;charset=utf8mb4';
                $tempPdo = new PDO($dsn, 'root', '');
                $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $tempPdo->exec("DROP DATABASE IF EXISTS `" . self::TEST_DATABASE . "`");
            } catch (\Exception $e) {
                // 削除失敗は無視
            }
        }

        self::$pdo = null;
        DbConnector::reset();
    }

    /**
     * テスト用テーブルをすべて削除
     */
    private function cleanupTestTables(): void
    {
        if (self::$pdo === null) {
            return;
        }

        // 外部キー制約を一時的に無効化
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $stmt = self::$pdo->query("SHOW TABLES LIKE '" . self::TABLE_PREFIX . "%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            self::$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }

        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * ディレクトリを再帰的に削除
     */
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

    /**
     * テーブル名にプレフィックスを付与
     */
    private function prefixTable(string $name): string
    {
        return self::TABLE_PREFIX . $name;
    }

    // ================================================================
    // DatabaseInspector のテスト
    // ================================================================

    public function test_DatabaseInspectorで既存テーブルの構造を取得できる(): void
    {
        $tableName = $this->prefixTable('users');

        // テスト用テーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition($tableName);

        $this->assertNotNull($tableDef);
        $this->assertEquals($tableName, $tableDef->getName());
        $this->assertEquals('InnoDB', $tableDef->getEngine());
        $this->assertCount(4, $tableDef->getColumns());

        // カラム確認
        $idColumn = $tableDef->getColumn('id');
        $this->assertNotNull($idColumn);
        $this->assertEquals('int', $idColumn->getType());
        $this->assertTrue($idColumn->isAutoIncrement());
        $this->assertTrue($idColumn->isUnsigned());

        $emailColumn = $tableDef->getColumn('email');
        $this->assertNotNull($emailColumn);
        $this->assertEquals('varchar', $emailColumn->getType());
        $this->assertEquals(255, $emailColumn->getLength());
        $this->assertFalse($emailColumn->isNullable());
    }

    public function test_DatabaseInspectorで存在しないテーブルはnullを返す(): void
    {
        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition('nonexistent_table_xyz');

        $this->assertNull($tableDef);
    }

    public function test_DatabaseInspectorでインデックスを取得できる(): void
    {
        $tableName = $this->prefixTable('posts');

        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status_created` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition($tableName);

        $indexes = $tableDef->getIndexes();
        $this->assertArrayHasKey('idx_user_id', $indexes);
        $this->assertArrayHasKey('idx_status_created', $indexes);
        $this->assertEquals(['user_id'], $indexes['idx_user_id']['columns']);
        $this->assertEquals(['status', 'created_at'], $indexes['idx_status_created']['columns']);
    }

    // ================================================================
    // MigrationManager のテスト
    // ================================================================

    public function test_新規テーブルのマイグレーションSQLを生成できる(): void
    {
        $tableName = $this->prefixTable('new_table');

        // テーブル定義JSONを作成
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'title' => ['type' => 'varchar', 'length' => 200, 'nullable' => false],
                'content' => ['type' => 'text', 'nullable' => true],
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/new_table.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);

        // プレビュー
        $preview = $manager->preview();

        $this->assertNotEmpty($preview['diffs']);
        $this->assertStringContainsString('CREATE TABLE', $preview['sql']);
        $this->assertStringContainsString($tableName, $preview['sql']);

        // ファイル生成
        $filepath = $manager->generateMigration();

        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // 生成されたSQLの内容を確認
        $sql = file_get_contents($filepath);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString($tableName, $sql);
    }

    public function test_生成したSQLを実行してテーブルを作成できる(): void
    {
        $tableName = $this->prefixTable('created_table');

        // テーブル定義JSONを作成
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'name' => ['type' => 'varchar', 'length' => 100, 'nullable' => false],
                'status' => [
                    'type' => 'enum',
                    'values' => ['active', 'inactive'],
                    'default' => 'active',
                ],
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/created_table.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        // SQLを実行
        self::$pdo->exec($preview['sql']);

        // テーブルが作成されたか確認
        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition($tableName);

        $this->assertNotNull($tableDef);
        $this->assertEquals($tableName, $tableDef->getName());
        $this->assertCount(4, $tableDef->getColumns());

        // ENUMカラムの確認
        $statusColumn = $tableDef->getColumn('status');
        $this->assertNotNull($statusColumn);
        $this->assertEquals('enum', $statusColumn->getType());
    }

    public function test_カラム追加のマイグレーションSQLを生成できる(): void
    {
        $tableName = $this->prefixTable('alter_table');

        // 既存テーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 新しいカラムを追加した定義
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'name' => ['type' => 'varchar', 'length' => 100, 'nullable' => false],
                'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],  // 新規
                'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],  // 新規
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/alter_table.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        $this->assertStringContainsString('ADD COLUMN', $preview['sql']);
        $this->assertStringContainsString('email', $preview['sql']);
        $this->assertStringContainsString('created_at', $preview['sql']);

        // SQLを実行
        self::$pdo->exec($preview['sql']);

        // カラムが追加されたか確認
        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition($tableName);

        $this->assertNotNull($tableDef->getColumn('email'));
        $this->assertNotNull($tableDef->getColumn('created_at'));
    }

    public function test_差分がない場合はnullが返される(): void
    {
        $tableName = $this->prefixTable('no_change');

        // テーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 同じ定義のJSON
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'name' => ['type' => 'varchar', 'length' => 100, 'nullable' => false],
            ],
            'primaryKey' => ['id'],
        ]);
        file_put_contents($this->tempDir . '/no_change.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $filepath = $manager->generateMigration();

        $this->assertNull($filepath);
    }

    public function test_インデックス追加のマイグレーションSQLを生成できる(): void
    {
        $tableName = $this->prefixTable('index_table');

        // インデックスなしでテーブルを作成
        self::$pdo->exec("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // インデックスを追加した定義
        $jsonContent = json_encode([
            'name' => $tableName,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
                'status' => ['type' => 'varchar', 'length' => 20, 'nullable' => false],
            ],
            'primaryKey' => ['id'],
            'indexes' => [
                'idx_email' => ['columns' => ['email'], 'unique' => true],
                'idx_status' => ['columns' => ['status'], 'unique' => false],
            ],
        ]);
        file_put_contents($this->tempDir . '/index_table.json', $jsonContent);

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        $this->assertStringContainsString('CREATE UNIQUE INDEX `idx_email`', $preview['sql']);
        $this->assertStringContainsString('CREATE INDEX `idx_status`', $preview['sql']);

        // SQLを実行してインデックスが作成されるか確認
        self::$pdo->exec($preview['sql']);

        $inspector = new DatabaseInspector(self::$pdo);
        $tableDef = $inspector->getTableDefinition($tableName);
        $indexes = $tableDef->getIndexes();

        $this->assertArrayHasKey('idx_email', $indexes);
        $this->assertTrue($indexes['idx_email']['unique']);
    }

    public function test_複数テーブルのマイグレーションを一括生成できる(): void
    {
        $tableName1 = $this->prefixTable('multi_table1');
        $tableName2 = $this->prefixTable('multi_table2');

        // テーブル定義JSON1
        file_put_contents($this->tempDir . '/table1.json', json_encode([
            'name' => $tableName1,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
            ],
            'primaryKey' => ['id'],
        ]));

        // テーブル定義JSON2
        file_put_contents($this->tempDir . '/table2.json', json_encode([
            'name' => $tableName2,
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
            ],
            'primaryKey' => ['id'],
        ]));

        $manager = new MigrationManager(self::$pdo, $this->tempDir, $this->outputDir);
        $preview = $manager->preview();

        $this->assertStringContainsString($tableName1, $preview['sql']);
        $this->assertStringContainsString($tableName2, $preview['sql']);

        // 両方のテーブルがCREATEされること
        $this->assertEquals(2, substr_count($preview['sql'], 'CREATE TABLE'));
    }
}
