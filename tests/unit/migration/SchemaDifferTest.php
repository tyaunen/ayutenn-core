<?php
namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\SchemaDiffer;
use ayutenn\core\migration\TableDefinition;
use PHPUnit\Framework\TestCase;

class SchemaDifferTest extends TestCase
{
    private SchemaDiffer $differ;

    protected function setUp(): void
    {
        $this->differ = new SchemaDiffer();
    }

    public function test_テーブルが存在しない場合はCREATE_TABLE差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
            ],
        ]);

        $diffs = $this->differ->diff($expected, null);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::CREATE_TABLE, $diffs[0]['type']);
        $this->assertEquals('users', $diffs[0]['table']);
    }

    public function test_新しいカラムが追加された場合はADD_COLUMN差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
            ],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::ADD_COLUMN, $diffs[0]['type']);
        $this->assertEquals('email', $diffs[0]['column']->getName());
    }

    public function test_カラムが削除された場合はDROP_COLUMN差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
            ],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::DROP_COLUMN, $diffs[0]['type']);
        $this->assertEquals('email', $diffs[0]['columnName']);
    }

    public function test_カラム定義が変更された場合はMODIFY_COLUMN差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'email' => ['type' => 'varchar', 'length' => 500],
            ],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::MODIFY_COLUMN, $diffs[0]['type']);
        $this->assertEquals('email', $diffs[0]['column']->getName());
    }

    public function test_インデックスが追加された場合はADD_INDEX差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
            'indexes' => [
                'idx_email' => ['columns' => ['email'], 'unique' => true],
            ],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
            'indexes' => [],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::ADD_INDEX, $diffs[0]['type']);
        $this->assertEquals('idx_email', $diffs[0]['indexName']);
    }

    public function test_インデックスが削除された場合はDROP_INDEX差分が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
            ],
            'indexes' => [],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int'],
            ],
            'indexes' => [
                'idx_old' => ['columns' => ['id'], 'unique' => false],
            ],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::DROP_INDEX, $diffs[0]['type']);
        $this->assertEquals('idx_old', $diffs[0]['indexName']);
    }

    public function test_差分がない場合は空の配列が返される(): void
    {
        $expected = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
            ],
        ]);

        $actual = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true],
            ],
        ]);

        $diffs = $this->differ->diff($expected, $actual);

        $this->assertEmpty($diffs);
    }

    public function test_複数テーブルの差分を一括検出できる(): void
    {
        $expected = [
            'users' => TableDefinition::fromArray([
                'name' => 'users',
                'columns' => ['id' => ['type' => 'int']],
            ]),
            'posts' => TableDefinition::fromArray([
                'name' => 'posts',
                'columns' => ['id' => ['type' => 'int']],
            ]),
        ];

        $actual = [
            'users' => TableDefinition::fromArray([
                'name' => 'users',
                'columns' => ['id' => ['type' => 'int']],
            ]),
        ];

        $diffs = $this->differ->diffAll($expected, $actual);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::CREATE_TABLE, $diffs[0]['type']);
        $this->assertEquals('posts', $diffs[0]['table']);
    }

    public function test_dropUnknownオプションで未定義テーブルの削除差分が返される(): void
    {
        $expected = [
            'users' => TableDefinition::fromArray([
                'name' => 'users',
                'columns' => ['id' => ['type' => 'int']],
            ]),
        ];

        $actual = [
            'users' => TableDefinition::fromArray([
                'name' => 'users',
                'columns' => ['id' => ['type' => 'int']],
            ]),
            'old_table' => TableDefinition::fromArray([
                'name' => 'old_table',
                'columns' => ['id' => ['type' => 'int']],
            ]),
        ];

        $diffs = $this->differ->diffAll($expected, $actual, dropUnknown: true);

        $this->assertCount(1, $diffs);
        $this->assertEquals(SchemaDiffer::DROP_TABLE, $diffs[0]['type']);
        $this->assertEquals('old_table', $diffs[0]['table']);
    }
}
