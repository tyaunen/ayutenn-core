<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

/**
 * 【概要】
 * カラム定義クラス
 *
 * 【解説】
 * テーブルのカラム定義を表現するValueObject。
 * JSONから読み込んだカラム情報を保持し、DDL生成に使用する。
 */
class Column
{
    private string $name;
    private string $type;
    private ?int $length = null;
    private ?int $precision = null;
    private ?int $scale = null;
    private bool $unsigned = false;
    private bool $nullable = false;
    private bool $autoIncrement = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private ?string $comment = null;
    private ?string $onUpdate = null;
    private ?array $enumValues = null;
    private bool $unique = false;
    private ?string $after = null;

    /**
     * コンストラクタ
     */
    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = strtolower($type);
    }

    /**
     * JSONの配列からColumnを生成
     */
    public static function fromArray(string $name, array $definition): self
    {
        $column = new self($name, $definition['type'] ?? 'varchar');

        if (isset($definition['length'])) {
            $column->length = (int)$definition['length'];
        }
        if (isset($definition['precision'])) {
            $column->precision = (int)$definition['precision'];
        }
        if (isset($definition['scale'])) {
            $column->scale = (int)$definition['scale'];
        }
        if (isset($definition['unsigned'])) {
            $column->unsigned = (bool)$definition['unsigned'];
        }
        if (isset($definition['nullable'])) {
            $column->nullable = (bool)$definition['nullable'];
        }
        if (isset($definition['autoIncrement'])) {
            $column->autoIncrement = (bool)$definition['autoIncrement'];
        }
        if (array_key_exists('default', $definition)) {
            $column->default = $definition['default'];
            $column->hasDefault = true;
        }
        if (isset($definition['comment'])) {
            $column->comment = $definition['comment'];
        }
        if (isset($definition['onUpdate'])) {
            $column->onUpdate = $definition['onUpdate'];
        }
        if (isset($definition['values'])) {
            $column->enumValues = $definition['values'];
        }
        if (isset($definition['unique'])) {
            $column->unique = (bool)$definition['unique'];
        }
        if (isset($definition['after'])) {
            $column->after = $definition['after'];
        }

        return $column;
    }

    // Getters
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getLength(): ?int { return $this->length; }
    public function getPrecision(): ?int { return $this->precision; }
    public function getScale(): ?int { return $this->scale; }
    public function isUnsigned(): bool { return $this->unsigned; }
    public function isNullable(): bool { return $this->nullable; }
    public function isAutoIncrement(): bool { return $this->autoIncrement; }
    public function getDefault(): mixed { return $this->default; }
    public function hasDefault(): bool { return $this->hasDefault; }
    public function getComment(): ?string { return $this->comment; }
    public function getOnUpdate(): ?string { return $this->onUpdate; }
    public function getEnumValues(): ?array { return $this->enumValues; }
    public function isUnique(): bool { return $this->unique; }
    public function getAfter(): ?string { return $this->after; }

    /**
     * カラム定義をSQL文字列に変換
     */
    public function toSQL(): string
    {
        $sql = '`' . $this->name . '` ' . $this->getTypeSQL();

        if ($this->unsigned && $this->isNumericType()) {
            $sql .= ' UNSIGNED';
        }

        $sql .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault();
        }

        if ($this->onUpdate) {
            $sql .= ' ON UPDATE ' . $this->onUpdate;
        }

        if ($this->comment !== null) {
            $sql .= " COMMENT '" . addslashes($this->comment) . "'";
        }

        return $sql;
    }

    /**
     * カラム型のSQL表現を取得
     */
    private function getTypeSQL(): string
    {
        return match ($this->type) {
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'tinyint' => 'TINYINT',
            'decimal' => sprintf('DECIMAL(%d,%d)', $this->precision ?? 10, $this->scale ?? 0),
            'varchar' => sprintf('VARCHAR(%d)', $this->length ?? 255),
            'char' => sprintf('CHAR(%d)', $this->length ?? 1),
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'date' => 'DATE',
            'time' => 'TIME',
            'boolean' => 'TINYINT(1)',
            'enum' => 'ENUM(' . implode(',', array_map(fn($v) => "'" . addslashes($v) . "'", $this->enumValues ?? [])) . ')',
            'json' => 'JSON',
            default => strtoupper($this->type),
        };
    }

    /**
     * 数値型かどうか
     */
    private function isNumericType(): bool
    {
        return in_array($this->type, ['int', 'bigint', 'tinyint', 'decimal']);
    }

    /**
     * デフォルト値をSQL形式にフォーマット
     */
    private function formatDefault(): string
    {
        if ($this->default === null) {
            return 'NULL';
        }

        // 特殊な値（関数）
        $specialValues = ['CURRENT_TIMESTAMP', 'NOW()', 'NULL'];
        if (is_string($this->default) && in_array(strtoupper($this->default), $specialValues)) {
            return strtoupper($this->default);
        }

        if (is_bool($this->default)) {
            return $this->default ? '1' : '0';
        }

        if (is_numeric($this->default)) {
            return (string)$this->default;
        }

        return "'" . addslashes($this->default) . "'";
    }

    /**
     * 比較用に定義を配列として取得
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'unsigned' => $this->unsigned,
            'nullable' => $this->nullable,
            'autoIncrement' => $this->autoIncrement,
            'default' => $this->default,
            'hasDefault' => $this->hasDefault,
            'comment' => $this->comment,
            'onUpdate' => $this->onUpdate,
            'enumValues' => $this->enumValues,
            'unique' => $this->unique,
        ];
    }

    /**
     * 別のカラムと等しいかどうか
     */
    public function equals(Column $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
