<?php

namespace ayutenn\core\validation;

/**
 * 個別のバリデーションルールを表現するValueObject
 *
 * JSONファイルまたはインライン配列から生成される。
 */
class ValidationRule
{
    /**
     * @param string $type 型 (string, int, number, boolean)
     * @param int|null $min 数値の最小値
     * @param int|null $max 数値の最大値
     * @param int|null $minLength 文字列の最小長
     * @param int|null $maxLength 文字列の最大長
     * @param int|null $minLine 文字列の最小行数
     * @param int|null $maxLine 文字列の最大行数
     * @param array $conditions 追加条件 (email, url, alphanumeric, etc.)
     */
    public function __construct(
        public readonly string $type = 'string',
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?int $minLine = null,
        public readonly ?int $maxLine = null,
        public readonly array $conditions = [],
    ) {}

    /**
     * 配列からルールを生成
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'string',
            min: $data['min'] ?? null,
            max: $data['max'] ?? null,
            minLength: $data['min_length'] ?? null,
            maxLength: $data['max_length'] ?? null,
            minLine: $data['min_line'] ?? null,
            maxLine: $data['max_line'] ?? null,
            conditions: $data['conditions'] ?? [],
        );
    }

    /**
     * 値をバリデート
     *
     * @param mixed $value 検証する値
     * @param string $label エラーメッセージ用のラベル
     * @return string エラーメッセージ（成功時は空文字）
     */
    public function validate(mixed $value, string $label): string
    {
        // 型チェック
        $typeError = $this->validateType($value);
        if ($typeError !== '') {
            return "{$label}は{$typeError}";
        }

        // 数値の範囲チェック
        if ($this->type === 'int' || $this->type === 'number') {
            $numericValue = is_numeric($value) ? (float) $value : 0;

            if ($this->min !== null && $numericValue < $this->min) {
                return "{$label}は{$this->min}以上である必要があります。";
            }

            if ($this->max !== null && $numericValue > $this->max) {
                return "{$label}は{$this->max}以下である必要があります。";
            }
        }

        // 文字列の長さ・行数チェック
        if ($this->type === 'string' && is_string($value)) {
            $normalized = str_replace("\r\n", "\n", $value);
            $length = mb_strlen($normalized);
            $lineCount = substr_count($normalized, "\n") + 1;

            if ($this->minLength !== null && $length < $this->minLength) {
                return "{$label}は{$this->minLength}文字以上である必要があります。（現在: {$length}文字）";
            }

            if ($this->maxLength !== null && $length > $this->maxLength) {
                return "{$label}は{$this->maxLength}文字以下である必要があります。（現在: {$length}文字）";
            }

            if ($this->minLine !== null && $lineCount < $this->minLine) {
                return "{$label}は{$this->minLine}行以上である必要があります。（現在: {$lineCount}行）";
            }

            if ($this->maxLine !== null && $lineCount > $this->maxLine) {
                return "{$label}は{$this->maxLine}行以下である必要があります。（現在: {$lineCount}行）";
            }
        }

        // 条件チェック
        foreach ($this->conditions as $condition) {
            $conditionError = $this->validateCondition($value, $condition);
            if ($conditionError !== '') {
                return "{$label}は{$conditionError}";
            }
        }

        return '';
    }

    /**
     * 値を指定された型にキャスト
     */
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            'int' => (int) $value,
            'number' => (float) $value,
            'string' => (string) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    /**
     * 型を検証
     */
    private function validateType(mixed $value): string
    {
        return match ($this->type) {
            'string' => is_string($value) ? '' : '文字列である必要があります。',
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? '' : '整数である必要があります。',
            'number' => is_numeric($value) ? '' : '数値である必要があります。',
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true) ? '' : '真偽値である必要があります。',
            default => '',
        };
    }

    /**
     * 条件を検証
     */
    private function validateCondition(mixed $value, string $condition): string
    {
        return match ($condition) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? ''
                : 'メールアドレス形式である必要があります。',

            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false
                ? ''
                : 'URL形式である必要があります。',

            'alphanumeric' => preg_match('/^[a-zA-Z0-9]+$/', (string) $value) === 1
                ? ''
                : '英数字のみである必要があります。',

            'alphabetic' => preg_match('/^[a-zA-Z]+$/', (string) $value) === 1
                ? ''
                : '英字のみである必要があります。',

            'numeric' => preg_match('/^[0-9]+$/', (string) $value) === 1
                ? ''
                : '数字のみである必要があります。',

            'datetime' => $this->isValidDatetime($value, 'Y/m/d H:i:s')
                ? ''
                : '日時形式（Y/m/d H:i:s）である必要があります。',

            'date' => $this->isValidDatetime($value, 'Y/m/d')
                ? ''
                : '日付形式（Y/m/d）である必要があります。',

            'color_code' => preg_match('/^#[a-fA-F0-9]{6}$/', (string) $value) === 1
                ? ''
                : 'カラーコード形式（#RRGGBB）である必要があります。',

            default => '',
        };
    }

    /**
     * 日時形式を検証
     */
    private function isValidDatetime(mixed $value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $dateTime = \DateTime::createFromFormat($format, $value);
        return $dateTime !== false && $dateTime->format($format) === $value;
    }
}
