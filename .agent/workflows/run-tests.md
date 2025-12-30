---
description: テストを実行する
---

# テスト実行

// turbo
```bash
composer test
```

または、以下のコマンドでも実行可能です：

```bash
./vendor/bin/phpunit
```

## 特定のテストのみ実行

```bash
./vendor/bin/phpunit --filter=テスト名
```

## テストが失敗した場合

1. エラーメッセージを確認
2. 該当するテストファイルを開く
3. 問題を特定して修正
4. 再度テストを実行

## 新しいテストを作成する場合

### ディレクトリ構造

```
tests/
└── unit/
    └── (機能名)/
        └── (クラス名)Test.php
```

### テストメソッド名

`test_(日本語の説明)` 形式で記述：

```php
public function test_正常な入力でtrueを返す(): void
public function test_不正な入力で例外が発生する(): void
```

### テストの原則

- 各テストは独立して実行可能であること
- setUp/tearDownで状態をリセットすること
- インテグレーションテストで実際の動きを再現することを重視
