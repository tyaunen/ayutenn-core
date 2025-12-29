---
description: データベーステーブルを宣言・作成する
---

# テーブルの作成

このプロジェクトは **ayutenn-core** フレームワークを使用しています。
テーブルを作成・変更する際は、**必ず宣言的マイグレーション機能**を使用してください。

## 手順

### 1. テーブル定義JSONを作成

`tables/` ディレクトリに JSON 形式でテーブル定義を作成します。

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

### 2. マイグレーションSQLを生成

```bash
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations
```

プレビューのみの場合:
```bash
php vendor/bin/migrate.php --config=./config/env.json --tables=./tables --output=./migrations --preview
```

### 3. SQLを実行

生成されたSQLファイルを確認し、データベースに適用します。

```bash
mysql -u user -p database < migrations/YYYYMMDD_HHMMSS_migration.sql
```

## カラム型一覧

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

## カラム共通属性

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `nullable` | boolean | `false` | NULL許容 |
| `default` | mixed | なし | デフォルト値 |
| `comment` | string | なし | カラムコメント |
| `unique` | boolean | `false` | ユニーク制約 |
| `unsigned` | boolean | `false` | 符号なし（数値型のみ） |
| `autoIncrement` | boolean | `false` | 自動採番（数値型のみ） |

## インデックス・外部キー

```json
{
  "name": "posts",
  "columns": { ... },
  "primaryKey": ["id"],
  "indexes": {
    "idx_user_id": {
      "columns": ["user_id"],
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

## 詳細ドキュメント

詳細は `vendor/tyaunen/ayutenn-core/docs/migration.md` を参照してください。
