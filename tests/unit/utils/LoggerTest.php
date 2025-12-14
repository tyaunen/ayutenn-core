<?php

namespace tests\unit\utils;

use ayutenn\core\utils\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $testLogDir;
    private ?Logger $logger;

    protected function setUp(): void
    {
        // テスト用のログディレクトリを作成
        $this->testLogDir = sys_get_temp_dir() . '/test_logs_' . uniqid() . '/';
        if (!is_dir($this->testLogDir)) {
            mkdir($this->testLogDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // テスト用ログファイルを削除
        if (is_dir($this->testLogDir)) {
            $files = glob($this->testLogDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testLogDir);
        }
    }

    public function test_ログインスタンスを取得できる(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function test_異なるパスで異なるインスタンスが作成される(): void
    {
        $logger1 = Logger::setup($this->testLogDir . 'log1/');
        $logger2 = Logger::setup($this->testLogDir . 'log2/');

        $this->assertNotSame($logger1, $logger2);

        // クリーンアップ
        if (is_dir($this->testLogDir . 'log1/')) {
            rmdir($this->testLogDir . 'log1/');
        }
        if (is_dir($this->testLogDir . 'log2/')) {
            rmdir($this->testLogDir . 'log2/');
        }
    }

    public function test_同じパスでは同じインスタンスが返される(): void
    {
        $logger1 = Logger::setup($this->testLogDir);
        $logger2 = Logger::setup($this->testLogDir);

        $this->assertSame($logger1, $logger2);
    }

    public function test_infoログが書き込まれる(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->info('テストメッセージ');

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('テストメッセージ', $content);
    }

    public function test_debugログが書き込まれる(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->debug('デバッグメッセージ');

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[DEBUG]', $content);
    }

    public function test_warningログが書き込まれる(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->warning('警告メッセージ');

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[WARNING]', $content);
    }

    public function test_errorログはスタックトレースを含む(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->error('エラーメッセージ');

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('Stack Trace:', $content);
    }

    public function test_emergencyログが書き込まれる(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->emergency('緊急メッセージ');

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[EMERGENCY]', $content);
        $this->assertStringContainsString('緊急メッセージ', $content);
    }

    public function test_コンテキスト情報がJSON形式で保存される(): void
    {
        $logger = Logger::setup($this->testLogDir);
        $logger->info('ユーザーログイン', ['user_id' => 123, 'username' => 'test']);

        $logFile = $this->testLogDir . 'log_' . date('Y-m-d') . '.txt';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('"user_id":123', $content);
        $this->assertStringContainsString('"username":"test"', $content);
    }

    public function test_ログレベル定数が正しく定義されている(): void
    {
        $this->assertEquals(100, Logger::DEBUG);
        $this->assertEquals(200, Logger::INFO);
        $this->assertEquals(250, Logger::NOTICE);
        $this->assertEquals(300, Logger::WARNING);
        $this->assertEquals(400, Logger::ERROR);
        $this->assertEquals(500, Logger::CRITICAL);
        $this->assertEquals(550, Logger::ALERT);
        $this->assertEquals(600, Logger::EMERGENCY);
    }
}
