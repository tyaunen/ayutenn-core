<?php

namespace ayutenn\core\tests\validation;

use PHPUnit\Framework\TestCase;
use ayutenn\core\validation\Validator;

class ValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/validation_test_' . uniqid();
        mkdir($this->tempDir);

        // テスト用ルールファイルを作成
        file_put_contents("{$this->tempDir}/user_seq.json", json_encode([
            'type' => 'string',
            'min_length' => 1,
            'max_length' => 10,
        ]));

        file_put_contents("{$this->tempDir}/user_name.json", json_encode([
            'type' => 'string',
            'min_length' => 1,
            'max_length' => 16,
        ]));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*.json"));
            rmdir($this->tempDir);
        }
    }

    public function test_item型のバリデーションができる(): void
    {
        $format = [
            'username' => [
                'type' => 'item',
                'name' => 'ユーザー名',
                'format' => 'user_name',
                'require' => true,
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate(['username' => 'testuser']);

        $this->assertFalse($result->hasErrors());
        $this->assertSame('testuser', $result->getValue('username'));
    }

    public function test_必須パラメータが欠けている場合エラーになる(): void
    {
        $format = [
            'username' => [
                'type' => 'item',
                'name' => 'ユーザー名',
                'format' => 'user_name',
                'require' => true,
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate([]);

        $this->assertTrue($result->hasErrors());
        $this->assertSame('ユーザー名は必須です。', $result->getError('username'));
    }

    public function test_任意パラメータが欠けていてもエラーにならない(): void
    {
        $format = [
            'nickname' => [
                'type' => 'item',
                'name' => 'ニックネーム',
                'format' => 'user_name',
                'require' => false,
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate([]);

        $this->assertFalse($result->hasErrors());
    }

    public function test_インラインフォーマットでバリデートできる(): void
    {
        $format = [
            'username' => [
                'type' => 'item',
                'name' => 'ユーザー名',
                'format' => [
                    'type' => 'string',
                    'min_length' => 1,
                    'max_length' => 16,
                ],
                'require' => true,
            ],
        ];

        $validator = new Validator($format); // rulesDirなしでOK
        $result = $validator->validate(['username' => 'testuser']);

        $this->assertFalse($result->hasErrors());
    }

    public function test_object型のネストバリデーションができる(): void
    {
        $format = [
            'user' => [
                'type' => 'object',
                'name' => 'ユーザー',
                'properties' => [
                    'user_seq' => [
                        'type' => 'item',
                        'name' => 'user_seq',
                        'format' => 'user_seq',
                        'require' => true,
                    ],
                    'user_name' => [
                        'type' => 'item',
                        'name' => 'ユーザー名',
                        'format' => 'user_name',
                        'require' => true,
                    ],
                ],
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate([
            'user' => [
                'user_seq' => 'aaa',
                'user_name' => 'tyaunen',
            ],
        ]);

        $this->assertFalse($result->hasErrors());
        $user = $result->getValue('user');
        $this->assertSame('aaa', $user['user_seq']);
        $this->assertSame('tyaunen', $user['user_name']);
    }

    public function test_list型のバリデーションができる(): void
    {
        $format = [
            'icon_list' => [
                'type' => 'list',
                'name' => 'アイコンリスト',
                'items' => [
                    'type' => 'item',
                    'name' => 'アイコン',
                    'format' => 'user_name',
                    'require' => true,
                ],
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate([
            'icon_list' => ['icon_1.jpg', 'icon_2.jpg'],
        ]);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(['icon_1.jpg', 'icon_2.jpg'], $result->getValue('icon_list'));
    }

    public function test_深いネスト構造をバリデートできる(): void
    {
        $format = [
            'user' => [
                'type' => 'object',
                'name' => 'ユーザー',
                'properties' => [
                    'user_seq' => [
                        'name' => 'user_seq',
                        'format' => 'user_seq',
                        'require' => true,
                    ],
                    'user_name' => [
                        'name' => 'ユーザー名',
                        'format' => 'user_name',
                        'require' => true,
                    ],
                    'icon_list' => [
                        'type' => 'list',
                        'name' => 'アイコンリスト',
                        'items' => [
                            'name' => 'アイコン',
                            'format' => 'user_name',
                            'require' => true,
                        ],
                    ],
                    'friends' => [
                        'type' => 'list',
                        'name' => 'フレンドリスト',
                        'items' => [
                            'type' => 'object',
                            'name' => 'フレンド',
                            'properties' => [
                                'user_seq' => [
                                    'name' => 'フレンドSEQ',
                                    'format' => 'user_seq',
                                    'require' => true,
                                ],
                                'user_name' => [
                                    'name' => 'フレンド名',
                                    'format' => 'user_name',
                                    'require' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $value = [
            'user' => [
                'user_seq' => 'aaa',
                'user_name' => 'tyaunen',
                'icon_list' => [
                    'icon_1.jpg',
                    'icon_2.jpg',
                ],
                'friends' => [
                    [
                        'user_seq' => 'bbb',
                        'user_name' => 'friend_1',
                    ],
                    [
                        'user_seq' => 'ccc',
                        'user_name' => 'friend_2',
                    ],
                ],
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate($value);

        $this->assertFalse($result->hasErrors());
        $user = $result->getValue('user');
        $this->assertSame('tyaunen', $user['user_name']);
        $this->assertCount(2, $user['icon_list']);
        $this->assertCount(2, $user['friends']);
        $this->assertSame('friend_1', $user['friends'][0]['user_name']);
    }

    public function test_ネスト内のバリデーションエラーを検出できる(): void
    {
        $format = [
            'user' => [
                'type' => 'object',
                'name' => 'ユーザー',
                'properties' => [
                    'user_name' => [
                        'type' => 'item',
                        'name' => 'ユーザー名',
                        'format' => 'user_name', // max_length: 16
                        'require' => true,
                    ],
                ],
            ],
        ];

        $value = [
            'user' => [
                'user_name' => '12345678901234567', // 17文字 > 16文字
            ],
        ];

        $validator = new Validator($format, $this->tempDir);
        $result = $validator->validate($value);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('ユーザー名', $result->getError('user'));
        $this->assertStringContainsString('16文字以下', $result->getError('user'));
    }
}
