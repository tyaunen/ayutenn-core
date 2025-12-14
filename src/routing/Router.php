<?php
namespace ayutenn\core\routing;

use ayutenn\core\routing\Route;
use ayutenn\core\config\Config;

/**
 * 【概要】
 * ルーター
 *
 * 【解説】
 * ルーターです。
 * 与えられたRouteをもとに、リクエストURIとHTTPメソッドに基づいて適切なアクションを実行します。
 *
 * 【無駄口】
 * リソースを隠す目的でルーターを使うことにしているが、俺はそもそもルーターがあんま好きではない。
 * URLがリソースを直接指定している方が直感的だと思う。
 * そういう人間が書いているルーターだから、必然機能は最低限である。
 *
 * 一番大きいところでは、動的ルーティングに対応していない。
 * ちょっと調べると要するにSEOに有利だとか、1つのURLが単一のリソースを指し示しているべきとか、
 * 色々理由は出てくるんだがSEOやURLのかっこよさについて気にしない限りは不要に思える。
 *
 */
class Router
{
    /** @var Route[] 登録済みルートの配列 */
    private array $routes = [];

    /** @var string すべてのパスに付与されるプレフィックス */
    private string $groupPrefix;

    /**
     * コンストラクタ
     *
     * @param string $route_dir ルート定義ファイルが格納されているディレクトリのパス
     * @param string $path_prefix routerのプレフィクス
     */
    public function __construct(string $route_dir, string $path_prefix)
    {
        // すべてのパスの接頭辞
        // /view/topとか書いた場合、/ayutenn/view/top みたいな形にしてくれる
        $this->groupPrefix = $path_prefix;

        // ルート定義ファイルの読み込み
        $route_dir = rtrim($route_dir, '/');
        $this->loadAllRoutes($route_dir);
    }

    /**
     * routesディレクトリ内の全てのルート定義ファイルをrequireで読み込み、
     * returnされた配列をマージして返す
     *
     * @param string $routes_dir ルート定義ファイルが格納されているディレクトリのパス
     * @return void
     */
    private function loadAllRoutes(string $routes_dir): void
    {
        $all = [];
        foreach (glob("{$routes_dir}/*.php") as $file) {
            $routes = require $file;
            if (is_array($routes)) {
                $all = array_merge($all, $routes);
            }else {
                throw new \Exception("エラー: {$file} のルート定義が不正です。ルート定義はRouteとRouteGroupを含む配列を返す必要があります。");
            }
        }

        $this->registerRoutes($all);
    }

    /**
     * ルート定義を登録する
     *
     * @param array<int, Route|RouteGroup> $routes ルート定義の配列
     * @param string $base_path ここまでのパス
     * @param array<int, Middleware> $parent_middleware 親が持つすべてのミドルウェア
     * @return void
     */
    public function registerRoutes(
        array $routes,
        string $base_path = '',
        array $parent_middleware = []
    ): void
    {
        foreach ($routes as $route) {
            if ($route instanceof RouteGroup) {
                // グループの場合、ミドルウェアを保持して再帰する
                $group_prefix = $base_path . $route->group;
                $group_middleware = array_merge($parent_middleware, $route->middleware ?? []);
                $this->registerRoutes($route->routes, $group_prefix, $group_middleware);

            } else if ($route instanceof Route) {
                // 単一ルートの場合、そのまま登録
                $method = strtolower($route->method);
                $path = $base_path . $route->path;
                $middleware = array_merge($parent_middleware, $route->middleware ?? []);
                $this->addRoute($method, $path, $route->routeAction, $route->targetResourceName, $middleware);

            } else {
                throw new \Exception("
                    エラー: Route, RouteGroup以外のインスタンスを登録しようとしました。
                    routeファイルが返す配列は、RouteとRouteGroupのインスタンスのみを含む必要があります。
                ");
            }
        }
    }

    /**
     * URIを正規化する
     * @param string $uri
     * @return string
     */
    private function normalizeUri(string $uri): string
    {
        return rtrim(parse_url($uri, PHP_URL_PATH), '/') ?: '/';
    }

    /**
     * ルートを追加する
     *
     * @param string $method HTTPメソッド "GET", "POST" など
     * @param string $path パス
     * @param string $callback_type コールバックタイプ "controller" or "view" or "api" or "redirect"
     * @param string $target_resource_name コールバックするファイル
     * @param array<int, Middleware> $middleware ミドルウェア 自分の子のすべてに適用される
     */
    private function addRoute(string $method, string $path, string $callback_type, string $target_resource_name, array $middleware = []): void
    {
        $full_path = $this->groupPrefix . $path;
        $this->routes[] = new Route($method, $full_path, $callback_type, $target_resource_name, $middleware);
    }

    /**
     * リクエストをディスパッチする
     *
     * @param string $request_method リクエストのHTTPメソッド "GET", "POST" など
     * @param string $request_uri リクエストURI
     * @throws \Exception
     */
    public function dispatch(string $request_method, string $request_uri): void
    {
        $request_method = strtoupper($request_method);
        $request_uri = $this->normalizeUri($request_uri);

        foreach ($this->routes as $route) {
            // URLとリクエスト方法にマッチするルートを探す
            if ($route->matches($request_method, $request_uri)) {
                $route->executeRouteAction();
                return;
            }
        }

        // マッチしなかったら404ページを返す
        Route::showNotFoundPage();
    }
}
