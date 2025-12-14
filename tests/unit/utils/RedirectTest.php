<?php

namespace tests\unit\utils;

use ayutenn\core\utils\Redirect;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
{
    protected function setUp(): void
    {
        // テストモードを有効に
        Redirect::$isTest = true;
        Redirect::$lastRedirectUrl = '';
        Redirect::$lastApiResponse = [];
    }

    protected function tearDown(): void
    {
        // リセット
        Redirect::$isTest = false;
        Redirect::$lastRedirectUrl = '';
        Redirect::$lastApiResponse = [];
    }

    public function test_リダイレクト先URLが保存される(): void
    {
        Redirect::redirect('/dashboard');

        $this->assertEquals('/dashboard', Redirect::$lastRedirectUrl);
    }

    public function test_GETパラメータ付きでリダイレクトできる(): void
    {
        Redirect::redirect('/search', ['q' => 'test', 'page' => 1]);

        $this->assertEquals('/search?q=test&page=1', Redirect::$lastRedirectUrl);
    }

    public function test_既存クエリパラメータがある場合はアンパサンドで連結(): void
    {
        Redirect::redirect('/search?existing=value', ['new' => 'param']);

        $this->assertEquals('/search?existing=value&new=param', Redirect::$lastRedirectUrl);
    }

    public function test_APIレスポンスが保存される(): void
    {
        $response = ['status' => 1, 'payload' => ['message' => 'success']];
        Redirect::apiResponse($response);

        $this->assertEquals($response, Redirect::$lastApiResponse);
    }

    public function test_APIレスポンスのデフォルト値(): void
    {
        Redirect::apiResponse();

        $this->assertEquals(['status' => 0, 'payload' => ''], Redirect::$lastApiResponse);
    }

    public function test_showメソッドでファイルパスが保存される(): void
    {
        Redirect::show('/path/to/template.php');

        $this->assertEquals('/path/to/template.php', Redirect::$lastRedirectUrl);
    }
}
