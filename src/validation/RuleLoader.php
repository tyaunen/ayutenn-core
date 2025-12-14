<?php

namespace ayutenn\core\validation;

/**
 * JSONファイルからルール定義を読み込むユーティリティ
 */
class RuleLoader
{
    /**
     * 単一のJSONファイルからルールを読み込む
     *
     * @throws \InvalidArgumentException ファイルが存在しない場合
     * @throws \RuntimeException JSONパースに失敗した場合
     */
    public static function fromJsonFile(string $filePath): ValidationRule
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("ルールファイルが見つかりません: {$filePath}");
        }

        $json = file_get_contents($filePath);

        if ($json === false) {
            throw new \RuntimeException("ファイルの読み込みに失敗しました: {$filePath}");
        }

        return self::fromJsonString($json);
    }

    /**
     * JSON文字列からルールを生成
     *
     * @throws \RuntimeException JSONパースに失敗した場合
     */
    public static function fromJsonString(string $json): ValidationRule
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("JSONのパースに失敗しました: " . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * 配列からルールを生成
     */
    public static function fromArray(array $data): ValidationRule
    {
        return ValidationRule::fromArray($data);
    }
}
