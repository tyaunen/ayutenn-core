<?php
namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\DDLGenerator;
use ayutenn\core\migration\SchemaDiffer;
use ayutenn\core\migration\TableDefinition;
use ayutenn\core\migration\Column;
use PHPUnit\Framework\TestCase;

class DDLGeneratorTest extends TestCase
{
    private DDLGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DDLGenerator();
    }

    public function test_CREATE_TABLE差分からSQL文を生成できる(): void
    {
        $table = TableDefinition::fromArray([
            'name' => 'users',
            'columns' => [
                'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true],
                'email' => ['type' => 'varchar', 'length' => 255],
            ],
            'primaryKey' => ['id'],
        ]);

        $diffs = [
            [
                'type' => SchemaDiffer::CREATE_TABLE,
                'table' => 'users',
                'definition' => $table,
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('-- Table: users (新規作成)', $sql);
    }

    public function test_ADD_COLUMN差分からSQL文を生成できる(): void
    {
        $column = Column::fromArray('email', [
            'type' => 'varchar',
            'length' => 255,
            'nullable' => false,
        ]);

        $diffs = [
            [
                'type' => SchemaDiffer::ADD_COLUMN,
                'table' => 'users',
                'column' => $column,
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255)', $sql);
        $this->assertStringContainsString('-- Table: users - カラム追加: email', $sql);
    }

    public function test_ADD_COLUMNでAFTER句が生成される(): void
    {
        $column = Column::fromArray('middle_name', [
            'type' => 'varchar',
            'length' => 100,
            'after' => 'first_name',
        ]);

        $diffs = [
            [
                'type' => SchemaDiffer::ADD_COLUMN,
                'table' => 'users',
                'column' => $column,
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('AFTER `first_name`', $sql);
    }

    public function test_MODIFY_COLUMN差分からSQL文を生成できる(): void
    {
        $column = Column::fromArray('email', [
            'type' => 'varchar',
            'length' => 500,
            'nullable' => false,
        ]);

        $diffs = [
            [
                'type' => SchemaDiffer::MODIFY_COLUMN,
                'table' => 'users',
                'column' => $column,
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ALTER TABLE `users` MODIFY COLUMN', $sql);
        $this->assertStringContainsString('VARCHAR(500)', $sql);
        $this->assertStringContainsString('-- Table: users - カラム変更: email', $sql);
    }

    public function test_DROP_COLUMN差分からSQL文を生成できる(): void
    {
        $diffs = [
            [
                'type' => SchemaDiffer::DROP_COLUMN,
                'table' => 'users',
                'columnName' => 'old_column',
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ALTER TABLE `users` DROP COLUMN `old_column`', $sql);
        $this->assertStringContainsString('-- Table: users - カラム削除: old_column', $sql);
    }

    public function test_ADD_INDEX差分からSQL文を生成できる(): void
    {
        $diffs = [
            [
                'type' => SchemaDiffer::ADD_INDEX,
                'table' => 'users',
                'indexName' => 'idx_email',
                'index' => ['columns' => ['email'], 'unique' => true],
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('CREATE UNIQUE INDEX `idx_email` ON `users` (`email`)', $sql);
    }

    public function test_DROP_INDEX差分からSQL文を生成できる(): void
    {
        $diffs = [
            [
                'type' => SchemaDiffer::DROP_INDEX,
                'table' => 'users',
                'indexName' => 'idx_old',
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('DROP INDEX `idx_old` ON `users`', $sql);
    }

    public function test_ADD_FOREIGN_KEY差分からSQL文を生成できる(): void
    {
        $diffs = [
            [
                'type' => SchemaDiffer::ADD_FOREIGN_KEY,
                'table' => 'posts',
                'fkName' => 'fk_posts_user',
                'foreignKey' => [
                    'columns' => ['user_id'],
                    'references' => ['table' => 'users', 'columns' => ['id']],
                    'onDelete' => 'CASCADE',
                    'onUpdate' => 'CASCADE',
                ],
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_user`', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `users` (`id`)', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
    }

    public function test_DROP_FOREIGN_KEY差分からSQL文を生成できる(): void
    {
        $diffs = [
            [
                'type' => SchemaDiffer::DROP_FOREIGN_KEY,
                'table' => 'posts',
                'fkName' => 'fk_old',
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ALTER TABLE `posts` DROP FOREIGN KEY `fk_old`', $sql);
    }

    public function test_差分が空の場合は空文字が返される(): void
    {
        $sql = $this->generator->generate([]);

        $this->assertEquals('', $sql);
    }

    public function test_複数の差分から連続したSQL文を生成できる(): void
    {
        $column = Column::fromArray('status', ['type' => 'varchar', 'length' => 20]);

        $diffs = [
            [
                'type' => SchemaDiffer::ADD_COLUMN,
                'table' => 'users',
                'column' => $column,
            ],
            [
                'type' => SchemaDiffer::ADD_INDEX,
                'table' => 'users',
                'indexName' => 'idx_status',
                'index' => ['columns' => ['status'], 'unique' => false],
            ],
        ];

        $sql = $this->generator->generate($diffs);

        $this->assertStringContainsString('ADD COLUMN', $sql);
        $this->assertStringContainsString('CREATE INDEX', $sql);
        // 2つのステートメントが含まれていることを確認
        $this->assertEquals(2, substr_count($sql, '-- Table: users'));
    }
}
