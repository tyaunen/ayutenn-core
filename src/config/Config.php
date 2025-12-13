<?php

declare(strict_types=1);

namespace ayutenn\core\config;

/**
 * 設定管理クラス
 *
 * JSONファイルから設定を読み込み、キーでアクセスを提供する静的クラス。
 * 複数のJSONファイルを読み込んだ場合、後から読み込んだ設定が上書きされる。
 * JSONはネストしないフラットな構造を前提とする。
 */
class Config
{
    /**
     * 設定データを保持する配列
     */
    private static array $config = [];

    /**
     * JSONファイルから設定を読み込む
     *
     * 既存の設定に対して上書きマージを行う。
     *
     * @param string $path JSONファイルのパス
     * @throws \InvalidArgumentException ファイルが存在しない場合
     * @throws \RuntimeException JSONのパースに失敗した場合
     */
    public static function loadFromJson(string $path): void
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Config file not found: {$path}");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse JSON: " . json_last_error_msg());
        }

        self::$config = array_merge(self::$config, $data);
    }

    /**
     * 設定値を取得する
     *
     * @param string $key 設定キー
     * @return mixed 設定値
     * @throws \InvalidArgumentException キーが存在しない場合
     */
    public static function get(string $key): mixed
    {
        if (!array_key_exists($key, self::$config)) {
            throw new \InvalidArgumentException("Config key not found: {$key}");
        }

        return self::$config[$key];
    }

    /**
     * 設定値を動的にセットする（テスト用）
     *
     * @param string $key 設定キー
     * @param mixed $value 設定する値
     */
    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }

    /**
     * 全ての設定をクリアする（テスト用）
     */
    public static function reset(): void
    {
        self::$config = [];
    }
}
