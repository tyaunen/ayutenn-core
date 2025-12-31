<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

/**
 * 【概要】
 * テーブル定義ローダー
 *
 * 【解説】
 * JSONファイルからテーブル定義を読み込み、TableDefinitionオブジェクトを生成する。
 * ルールディレクトリが指定されている場合、カラム定義のformatキーを解決する。
 */
class TableDefinitionLoader
{
    private ?RuleToColumnConverter $converter = null;

    /**
     * コンストラクタ
     *
     * @param string|null $rulesDirectory ルールファイルのディレクトリパス（省略可）
     */
    public function __construct(?string $rulesDirectory = null)
    {
        if ($rulesDirectory !== null && is_dir($rulesDirectory)) {
            $this->converter = new RuleToColumnConverter($rulesDirectory);
        }
    }

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

        // formatキーを解決
        $data = $this->resolveFormats($data);

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

    /**
     * カラム定義のformatキーを解決する
     *
     * @param array $data テーブル定義配列
     * @return array formatが解決されたテーブル定義配列
     */
    private function resolveFormats(array $data): array
    {
        if (!isset($data['columns']) || !is_array($data['columns'])) {
            return $data;
        }

        foreach ($data['columns'] as $columnName => $columnDef) {
            if (isset($columnDef['format'])) {
                $data['columns'][$columnName] = $this->resolveColumnFormat($columnDef);
            }
        }

        return $data;
    }

    /**
     * 個別カラムのformatを解決する
     *
     * @param array $columnDef カラム定義
     * @return array 解決されたカラム定義
     * @throws \InvalidArgumentException converterが設定されていない場合
     */
    private function resolveColumnFormat(array $columnDef): array
    {
        if ($this->converter === null) {
            throw new \InvalidArgumentException(
                "formatキーを使用するにはルールディレクトリの指定が必要です。" .
                "TableDefinitionLoaderのコンストラクタにルールディレクトリを渡すか、" .
                "CLIの--rulesオプションまたは設定ファイルのMODEL_DIRECTORYを指定してください。"
            );
        }

        $formatName = $columnDef['format'];

        // format以外の属性を取得（テーブル定義側で上書き可能な属性）
        $overrides = array_diff_key($columnDef, ['format' => true]);

        // ルールからカラム定義を生成して上書き属性をマージ
        return $this->converter->convert($formatName, $overrides);
    }
}
