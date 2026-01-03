<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

use PDO;

/**
 * 【概要】
 * データベース検査クラス
 *
 * 【解説】
 * INFORMATION_SCHEMAを使用して、実際のMySQLデータベースの構造を取得する。
 */
class DatabaseInspector
{
    private PDO $pdo;
    private string $database;

    /**
     * コンストラクタ
     *
     * @param PDO $pdo PDO接続
     * @param string|null $database データベース名（省略時は接続中のDB）
     */
    public function __construct(PDO $pdo, ?string $database = null)
    {
        $this->pdo = $pdo;
        $this->database = $database ?? $this->getCurrentDatabase();
    }

    /**
     * 接続中のデータベース名を取得
     */
    private function getCurrentDatabase(): string
    {
        $stmt = $this->pdo->query('SELECT DATABASE()');
        return $stmt->fetchColumn() ?: '';
    }

    /**
     * データベース内のすべてのテーブル名を取得
     *
     * @return string[]
     */
    public function getAllTables(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
        );
        $stmt->execute([$this->database]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 指定したテーブルの定義を取得
     *
     * @param string $tableName テーブル名
     * @return TableDefinition|null テーブルが存在しない場合はnull
     */
    public function getTableDefinition(string $tableName): ?TableDefinition
    {
        // テーブルの存在確認と基本情報取得
        $tableInfo = $this->getTableInfo($tableName);
        if ($tableInfo === null) {
            return null;
        }

        $columns = $this->getColumns($tableName);
        $indexes = $this->getIndexes($tableName);

        // 暗黙的ユニークインデックス（uk_{column_name}パターン）を検出し、
        // カラムのunique属性に変換してindexesから除外
        $implicitUniqueIndexes = $this->detectImplicitUniqueIndexes($columns, $indexes);

        foreach ($implicitUniqueIndexes as $columnName) {
            if (isset($columns[$columnName])) {
                $columns[$columnName]['unique'] = true;
            }
        }

        // 暗黙的インデックスを除外
        foreach ($implicitUniqueIndexes as $columnName) {
            $implicitIndexName = 'uk_' . $columnName;
            unset($indexes[$implicitIndexName]);
        }

        $definition = [
            'name' => $tableName,
            'engine' => $tableInfo['ENGINE'],
            'charset' => $this->extractCharset($tableInfo['TABLE_COLLATION']),
            'collation' => $tableInfo['TABLE_COLLATION'],
            'comment' => $tableInfo['TABLE_COMMENT'] ?: null,
            'columns' => $columns,
            'primaryKey' => $this->getPrimaryKey($tableName),
            'indexes' => $indexes,
            'foreignKeys' => $this->getForeignKeys($tableName),
        ];

        return TableDefinition::fromArray($definition);
    }

    /**
     * 暗黙的ユニークインデックスを検出
     *
     * uk_{column_name}パターンのインデックスで、単一カラムのユニークインデックスを
     * カラムのunique属性として認識する
     *
     * @param array $columns カラム情報
     * @param array $indexes インデックス情報
     * @return string[] 暗黙的ユニークインデックスに対応するカラム名の配列
     */
    private function detectImplicitUniqueIndexes(array $columns, array $indexes): array
    {
        $implicitColumns = [];

        foreach ($indexes as $indexName => $indexDef) {
            // uk_{column_name}パターンをチェック
            if (!str_starts_with($indexName, 'uk_')) {
                continue;
            }

            // ユニークインデックスであることを確認
            if (!$indexDef['unique']) {
                continue;
            }

            // 単一カラムのインデックスであることを確認
            if (count($indexDef['columns']) !== 1) {
                continue;
            }

            $columnName = $indexDef['columns'][0];

            // インデックス名がuk_{column_name}と一致することを確認
            if ($indexName !== 'uk_' . $columnName) {
                continue;
            }

            // 対応するカラムが存在することを確認
            if (!isset($columns[$columnName])) {
                continue;
            }

            $implicitColumns[] = $columnName;
        }

        return $implicitColumns;
    }

    /**
     * テーブルの基本情報を取得
     */
    private function getTableInfo(string $tableName): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ENGINE, TABLE_COLLATION, TABLE_COMMENT
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $stmt->execute([$this->database, $tableName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * カラム情報を取得
     */
    private function getColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE,
                    COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT, CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION, NUMERIC_SCALE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$this->database, $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($rows as $row) {
            $columns[$row['COLUMN_NAME']] = $this->parseColumnInfo($row);
        }

        return $columns;
    }

    /**
     * カラム情報をパースしてJSON形式に変換
     */
    private function parseColumnInfo(array $row): array
    {
        $type = $this->normalizeType($row['DATA_TYPE']);
        $columnType = $row['COLUMN_TYPE'];

        $column = [
            'type' => $type,
            'nullable' => $row['IS_NULLABLE'] === 'YES',
        ];

        // UNSIGNED判定
        if (str_contains($columnType, 'unsigned')) {
            $column['unsigned'] = true;
        }

        // AUTO_INCREMENT判定
        if (str_contains($row['EXTRA'], 'auto_increment')) {
            $column['autoIncrement'] = true;
        }

        // 長さ (varchar, char)
        if ($row['CHARACTER_MAXIMUM_LENGTH'] !== null) {
            $column['length'] = (int)$row['CHARACTER_MAXIMUM_LENGTH'];
        }

        // 精度とスケール (decimal)
        if ($type === 'decimal') {
            $column['precision'] = (int)$row['NUMERIC_PRECISION'];
            $column['scale'] = (int)$row['NUMERIC_SCALE'];
        }

        // ENUM値の抽出
        if ($type === 'enum') {
            $column['values'] = $this->extractEnumValues($columnType);
        }

        // デフォルト値
        // DEFAULT NULLは暗黙的なデフォルトのため、明示的なデフォルトとしては扱わない
        if ($row['COLUMN_DEFAULT'] !== null) {
            $normalizedDefault = $this->normalizeDefaultValue($row['COLUMN_DEFAULT'], $type);
            if ($normalizedDefault !== null) {
                $column['default'] = $normalizedDefault;
            }
        }

        // ON UPDATE
        if (str_contains($row['EXTRA'], 'on update')) {
            if (preg_match('/on update (\S+)/i', $row['EXTRA'], $matches)) {
                $column['onUpdate'] = strtoupper($matches[1]);
            }
        }

        // コメント
        if (!empty($row['COLUMN_COMMENT'])) {
            $column['comment'] = $row['COLUMN_COMMENT'];
        }

        return $column;
    }

    /**
     * デフォルト値を正規化
     *
     * DBから取得したデフォルト値をJSON定義と比較可能な形式に変換する
     *
     * @param mixed $value DBから取得したデフォルト値
     * @param string $type カラムの型
     * @return mixed 正規化されたデフォルト値（nullを返した場合はデフォルト値なしとして扱われる）
     */
    private function normalizeDefaultValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        // 文字列"NULL"は暗黙的なデフォルト値のため、明示的なデフォルトとしては扱わない
        $upperValue = strtoupper((string)$value);
        if ($upperValue === 'NULL') {
            return null;
        }

        // CURRENT_TIMESTAMP系の正規化
        if (in_array($upperValue, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'NOW()'])) {
            return 'CURRENT_TIMESTAMP';
        }

        // 数値型の場合、文字列を適切な型に変換
        if (in_array($type, ['int', 'bigint', 'tinyint'])) {
            if (is_numeric($value)) {
                return (int)$value;
            }
        }

        if ($type === 'decimal') {
            if (is_numeric($value)) {
                return (float)$value;
            }
        }

        // boolean型（tinyint(1)）の場合
        if ($type === 'boolean' || $type === 'tinyint') {
            if ($value === '1' || $value === 1) {
                return true;
            }
            if ($value === '0' || $value === 0) {
                return (int)$value;  // 0は数値として保持
            }
        }

        // 文字列型の場合、周囲のクォートを除去
        if (in_array($type, ['varchar', 'char', 'text', 'longtext', 'enum'])) {
            $stringValue = (string)$value;
            // シングルクォートで囲まれている場合は除去
            if (preg_match("/^'(.*)'$/", $stringValue, $matches)) {
                return $matches[1];
            }
        }

        return $value;
    }

