<?php

namespace tests\unit\routing;

use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;
use ayutenn\core\routing\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * テスト用ミドルウェア
 */
class GroupTestMiddleware extends Middleware
{
    public function shouldOverride(): bool
    {
        return false;
    }
}

class RouteGroupTest extends TestCase
{
    public function test_デフォルト値でRouteGroupを作成できる(): void
    {
        $group = new RouteGroup();

        $this->assertEquals('/', $group->group);
        $this->assertEquals([], $group->routes);
        $this->assertEquals([], $group->middleware);
    }

    public function test_カスタムグループプレフィックスを設定できる(): void
    {
        $group = new RouteGroup(group: '/admin');

        $this->assertEquals('/admin', $group->group);
    }

    public function test_ルートを含むグループを作成できる(): void
    {
        $route1 = new Route(path: '/dashboard');
        $route2 = new Route(path: '/users');

        $group = new RouteGroup(
            group: '/admin',
            routes: [$route1, $route2]
        );

        $this->assertCount(2, $group->routes);
        $this->assertSame($route1, $group->routes[0]);
        $this->assertSame($route2, $group->routes[1]);
    }

    public function test_ミドルウェアを設定できる(): void
    {
        $middleware = new GroupTestMiddleware();
        $group = new RouteGroup(
            group: '/admin',
            middleware: [$middleware]
        );

        $this->assertCount(1, $group->middleware);
        $this->assertSame($middleware, $group->middleware[0]);
    }

    public function test_ネストしたRouteGroupを作成できる(): void
    {
        $innerRoute = new Route(path: '/settings');
        $innerGroup = new RouteGroup(
            group: '/config',
            routes: [$innerRoute]
        );

        $outerGroup = new RouteGroup(
            group: '/admin',
            routes: [$innerGroup]
        );

        $this->assertCount(1, $outerGroup->routes);
        $this->assertInstanceOf(RouteGroup::class, $outerGroup->routes[0]);
    }

    public function test_複数のミドルウェアを設定できる(): void
    {
        $middleware1 = new GroupTestMiddleware();
        $middleware2 = new GroupTestMiddleware();

        $group = new RouteGroup(
            middleware: [$middleware1, $middleware2]
        );

        $this->assertCount(2, $group->middleware);
    }

    public function test_RouteとRouteGroupを混在させられる(): void
    {
        $route = new Route(path: '/home');
        $subGroup = new RouteGroup(group: '/api');

        $group = new RouteGroup(
            group: '/app',
            routes: [$route, $subGroup]
        );

        $this->assertCount(2, $group->routes);
        $this->assertInstanceOf(Route::class, $group->routes[0]);
        $this->assertInstanceOf(RouteGroup::class, $group->routes[1]);
    }
}
