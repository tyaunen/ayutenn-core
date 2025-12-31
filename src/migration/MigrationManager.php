<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

use PDO;

/**
 * 【概要】
 * マイグレーションマネージャー
 *
 * 【解説】
 * マイグレーション処理全体を制御するクラス。
 * テーブル定義の読み込み、DBとの差分検出、DDLファイル出力を行う。
 */
class MigrationManager
{
    private PDO $pdo;
    private string $definitionsDir;
    private string $outputDir;
    private TableDefinitionLoader $loader;
    private DatabaseInspector $inspector;
    private SchemaDiffer $differ;
    private DDLGenerator $generator;

    /**
     * コンストラクタ
     *
     * @param PDO $pdo PDO接続
     * @param string $definitionsDir テーブル定義JSONディレクトリ
     * @param string $outputDir SQL出力ディレクトリ
     * @param string|null $rulesDirectory ルールファイルディレクトリ（formatキー使用時に必須）
     */
    public function __construct(
        PDO $pdo,
        string $definitionsDir,
        string $outputDir,
        ?string $rulesDirectory = null
    ) {
        $this->pdo = $pdo;
        $this->definitionsDir = $definitionsDir;
        $this->outputDir = $outputDir;
        $this->loader = new TableDefinitionLoader($rulesDirectory);
        $this->inspector = new DatabaseInspector($pdo);
        $this->differ = new SchemaDiffer();
        $this->generator = new DDLGenerator();
    }

    /**
     * マイグレーションSQLファイルを生成
     *
     * @param bool $dropUnknown 定義にないテーブルを削除するか
     * @return string|null 生成したSQLファイルのパス（差分がない場合はnull）
     */
    public function generateMigration(bool $dropUnknown = false): ?string
    {
        // テーブル定義を読み込み
        $expectedTables = $this->loader->loadFromDirectory($this->definitionsDir);

        if (empty($expectedTables)) {
            return null;
        }

        // 実際のDB構造を取得
        $actualTables = $this->getActualTables($expectedTables);

        // 差分を検出
        $diffs = $this->differ->diffAll($expectedTables, $actualTables, $dropUnknown);

        if (empty($diffs)) {
            return null;
        }

        // DDLを生成
        $sql = $this->buildMigrationSQL($diffs);

        // ファイルに出力
        return $this->writeToFile($sql);
    }

    /**
     * 実際のDB構造を取得
     *
     * @param TableDefinition[] $expectedTables 期待されるテーブル定義
     * @return TableDefinition[] 実際のテーブル定義
     */
    private function getActualTables(array $expectedTables): array
    {
        $actualTables = [];

        foreach ($expectedTables as $tableName => $table) {
            $actualTable = $this->inspector->getTableDefinition($tableName);
            if ($actualTable !== null) {
                $actualTables[$tableName] = $actualTable;
            }
        }

        return $actualTables;
    }

    /**
     * マイグレーションSQL全体を構築
     */
    private function buildMigrationSQL(array $diffs): string
    {
        $header = $this->buildHeader($diffs);
        $body = $this->generator->generate($diffs);

        return $header . "\n\n" . $body . "\n";
    }

    /**
     * SQLファイルのヘッダーを生成
     */
    private function buildHeader(array $diffs): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $summary = $this->summarizeDiffs($diffs);

        $header = "-- ============================================\n";
        $header .= "-- Migration generated at {$timestamp}\n";
        $header .= "-- Declarative Migration Tool\n";
        $header .= "-- ============================================\n";
        $header .= "--\n";
        $header .= "-- Summary:\n";

        foreach ($summary as $line) {
            $header .= "-- {$line}\n";
        }

        return $header;
    }

    /**
     * 差分のサマリーを生成
     */
    private function summarizeDiffs(array $diffs): array
    {
        $summary = [];
        $counts = [];

        foreach ($diffs as $diff) {
            $type = $diff['type'];
            $table = $diff['table'];
            $key = "{$type}:{$table}";

            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'type' => $type,
                    'table' => $table,
                    'count' => 0,
                ];
            }
            $counts[$key]['count']++;
        }

        foreach ($counts as $item) {
            $typeLabel = $this->getTypeLabel($item['type']);
            $summary[] = "{$item['table']}: {$typeLabel}";
        }

        return $summary;
    }

    /**
     * 差分タイプのラベルを取得
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            SchemaDiffer::CREATE_TABLE => 'テーブル作成',
            SchemaDiffer::DROP_TABLE => 'テーブル削除',
            SchemaDiffer::ADD_COLUMN => 'カラム追加',
            SchemaDiffer::MODIFY_COLUMN => 'カラム変更',
            SchemaDiffer::DROP_COLUMN => 'カラム削除',
            SchemaDiffer::ADD_INDEX => 'インデックス追加',
            SchemaDiffer::DROP_INDEX => 'インデックス削除',
            SchemaDiffer::ADD_FOREIGN_KEY => '外部キー追加',
            SchemaDiffer::DROP_FOREIGN_KEY => '外部キー削除',
            default => $type,
        };
    }

    /**
     * SQLをファイルに出力
     */
    private function writeToFile(string $sql): string
    {
        // 出力ディレクトリがなければ作成
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_migration.sql";
        $filepath = $this->outputDir . '/' . $filename;

        file_put_contents($filepath, $sql);

        return $filepath;
    }

    /**
     * 差分のプレビューを取得（ファイル出力なし）
     *
     * @param bool $dropUnknown 定義にないテーブルを削除するか
     * @return array{diffs: array, sql: string}
     */
    public function preview(bool $dropUnknown = false): array
    {
        $expectedTables = $this->loader->loadFromDirectory($this->definitionsDir);

        if (empty($expectedTables)) {
            return ['diffs' => [], 'sql' => ''];
        }

        $actualTables = $this->getActualTables($expectedTables);
        $diffs = $this->differ->diffAll($expectedTables, $actualTables, $dropUnknown);

        if (empty($diffs)) {
            return ['diffs' => [], 'sql' => ''];
        }

        $sql = $this->buildMigrationSQL($diffs);

        return [
            'diffs' => $diffs,
            'sql' => $sql,
        ];
    }
}
