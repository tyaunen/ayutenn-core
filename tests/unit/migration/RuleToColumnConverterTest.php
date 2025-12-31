<?php
declare(strict_types=1);

namespace ayutenn\core\tests\unit\migration;

use ayutenn\core\migration\RuleToColumnConverter;
use PHPUnit\Framework\TestCase;

/**
 * RuleToColumnConverterのユニットテスト
 */
class RuleToColumnConverterTest extends TestCase
{
    private string $rulesDir;

    protected function setUp(): void
    {
        $this->rulesDir = __DIR__ . '/../../fixtures/rules';
    }

    /**
     * コンストラクタが存在しないディレクトリでエラーを投げることをテスト
     */
    public function testConstructorThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ルールディレクトリが見つかりません');
        new RuleToColumnConverter('/nonexistent/path');
    }

    /**
     * 基本的なstring型の変換をテスト（dbセクションなし）
     */
    public function testConvertStringTypeWithMaxLength(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('username');

        $this->assertEquals('varchar', $result['type']);
        $this->assertEquals(100, $result['length']);
    }

    /**
     * dbセクションありの変換をテスト
     */
    public function testConvertWithDbSection(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('user_id');

        $this->assertEquals('char', $result['type']);
        $this->assertEquals(16, $result['length']);
    }

    /**
     * email条件の変換をテスト
     */
    public function testConvertEmailCondition(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('email');

        $this->assertEquals('varchar', $result['type']);
        $this->assertEquals(254, $result['length']);
    }

    /**
     * color_code条件の変換をテスト
     */
    public function testConvertColorCodeCondition(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('color_code');

        $this->assertEquals('char', $result['type']);
        $this->assertEquals(7, $result['length']);
    }

    /**
     * int型の変換をテスト
     */
    public function testConvertIntType(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('quantity');

        $this->assertEquals('int', $result['type']);
    }

    /**
     * テーブル定義側のオーバーライドが適用されることをテスト
     */
    public function testColumnOverridesAreApplied(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('username', [
            'nullable' => true,
            'comment' => 'ユーザー名',
            'default' => 'guest',
        ]);

        $this->assertEquals('varchar', $result['type']);
        $this->assertEquals(100, $result['length']);
        $this->assertTrue($result['nullable']);
        $this->assertEquals('ユーザー名', $result['comment']);
        $this->assertEquals('guest', $result['default']);
    }

    /**
     * 存在しないルールファイルでエラーを投げることをテスト
     */
    public function testConvertThrowsExceptionForNonExistentRule(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ルールファイルが見つかりません');
        $converter->convert('nonexistent_rule');
    }

    /**
     * .json拡張子付きでも動作することをテスト
     */
    public function testConvertWorksWithJsonExtension(): void
    {
        $converter = new RuleToColumnConverter($this->rulesDir);

        $result = $converter->convert('username.json');

        $this->assertEquals('varchar', $result['type']);
        $this->assertEquals(100, $result['length']);
    }
}
