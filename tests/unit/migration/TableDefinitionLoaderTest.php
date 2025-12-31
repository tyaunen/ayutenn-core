<?php
namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\TableDefinitionLoader;
use ayutenn\core\migration\TableDefinition;
use PHPUnit\Framework\TestCase;

class TableDefinitionLoaderTest extends TestCase
{
    private string $tempDir;
    private string $rulesDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/migration_test_' . uniqid();
        mkdir($this->tempDir);
        $this->rulesDir = __DIR__ . '/../../fixtures/rules';
    }

    protected function tearDown(): void
    {
        // 一時ディレクトリのクリーンアップ
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function test_JSONファイルからテーブル定義を読み込める(): void
    {
        $jsonPath = $this->tempDir . '/users.json';
        file_put_contents($jsonPath, json_encode([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
            'primaryKey' => ['id'],
        ]));

        $loader = new TableDefinitionLoader();
        $table = $loader->load($jsonPath);

        $this->assertInstanceOf(TableDefinition::class, $table);
        $this->assertEquals('users', $table->getName());
        $this->assertCount(2, $table->getColumns());
    }

    public function test_存在しないファイルを読み込むと例外が発生する(): void
    {
        $loader = new TableDefinitionLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ファイルが見つかりません');

        $loader->load('/nonexistent/path/table.json');
    }

    public function test_不正なJSONを読み込むと例外が発生する(): void
    {
        $jsonPath = $this->tempDir . '/invalid.json';
        file_put_contents($jsonPath, '{ invalid json }');

        $loader = new TableDefinitionLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSONパースエラー');

        $loader->load($jsonPath);
    }

    public function test_ディレクトリ内のすべてのJSONファイルを読み込める(): void
    {
        // 複数のJSONファイルを作成
        file_put_contents($this->tempDir . '/users.json', json_encode([
            'name' => 'users',
            'columns' => ['id' => ['type' => 'int']],
        ]));

        file_put_contents($this->tempDir . '/posts.json', json_encode([
            'name' => 'posts',
            'columns' => ['id' => ['type' => 'int']],
        ]));

        file_put_contents($this->tempDir . '/comments.json', json_encode([
            'name' => 'comments',
            'columns' => ['id' => ['type' => 'int']],
        ]));

        $loader = new TableDefinitionLoader();
        $tables = $loader->loadFromDirectory($this->tempDir);

        $this->assertCount(3, $tables);
        $this->assertArrayHasKey('users', $tables);
        $this->assertArrayHasKey('posts', $tables);
        $this->assertArrayHasKey('comments', $tables);
    }

    public function test_存在しないディレクトリを読み込むと例外が発生する(): void
    {
        $loader = new TableDefinitionLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ディレクトリが見つかりません');

        $loader->loadFromDirectory('/nonexistent/directory');
    }

    public function test_空のディレクトリからは空の配列が返される(): void
    {
        $loader = new TableDefinitionLoader();
        $tables = $loader->loadFromDirectory($this->tempDir);

        $this->assertIsArray($tables);
        $this->assertEmpty($tables);
    }

    public function test_JSON以外のファイルは無視される(): void
    {
        file_put_contents($this->tempDir . '/users.json', json_encode([
            'name' => 'users',
            'columns' => ['id' => ['type' => 'int']],
        ]));

        file_put_contents($this->tempDir . '/readme.txt', 'This is not JSON');
        file_put_contents($this->tempDir . '/data.xml', '<root></root>');

        $loader = new TableDefinitionLoader();
        $tables = $loader->loadFromDirectory($this->tempDir);

        $this->assertCount(1, $tables);
        $this->assertArrayHasKey('users', $tables);
    }

    // ========================================
    // format機能のテスト
    // ========================================

    public function test_formatキーを使用してルールからカラム定義を解決できる(): void
    {
        $jsonPath = $this->tempDir . '/users.json';
        file_put_contents($jsonPath, json_encode([
            'name' => 'users',
            'columns' => [
                'user_id' => [
                    'format' => 'user_id',
                    'nullable' => false,
                    'comment' => 'ユーザーID',
                ],
                'user_name' => [
                    'format' => 'username',
                    'nullable' => false,
                    'comment' => 'ユーザー名',
                ],
            ],
            'primaryKey' => ['user_id'],
        ]));

        $loader = new TableDefinitionLoader($this->rulesDir);
        $table = $loader->load($jsonPath);

        $this->assertEquals('users', $table->getName());

        // user_id: dbセクションからchar(16)
        $userIdColumn = $table->getColumn('user_id');
        $this->assertNotNull($userIdColumn);
        $this->assertEquals('char', $userIdColumn->getType());
        $this->assertEquals(16, $userIdColumn->getLength());
        $this->assertFalse($userIdColumn->isNullable());
        $this->assertEquals('ユーザーID', $userIdColumn->getComment());

        // user_name: string + max_lengthからvarchar(100)
        $userNameColumn = $table->getColumn('user_name');
        $this->assertNotNull($userNameColumn);
        $this->assertEquals('varchar', $userNameColumn->getType());
        $this->assertEquals(100, $userNameColumn->getLength());
        $this->assertFalse($userNameColumn->isNullable());
        $this->assertEquals('ユーザー名', $userNameColumn->getComment());
    }

    public function test_ルールディレクトリ未指定でformatキーを使用すると例外が発生する(): void
    {
        $jsonPath = $this->tempDir . '/users.json';
        file_put_contents($jsonPath, json_encode([
            'name' => 'users',
            'columns' => [
                'user_name' => [
                    'format' => 'username',
                ],
            ],
        ]));

        $loader = new TableDefinitionLoader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('formatキーを使用するにはルールディレクトリの指定が必要です');

        $loader->load($jsonPath);
    }

    public function test_存在しないルールファイルを指定すると例外が発生する(): void
    {
        $jsonPath = $this->tempDir . '/users.json';
        file_put_contents($jsonPath, json_encode([
            'name' => 'users',
            'columns' => [
                'user_name' => [
                    'format' => 'nonexistent_rule',
                ],
            ],
        ]));

        $loader = new TableDefinitionLoader($this->rulesDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ルールファイルが見つかりません');

        $loader->load($jsonPath);
    }

    public function test_formatと従来の定義を混在させることができる(): void
    {
        $jsonPath = $this->tempDir . '/users.json';
        file_put_contents($jsonPath, json_encode([
            'name' => 'users',
            'columns' => [
                'id' => [
                    'type' => 'int',
                    'unsigned' => true,
                    'autoIncrement' => true,
                ],
                'user_name' => [
                    'format' => 'username',
                    'nullable' => false,
                ],
                'created_at' => [
                    'type' => 'datetime',
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ],
            'primaryKey' => ['id'],
        ]));

        $loader = new TableDefinitionLoader($this->rulesDir);
        $table = $loader->load($jsonPath);

        $this->assertCount(3, $table->getColumns());

        // 従来の定義
        $idColumn = $table->getColumn('id');
        $this->assertEquals('int', $idColumn->getType());
        $this->assertTrue($idColumn->isAutoIncrement());

        // format定義
        $userNameColumn = $table->getColumn('user_name');
        $this->assertEquals('varchar', $userNameColumn->getType());
        $this->assertEquals(100, $userNameColumn->getLength());

        // 従来の定義
        $createdAtColumn = $table->getColumn('created_at');
        $this->assertEquals('datetime', $createdAtColumn->getType());
    }
}
