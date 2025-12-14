<?php

namespace tests\unit\utils;

use ayutenn\core\utils\Uuid;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    public function test_UUIDv7形式で生成される(): void
    {
        $uuid = Uuid::generateUuid7();

        // UUIDv7形式の正規表現: xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
        // y は 8, 9, a, b のいずれか（バリアント10xx）
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

        $this->assertMatchesRegularExpression($pattern, $uuid);
    }

    public function test_バージョン7のビットが正しく設定される(): void
    {
        $uuid = Uuid::generateUuid7();

        // バージョンビット（14文字目）が '7' であることを確認
        $parts = explode('-', $uuid);
        $this->assertStringStartsWith('7', $parts[2]);
    }

    public function test_バリアントビットがRFC9562準拠で設定される(): void
    {
        $uuid = Uuid::generateUuid7();

        // バリアントビット（4番目のセクションの先頭）が 8, 9, a, b のいずれかであることを確認
        $parts = explode('-', $uuid);
        $variantChar = $parts[3][0];

        $this->assertContains($variantChar, ['8', '9', 'a', 'b']);
    }

    public function test_生成されるUUIDはユニーク(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = Uuid::generateUuid7();
        }

        // 全て異なることを確認
        $unique = array_unique($uuids);
        $this->assertCount(100, $unique);
    }

    public function test_時系列順にソート可能(): void
    {
        $uuid1 = Uuid::generateUuid7();
        usleep(1000); // 1ms待機
        $uuid2 = Uuid::generateUuid7();
        usleep(1000);
        $uuid3 = Uuid::generateUuid7();

        // 文字列比較で時系列順になることを確認
        $this->assertTrue($uuid1 < $uuid2);
        $this->assertTrue($uuid2 < $uuid3);
    }

    public function test_UUID長さが36文字(): void
    {
        $uuid = Uuid::generateUuid7();
        $this->assertEquals(36, strlen($uuid));
    }
}
