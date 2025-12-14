<?php

namespace tests\unit\routing;

use ayutenn\core\routing\Route;
use ayutenn\core\routing\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * テスト用ミドルウェア - 上書きする
 */
class TestOverrideMiddleware extends Middleware
{
    public bool $handleCalled = false;

    public function __construct()
    {
        parent::__construct(
            routeAction: 'redirect',
            targetResourceName: '/login'
        );
    }

    public function handle(): void
    {
        $this->handleCalled = true;
    }

    public function shouldOverride(): bool
    {
        return true;
    }
}

/**
 * テスト用ミドルウェア - 上書きしない
 */
class TestPassMiddleware extends Middleware
{
    public bool $handleCalled = false;

    public function handle(): void
    {
        $this->handleCalled = true;
    }

    public function shouldOverride(): bool
    {
        return false;
    }
}

class RouteTest extends TestCase
{
    public function test_デフォルト値でRouteを作成できる(): void
    {
        $route = new Route();

        $this->assertEquals('GET', $route->method);
        $this->assertEquals('', $route->path); // '/' は末尾が削除されて '' になる
        $this->assertEquals('view', $route->routeAction);
        $this->assertEquals('top', $route->targetResourceName);
        $this->assertEquals([], $route->middleware);
    }

    public function test_カスタム値でRouteを作成できる(): void
    {
        $route = new Route(
            method: 'POST',
            path: '/api/users',
            routeAction: 'api',
            targetResourceName: '/users/CreateUser'
        );

        $this->assertEquals('POST', $route->method);
        $this->assertEquals('/api/users', $route->path);
        $this->assertEquals('api', $route->routeAction);
        $this->assertEquals('/users/CreateUser', $route->targetResourceName);
    }

    public function test_パス末尾のスラッシュは削除される(): void
    {
        $route = new Route(path: '/users/');
        $this->assertEquals('/users', $route->path);
    }

    public function test_不正なrouteActionは例外をスローする(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('不正なルートアクション');

        new Route(routeAction: 'invalid');
    }

    public function test_matchesはメソッドとパスが一致する場合trueを返す(): void
    {
        $route = new Route(
            method: 'GET',
            path: '/users'
        );

        $this->assertTrue($route->matches('GET', '/users'));
    }

    public function test_matchesはメソッドが異なる場合falseを返す(): void
    {
        $route = new Route(
            method: 'GET',
            path: '/users'
        );

        $this->assertFalse($route->matches('POST', '/users'));
    }

    public function test_matchesはパスが異なる場合falseを返す(): void
    {
        $route = new Route(
            method: 'GET',
            path: '/users'
        );

        $this->assertFalse($route->matches('GET', '/posts'));
    }

    public function test_matchesはリクエストURIの末尾スラッシュを無視する(): void
    {
        $route = new Route(
            method: 'GET',
            path: '/users'
        );

        $this->assertTrue($route->matches('GET', '/users/'));
    }

    public function test_matchesはHTTPメソッドの大文字小文字を無視する(): void
    {
        $route = new Route(
            method: 'get',
            path: '/users'
        );

        $this->assertTrue($route->matches('GET', '/users'));
    }

    public function test_ミドルウェアを設定できる(): void
    {
        $middleware = new TestPassMiddleware();
        $route = new Route(
            middleware: [$middleware]
        );

        $this->assertCount(1, $route->middleware);
        $this->assertSame($middleware, $route->middleware[0]);
    }

    public function test_validなrouteActionのリスト(): void
    {
        $validActions = ['controller', 'view', 'api', 'redirect'];

        foreach ($validActions as $action) {
            $route = new Route(routeAction: $action);
            $this->assertEquals($action, $route->routeAction);
        }
    }
}
