<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ayutenn\core\database\DbConnector;
use ayutenn\core\config\Config;
use PDO;

/**
 * DbConnectorクラスのテスト
 */
class DbConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        // テストごとに接続と設定をリセット
        DbConnector::reset();
        Config::reset();
    }

    protected function tearDown(): void
    {
        DbConnector::reset();
        Config::reset();
    }

    public function test_PDO接続を取得できる(): void
    {
        // SQLiteのインメモリDBを使用（テスト用）
        Config::set('PDO_DSN', 'sqlite::memory:');
        Config::set('PDO_USERNAME', null);
        Config::set('PDO_PASSWORD', null);

        $pdo = DbConnector::connectWithPdo();

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function test_同じ接続がシングルトンとして再利用される(): void
    {
        Config::set('PDO_DSN', 'sqlite::memory:');
        Config::set('PDO_USERNAME', null);
        Config::set('PDO_PASSWORD', null);

        $pdo1 = DbConnector::connectWithPdo();
        $pdo2 = DbConnector::connectWithPdo();

        $this->assertSame($pdo1, $pdo2);
    }

    public function test_トランザクション中にロールバックできる(): void
    {
        Config::set('PDO_DSN', 'sqlite::memory:');
        Config::set('PDO_USERNAME', null);
        Config::set('PDO_PASSWORD', null);

        $pdo = DbConnector::connectWithPdo();
        $pdo->beginTransaction();

        $result = DbConnector::rollbackIfInTransaction();

        $this->assertTrue($result);
        $this->assertFalse($pdo->inTransaction());
    }

    public function test_トランザクションがない場合はロールバックしない(): void
    {
        Config::set('PDO_DSN', 'sqlite::memory:');
        Config::set('PDO_USERNAME', null);
        Config::set('PDO_PASSWORD', null);

        DbConnector::connectWithPdo();

        $result = DbConnector::rollbackIfInTransaction();

        $this->assertFalse($result);
    }

    public function test_接続がない場合はロールバックしない(): void
    {
        // reset()で接続をクリアした状態
        $result = DbConnector::rollbackIfInTransaction();

        $this->assertFalse($result);
    }

    public function test_リセット後に新しい接続が作成される(): void
    {
        Config::set('PDO_DSN', 'sqlite::memory:');
        Config::set('PDO_USERNAME', null);
        Config::set('PDO_PASSWORD', null);

        $pdo1 = DbConnector::connectWithPdo();
        DbConnector::reset();
        $pdo2 = DbConnector::connectWithPdo();

        $this->assertNotSame($pdo1, $pdo2);
    }
}
