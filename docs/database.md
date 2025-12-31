# Database（データベース）

データベース接続・操作に関するクラス群。PDO接続管理、クエリ実行の抽象化、処理結果のラッピングを提供する。

---

## DbConnector

PDO接続を管理するシングルトンクラス。同一リクエスト内では接続を使いまわす。

### 基本的な使い方

```php
use ayutenn\core\database\DbConnector;

// PDO接続を取得
$pdo = DbConnector::connectWithPdo();

// トランザクション
$pdo->beginTransaction();
try {
    // ...処理
    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollback();
}
```

### 必要な設定（env.json）

```json
{
    "PDO_DSN": "mysql:host=localhost;dbname=mydb;charset=utf8mb4",
    "PDO_USERNAME": "root",
    "PDO_PASSWORD": "password"
}
```

### APIリファレンス

#### `connectWithPdo(): PDO`

PDO接続を取得する。シングルトンパターンで、初回呼び出し時に接続を作成し、以降は同じ接続を返す。

#### `rollbackIfInTransaction(): bool`

接続済みかつトランザクション中の場合のみロールバックを実行する。ロールバックした場合は `true` を返す。

#### `reset(): void`

接続をリセットする（テスト用）。

---

## DataManager

データベース操作の抽象基底クラス。マネージャークラスを作成する際の基底として使用する。

### 基本的な使い方

```php
use ayutenn\core\database\DataManager;
use ayutenn\core\database\DbConnector;
use PDO;

class UserManager extends DataManager
{
    public function findById(int $id): ?array
    {
        return $this->executeAndFetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => [$id, PDO::PARAM_INT]]
        );
    }

    public function findAll(): array
    {
        return $this->executeAndFetchAll(
            'SELECT * FROM users',
            []
        );
    }
}

// 使用例
$pdo = DbConnector::connectWithPdo();
$userManager = new UserManager($pdo);
$user = $userManager->findById(1);
```

### APIリファレンス

#### `__construct(PDO $pdo)`

PDO接続を受け取りインスタンスを生成する。

#### `executeStatement(string $sql, array $params): PDOStatement` (protected)

SQLを実行し、PDOStatementを返す。パラメータ配列は `[プレースホルダ => [値, PDOデータ型定数]]` の形式。

#### `executeAndFetchAll(string $sql, array $params): array` (protected)

SQLを実行し、結果を連想配列の配列で返す。結果がない場合は空配列。

#### `executeAndFetchOne(string $sql, array $params): ?array` (protected)

SQLを実行し、結果を1行だけ連想配列で返す。結果がない場合は `null`。

---

## QueryResult

データベース処理結果を表現するデータストアクラス。処理の成功/失敗状態とデータを一緒に扱う。

### 基本的な使い方

```php
use ayutenn\core\database\QueryResult;

// 成功結果を生成
$result = QueryResult::success('ユーザーを取得しました', $userData);

// エラー結果を生成
$result = QueryResult::error('ユーザーが見つかりません');

// 警告結果を生成
$result = QueryResult::alert('データが古い可能性があります', $data);

// 結果の確認
if ($result->isSucceed()) {
    $data = $result->getData();
} else {
    echo $result->getErrorMessage();
}
```

### 終了コード定数

| 定数 | 値 | 説明 |
|------|-----|------|
| `CODE_SUCCESS` | 0 | 正常終了 |
| `CODE_ALERT` | 100 | 警告（成功だが注意が必要） |
| `CODE_ERROR` | 900 | エラー |

### APIリファレンス

#### ファクトリーメソッド

| メソッド | 説明 |
|---------|------|
| `success(string $message, $data = null): self` | 成功結果を生成 |
| `error(string $message, $data = null): self` | エラー結果を生成 |
| `alert(string $message, $data = null): self` | 警告結果を生成 |

#### インスタンスメソッド

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `isSucceed()` | bool | 成功かどうか（CODE_SUCCESSのみtrue） |
| `getData()` | mixed | 処理結果データを取得 |
| `getErrorMessage()` | ?string | エラー時はメッセージ、成功時はnull |
| `getCodeName()` | string | 終了状態の日本語名（正常終了/警告/エラー） |

### 活用シーン

- 検索結果が0件なのかエラーなのかを区別したい場合
- 処理の成否と結果データを一緒に返したい場合
- 成功したが警告を伝えたい場合

```php
public function findUser(int $id): QueryResult
{
    try {
        $user = $this->executeAndFetchOne('SELECT * FROM users WHERE id = :id', [':id' => [$id, PDO::PARAM_INT]]);

        if ($user === null) {
            return QueryResult::alert('ユーザーが見つかりません');
        }

        return QueryResult::success('取得成功', $user);
    } catch (\Exception $e) {
        return QueryResult::error($e->getMessage());
    }
}
```
