<?php

namespace ayutenn\core\tests\validation;

use PHPUnit\Framework\TestCase;
use ayutenn\core\validation\RuleLoader;
use ayutenn\core\validation\ValidationRule;

class RuleLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/validation_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*.json"));
            rmdir($this->tempDir);
        }
    }

    public function test_JSON文字列からルールを生成できる(): void
    {
        $json = '{"type": "string", "max_length": 16, "conditions": ["alphanumeric"]}';

        $rule = RuleLoader::fromJsonString($json);

        $this->assertInstanceOf(ValidationRule::class, $rule);
        $this->assertSame('string', $rule->type);
        $this->assertSame(16, $rule->maxLength);
        $this->assertSame(['alphanumeric'], $rule->conditions);
    }

    public function test_配列からルールを生成できる(): void
    {
        $rule = RuleLoader::fromArray([
            'type' => 'int',
            'min' => 0,
            'max' => 100,
        ]);

        $this->assertInstanceOf(ValidationRule::class, $rule);
        $this->assertSame('int', $rule->type);
        $this->assertSame(0, $rule->min);
        $this->assertSame(100, $rule->max);
    }

    public function test_JSONファイルからルールを読み込める(): void
    {
        $filePath = "{$this->tempDir}/username.json";
        file_put_contents($filePath, '{"type": "string", "max_length": 16}');

        $rule = RuleLoader::fromJsonFile($filePath);

        $this->assertInstanceOf(ValidationRule::class, $rule);
        $this->assertSame('string', $rule->type);
        $this->assertSame(16, $rule->maxLength);
    }

    public function test_存在しないファイルで例外が発生する(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ルールファイルが見つかりません');

        RuleLoader::fromJsonFile('/nonexistent/path/file.json');
    }

    public function test_不正なJSONで例外が発生する(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSONのパースに失敗しました');

        RuleLoader::fromJsonString('{ invalid json }');
    }
}
