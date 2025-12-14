<?php

namespace tests\unit\requests;

use ayutenn\core\FrameworkPaths;
use ayutenn\core\requests\Api;
use ayutenn\core\utils\Redirect;
use PHPUnit\Framework\TestCase;

/**
 * テスト用の具象APIクラス
 */
class TestApi extends Api
{
    protected array $RequestParameterFormat = [
        'name' => [
            'name' => '名前',
            'format' => [
                'type' => 'string',
                'max_length' => 10,
            ],
            'require' => true,
        ],
        'age' => [
            'name' => '年齢',
            'format' => [
                'type' => 'int',
                'min' => 0,
                'max' => 150,
            ],
            'require' => false,
        ],
    ];

    public bool $mainCalled = false;
    public array $receivedParameter = [];

    public function main(): array
    {
        $this->mainCalled = true;
        $this->receivedParameter = $this->parameter;
        return $this->createResponse(true, ['message' => 'success']);
    }
}

/**
 * バリデーションなしのAPIクラス
 */
class NoValidationApi extends Api
{
    protected array $RequestParameterFormat = [];

    public function main(): array
    {
        return $this->createResponse(true, ['data' => 'test']);
    }
}

class ApiTest extends TestCase
{
    protected function setUp(): void
    {
        Redirect::$isTest = true;
        Redirect::$lastApiResponse = [];
        FrameworkPaths::reset();
        FrameworkPaths::init([
            'controllerDir' => __DIR__ . '/controllers',
            'viewDir' => __DIR__ . '/views',
            'apiDir' => __DIR__ . '/api',
            'pathRoot' => '/app',
            'validationRulesDir' => __DIR__ . '/rules',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Redirect::$isTest = false;
        Redirect::$lastApiResponse = [];
        FrameworkPaths::reset();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
    }

    public function test_バリデーション成功時にmainが実行される(): void
    {
        $_POST = ['name' => 'テスト'];

        $api = new TestApi();
        $api->run();

        $this->assertTrue($api->mainCalled);
        $this->assertEquals(['name' => 'テスト'], $api->receivedParameter);
    }

    public function test_バリデーション成功時のレスポンスが正しい(): void
    {
        $_POST = ['name' => 'テスト'];

        $api = new TestApi();
        $api->run();

        $response = Redirect::$lastApiResponse;
        $this->assertEquals(0, $response['status']);
        $this->assertEquals('success', $response['payload']['message']);
    }

    public function test_必須パラメータが欠けている場合エラーレスポンスを返す(): void
    {
        $_POST = [];

        $api = new TestApi();
        $api->run();

        $response = Redirect::$lastApiResponse;
        $this->assertEquals(9, $response['status']);
        $this->assertEquals('リクエストパラメータにエラーがあります。', $response['payload']['message']);
        $this->assertArrayHasKey('name', $response['payload']['errors']);
    }

    public function test_バリデーションエラー時はmainが実行されない(): void
    {
        $_POST = [];

        $api = new TestApi();
        $api->run();

        $this->assertFalse($api->mainCalled);
    }

    public function test_GETリクエストの場合はGETパラメータを使用(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['name' => 'GETテスト'];

        $api = new TestApi();
        $api->run();

        $this->assertTrue($api->mainCalled);
        $this->assertEquals(['name' => 'GETテスト'], $api->receivedParameter);
    }

    public function test_任意パラメータは省略可能(): void
    {
        $_POST = ['name' => 'テスト'];

        $api = new TestApi();
        $api->run();

        $this->assertTrue($api->mainCalled);
        $this->assertArrayNotHasKey('age', $api->receivedParameter);
    }

    public function test_パラメータが型変換される(): void
    {
        $_POST = ['name' => 'テスト', 'age' => '25'];

        $api = new TestApi();
        $api->run();

        $this->assertTrue($api->mainCalled);
        $this->assertSame(25, $api->receivedParameter['age']);
    }

    public function test_バリデーションフォーマットが空の場合はそのまま成功(): void
    {
        $_POST = ['any' => 'value'];

        $api = new NoValidationApi();
        $api->run();

        $response = Redirect::$lastApiResponse;
        $this->assertEquals(0, $response['status']);
    }

    public function test_createResponseで成功レスポンスを生成(): void
    {
        $_POST = ['name' => 'テスト'];

        $api = new TestApi();
        $api->run();

        $response = Redirect::$lastApiResponse;
        $this->assertEquals(0, $response['status']);
        $this->assertIsArray($response['payload']);
    }

    public function test_文字数超過時にバリデーションエラー(): void
    {
        $_POST = ['name' => 'これは10文字を超える長い名前です'];

        $api = new TestApi();
        $api->run();

        $response = Redirect::$lastApiResponse;
        $this->assertEquals(9, $response['status']);
        $this->assertArrayHasKey('name', $response['payload']['errors']);
    }
}
