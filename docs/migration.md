# マイグレーション機能

宣言的なマイグレーションツールです。テーブル定義をJSONファイルで管理し、実際のデータベースとの差分からDDL（SQLファイル）を自動生成します。

## 概要

従来のマイグレーションツールは変更差分を順次記述するのに対し、このツールは**宣言的アプローチ**を採用しています。

- テーブルの「あるべき姿」をJSONで定義
- 現在のDBと比較して差分を自動検出
- 差分を解消するSQLを自動生成

## クイックスタート

### 1. テーブル定義JSONを作成

```json
// tables/users.json
{
  "name": "users",
  "comment": "ユーザーテーブル",
  "columns": {
    "id": {
      "type": "int",
      "unsigned": true,
      "autoIncrement": true
    },
    "email": {
      "type": "varchar",
      "length": 255,
      "nullable": false,
      "unique": true
    },
    "created_at": {
      "type": "datetime",
      "default": "CURRENT_TIMESTAMP"
    }
  },
  "primaryKey": ["id"]
}
```

### 2. マイグレーションを生成

#### CLI（推奨）

```bash
# 設定ファイルを使用
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations

# プレビュー（ファイル出力なし）
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --preview

# DSN直接指定
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --tables=./tables --output=./migrations
```

#### PHPコードから

```php
use ayutenn\core\migration\MigrationManager;
use ayutenn\core\database\DbConnector;

$pdo = DbConnector::connectWithPdo();
$manager = new MigrationManager(
    $pdo,
    '/path/to/tables',      // テーブル定義JSONディレクトリ
    '/path/to/migrations'   // SQL出力ディレクトリ
);

// SQLファイルを生成
$filepath = $manager->generateMigration();

// previewで事前確認も可能
$preview = $manager->preview();
echo $preview['sql'];
```

### 3. 生成されたSQLを実行

```bash
mysql -u user -p database < migrations/20241214_101154_migration.sql
```

---

## テーブル定義JSONスキーマ

### 基本構造

```json
{
  "name": "テーブル名（必須）",
  "comment": "テーブルコメント",
  "engine": "InnoDB",
  "charset": "utf8mb4",
  "collation": "utf8mb4_unicode_ci",
  "columns": { ... },
  "primaryKey": ["カラム名"],
  "indexes": { ... },
  "foreignKeys": { ... }
}
```

> [!NOTE]
> `engine`, `charset`, `collation` は省略可能です。
> 省略した場合、デフォルト値（InnoDB, utf8mb4, utf8mb4_unicode_ci）が使用されます。

### カラム定義

```json
"columns": {
  "カラム名": {
    "type": "型名",
    "length": 255,
    "nullable": false,
    "default": "デフォルト値",
    ...
  }
}
```

#### サポートするカラム型

| 型 | type値 | 追加属性 |
|---|---|---|
| INT | `int` | `unsigned`, `autoIncrement` |
| BIGINT | `bigint` | `unsigned`, `autoIncrement` |
| TINYINT | `tinyint` | `unsigned` |
| DECIMAL | `decimal` | `precision`, `scale` |
| VARCHAR | `varchar` | `length`（必須） |
| CHAR | `char` | `length`（必須） |
| TEXT | `text` | - |
| LONGTEXT | `longtext` | - |
| DATETIME | `datetime` | `onUpdate` |
| TIMESTAMP | `timestamp` | `onUpdate` |
| DATE | `date` | - |
| TIME | `time` | - |
| BOOLEAN | `boolean` | - |
| ENUM | `enum` | `values`（必須） |
| JSON | `json` | - |

#### カラム共通属性

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `nullable` | boolean | `false` | NULL許容 |
| `default` | mixed | なし | デフォルト値 |
| `comment` | string | なし | カラムコメント |
| `unique` | boolean | `false` | ユニーク制約 |
| `unsigned` | boolean | `false` | 符号なし（数値型のみ） |
| `autoIncrement` | boolean | `false` | 自動採番（数値型のみ） |
| `after` | string | なし | 配置位置（ALTER時用） |
| `onUpdate` | string | なし | ON UPDATE句 |

#### カラム定義例

```json
"columns": {
  "id": {
    "type": "int",
    "unsigned": true,
    "autoIncrement": true
  },
  "email": {
    "type": "varchar",
    "length": 255,
    "nullable": false,
    "unique": true,
    "comment": "メールアドレス"
  },
  "price": {
    "type": "decimal",
    "precision": 10,
    "scale": 2,
    "default": 0
  },
  "status": {
    "type": "enum",
    "values": ["active", "inactive", "pending"],
    "default": "active"
  },
  "created_at": {
    "type": "datetime",
    "default": "CURRENT_TIMESTAMP"
  },
  "updated_at": {
    "type": "datetime",
    "nullable": true,
    "onUpdate": "CURRENT_TIMESTAMP"
  }
}
```

### インデックス定義

```json
"indexes": {
  "インデックス名": {
    "columns": ["カラム名1", "カラム名2"],
    "unique": false
  }
}
```

### 外部キー定義

```json
"foreignKeys": {
  "外部キー名": {
    "columns": ["カラム名"],
    "references": {
      "table": "参照先テーブル",
      "columns": ["参照先カラム"]
    },
    "onDelete": "CASCADE",
    "onUpdate": "CASCADE"
  }
}
```

