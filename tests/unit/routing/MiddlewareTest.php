<?php

namespace tests\unit\routing;

use ayutenn\core\routing\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * テスト用の具象Middlewareクラス - 上書きする
 */
class OverrideMiddleware extends Middleware
{
    public bool $handleCalled = false;

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
 * テスト用の具象Middlewareクラス - 上書きしない
 */
class PassthroughMiddleware extends Middleware
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

class MiddlewareTest extends TestCase
{
    public function test_デフォルトのrouteActionはview(): void
    {
        $middleware = new OverrideMiddleware();
        $this->assertEquals('view', $middleware->routeAction);
    }

    public function test_デフォルトのtargetResourceNameはtop(): void
    {
        $middleware = new OverrideMiddleware();
        $this->assertEquals('top', $middleware->targetResourceName);
    }

    public function test_コンストラクタでrouteActionを設定できる(): void
    {
        $middleware = new OverrideMiddleware(routeAction: 'redirect');
        $this->assertEquals('redirect', $middleware->routeAction);
    }

    public function test_コンストラクタでtargetResourceNameを設定できる(): void
    {
        $middleware = new OverrideMiddleware(targetResourceName: '/login');
        $this->assertEquals('/login', $middleware->targetResourceName);
    }

    public function test_shouldOverrideがtrueを返す場合(): void
    {
        $middleware = new OverrideMiddleware();
        $this->assertTrue($middleware->shouldOverride());
    }

    public function test_shouldOverrideがfalseを返す場合(): void
    {
        $middleware = new PassthroughMiddleware();
        $this->assertFalse($middleware->shouldOverride());
    }

    public function test_handleメソッドが呼び出される(): void
    {
        $middleware = new OverrideMiddleware();
        $middleware->handle();
        $this->assertTrue($middleware->handleCalled);
    }

    public function test_カスタム値で初期化できる(): void
    {
        $middleware = new OverrideMiddleware(
            routeAction: 'controller',
            targetResourceName: '/auth/login'
        );

        $this->assertEquals('controller', $middleware->routeAction);
        $this->assertEquals('/auth/login', $middleware->targetResourceName);
    }
}
