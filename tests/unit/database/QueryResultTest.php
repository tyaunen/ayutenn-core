<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ayutenn\core\database\QueryResult;

/**
 * QueryResultクラスのテスト
 */
class QueryResultTest extends TestCase
{
    public function test_成功結果を生成できる(): void
    {
        $result = QueryResult::success('処理完了', ['id' => 1]);

        $this->assertTrue($result->isSucceed());
        $this->assertEquals(['id' => 1], $result->getData());
        $this->assertNull($result->getErrorMessage());
        $this->assertEquals('正常終了', $result->getCodeName());
    }

    public function test_エラー結果を生成できる(): void
    {
        $result = QueryResult::error('データベースエラー');

        $this->assertFalse($result->isSucceed());
        $this->assertNull($result->getData());
        $this->assertEquals('【エラー】 データベースエラー', $result->getErrorMessage());
        $this->assertEquals('エラー', $result->getCodeName());
    }

    public function test_警告結果を生成できる(): void
    {
        $result = QueryResult::alert('データが見つかりません');

        $this->assertFalse($result->isSucceed());
        $this->assertEquals('【警告】 データが見つかりません', $result->getErrorMessage());
        $this->assertEquals('警告', $result->getCodeName());
    }

    public function test_デフォルトメッセージで成功結果を生成できる(): void
    {
        $result = QueryResult::success();

        $this->assertTrue($result->isSucceed());
        $this->assertNull($result->getData());
    }

    public function test_デフォルトメッセージでエラー結果を生成できる(): void
    {
        $result = QueryResult::error();

        $this->assertFalse($result->isSucceed());
        $this->assertStringContainsString('不明なエラー', $result->getErrorMessage());
    }

    public function test_データ付きでエラー結果を生成できる(): void
    {
        $errorDetails = ['field' => 'email', 'reason' => 'invalid'];
        $result = QueryResult::error('バリデーションエラー', $errorDetails);

        $this->assertFalse($result->isSucceed());
        $this->assertEquals($errorDetails, $result->getData());
    }
}
