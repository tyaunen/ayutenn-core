<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ayutenn\core\database\DataManager;
use PDO;

/**
 * DataManagerのテスト用具体クラス
 */
class TestDataManager extends DataManager
{
    /**
     * テスト用にpublicに公開
     */
    public function testExecuteStatement(string $sql, array $params): \PDOStatement
    {
        return $this->executeStatement($sql, $params);
    }

    public function testExecuteAndFetchAll(string $sql, array $params): array
    {
        return $this->executeAndFetchAll($sql, $params);
    }

    public function testExecuteAndFetchOne(string $sql, array $params): ?array
    {
        return $this->executeAndFetchOne($sql, $params);
    }
}

/**
 * DataManagerクラスのテスト
 */
class DataManagerTest extends TestCase
{
    private PDO $pdo;
    private TestDataManager $manager;

    protected function setUp(): void
    {
        // SQLiteインメモリDBでテスト
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // テスト用テーブルを作成
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
        ');

        $this->manager = new TestDataManager($this->pdo);
    }

    public function test_SQLを実行してステートメントを取得できる(): void
    {
        $stmt = $this->manager->testExecuteStatement(
            'INSERT INTO users (name, email) VALUES (:name, :email)',
            [
                ':name' => ['田中太郎', PDO::PARAM_STR],
                ':email' => ['tanaka@example.com', PDO::PARAM_STR],
            ]
        );

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $this->assertEquals(1, $this->pdo->lastInsertId());
    }

    public function test_複数行を取得できる(): void
    {
        // データ挿入
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('田中', 'tanaka@example.com')");
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('山田', 'yamada@example.com')");

        $results = $this->manager->testExecuteAndFetchAll(
            'SELECT * FROM users ORDER BY id',
            []
        );

        $this->assertCount(2, $results);
        $this->assertEquals('田中', $results[0]['name']);
        $this->assertEquals('山田', $results[1]['name']);
    }

    public function test_結果が0件の場合は空配列を返す(): void
    {
        $results = $this->manager->testExecuteAndFetchAll(
            'SELECT * FROM users WHERE id = :id',
            [':id' => [999, PDO::PARAM_INT]]
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_1行を取得できる(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('田中', 'tanaka@example.com')");

        $result = $this->manager->testExecuteAndFetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => [1, PDO::PARAM_INT]]
        );

        $this->assertIsArray($result);
        $this->assertEquals('田中', $result['name']);
        $this->assertEquals('tanaka@example.com', $result['email']);
    }

    public function test_1行取得で結果がない場合はnullを返す(): void
    {
        $result = $this->manager->testExecuteAndFetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => [999, PDO::PARAM_INT]]
        );

        $this->assertNull($result);
    }

    public function test_プレースホルダでパラメータをバインドできる(): void
    {
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('田中', 'tanaka@example.com')");
        $this->pdo->exec("INSERT INTO users (name, email) VALUES ('山田', 'yamada@example.com')");

        $result = $this->manager->testExecuteAndFetchOne(
            'SELECT * FROM users WHERE name = :name',
            [':name' => ['山田', PDO::PARAM_STR]]
        );

        $this->assertEquals('山田', $result['name']);
        $this->assertEquals('yamada@example.com', $result['email']);
    }
}
