<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

/**
 * 【概要】
 * スキーマ差分検出クラス
 *
 * 【解説】
 * 期待されるテーブル定義と実際のデータベース構造を比較し、
 * 差分を検出する。
 */
class SchemaDiffer
{
    /**
     * 差分タイプ定数
     */
    public const CREATE_TABLE = 'create_table';
    public const DROP_TABLE = 'drop_table';
    public const ADD_COLUMN = 'add_column';
    public const MODIFY_COLUMN = 'modify_column';
    public const DROP_COLUMN = 'drop_column';
    public const ADD_INDEX = 'add_index';
    public const DROP_INDEX = 'drop_index';
    public const ADD_FOREIGN_KEY = 'add_foreign_key';
    public const DROP_FOREIGN_KEY = 'drop_foreign_key';

    /**
     * テーブル定義の差分を検出
     *
     * @param TableDefinition $expected 期待されるテーブル定義
     * @param TableDefinition|null $actual 実際のテーブル定義（存在しない場合はnull）
     * @return array 差分情報の配列
     */
    public function diff(TableDefinition $expected, ?TableDefinition $actual): array
    {
        $diffs = [];

        // テーブルが存在しない場合
        if ($actual === null) {
            $diffs[] = [
                'type' => self::CREATE_TABLE,
                'table' => $expected->getName(),
                'definition' => $expected,
            ];
            return $diffs;
        }

        // カラムの差分
        $diffs = array_merge($diffs, $this->diffColumns($expected, $actual));

        // インデックスの差分
        $diffs = array_merge($diffs, $this->diffIndexes($expected, $actual));

        // 外部キーの差分
        $diffs = array_merge($diffs, $this->diffForeignKeys($expected, $actual));

        return $diffs;
    }

    /**
     * 複数テーブルの差分を検出
     *
     * @param TableDefinition[] $expected 期待されるテーブル定義の配列
     * @param TableDefinition[] $actual 実際のテーブル定義の配列
     * @param bool $dropUnknown 定義にないテーブルを削除するか
     * @return array 差分情報の配列
     */
    public function diffAll(array $expected, array $actual, bool $dropUnknown = false): array
    {
        $diffs = [];

        // 期待されるテーブルについて差分検出
        foreach ($expected as $tableName => $expectedTable) {
            $actualTable = $actual[$tableName] ?? null;
            $diffs = array_merge($diffs, $this->diff($expectedTable, $actualTable));
        }

        // 定義にないテーブルの削除（オプション）
        if ($dropUnknown) {
            foreach ($actual as $tableName => $actualTable) {
                if (!isset($expected[$tableName])) {
                    $diffs[] = [
                        'type' => self::DROP_TABLE,
                        'table' => $tableName,
                    ];
                }
            }
        }

        return $diffs;
    }

    /**
     * カラムの差分を検出
     */
    private function diffColumns(TableDefinition $expected, TableDefinition $actual): array
    {
        $diffs = [];
        $tableName = $expected->getName();

        $expectedColumns = $expected->getColumns();
        $actualColumns = $actual->getColumns();

        // 追加・変更されたカラム
        foreach ($expectedColumns as $columnName => $expectedColumn) {
            $actualColumn = $actualColumns[$columnName] ?? null;

            if ($actualColumn === null) {
                // 新規カラム
                $diffs[] = [
                    'type' => self::ADD_COLUMN,
                    'table' => $tableName,
                    'column' => $expectedColumn,
                ];
            } elseif (!$expectedColumn->equals($actualColumn)) {
                // 変更されたカラム
                $diffs[] = [
                    'type' => self::MODIFY_COLUMN,
                    'table' => $tableName,
                    'column' => $expectedColumn,
                    'from' => $actualColumn,
                ];
            }
        }

        // 削除されたカラム
        foreach ($actualColumns as $columnName => $actualColumn) {
            if (!isset($expectedColumns[$columnName])) {
                $diffs[] = [
                    'type' => self::DROP_COLUMN,
                    'table' => $tableName,
                    'columnName' => $columnName,
                ];
            }
        }

        return $diffs;
    }

    /**
     * インデックスの差分を検出
     */
    private function diffIndexes(TableDefinition $expected, TableDefinition $actual): array
    {
        $diffs = [];
        $tableName = $expected->getName();

        $expectedIndexes = $expected->getIndexes();
        $actualIndexes = $actual->getIndexes();

        // 追加・変更されたインデックス
        foreach ($expectedIndexes as $indexName => $expectedIndex) {
            $actualIndex = $actualIndexes[$indexName] ?? null;

            if ($actualIndex === null) {
                $diffs[] = [
                    'type' => self::ADD_INDEX,
                    'table' => $tableName,
                    'indexName' => $indexName,
                    'index' => $expectedIndex,
                ];
            } elseif ($expectedIndex !== $actualIndex) {
                // インデックスは変更不可なので、削除して再作成
                $diffs[] = [
                    'type' => self::DROP_INDEX,
                    'table' => $tableName,
                    'indexName' => $indexName,
                ];
                $diffs[] = [
                    'type' => self::ADD_INDEX,
                    'table' => $tableName,
                    'indexName' => $indexName,
                    'index' => $expectedIndex,
                ];
            }
        }

        // 削除されたインデックス
        foreach ($actualIndexes as $indexName => $actualIndex) {
            if (!isset($expectedIndexes[$indexName])) {
                $diffs[] = [
                    'type' => self::DROP_INDEX,
                    'table' => $tableName,
                    'indexName' => $indexName,
                ];
            }
        }

        return $diffs;
    }

    /**
     * 外部キーの差分を検出
     */
    private function diffForeignKeys(TableDefinition $expected, TableDefinition $actual): array
    {
        $diffs = [];
        $tableName = $expected->getName();

        $expectedFKs = $expected->getForeignKeys();
        $actualFKs = $actual->getForeignKeys();

        // 追加・変更された外部キー
        foreach ($expectedFKs as $fkName => $expectedFK) {
            $actualFK = $actualFKs[$fkName] ?? null;

            if ($actualFK === null) {
                $diffs[] = [
                    'type' => self::ADD_FOREIGN_KEY,
                    'table' => $tableName,
                    'fkName' => $fkName,
                    'foreignKey' => $expectedFK,
                ];
            } elseif ($expectedFK !== $actualFK) {
                // 外部キーは変更不可なので、削除して再作成
                $diffs[] = [
                    'type' => self::DROP_FOREIGN_KEY,
                    'table' => $tableName,
                    'fkName' => $fkName,
                ];
                $diffs[] = [
                    'type' => self::ADD_FOREIGN_KEY,
                    'table' => $tableName,
                    'fkName' => $fkName,
                    'foreignKey' => $expectedFK,
                ];
            }
        }

        // 削除された外部キー
        foreach ($actualFKs as $fkName => $actualFK) {
            if (!isset($expectedFKs[$fkName])) {
                $diffs[] = [
                    'type' => self::DROP_FOREIGN_KEY,
                    'table' => $tableName,
                    'fkName' => $fkName,
                ];
            }
        }

        return $diffs;
    }
}