---

## 完全なテーブル定義例

```json
{
  "name": "posts",
  "comment": "投稿テーブル",
  "columns": {
    "id": {
      "type": "int",
      "unsigned": true,
      "autoIncrement": true
    },
    "user_id": {
      "type": "int",
      "unsigned": true,
      "comment": "投稿者ID"
    },
    "title": {
      "type": "varchar",
      "length": 200,
      "nullable": false
    },
    "content": {
      "type": "text",
      "nullable": true
    },
    "status": {
      "type": "enum",
      "values": ["draft", "published", "archived"],
      "default": "draft"
    },
    "view_count": {
      "type": "int",
      "unsigned": true,
      "default": 0
    },
    "created_at": {
      "type": "datetime",
      "default": "CURRENT_TIMESTAMP"
    },
    "updated_at": {
      "type": "datetime",
      "nullable": true,
      "onUpdate": "CURRENT_TIMESTAMP"
    }
  },
  "primaryKey": ["id"],
  "indexes": {
    "idx_user_id": {
      "columns": ["user_id"],
      "unique": false
    },
    "idx_status_created": {
      "columns": ["status", "created_at"],
      "unique": false
    }
  },
  "foreignKeys": {
    "fk_posts_user": {
      "columns": ["user_id"],
      "references": {
        "table": "users",
        "columns": ["id"]
      },
      "onDelete": "CASCADE",
      "onUpdate": "CASCADE"
    }
  }
}
```

---

## クラス構成

| クラス | 役割 |
|---|---|
| `Column` | カラム定義を表現 |
| `TableDefinition` | テーブル全体の定義を保持 |
| `TableDefinitionLoader` | JSONファイルからテーブル定義を読み込み |
| `DatabaseInspector` | 実際のDB構造を取得 |
| `SchemaDiffer` | 期待される定義と実際の構造の差分を検出 |
| `DDLGenerator` | 差分からDDL文を生成 |
| `MigrationManager` | 全体の処理フローを制御 |

---

## CLI Reference

コマンドラインからマイグレーションを実行できます。

### 使用方法

```bash
# Composerパッケージとして利用する場合
php vendor/bin/migrate.php [options]

# フレームワーク単体で利用する場合
php bin/migrate.php [options]
```

### オプション

| オプション | 説明 |
|---|---|
| `--config=<path>` | 設定ファイルパス（`PDO_DSN`, `PDO_USERNAME`, `PDO_PASSWORD`を含む） |
| `--dsn=<dsn>` | PDO DSN（`--config`がない場合は必須） |
| `--user=<user>` | DBユーザー名（`--config`がない場合は必須） |
| `--password=<password>` | DBパスワード（省略時: 空文字） |
| `--tables=<dir>` | テーブル定義JSONディレクトリ（必須） |
| `--output=<dir>` | SQL出力ディレクトリ（必須） |
| `--preview` | プレビューのみ（ファイル出力しない） |
| `--drop-unknown` | 定義にないテーブルを削除対象に含める |
| `--help` | ヘルプを表示 |

### 使用例

```bash
# 設定ファイルを使用（推奨）
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations

# プレビューのみ
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --preview

# DSN直接指定
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --tables=./tables --output=./migrations

# 定義にないテーブルを削除対象に含める
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --drop-unknown
```

---

## API

### MigrationManager

```php
// コンストラクタ
$manager = new MigrationManager(
    PDO $pdo,
    string $definitionsDir,  // テーブル定義JSONディレクトリ
    string $outputDir        // SQL出力ディレクトリ
);

// マイグレーションSQLを生成してファイルに出力
// 返り値: 生成したファイルパス（差分がない場合はnull）
$filepath = $manager->generateMigration(bool $dropUnknown = false);

// プレビュー（ファイル出力なし）
$result = $manager->preview(bool $dropUnknown = false);
// $result['diffs'] - 検出された差分の配列
// $result['sql'] - 生成されるSQL文
```

### 出力ファイル

- **ファイル名形式**: `{YYYYMMDD_HHMMSS}_migration.sql`
- **例**: `20241214_101154_migration.sql`

```sql
-- ============================================
-- Migration generated at 2024-12-14 10:11:54
-- Declarative Migration Tool
-- ============================================
--
-- Summary:
-- users: テーブル作成
-- posts: カラム追加

-- Table: users (新規作成)
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: posts - カラム追加: view_count
ALTER TABLE `posts` ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0;
```

---

## 検出される差分

| 差分タイプ | 説明 |
|---|---|
| `create_table` | テーブルが存在しない |
| `drop_table` | 定義にないテーブル（`dropUnknown=true`時のみ） |
| `add_column` | カラム追加 |
| `modify_column` | カラム定義変更 |
| `drop_column` | カラム削除 |
| `add_index` | インデックス追加 |
| `drop_index` | インデックス削除 |
| `add_foreign_key` | 外部キー追加 |
| `drop_foreign_key` | 外部キー削除 |

> [!WARNING]
> カラムの順序変更は検出対象外です。
> カラムの順序を変更したい場合は、手動でALTER文を作成してください。
