<?php
namespace ayutenn\core\migration;

/**
 * 【概要】
 * テーブル定義ローダー
 *
 * 【解説】
 * JSONファイルからテーブル定義を読み込み、TableDefinitionオブジェクトを生成する。
 */
class TableDefinitionLoader
{
    /**
     * 単一のJSONファイルからテーブル定義を読み込む
     *
     * @param string $jsonPath JSONファイルのパス
     * @return TableDefinition
     * @throws \InvalidArgumentException ファイルが存在しない、またはJSONが不正な場合
     */
    public function load(string $jsonPath): TableDefinition
    {
        if (!file_exists($jsonPath)) {
            throw new \InvalidArgumentException("ファイルが見つかりません: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("JSONパースエラー: " . json_last_error_msg());
        }

        return TableDefinition::fromArray($data);
    }

    /**
     * ディレクトリ内のすべてのJSONファイルからテーブル定義を読み込む
     *
     * @param string $directory ディレクトリパス
     * @return TableDefinition[] テーブル名をキーとした配列
     * @throws \InvalidArgumentException ディレクトリが存在しない場合
     */
    public function loadFromDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("ディレクトリが見つかりません: {$directory}");
        }

        $tables = [];
        $files = glob($directory . '/*.json');

        foreach ($files as $file) {
            $table = $this->load($file);
            $tables[$table->getName()] = $table;
        }

        return $tables;
    }
}
