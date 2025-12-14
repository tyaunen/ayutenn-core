<?php

namespace ayutenn\core\validation;

/**
 * バリデーション結果を保持するValueObject
 */
class ValidationResult
{
    /**
     * @param array<string, string> $errors パラメータ名 => エラーメッセージ
     * @param array<string, mixed> $castedValues キャスト済みの値
     */
    public function __construct(
        private array $errors = [],
        private array $castedValues = []
    ) {}

    /**
     * エラーがあるかどうか
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * 全エラーを取得
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * キャスト済みの値を取得
     *
     * @return array<string, mixed>
     */
    public function getCastedValues(): array
    {
        return $this->castedValues;
    }

    /**
     * 特定パラメータのエラーを取得
     */
    public function getError(string $paramName): ?string
    {
        return $this->errors[$paramName] ?? null;
    }

    /**
     * 特定パラメータのキャスト済み値を取得
     */
    public function getValue(string $paramName): mixed
    {
        return $this->castedValues[$paramName] ?? null;
    }
}
