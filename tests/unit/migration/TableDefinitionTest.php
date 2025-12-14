<?php
namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\TableDefinition;
use ayutenn\core\migration\Column;
use PHPUnit\Framework\TestCase;

class TableDefinitionTest extends TestCase
{
    public function test_配列からテーブル定義を生成できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
            'primaryKey' => ['id'],
        ]);

        $this->assertEquals('users', $definition->getName());
        $this->assertCount(2, $definition->getColumns());
        $this->assertEquals(['id'], $definition->getPrimaryKey());
    }

    public function test_デフォルト値が正しく設定される(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [],
        ]);

        $this->assertEquals('InnoDB', $definition->getEngine());
        $this->assertEquals('utf8mb4', $definition->getCharset());
        $this->assertEquals('utf8mb4_unicode_ci', $definition->getCollation());
    }

    public function test_カスタム設定が反映される(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'logs',
            'engine' => 'MyISAM',
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'comment' => 'ログテーブル',
            'columns' => [],
        ]);

        $this->assertEquals('MyISAM', $definition->getEngine());
        $this->assertEquals('utf8', $definition->getCharset());
        $this->assertEquals('utf8_general_ci', $definition->getCollation());
        $this->assertEquals('ログテーブル', $definition->getComment());
    }

    public function test_CREATE_TABLE文を生成できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'comment' => 'ユーザーテーブル',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
            ],
            'primaryKey' => ['id'],
        ]);

        $sql = $definition->toCreateSQL();

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` INT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString("ENGINE=InnoDB", $sql);
        $this->assertStringContainsString("DEFAULT CHARSET=utf8mb4", $sql);
        $this->assertStringContainsString("COMMENT='ユーザーテーブル'", $sql);
    }

    public function test_インデックスを持つテーブルのCREATE文を生成できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
                'status' => ['type' => 'varchar', 'length' => 20],
            ],
            'primaryKey' => ['id'],
            'indexes' => [
                'idx_email' => ['columns' => ['email'], 'unique' => true],
                'idx_status' => ['columns' => ['status'], 'unique' => false],
            ],
        ]);

        $sql = $definition->toCreateSQL();

        $this->assertStringContainsString('UNIQUE KEY `idx_email` (`email`)', $sql);
        $this->assertStringContainsString('KEY `idx_status` (`status`)', $sql);
    }

    public function test_外部キーを持つテーブルのCREATE文を生成できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'posts',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'user_id' => ['type' => 'int', 'unsigned' => true],
            ],
            'primaryKey' => ['id'],
            'foreignKeys' => [
                'fk_posts_user' => [
                    'columns' => ['user_id'],
                    'references' => ['table' => 'users', 'columns' => ['id']],
                    'onDelete' => 'CASCADE',
                    'onUpdate' => 'CASCADE',
                ],
            ],
        ]);

        $sql = $definition->toCreateSQL();

        $this->assertStringContainsString('CONSTRAINT `fk_posts_user`', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `users` (`id`)', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
        $this->assertStringContainsString('ON UPDATE CASCADE', $sql);
    }

    public function test_テーブル名がない場合は例外が発生する(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('テーブル名は必須です');

        TableDefinition::fromArray([
            'columns' => [],
        ]);
    }

    public function test_カラム名を配列で取得できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
                'email' => ['type' => 'varchar', 'length' => 255],
                'name' => ['type' => 'varchar', 'length' => 100],
            ],
        ]);

        $columnNames = $definition->getColumnNames();

        $this->assertEquals(['id', 'email', 'name'], $columnNames);
    }

    public function test_特定のカラムを取得できる(): void
    {
        $definition = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
        ]);

        $idColumn = $definition->getColumn('id');
        $unknownColumn = $definition->getColumn('unknown');

        $this->assertInstanceOf(Column::class, $idColumn);
        $this->assertEquals('id', $idColumn->getName());
        $this->assertNull($unknownColumn);
    }
}
