<?php

namespace ayutenn\core\validation;

/**
 * バリデーションのエントリーポイント
 *
 * フォーマット配列とルールディレクトリを受け取り、バリデーションを実行する。
 *
 * フォーマット配列の構造:
 * - type: 'item' | 'object' | 'list'
 * - name: エラーメッセージ用のラベル
 * - format: ルールファイル名（文字列）またはインライン定義（配列）※item型の場合
 * - require: 必須かどうか（デフォルト: true）
 * - properties: プロパティ定義（object型の場合）
 * - items: 要素定義（list型の場合）
 */
class Validator
{
    private array $format;
    private ?string $rulesDir;

    /**
     * @param array $format フォーマット配列
     * @param string|null $rulesDir ルールファイルが格納されたディレクトリ（formatが文字列の場合に必要）
     */
    public function __construct(array $format, ?string $rulesDir = null)
    {
        $this->format = $format;
        $this->rulesDir = $rulesDir ? rtrim($rulesDir, '/\\') : null;
    }

    /**
     * バリデーション実行
     */
    public function validate(array $params): ValidationResult
    {
        $errors = [];
        $castedValues = [];

        foreach ($this->format as $paramName => $config) {
            $value = $params[$paramName] ?? null;
            $paramExists = array_key_exists($paramName, $params);
            $name = $config['name'] ?? $paramName;
            $require = $config['require'] ?? true;

            // 値が存在しない場合
            if (!$paramExists || $this->isEmpty($value)) {
                if ($require) {
                    $errors[$paramName] = "{$name}は必須です。";
                }
                continue;
            }

            // 型に応じたバリデーション
            $result = $this->validateField($config, $value);

            if ($result['error'] !== '') {
                $errors[$paramName] = $result['error'];
            } else {
                $castedValues[$paramName] = $result['value'];
            }
        }

        return new ValidationResult($errors, $castedValues);
    }

    /**
     * フィールドをバリデート
     *
     * @return array{error: string, value: mixed}
     */
    private function validateField(array $config, mixed $value): array
    {
        $type = $config['type'] ?? 'item';

        return match ($type) {
            'object' => $this->validateObject($config, $value),
            'list' => $this->validateList($config, $value),
            default => $this->validateItem($config, $value),
        };
    }

    /**
     * item型のバリデーション
     */
    private function validateItem(array $config, mixed $value): array
    {
        $name = $config['name'] ?? 'この項目';
        $format = $config['format'] ?? null;

        if ($format === null) {
            // フォーマット指定なし、そのまま返す
            return ['error' => '', 'value' => $value];
        }

        // ルールを取得
        $rule = $this->getRule($format);
        $error = $rule->validate($value, $name);

        if ($error !== '') {
            return ['error' => $error, 'value' => null];
        }

        return ['error' => '', 'value' => $rule->cast($value)];
    }

    /**
     * object型のバリデーション
     */
    private function validateObject(array $config, mixed $value): array
    {
        $name = $config['name'] ?? 'この項目';

        if (!is_array($value)) {
            return ['error' => "{$name}はオブジェクト形式である必要があります。", 'value' => null];
        }

        $properties = $config['properties'] ?? [];
        if (empty($properties)) {
            return ['error' => '', 'value' => $value];
        }

        $castedObject = [];
        $childErrors = [];

        foreach ($properties as $propName => $propConfig) {
            $propValue = $value[$propName] ?? null;
            $propExists = array_key_exists($propName, $value);
            $propName2 = $propConfig['name'] ?? $propName;
            $propRequire = $propConfig['require'] ?? true;

            if (!$propExists || $this->isEmpty($propValue)) {
                if ($propRequire) {
                    $childErrors[] = "{$propName2}は必須です。";
                }
                continue;
            }

            $result = $this->validateField($propConfig, $propValue);

            if ($result['error'] !== '') {
                $childErrors[] = $result['error'];
            } else {
                $castedObject[$propName] = $result['value'];
            }
        }

        if (count($childErrors) > 0) {
            return ['error' => implode(' ', $childErrors), 'value' => null];
        }

        return ['error' => '', 'value' => $castedObject];
    }

    /**
     * list型のバリデーション
     */
    private function validateList(array $config, mixed $value): array
    {
        $name = $config['name'] ?? 'この項目';

        if (!is_array($value)) {
            return ['error' => "{$name}はリスト形式である必要があります。", 'value' => null];
        }

        $itemsConfig = $config['items'] ?? null;
        if ($itemsConfig === null) {
            return ['error' => '', 'value' => $value];
        }

        $castedList = [];
        $childErrors = [];
        $itemName = $itemsConfig['name'] ?? $name;

        foreach ($value as $index => $item) {
            // リスト要素のrequireチェック
            $itemRequire = $itemsConfig['require'] ?? true;

            if ($this->isEmpty($item)) {
                if ($itemRequire) {
                    $childErrors[] = "{$itemName}[{$index}]は必須です。";
                }
                continue;
            }

            $result = $this->validateField($itemsConfig, $item);

            if ($result['error'] !== '') {
                $childErrors[] = "[{$index}]: {$result['error']}";
            } else {
                $castedList[] = $result['value'];
            }
        }

        if (count($childErrors) > 0) {
            return ['error' => implode(' ', $childErrors), 'value' => null];
        }

        return ['error' => '', 'value' => $castedList];
    }

    /**
     * ルールを取得（文字列ならファイルから、配列ならインラインから）
     */
    private function getRule(string|array $format): ValidationRule
    {
        if (is_array($format)) {
            // インライン定義
            return ValidationRule::fromArray($format);
        }

        // ファイルから読み込み
        if ($this->rulesDir === null) {
            throw new \InvalidArgumentException("formatが文字列の場合、rulesDirを指定してください。");
        }

        return RuleLoader::fromJsonFile("{$this->rulesDir}/{$format}.json");
    }

    /**
     * 値が空かどうか
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && count($value) === 0) {
            return true;
        }
        return false;
    }
}
