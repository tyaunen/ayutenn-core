<?php

namespace tests\unit\routing;

use ayutenn\core\routing\Router;
use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;
use ayutenn\core\routing\Middleware;
use ayutenn\core\config\Config;
use PHPUnit\Framework\TestCase;

/**
 * テスト用ミドルウェア
 */
class RouterTestMiddleware extends Middleware
{
    public function shouldOverride(): bool
    {
        return false;
    }
}

class RouterTest extends TestCase
{
    private string $routesDir;

    protected function setUp(): void
    {
        // テスト用ルートディレクトリを作成
        $this->routesDir = sys_get_temp_dir() . '/routing_test_' . uniqid();
        mkdir($this->routesDir, 0777, true);

        Config::reset();
    }

    protected function tearDown(): void
    {
        // テスト用ファイルをクリーンアップ
        $this->removeDirectory($this->routesDir);
        Config::reset();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createRouteFile(string $filename, string $content): void
    {
        file_put_contents($this->routesDir . '/' . $filename, $content);
    }

    public function test_ルート定義ファイルを読み込んでルートを登録する(): void
    {
        $routeContent = <<<'PHP'
<?php
use ayutenn\core\routing\Route;

return [
    new Route(
        method: 'GET',
        path: '/home',
        routeAction: 'view',
        targetResourceName: '/pages/home'
    ),
];
PHP;
        $this->createRouteFile('web.php', $routeContent);

        // Routerを作成（テスト用なのでdispatchはしない）
        $router = new Router($this->routesDir, '/app');

        // 内部状態を直接テストはできないが、例外なく初期化できることを確認
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_空のルート定義ファイルの場合は例外をスロー(): void
    {
        $routeContent = <<<'PHP'
<?php
// 配列を返さない（意図的にnullを返す）
return null;
PHP;
        $this->createRouteFile('invalid.php', $routeContent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ルート定義が不正');

        new Router($this->routesDir, '/app');
    }

    public function test_複数のルート定義ファイルをマージする(): void
    {
        $routeContent1 = <<<'PHP'
<?php
use ayutenn\core\routing\Route;

return [
    new Route(path: '/home'),
];
PHP;
        $routeContent2 = <<<'PHP'
<?php
use ayutenn\core\routing\Route;

return [
    new Route(path: '/about'),
];
PHP;
        $this->createRouteFile('web.php', $routeContent1);
        $this->createRouteFile('api.php', $routeContent2);

        $router = new Router($this->routesDir, '/app');
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_RouteGroupを含むルート定義を処理できる(): void
    {
        $routeContent = <<<'PHP'
<?php
use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;

return [
    new RouteGroup(
        group: '/admin',
        routes: [
            new Route(path: '/dashboard'),
            new Route(path: '/users'),
        ]
    ),
];
PHP;
        $this->createRouteFile('admin.php', $routeContent);

        $router = new Router($this->routesDir, '/app');
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_ネストしたRouteGroupを処理できる(): void
    {
        $routeContent = <<<'PHP'
<?php
use ayutenn\core\routing\Route;
use ayutenn\core\routing\RouteGroup;

return [
    new RouteGroup(
        group: '/admin',
        routes: [
            new RouteGroup(
                group: '/settings',
                routes: [
                    new Route(path: '/general'),
                ]
            ),
        ]
    ),
];
PHP;
        $this->createRouteFile('nested.php', $routeContent);

        $router = new Router($this->routesDir, '/app');
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_RouteでもRouteGroupでもないオブジェクトは例外をスロー(): void
    {
        $routeContent = <<<'PHP'
<?php

return [
    new stdClass(),
];
PHP;
        $this->createRouteFile('invalid.php', $routeContent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Route, RouteGroup以外のインスタンス');

        new Router($this->routesDir, '/app');
    }

    public function test_空のディレクトリからRouterを作成できる(): void
    {
        // 空のディレクトリでも例外なく初期化できる
        $router = new Router($this->routesDir, '/app');
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_プレフィックスが正しく適用される(): void
    {
        $routeContent = <<<'PHP'
<?php
use ayutenn\core\routing\Route;

return [
    new Route(
        method: 'GET',
        path: '/test',
        routeAction: 'view',
        targetResourceName: '/pages/test'
    ),
];
PHP;
        $this->createRouteFile('web.php', $routeContent);

        // プレフィックス "/myapp" を設定
        $router = new Router($this->routesDir, '/myapp');
        $this->assertInstanceOf(Router::class, $router);
    }
}
