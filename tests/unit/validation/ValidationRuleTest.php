<?php

namespace ayutenn\core\tests\validation;

use PHPUnit\Framework\TestCase;
use ayutenn\core\validation\ValidationRule;

class ValidationRuleTest extends TestCase
{
    // ========================================
    // 文字列長のテスト（max_length）
    // ========================================

    public function test_maxLength_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'string', maxLength: 5);

        $error = $rule->validate('12345', '名前'); // 5文字

        $this->assertSame('', $error);
    }

    public function test_maxLength_境界値超えはNG(): void
    {
        $rule = new ValidationRule(type: 'string', maxLength: 5);

        $error = $rule->validate('123456', '名前'); // 6文字

        $this->assertSame('名前は5文字以下である必要があります。（現在: 6文字）', $error);
    }

    public function test_maxLength_マルチバイト文字でも正しくカウントされる(): void
    {
        $rule = new ValidationRule(type: 'string', maxLength: 3);

        $this->assertSame('', $rule->validate('あいう', '名前')); // 3文字 OK
        $this->assertStringContainsString('3文字以下', $rule->validate('あいうえ', '名前')); // 4文字 NG
    }

    // ========================================
    // 文字列長のテスト（min_length）
    // ========================================

    public function test_minLength_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'string', minLength: 3);

        $error = $rule->validate('abc', '名前'); // 3文字

        $this->assertSame('', $error);
    }

    public function test_minLength_境界値未満はNG(): void
    {
        $rule = new ValidationRule(type: 'string', minLength: 3);

        $error = $rule->validate('ab', '名前'); // 2文字

        $this->assertSame('名前は3文字以上である必要があります。（現在: 2文字）', $error);
    }

    // ========================================
    // 行数のテスト（max_line）
    // ========================================

    public function test_maxLine_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'string', maxLine: 2);

        $error = $rule->validate("1行目\n2行目", '説明'); // 2行

        $this->assertSame('', $error);
    }

    public function test_maxLine_境界値超えはNG(): void
    {
        $rule = new ValidationRule(type: 'string', maxLine: 2);

        $error = $rule->validate("1行目\n2行目\n3行目", '説明'); // 3行

        $this->assertSame('説明は2行以下である必要があります。（現在: 3行）', $error);
    }

    public function test_maxLine_CRLFでも正しくカウントされる(): void
    {
        $rule = new ValidationRule(type: 'string', maxLine: 2);

        $this->assertSame('', $rule->validate("1行目\r\n2行目", '説明')); // 2行 OK
        $this->assertStringContainsString('2行以下', $rule->validate("1行目\r\n2行目\r\n3行目", '説明')); // 3行 NG
    }

    // ========================================
    // 行数のテスト（min_line）
    // ========================================

    public function test_minLine_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'string', minLine: 3);

        $error = $rule->validate("1\n2\n3", '説明'); // 3行

        $this->assertSame('', $error);
    }

    public function test_minLine_境界値未満はNG(): void
    {
        $rule = new ValidationRule(type: 'string', minLine: 3);

        $error = $rule->validate("1\n2", '説明'); // 2行

        $this->assertSame('説明は3行以上である必要があります。（現在: 2行）', $error);
    }

    // ========================================
    // 数値範囲のテスト（min）
    // ========================================

    public function test_min_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'int', min: 0);

        $error = $rule->validate(0, '年齢');

        $this->assertSame('', $error);
    }

    public function test_min_境界値超えもOK(): void
    {
        $rule = new ValidationRule(type: 'int', min: 0);

        $error = $rule->validate(1, '年齢');

        $this->assertSame('', $error);
    }

    public function test_min_境界値未満はNG(): void
    {
        $rule = new ValidationRule(type: 'int', min: 0);

        $error = $rule->validate(-1, '年齢');

        $this->assertSame('年齢は0以上である必要があります。', $error);
    }

    // ========================================
    // 数値範囲のテスト（max）
    // ========================================

    public function test_max_境界値ちょうどはOK(): void
    {
        $rule = new ValidationRule(type: 'int', max: 100);

        $error = $rule->validate(100, '年齢');

        $this->assertSame('', $error);
    }

    public function test_max_境界値未満もOK(): void
    {
        $rule = new ValidationRule(type: 'int', max: 100);

        $error = $rule->validate(99, '年齢');

        $this->assertSame('', $error);
    }

    public function test_max_境界値超えはNG(): void
    {
        $rule = new ValidationRule(type: 'int', max: 100);

        $error = $rule->validate(101, '年齢');

        $this->assertSame('年齢は100以下である必要があります。', $error);
    }

    // ========================================
    // 数値範囲のテスト（min と max の組み合わせ）
    // ========================================

    public function test_minMax_範囲内はOK(): void
    {
        $rule = new ValidationRule(type: 'int', min: 1, max: 100);

        $this->assertSame('', $rule->validate(1, '値'));    // 下限
        $this->assertSame('', $rule->validate(50, '値'));   // 中間
        $this->assertSame('', $rule->validate(100, '値'));  // 上限
    }

    public function test_minMax_範囲外はNG(): void
    {
        $rule = new ValidationRule(type: 'int', min: 1, max: 100);

        $this->assertStringContainsString('1以上', $rule->validate(0, '値'));
        $this->assertStringContainsString('100以下', $rule->validate(101, '値'));
    }

    // ========================================
    // email条件のテスト
    // ========================================

    public function test_email_正しい形式はOK(): void
    {
        $rule = new ValidationRule(type: 'string', conditions: ['email']);

        $this->assertSame('', $rule->validate('test@example.com', 'メール'));
        $this->assertSame('', $rule->validate('user.name+tag@domain.co.jp', 'メール'));
    }

    public function test_email_不正な形式はNG(): void
    {
        $rule = new ValidationRule(type: 'string', conditions: ['email']);

        $this->assertStringContainsString('メールアドレス形式', $rule->validate('invalid', 'メール'));
        $this->assertStringContainsString('メールアドレス形式', $rule->validate('missing@domain', 'メール'));
        $this->assertStringContainsString('メールアドレス形式', $rule->validate('@nodomain.com', 'メール'));
    }

    // ========================================
    // alphanumeric条件のテスト
    // ========================================

    public function test_alphanumeric_英数字のみはOK(): void
    {
        $rule = new ValidationRule(type: 'string', conditions: ['alphanumeric']);

        $this->assertSame('', $rule->validate('abc123', 'ID'));
        $this->assertSame('', $rule->validate('ABC', 'ID'));
        $this->assertSame('', $rule->validate('123', 'ID'));
    }

    public function test_alphanumeric_記号を含むとNG(): void
    {
        $rule = new ValidationRule(type: 'string', conditions: ['alphanumeric']);

        $this->assertStringContainsString('英数字のみ', $rule->validate('user@name', 'ID'));
        $this->assertStringContainsString('英数字のみ', $rule->validate('user-name', 'ID'));
        $this->assertStringContainsString('英数字のみ', $rule->validate('user_name', 'ID'));
        $this->assertStringContainsString('英数字のみ', $rule->validate('あいう', 'ID'));
    }

    // ========================================
    // 型変換（キャスト）のテスト
    // ========================================

    public function test_intにキャストできる(): void
    {
        $rule = new ValidationRule(type: 'int');

        $this->assertSame(123, $rule->cast('123'));
        $this->assertSame(0, $rule->cast('0'));
        $this->assertSame(-5, $rule->cast('-5'));
    }

    public function test_numberにキャストできる(): void
    {
        $rule = new ValidationRule(type: 'number');

        $this->assertSame(123.45, $rule->cast('123.45'));
        $this->assertSame(0.0, $rule->cast('0'));
    }

    public function test_booleanにキャストできる(): void
    {
        $rule = new ValidationRule(type: 'boolean');

        $this->assertTrue($rule->cast('1'));
        $this->assertTrue($rule->cast(1));
        $this->assertTrue($rule->cast('true'));
        $this->assertFalse($rule->cast('0'));
        $this->assertFalse($rule->cast(0));
        $this->assertFalse($rule->cast('false'));
    }

    // ========================================
    // fromArrayのテスト
    // ========================================

    public function test_スネークケース配列からルールを生成できる(): void
    {
        $rule = ValidationRule::fromArray([
            'type' => 'string',
            'min_length' => 1,
            'max_length' => 16,
            'min_line' => 1,
            'max_line' => 5,
        ]);

        $this->assertSame('string', $rule->type);
        $this->assertSame(1, $rule->minLength);
        $this->assertSame(16, $rule->maxLength);
        $this->assertSame(1, $rule->minLine);
        $this->assertSame(5, $rule->maxLine);
    }

    public function test_条件付き配列からルールを生成できる(): void
    {
        $rule = ValidationRule::fromArray([
            'type' => 'string',
            'conditions' => ['email', 'alphanumeric'],
        ]);

        $this->assertSame(['email', 'alphanumeric'], $rule->conditions);
    }

    // ========================================
    // 型チェックのテスト
    // ========================================

    public function test_string型に数値を渡すとNG(): void
    {
        $rule = new ValidationRule(type: 'string');

        $error = $rule->validate(123, 'テスト');

        $this->assertStringContainsString('文字列である必要があります', $error);
    }

    public function test_int型に文字列を渡すとNG(): void
    {
        $rule = new ValidationRule(type: 'int');

        $error = $rule->validate('abc', 'テスト');

        $this->assertStringContainsString('整数である必要があります', $error);
    }

    public function test_int型に数値文字列を渡すとOK(): void
    {
        $rule = new ValidationRule(type: 'int');

        $error = $rule->validate('123', 'テスト');

        $this->assertSame('', $error);
    }
}
