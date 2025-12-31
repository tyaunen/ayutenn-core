<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ayutenn\core\config\Config;

class ConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        Config::reset();
        $this->tempDir = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Config::reset();
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createJsonFile(string $filename, array $data): string
    {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }

    // ========================================
    // loadFromJson テスト
    // ========================================

    public function test_JSONファイルから設定を読み込める(): void
    {
        $path = $this->createJsonFile('app.json', [
            'app_name' => 'Test App',
            'app_version' => '1.0.0',
        ]);

        Config::loadFromJson($path);

        $this->assertSame('Test App', Config::get('app_name'));
        $this->assertSame('1.0.0', Config::get('app_version'));
    }

    public function test_存在しないファイルを読み込むと例外が発生する(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file not found');

        Config::loadFromJson('/nonexistent/path/config.json');
    }

    public function test_不正なJSONファイルを読み込むと例外が発生する(): void
    {
        $path = $this->tempDir . '/invalid.json';
        file_put_contents($path, '{ invalid json }');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse JSON');

        Config::loadFromJson($path);
    }

    /**
     * @test
     */
    public function 複数のJSONファイルを読み込むとマージされる(): void
    {
        $appPath = $this->createJsonFile('app.json', [
            'app_name' => 'My App',
            'feature_enabled' => true,
        ]);

        $envPath = $this->createJsonFile('env.json', [
            'app_debug' => true,
            'db_host' => 'localhost',
        ]);

        Config::loadFromJson($appPath);
        Config::loadFromJson($envPath);

        // 両方の設定が保持されている
        $this->assertSame('My App', Config::get('app_name'));
        $this->assertTrue(Config::get('feature_enabled'));
        $this->assertTrue(Config::get('app_debug'));
        $this->assertSame('localhost', Config::get('db_host'));
    }

    /**
     * @test
     */
    public function キーが重複している場合は例外が発生する(): void
    {
        $path1 = $this->createJsonFile('config1.json', ['key' => 'value1']);
        $path2 = $this->createJsonFile('config2.json', ['key' => 'value2']);

        Config::loadFromJson($path1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config key duplicate error: key is already defined.');

        Config::loadFromJson($path2);
    }

    // ========================================
    // load テスト
    // ========================================

    /**
     * @test
     */
    public function load_配列で指定した複数のJSONファイルを順に読み込める(): void
    {
        $path1 = $this->createJsonFile('config1.json', ['key1' => 'value1']);
        $path2 = $this->createJsonFile('config2.json', ['key2' => 'value2']);

        Config::load([$path1, $path2]);

        $this->assertSame('value1', Config::get('key1'));
        $this->assertSame('value2', Config::get('key2'));
    }

    // ========================================
    // loadFromDirectory テスト
    // ========================================

    /**
     * @test
     */
    public function loadFromDirectory_ディレクトリ内のJSONファイルを読み込める(): void
    {
        // 1.json と 2.json でキーが重複しないように修正
        $this->createJsonFile('1_base.json', ['key1' => 'base', 'val' => 1]);
        $this->createJsonFile('2_other.json', ['key2' => 'other']);
        // json以外のファイルは無視されるべき
        file_put_contents($this->tempDir . '/ignore.txt', 'ignored');

        Config::loadFromDirectory($this->tempDir);

        $this->assertSame('base', Config::get('key1'));
        $this->assertSame('other', Config::get('key2'));
        $this->assertSame(1, Config::get('val'));
    }

    /**
     * @test
     */
    public function loadFromDirectory_キー重複がある場合は例外が発生する(): void
    {
        // 名前順で先に読まれる
        $this->createJsonFile('1.json', ['key' => 'value1']);
        // 後から読まれて重複エラーになる
        $this->createJsonFile('2.json', ['key' => 'value2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Config key duplicate error: key is already defined.');

        Config::loadFromDirectory($this->tempDir);
    }

    /**
     * @test
     */
    public function loadFromDirectory_存在しないディレクトリを指定すると例外が発生する(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory not found');

        Config::loadFromDirectory('/nonexistent/dir');
    }

    /**
     * @test
     */
    public function loadFromDirectory_空のディレクトリでもエラーにならない(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir);

        Config::loadFromDirectory($emptyDir);

        // 例外が発生しなければOK
        $this->assertTrue(true);
    }

    // ========================================
    // get テスト
    // ========================================

    public function test_存在するキーの値を取得できる(): void
    {
        Config::set('app_name', 'Test');

        $this->assertSame('Test', Config::get('app_name'));
    }

    public function test_存在しないキーを取得すると例外が発生する(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config key not found: nonexistent');

        Config::get('nonexistent');
    }

    public function test_様々な型の値を取得できる(): void
    {
        Config::set('string', 'hello');
        Config::set('int', 42);
        Config::set('float', 3.14);
        Config::set('bool', true);
        Config::set('null', null);
        Config::set('array', [1, 2, 3]);

        $this->assertSame('hello', Config::get('string'));
        $this->assertSame(42, Config::get('int'));
        $this->assertSame(3.14, Config::get('float'));
        $this->assertTrue(Config::get('bool'));
        $this->assertNull(Config::get('null'));
        $this->assertSame([1, 2, 3], Config::get('array'));
    }

    // ========================================
    // set テスト
    // ========================================

    public function test_値をセットできる(): void
    {
        Config::set('key', 'value');

        $this->assertSame('value', Config::get('key'));
    }

    public function test_既存の値を上書きできる(): void
    {
        Config::set('key', 'original');
        Config::set('key', 'updated');

        $this->assertSame('updated', Config::get('key'));
    }

    // ========================================
    // reset テスト
    // ========================================

    public function test_resetで全ての設定がクリアされる(): void
    {
        Config::set('app_name', 'Test');
        Config::set('db_host', 'localhost');

        Config::reset();

        $this->expectException(\InvalidArgumentException::class);
        Config::get('app_name');
    }
}
