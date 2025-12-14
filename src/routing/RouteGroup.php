<?php
namespace ayutenn\core\routing;

/**
 * 【概要】
 * ルートグループクラス
 *
 * 【解説】
 * ルートのグループを定義するクラスです。
 * グループ内のすべてのルートに共通のミドルウェアを適用できます。
 *
 * ルートグループは、特定のパスに対して一括で設定を行うために使用します。
 * 例えば、認証が必要なAPIやビューをまとめて管理することができます。
 *
 * 【無駄口】
 * とくにいうことなし
 *
 */
class RouteGroup
{
    /**
     * ルートグループの定義
     *
     * @param string $group グループのパス（プレフィックス）
     * @param array<int, Route|RouteGroup> $routes ルートまたはサブグループの配列
     * @param array<int, Middleware> $middleware ミドルウェアの配列（子のすべてに適用される）
     */
    public function __construct(
        public string $group = '/',
        public array $routes = [],
        public array $middleware = [],
    ) {}
}
