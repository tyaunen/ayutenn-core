<?php

namespace tests\unit\utils;

use ayutenn\core\utils\FileHandler;
use PHPUnit\Framework\TestCase;

class FileHandlerTest extends TestCase
{
    private string $testDir;
    private FileHandler $handler;

    protected function setUp(): void
    {
        // テスト用ディレクトリを作成
        $this->testDir = sys_get_temp_dir() . '/test_uploads_' . uniqid() . '/';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        $this->handler = new FileHandler($this->testDir);
    }

    protected function tearDown(): void
    {
        // テスト用ディレクトリを再帰的に削除
        $this->deleteDirectory($this->testDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_ディレクトリサイズを計算できる(): void
    {
        // テストファイルを作成
        file_put_contents($this->testDir . 'test1.txt', 'Hello');
        file_put_contents($this->testDir . 'test2.txt', 'World!');

        $size = $this->handler->getDirectorySize($this->testDir);

        $this->assertEquals(11, $size); // 5 + 6 バイト
    }

    public function test_ファイル一覧を取得できる(): void
    {
        file_put_contents($this->testDir . 'file1.txt', 'content1');
        file_put_contents($this->testDir . 'file2.txt', 'content2');

        $files = $this->handler->listFiles();

        $this->assertCount(2, $files);

        $names = array_column($files, 'name');
        $this->assertContains('file1.txt', $names);
        $this->assertContains('file2.txt', $names);
    }

    public function test_ファイル一覧に詳細情報が含まれる(): void
    {
        file_put_contents($this->testDir . 'test.txt', 'hello');

        $files = $this->handler->listFiles();

        $this->assertArrayHasKey('name', $files[0]);
        $this->assertArrayHasKey('path', $files[0]);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertArrayHasKey('formatted_size', $files[0]);
        $this->assertArrayHasKey('modified', $files[0]);
        $this->assertArrayHasKey('extension', $files[0]);
    }

    public function test_ファイルを削除できる(): void
    {
        $filePath = $this->testDir . 'delete_me.txt';
        file_put_contents($filePath, 'to be deleted');

        $result = $this->handler->deleteFile($filePath);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_存在しないファイルの削除は失敗(): void
    {
        $result = $this->handler->deleteFile($this->testDir . 'nonexistent.txt');

        $this->assertFalse($result);

        $errors = $this->handler->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('存在しません', $errors[0]);
    }

    public function test_ディレクトリトラバーサル攻撃を防止(): void
    {
        // テスト用ファイルをuploadDirectory外に作成
        $outsideFile = sys_get_temp_dir() . '/outside_test_' . uniqid() . '.txt';
        file_put_contents($outsideFile, 'secret data');

        // ディレクトリトラバーサルを試みる
        $traversalPath = $this->testDir . '../' . basename($outsideFile);

        $result = $this->handler->deleteFile($traversalPath);

        $this->assertFalse($result);

        $errors = $this->handler->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('許可されていない', $errors[0]);

        // 外部ファイルが削除されていないことを確認
        $this->assertFileExists($outsideFile);

        // クリーンアップ
        unlink($outsideFile);
    }

    public function test_空のディレクトリを一覧取得(): void
    {
        $files = $this->handler->listFiles();

        $this->assertEmpty($files);
    }

    public function test_エラー一覧を取得できる(): void
    {
        // 存在しないファイルを削除しようとしてエラーを発生させる
        $this->handler->deleteFile($this->testDir . 'nonexistent.txt');

        $errors = $this->handler->getErrors();

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }
}
