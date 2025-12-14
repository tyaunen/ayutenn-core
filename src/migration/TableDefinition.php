<?php
namespace ayutenn\core\migration;

/**
 * 【概要】
 * テーブル定義クラス
 *
 * 【解説】
 * テーブル全体の定義を保持するクラス。
 * カラム、インデックス、外部キーなどのテーブル構造を表現する。
 */
class TableDefinition
{
    // デフォルト値
    public const DEFAULT_ENGINE = 'InnoDB';
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_COLLATION = 'utf8mb4_unicode_ci';

    private string $name;
    private ?string $comment = null;
    private string $engine;
    private string $charset;
    private string $collation;

    /** @var Column[] */
    private array $columns = [];

    /** @var string[] */
    private array $primaryKey = [];

    /** @var array<string, array{columns: string[], unique: bool}> */
    private array $indexes = [];

    /** @var array<string, array{columns: string[], references: array{table: string, columns: string[]}, onDelete: string, onUpdate: string}> */
    private array $foreignKeys = [];

    /**
     * コンストラクタ
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->engine = self::DEFAULT_ENGINE;
        $this->charset = self::DEFAULT_CHARSET;
        $this->collation = self::DEFAULT_COLLATION;
    }

    /**
     * JSONの配列からTableDefinitionを生成
     */
    public static function fromArray(array $definition): self
    {
        if (!isset($definition['name'])) {
            throw new \InvalidArgumentException('テーブル名は必須です。');
        }

        $table = new self($definition['name']);

        if (isset($definition['comment'])) {
            $table->comment = $definition['comment'];
        }
        if (isset($definition['engine'])) {
            $table->engine = $definition['engine'];
        }
        if (isset($definition['charset'])) {
            $table->charset = $definition['charset'];
        }
        if (isset($definition['collation'])) {
            $table->collation = $definition['collation'];
        }

        // カラム
        if (isset($definition['columns']) && is_array($definition['columns'])) {
            foreach ($definition['columns'] as $columnName => $columnDef) {
                $table->columns[$columnName] = Column::fromArray($columnName, $columnDef);
            }
        }

        // 主キー
        if (isset($definition['primaryKey'])) {
            $table->primaryKey = (array)$definition['primaryKey'];
        }

        // インデックス
        if (isset($definition['indexes']) && is_array($definition['indexes'])) {
            foreach ($definition['indexes'] as $indexName => $indexDef) {
                $table->indexes[$indexName] = [
                    'columns' => $indexDef['columns'] ?? [],
                    'unique' => $indexDef['unique'] ?? false,
                ];
            }
        }

        // 外部キー
        if (isset($definition['foreignKeys']) && is_array($definition['foreignKeys'])) {
            foreach ($definition['foreignKeys'] as $fkName => $fkDef) {
                $table->foreignKeys[$fkName] = [
                    'columns' => $fkDef['columns'] ?? [],
                    'references' => $fkDef['references'] ?? ['table' => '', 'columns' => []],
                    'onDelete' => $fkDef['onDelete'] ?? 'RESTRICT',
                    'onUpdate' => $fkDef['onUpdate'] ?? 'RESTRICT',
                ];
            }
        }

        return $table;
    }

    // Getters
    public function getName(): string { return $this->name; }
    public function getComment(): ?string { return $this->comment; }
    public function getEngine(): string { return $this->engine; }
    public function getCharset(): string { return $this->charset; }
    public function getCollation(): string { return $this->collation; }

    /** @return Column[] */
    public function getColumns(): array { return $this->columns; }

    public function getColumn(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    /** @return string[] */
    public function getPrimaryKey(): array { return $this->primaryKey; }

    /** @return array<string, array{columns: string[], unique: bool}> */
    public function getIndexes(): array { return $this->indexes; }

    /** @return array<string, array{columns: string[], references: array{table: string, columns: string[]}, onDelete: string, onUpdate: string}> */
    public function getForeignKeys(): array { return $this->foreignKeys; }

    /**
     * CREATE TABLE文を生成
     */
    public function toCreateSQL(): string
    {
        $lines = [];

        // カラム定義
        foreach ($this->columns as $column) {
            $lines[] = '    ' . $column->toSQL();
        }

        // 主キー
        if (!empty($this->primaryKey)) {
            $pkColumns = implode('`, `', $this->primaryKey);
            $lines[] = "    PRIMARY KEY (`{$pkColumns}`)";
        }

        // カラムのユニーク制約
        foreach ($this->columns as $column) {
            if ($column->isUnique()) {
                $lines[] = "    UNIQUE KEY `uk_{$column->getName()}` (`{$column->getName()}`)";
            }
        }

        // インデックス
        foreach ($this->indexes as $indexName => $indexDef) {
            $indexColumns = implode('`, `', $indexDef['columns']);
            $keyType = $indexDef['unique'] ? 'UNIQUE KEY' : 'KEY';
            $lines[] = "    {$keyType} `{$indexName}` (`{$indexColumns}`)";
        }

        // 外部キー
        foreach ($this->foreignKeys as $fkName => $fkDef) {
            $fkColumns = implode('`, `', $fkDef['columns']);
            $refTable = $fkDef['references']['table'];
            $refColumns = implode('`, `', $fkDef['references']['columns']);
            $onDelete = $fkDef['onDelete'];
            $onUpdate = $fkDef['onUpdate'];
            $lines[] = "    CONSTRAINT `{$fkName}` FOREIGN KEY (`{$fkColumns}`) REFERENCES `{$refTable}` (`{$refColumns}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
        }

        $columnsSql = implode(",\n", $lines);

        $sql = "CREATE TABLE `{$this->name}` (\n{$columnsSql}\n)";
        $sql .= " ENGINE={$this->engine}";
        $sql .= " DEFAULT CHARSET={$this->charset}";
        $sql .= " COLLATE={$this->collation}";

        if ($this->comment !== null) {
            $sql .= " COMMENT='" . addslashes($this->comment) . "'";
        }

        return $sql . ';';
    }

    /**
     * カラム名の配列を取得
     *
     * @return string[]
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }
}
