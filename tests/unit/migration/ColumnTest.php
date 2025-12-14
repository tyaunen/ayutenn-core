<?php
namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\Column;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function test_基本的なカラム定義からSQLを生成できる(): void
    {
        $column = Column::fromArray('id', [
            'type' => 'int',
            'unsigned' => true,
            'autoIncrement' => true,
            'nullable' => false,
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('`id`', $sql);
        $this->assertStringContainsString('INT', $sql);
        $this->assertStringContainsString('UNSIGNED', $sql);
        $this->assertStringContainsString('NOT NULL', $sql);
        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
    }

    public function test_VARCHAR型の長さが正しく出力される(): void
    {
        $column = Column::fromArray('email', [
            'type' => 'varchar',
            'length' => 255,
            'nullable' => false,
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('VARCHAR(255)', $sql);
        $this->assertStringContainsString('NOT NULL', $sql);
    }

    public function test_NULLABLE指定が正しく出力される(): void
    {
        $column = Column::fromArray('deleted_at', [
            'type' => 'datetime',
            'nullable' => true,
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('NULL', $sql);
        $this->assertStringNotContainsString('NOT NULL', $sql);
    }

    public function test_デフォルト値が正しく出力される(): void
    {
        $column = Column::fromArray('status', [
            'type' => 'varchar',
            'length' => 20,
            'default' => 'active',
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString("DEFAULT 'active'", $sql);
    }

    public function test_数値のデフォルト値はクォートされない(): void
    {
        $column = Column::fromArray('count', [
            'type' => 'int',
            'default' => 0,
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('DEFAULT 0', $sql);
    }

    public function test_CURRENT_TIMESTAMPはそのまま出力される(): void
    {
        $column = Column::fromArray('created_at', [
            'type' => 'datetime',
            'default' => 'CURRENT_TIMESTAMP',
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('DEFAULT CURRENT_TIMESTAMP', $sql);
    }

    public function test_ON_UPDATEが正しく出力される(): void
    {
        $column = Column::fromArray('updated_at', [
            'type' => 'datetime',
            'nullable' => true,
            'onUpdate' => 'CURRENT_TIMESTAMP',
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('ON UPDATE CURRENT_TIMESTAMP', $sql);
    }

    public function test_ENUM型が正しく出力される(): void
    {
        $column = Column::fromArray('status', [
            'type' => 'enum',
            'values' => ['active', 'inactive', 'pending'],
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString("ENUM('active','inactive','pending')", $sql);
    }

    public function test_DECIMAL型の精度とスケールが正しく出力される(): void
    {
        $column = Column::fromArray('price', [
            'type' => 'decimal',
            'precision' => 10,
            'scale' => 2,
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString('DECIMAL(10,2)', $sql);
    }

    public function test_コメントが正しく出力される(): void
    {
        $column = Column::fromArray('email', [
            'type' => 'varchar',
            'length' => 255,
            'comment' => 'ユーザーのメールアドレス',
        ]);

        $sql = $column->toSQL();

        $this->assertStringContainsString("COMMENT 'ユーザーのメールアドレス'", $sql);
    }

    public function test_2つのカラムの等価性を比較できる(): void
    {
        $column1 = Column::fromArray('id', [
            'type' => 'int',
            'unsigned' => true,
        ]);

        $column2 = Column::fromArray('id', [
            'type' => 'int',
            'unsigned' => true,
        ]);

        $column3 = Column::fromArray('id', [
            'type' => 'int',
            'unsigned' => false,
        ]);

        $this->assertTrue($column1->equals($column2));
        $this->assertFalse($column1->equals($column3));
    }

    public function test_toArrayで定義を配列として取得できる(): void
    {
        $column = Column::fromArray('email', [
            'type' => 'varchar',
            'length' => 255,
            'nullable' => false,
            'unique' => true,
        ]);

        $array = $column->toArray();

        $this->assertEquals('email', $array['name']);
        $this->assertEquals('varchar', $array['type']);
        $this->assertEquals(255, $array['length']);
        $this->assertFalse($array['nullable']);
        $this->assertTrue($array['unique']);
    }
}
