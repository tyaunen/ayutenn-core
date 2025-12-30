<?php
declare(strict_types=1);

namespace ayutenn\core\routing;

/**
 * 【概要】
 * ミドルウェア抽象クラス
 *
 * 【解説】
 * ルートに条件を付与したり、ルート前に処理を行うクラス
 *
 * RouterはURLに一致するRouteを見つけた場合、そのRouteが持つMiddlewareを順番に確認する。
 *
 * 1. まず handle() が呼ばれ、副作用処理（フラッシュメッセージ等）を実行する
 * 2. 次に shouldOverride() で判定し、trueの場合のみRouteの設定を上書きする
 *
 * 【使用例】
 * ログインチェックをするMiddlewareの場合：
 * - handle() で FlashMessage::info("ログインが必要です。"); を実行
 * - shouldOverride() でログイン状態に応じて true/false を返す
 *
 */
abstract class Middleware
{
    /**
     * ルートの定義
     * @param string $routeAction どのファイルを読み込むか "controller" or "view" or "api" or "redirect"
     * @param string $targetResourceName 読み込むファイルの名前（.phpは省略する）
     *               routeAction = "controller" の場合は、同名クラスのrunメソッドを呼び出す
     *               routeAction = "view" の場合は、同名の.phpファイルへ転送する
     *               routeAction = "api" の場合は、同名クラスのrunメソッドを呼び出す
     *               routeAction = "redirect" の場合は、$targetResourceNameにリダイレクト
     */
    public function __construct(
        public string $routeAction = 'view',
        public string $targetResourceName = 'top'
    ) {}

    /**
     * 副作用処理を実行する
     * フラッシュメッセージの設定など、ルート判定前に行いたい処理を記述する
     *
     * @return void
     */
    public function handle(): void
    {
        // デフォルトでは何もしない。サブクラスでオーバーライドして使用する
    }

    /**
     * ルートを上書きすべきかどうかを判定する
     * trueを返した場合、RouteのrouteActionとtargetResourceNameがこのMiddlewareの値で上書きされる
     *
     * @return bool
     */
    abstract public function shouldOverride(): bool;
}
