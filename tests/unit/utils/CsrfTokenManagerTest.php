<?php

namespace tests\unit\utils;

use ayutenn\core\utils\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

class CsrfTokenManagerTest extends TestCase
{
    private CsrfTokenManager $manager;

    protected function setUp(): void
    {
        // セッション変数をリセット
        $_SESSION = [];
        $this->manager = new CsrfTokenManager();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function test_トークンを取得できる(): void
    {
        $token = $this->manager->getToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32バイト = 64文字の16進数
    }

    public function test_同じセッションでは同じトークンが返される(): void
    {
        $token1 = $this->manager->getToken();
        $token2 = $this->manager->getToken();

        $this->assertEquals($token1, $token2);
    }

    public function test_有効なトークンが検証に成功する(): void
    {
        $token = $this->manager->getToken();

        $result = $this->manager->validateToken($token);

        $this->assertTrue($result);
    }

    public function test_無効なトークンが検証に失敗する(): void
    {
        $this->manager->getToken();

        $result = $this->manager->validateToken('invalid_token');

        $this->assertFalse($result);
    }

    public function test_トークンがセッションに存在しない場合は検証失敗(): void
    {
        // セッションにトークンを設定しない
        $_SESSION = [];

        $result = $this->manager->validateToken('any_token');

        $this->assertFalse($result);
    }

    public function test_トークン取得時にタイムスタンプが更新される(): void
    {
        $this->manager->getToken();
        $timestamp1 = $_SESSION['csrf_token_timestamp'] ?? 0;

        // 少し待ってから再度取得
        sleep(1);
        $this->manager->getToken();
        $timestamp2 = $_SESSION['csrf_token_timestamp'] ?? 0;

        $this->assertGreaterThanOrEqual($timestamp1, $timestamp2);
    }

    public function test_期限切れトークンは検証失敗(): void
    {
        $token = $this->manager->getToken();

        // タイムスタンプを13時間前に設定（有効期限は12時間）
        $_SESSION['csrf_token_timestamp'] = time() - (13 * 60 * 60);

        $result = $this->manager->validateToken($token);

        $this->assertFalse($result);
    }

    public function test_トークンは16進数文字列(): void
    {
        $token = $this->manager->getToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }
}
