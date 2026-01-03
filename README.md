# ayutenn-core

御茶請稔による PHP オレオレフレームワークです。
学習目的でフルスクラッチ実装を優先しており、第三者からの利用は想定していません。
ほぼAI産です。

## 特徴

- **シンプル設計** - 必要最小限の機能のみ実装
- **フルスクラッチ** - 外部ライブラリを最小限に抑え、学習目的で自作
- **宣言的マイグレーション** - JSON でテーブル定義を記述し、差分 SQL を自動生成

## 要件

- PHP 8.0 以上
- Composer

## インストール

このパッケージは Packagist には公開されていません。GitHub リポジトリから直接インストールしてください。

### 1. composer.json に repositories を追加

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/tyaunen/ayutenn-core"
        }
    ]
}
```

### 2. パッケージをインストール

```bash
composer require tyaunen/ayutenn-core
```

### 開発版を使用する場合

安定版リリース前や最新の開発版を使用したい場合：

```bash
composer require tyaunen/ayutenn-core:dev-main
```

> **Note**
> `minimum-stability` が `stable` の場合、開発版をインストールするには上記のようにブランチを明示的に指定するか、`composer.json` に `"minimum-stability": "dev"` を設定してください。

## 機能一覧

| 機能 | 説明 | ドキュメント |
|------|------|--------------|
| **FrameworkPaths** | パス設定を一元管理（bootstrap用） | [docs/framework_paths.md](docs/framework_paths.md) |
| **Config** | JSON から設定を読み込む静的クラス | [docs/config.md](docs/config.md) |
| **Database** | DB接続管理、クエリ実行抽象化、結果ラッピング | [docs/database.md](docs/database.md) |
| **Routing** | URL ルーティング、ミドルウェア、ルートグループ | [docs/routing.md](docs/routing.md) |
| **Validation** | 宣言的なバリデーションとキャスト | [docs/validation.md](docs/validation.md) |
| **Requests** | API / Controller の基底クラス | [docs/requests.md](docs/requests.md) |
| **Migration** | 宣言的マイグレーション（JSON → SQL）+ CLI | [docs/migration.md](docs/migration.md) |
| **Session** | フラッシュメッセージ管理 | [docs/session.md](docs/session.md) |
| **Utils** | CSRF, ファイル操作, ロガー, UUID 等 | [docs/utils.md](docs/utils.md) |

## CLIツール

### マイグレーション

コマンドラインからマイグレーションを実行できます：

```bash
# 基本的な使用方法
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --password=secret --tables=./migrations/define --output=./migrations/ddl

# プレビューモード（SQLを表示するだけで実行しない）
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --password=secret --tables=./migrations/define --output=./migrations/ddl --preview

# ルールファイルを使用（formatキー使用時）
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --password=secret --tables=./migrations/define --output=./migrations/ddl --rules=./app/model

# 不要なテーブルを削除
php vendor/bin/migrate.php --dsn="mysql:host=localhost;dbname=mydb" --user=root --password=secret --tables=./migrations/define --output=./migrations/ddl --drop-unknown
```

詳細は [docs/migration.md](docs/migration.md) を参照してください。

## ディレクトリ構成

```
├── agent-workflows/   # AIエージェント向けワークフロー（親プロジェクトにコピーして使用）
├── bin/               # CLIツール
│   └── migrate.php    # マイグレーションCLI
├── docs/              # ドキュメント
├── src/
│   ├── FrameworkPaths.php  # パス設定クラス
│   ├── config/             # 設定管理
│   ├── database/           # DB接続・クエリ実行
│   ├── migration/          # マイグレーションツール
│   ├── requests/           # API / Controller 基底クラス
│   ├── routing/            # ルーティング
│   ├── session/            # セッション管理
│   ├── utils/              # ユーティリティ
│   └── validation/         # バリデーション
├── tests/             # テストコード
├── workflows/         # フレームワーク開発用ワークフロー
├── AGENT.md           # AI開発ガイドライン
└── README.md
```

## 開発者向け情報

### AGENT.md

AIエージェントがこのプロジェクトで開発する際のガイドラインは [AGENT.md](AGENT.md) を参照してください。

タスク遂行フローとして、以下の順序が推奨されています：

1. ドキュメント確認（`/docs` の参照）
2. 不明点の確認
3. 実装提案
4. タスクの遂行
5. テストコードの作成と実行
6. セルフレビュー
7. ドキュメントの更新

## AIエージェント向けワークフロー

このフレームワークには、AIエージェントがフレームワークの機能を正しく使用するためのワークフローファイルが含まれています。

### 親プロジェクトでの利用

Composerでインストール後、ワークフローファイルを親プロジェクトにコピーしてください：

**PowerShell（Windows）:**
```powershell
Copy-Item -Path "vendor/tyaunen/ayutenn-core/agent-workflows/*" -Destination ".agent/workflows/" -Recurse -Force
```

**Bash（Linux/Mac）:**
```bash
mkdir -p .agent/workflows
cp -r vendor/tyaunen/ayutenn-core/agent-workflows/* .agent/workflows/
```

### 利用可能なワークフロー

| コマンド | 説明 |
|---------|------|
| `/setup-project` | プロジェクトの初期設定（Bootstrap）を行う |
| `/create-table` | データベーステーブルを宣言・作成する |
| `/create-route` | URLルーティングを定義する |
| `/create-validation` | バリデーションルールを定義する |
| `/create-controller` | Webフォーム処理用コントローラーを作成する |
| `/create-api` | JSON APIエンドポイントを作成する |
| `/add-flash-message` | フラッシュメッセージを追加する |
| `/add-csrf-protection` | CSRF保護を追加する |
| `/add-file-upload` | ファイルアップロード機能を追加する |
| `/add-logging` | ログ出力機能を追加する |

## テスト

```bash
composer test
```

テストメソッド名は `test_(日本語の説明)` 形式で記述します。

```php
public function test_JSONファイルから設定を読み込める(): void
```

## ライセンス

CC-BY-1.0
