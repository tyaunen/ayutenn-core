<?php
declare(strict_types=1);

namespace ayutenn\core\migration;

/**
 * 【概要】
 * ルールファイルからカラム定義への変換クラス
 *
 * 【解説】
 * バリデーションルールファイル（JSON）を読み込み、
 * マイグレーション用のカラム定義を生成する。
 */
class RuleToColumnConverter
{
    private string $rulesDirectory;

    /**
     * コンストラクタ
     *
     * @param string $rulesDirectory ルールファイルのディレクトリパス
     */
    public function __construct(string $rulesDirectory)
    {
        if (!is_dir($rulesDirectory)) {
            throw new \InvalidArgumentException("ルールディレクトリが見つかりません: {$rulesDirectory}");
        }
        $this->rulesDirectory = rtrim($rulesDirectory, '/\\');
    }

    /**
     * ルールファイル名からカラム定義配列を生成
     *
     * @param string $ruleName ルールファイル名（拡張子なし、または.json付き）
     * @param array $columnOverrides テーブル定義側で指定された属性（nullable, default等）
     * @return array カラム定義配列（Column::fromArrayに渡せる形式）
     * @throws \InvalidArgumentException ルールファイルが見つからない場合
     */
    public function convert(string $ruleName, array $columnOverrides = []): array
    {
        $rule = $this->loadRule($ruleName);
        $columnDef = $this->ruleToColumnDefinition($rule);

        // テーブル定義側の属性で上書き（nullable, default, comment, unique等）
        return array_merge($columnDef, $columnOverrides);
    }

    /**
     * ルールファイルを読み込む
     *
     * @param string $ruleName ルールファイル名
     * @return array ルール定義
     * @throws \InvalidArgumentException ファイルが見つからない、またはJSONが不正な場合
     */
    private function loadRule(string $ruleName): array
    {
        // 拡張子がなければ.jsonを追加
        if (!str_ends_with($ruleName, '.json')) {
            $ruleName .= '.json';
        }

        $filePath = $this->rulesDirectory . DIRECTORY_SEPARATOR . $ruleName;

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("ルールファイルが見つかりません: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("JSONパースエラー ({$ruleName}): " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * ルール定義からカラム定義を生成
     *
     * @param array $rule ルール定義
     * @return array カラム定義配列
     */
    private function ruleToColumnDefinition(array $rule): array
    {
        // dbセクションがあれば優先使用
        if (isset($rule['db'])) {
            return $this->fromDbSection($rule['db'], $rule);
        }

        // conditionsから型を推論
        $conditions = $rule['conditions'] ?? [];
        foreach ($conditions as $condition) {
            $inferred = $this->inferFromCondition($condition);
            if ($inferred !== null) {
                return $inferred;
            }
        }

        // typeから型を推論
        return $this->inferFromType($rule);
    }

    /**
     * dbセクションからカラム定義を生成
     *
     * @param array $dbSection dbセクションの内容
     * @param array $rule 元のルール定義（max_length等の参照用）
     * @return array カラム定義配列
     */
    private function fromDbSection(array $dbSection, array $rule): array
    {
        $columnDef = [
            'type' => $dbSection['type'] ?? 'varchar',
        ];

        // lengthはdbセクションにあればそれを使用、なければmax_lengthから
        if (isset($dbSection['length'])) {
            $columnDef['length'] = $dbSection['length'];
        } elseif (isset($rule['max_length']) && $this->needsLength($columnDef['type'])) {
            $columnDef['length'] = $rule['max_length'];
        }

        // その他のDB属性
        if (isset($dbSection['unsigned'])) {
            $columnDef['unsigned'] = $dbSection['unsigned'];
        }
        if (isset($dbSection['precision'])) {
            $columnDef['precision'] = $dbSection['precision'];
        }
        if (isset($dbSection['scale'])) {
            $columnDef['scale'] = $dbSection['scale'];
        }
        if (isset($dbSection['values'])) {
            $columnDef['values'] = $dbSection['values'];
        }

        return $columnDef;
    }

    /**
     * conditionから型を推論
     *
     * @param string $condition 条件名
     * @return array|null カラム定義配列、推論できない場合はnull
     */
    private function inferFromCondition(string $condition): ?array
    {
        return match ($condition) {
            'email' => ['type' => 'varchar', 'length' => 254],
            'url' => ['type' => 'text'],
            'color_code' => ['type' => 'char', 'length' => 7],
            'datetime' => ['type' => 'datetime'],
            'date' => ['type' => 'date'],
            default => null,
        };
    }

    /**
     * typeから型を推論
     *
     * @param array $rule ルール定義
     * @return array カラム定義配列
     */
    private function inferFromType(array $rule): array
    {
        $type = $rule['type'] ?? 'string';
        $maxLength = $rule['max_length'] ?? null;

        return match ($type) {
            'string' => $this->inferStringType($maxLength),
            'int' => ['type' => 'int'],
            'number' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2],
            'boolean' => ['type' => 'boolean'],
            default => ['type' => 'varchar', 'length' => 255],
        };
    }

    /**
     * string型の場合のDB型を推論
     *
     * @param int|null $maxLength 最大長
     * @return array カラム定義配列
     */
    private function inferStringType(?int $maxLength): array
    {
        if ($maxLength === null) {
            return ['type' => 'varchar', 'length' => 255];
        }

        if ($maxLength <= 255) {
            return ['type' => 'varchar', 'length' => $maxLength];
        }

        if ($maxLength <= 65535) {
            return ['type' => 'text'];
        }

        return ['type' => 'longtext'];
    }

    /**
     * 型がlength属性を必要とするかどうか
     *
     * @param string $type カラム型
     * @return bool
     */
    private function needsLength(string $type): bool
    {
        return in_array(strtolower($type), ['varchar', 'char']);
    }
}
