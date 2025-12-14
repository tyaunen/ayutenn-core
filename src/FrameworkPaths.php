<?php

declare(strict_types=1);

namespace ayutenn\core;

/**
 * フレームワークパス設定クラス
 *
 * 親プロジェクトのbootstrap処理から受け取ったパス設定を一元管理する静的クラス。
 * このフレームワークを利用するプロジェクトは、bootstrap時に init() を呼び出して
 * コントローラー、ビュー、APIなどのディレクトリパスを設定する必要がある。
 *
 * 使用例:
 * ```php
 * // 親プロジェクトの bootstrap.php
 * use ayutenn\core\FrameworkPaths;
 *
 * FrameworkPaths::init([
 *     'controllerDir' => __DIR__ . '/controllers',
 *     'viewDir' => __DIR__ . '/views',
 *     'apiDir' => __DIR__ . '/api',
 *     'pathRoot' => '/myapp',
 *     'validationRulesDir' => __DIR__ . '/rules',
 * ]);
 * ```
 */
class FrameworkPaths
{
    /**
     * パス設定を保持する配列
     */
    private static ?array $paths = null;

    /**
     * 必須の設定キー
     */
    private const REQUIRED_KEYS = [
        'controllerDir',
        'viewDir',
        'apiDir',
        'pathRoot',
        'validationRulesDir',
    ];

    /**
     * フレームワークパスを初期化する
     *
     * 親プロジェクトのbootstrap処理から呼び出されることを想定。
     * 必須キーが不足している場合は例外をスローする。
     *
     * @param array $config パス設定の連想配列
     * @throws \InvalidArgumentException 必須キーが見つからない場合
     */
    public static function init(array $config): void
    {
        $missing = [];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($config[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                '必須のパス設定が見つかりません: ' . implode(', ', $missing)
            );
        }

        self::$paths = $config;
    }

    /**
     * 初期化済みかどうかを確認
     */
    public static function isInitialized(): bool
    {
        return self::$paths !== null;
    }

    /**
     * 初期化されていない場合に例外をスロー
     *
     * @throws \RuntimeException 未初期化の場合
     */
    private static function ensureInitialized(): void
    {
        if (self::$paths === null) {
            throw new \RuntimeException(
                'FrameworkPaths が初期化されていません。' .
                '親プロジェクトの bootstrap 処理で FrameworkPaths::init() を呼び出してください。'
            );
        }
    }

    /**
     * コントローラーディレクトリを取得
     *
     * @return string コントローラーディレクトリの絶対パス
     */
    public static function getControllerDir(): string
    {
        self::ensureInitialized();
        return self::$paths['controllerDir'];
    }

    /**
     * ビューディレクトリを取得
     *
     * @return string ビューディレクトリの絶対パス
     */
    public static function getViewDir(): string
    {
        self::ensureInitialized();
        return self::$paths['viewDir'];
    }

    /**
     * APIディレクトリを取得
     *
     * @return string APIディレクトリの絶対パス
     */
    public static function getApiDir(): string
    {
        self::ensureInitialized();
        return self::$paths['apiDir'];
    }

    /**
     * パスルート（URLのベースパス）を取得
     *
     * @return string パスルート（例: '/myapp'）
     */
    public static function getPathRoot(): string
    {
        self::ensureInitialized();
        return self::$paths['pathRoot'];
    }

    /**
     * バリデーションルールディレクトリを取得
     *
     * @return string バリデーションルールディレクトリの絶対パス
     */
    public static function getValidationRulesDir(): string
    {
        self::ensureInitialized();
        return self::$paths['validationRulesDir'];
    }

    /**
     * 404ページのビューファイル名を取得
     *
     * @return string|null ビューファイル名（設定されていない場合は null）
     */
    public static function getNotFoundView(): ?string
    {
        self::ensureInitialized();
        return self::$paths['notFoundView'] ?? null;
    }

    /**
     * 設定をリセットする（テスト用）
     */
    public static function reset(): void
    {
        self::$paths = null;
    }
}