    /**
     * データ型を正規化
     */
    private function normalizeType(string $dataType): string
    {
        return match (strtolower($dataType)) {
            'int', 'integer' => 'int',
            'bigint' => 'bigint',
            'tinyint' => 'tinyint',
            'decimal', 'numeric' => 'decimal',
            'varchar' => 'varchar',
            'char' => 'char',
            'text' => 'text',
            'longtext' => 'longtext',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'enum' => 'enum',
            'json' => 'json',
            default => strtolower($dataType),
        };
    }

    /**
     * ENUM値を抽出
     */
    private function extractEnumValues(string $columnType): array
    {
        if (preg_match("/enum\((.+)\)/i", $columnType, $matches)) {
            $values = [];
            preg_match_all("/'([^']+)'/", $matches[1], $valueMatches);
            return $valueMatches[1];
        }
        return [];
    }

    /**
     * 主キーを取得
     */
    private function getPrimaryKey(string $tableName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$this->database, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * インデックスを取得（主キーと外部キー以外）
     */
    private function getIndexes(string $tableName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME != 'PRIMARY'
             ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        );
        $stmt->execute([$this->database, $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 外部キーのインデックスを除外するため、外部キー名を取得
        $fkNames = array_keys($this->getForeignKeys($tableName));

        $indexes = [];
        foreach ($rows as $row) {
            $indexName = $row['INDEX_NAME'];

            // 外部キーに関連するインデックスはスキップ
            if (in_array($indexName, $fkNames)) {
                continue;
            }

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] == 0,
                ];
            }
            $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        return $indexes;
    }

    /**
     * 外部キーを取得
     */
    private function getForeignKeys(string $tableName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT kcu.CONSTRAINT_NAME, kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
                    rc.DELETE_RULE, rc.UPDATE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION"
        );
        $stmt->execute([$this->database, $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];
        foreach ($rows as $row) {
            $fkName = $row['CONSTRAINT_NAME'];

            if (!isset($foreignKeys[$fkName])) {
                $foreignKeys[$fkName] = [
                    'columns' => [],
                    'references' => [
                        'table' => $row['REFERENCED_TABLE_NAME'],
                        'columns' => [],
                    ],
                    'onDelete' => $row['DELETE_RULE'],
                    'onUpdate' => $row['UPDATE_RULE'],
                ];
            }

            $foreignKeys[$fkName]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$fkName]['references']['columns'][] = $row['REFERENCED_COLUMN_NAME'];
        }

        return $foreignKeys;
    }

    /**
     * CollationからCharsetを抽出
     */
    private function extractCharset(string $collation): string
    {
        $parts = explode('_', $collation);
        return $parts[0] ?? 'utf8mb4';
    }
}
