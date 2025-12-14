<?php

namespace tests\unit\utils;

use ayutenn\core\utils\DiscordWebhook;
use ayutenn\core\config\Config;
use PHPUnit\Framework\TestCase;

/**
 * DiscordWebhookのテスト
 *
 * 注意: 実際のWebhook送信はモック化できないため、
 * 主にオブジェクト生成と戻り値の構造テストを行います。
 */
class DiscordWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        // テスト用のConfig設定
        Config::set('DISCORD_WEBHOOK_USER_NAME', 'TestBot');
        Config::set('DISCORD_WEBHOOK_AVATAR_ICON', 'https://example.com/avatar.png');
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    public function test_インスタンスを生成できる(): void
    {
        $embeds = [
            [
                'title' => 'テスト',
                'description' => 'テストメッセージ',
                'color' => 5814783
            ]
        ];

        $webhook = new DiscordWebhook($embeds);

        $this->assertInstanceOf(DiscordWebhook::class, $webhook);
    }

    public function test_空のembedsでインスタンス生成できる(): void
    {
        $webhook = new DiscordWebhook([]);

        $this->assertInstanceOf(DiscordWebhook::class, $webhook);
    }

    public function test_複数のembedsでインスタンス生成できる(): void
    {
        $embeds = [
            ['title' => 'Embed1', 'description' => 'First'],
            ['title' => 'Embed2', 'description' => 'Second'],
            ['title' => 'Embed3', 'description' => 'Third']
        ];

        $webhook = new DiscordWebhook($embeds);

        $this->assertInstanceOf(DiscordWebhook::class, $webhook);
    }

    public function test_無効なURLへの送信は失敗を返す(): void
    {
        $webhook = new DiscordWebhook([['title' => 'Test']]);

        // 存在しないURLへ送信（高速で失敗する）
        $result = $webhook->sendWebhook('http://127.0.0.1:1/webhook');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('http_code', $result);
        $this->assertArrayHasKey('error', $result);

        // 無効なURLなので失敗するはず
        $this->assertFalse($result['success']);
    }

    public function test_戻り値に必要なキーと型が含まれている(): void
    {
        $webhook = new DiscordWebhook([]);

        // ローカルホストの存在しないエンドポイント（高速で失敗する）
        $result = $webhook->sendWebhook('http://127.0.0.1:1/test');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('http_code', $result);
        $this->assertArrayHasKey('error', $result);

        $this->assertIsBool($result['success']);
        $this->assertIsInt($result['http_code']);
        $this->assertIsString($result['error']);
    }
}
