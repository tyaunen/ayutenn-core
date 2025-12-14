<?php

declare(strict_types=1);

namespace ayutenn\core\tests\unit;

use ayutenn\core\FrameworkPaths;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * FrameworkPaths のテスト
 */
class FrameworkPathsTest extends TestCase
{
    protected function setUp(): void
    {
        FrameworkPaths::reset();
    }

    protected function tearDown(): void
    {
        FrameworkPaths::reset();
    }

    private function getValidConfig(): array
    {
        return [
            'controllerDir' => '/path/to/controllers',
            'viewDir' => '/path/to/views',
            'apiDir' => '/path/to/api',
            'pathRoot' => '/myapp',
            'validationRulesDir' => '/path/to/rules',
        ];
    }

    public function test_正常に初期化できる(): void
    {
        $config = $this->getValidConfig();

        FrameworkPaths::init($config);

        $this->assertTrue(FrameworkPaths::isInitialized());
    }

    public function test_必須キーが欠けている場合は例外をスロー(): void
    {
        $config = [
            'controllerDir' => '/path/to/controllers',
            // viewDir が欠けている
            'apiDir' => '/path/to/api',
            'pathRoot' => '/myapp',
            'validationRulesDir' => '/path/to/rules',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('viewDir');

        FrameworkPaths::init($config);
    }

    public function test_複数の必須キーが欠けている場合はすべて報告される(): void
    {
        $config = [
            'controllerDir' => '/path/to/controllers',
            // viewDir, apiDir が欠けている
            'pathRoot' => '/myapp',
            'validationRulesDir' => '/path/to/rules',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('viewDir');

        FrameworkPaths::init($config);
    }

    public function test_getControllerDir_パスを取得できる(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertSame('/path/to/controllers', FrameworkPaths::getControllerDir());
    }

    public function test_getViewDir_パスを取得できる(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertSame('/path/to/views', FrameworkPaths::getViewDir());
    }

    public function test_getApiDir_パスを取得できる(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertSame('/path/to/api', FrameworkPaths::getApiDir());
    }

    public function test_getPathRoot_パスを取得できる(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertSame('/myapp', FrameworkPaths::getPathRoot());
    }

    public function test_getValidationRulesDir_パスを取得できる(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertSame('/path/to/rules', FrameworkPaths::getValidationRulesDir());
    }

    public function test_getNotFoundView_設定されている場合は値を返す(): void
    {
        $config = $this->getValidConfig();
        $config['notFoundView'] = '404.php';
        FrameworkPaths::init($config);

        $this->assertSame('404.php', FrameworkPaths::getNotFoundView());
    }

    public function test_getNotFoundView_設定されていない場合はnullを返す(): void
    {
        FrameworkPaths::init($this->getValidConfig());

        $this->assertNull(FrameworkPaths::getNotFoundView());
    }

    public function test_未初期化状態でgetterを呼ぶと例外をスロー(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FrameworkPaths が初期化されていません');

        FrameworkPaths::getViewDir();
    }

    public function test_isInitialized_初期化前はfalseを返す(): void
    {
        $this->assertFalse(FrameworkPaths::isInitialized());
    }

    public function test_reset_設定をクリアできる(): void
    {
        FrameworkPaths::init($this->getValidConfig());
        $this->assertTrue(FrameworkPaths::isInitialized());

        FrameworkPaths::reset();

        $this->assertFalse(FrameworkPaths::isInitialized());
    }
}
