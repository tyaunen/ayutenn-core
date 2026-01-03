<?php
require 'vendor/autoload.php';

use ayutenn\core\migration\DatabaseInspector;
use ayutenn\core\migration\TableDefinition;
use ayutenn\core\migration\SchemaDiffer;

$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE IF NOT EXISTS diff_test');
$pdo->exec('USE diff_test');

$inspector = new DatabaseInspector($pdo);
$differ = new SchemaDiffer();

echo "=== デフォルト値とコメントの差分チェック総合テスト ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

// テスト1: 数値デフォルト
$pdo->exec('DROP TABLE IF EXISTS t1');
$pdo->exec('CREATE TABLE t1 (id INT AUTO_INCREMENT PRIMARY KEY, count INT DEFAULT 0) ENGINE=InnoDB');

$actual = $inspector->getTableDefinition('t1');
$expected = TableDefinition::fromArray([
    'name' => 't1',
    'columns' => [
        'id' => ['type' => 'int', 'autoIncrement' => true],
        'count' => ['type' => 'int', 'default' => 0],
    ],
    'primaryKey' => ['id'],
]);

echo "1. 数値デフォルト (default: 0): ";
$diffs = $differ->diff($expected, $actual);
if (count($diffs) === 0) {
    echo "OK\n";
    $testsPassed++;
} else {
    echo "FAILED (差分: " . count($diffs) . ")\n";
    $testsFailed++;
}

// テスト2: CURRENT_TIMESTAMP
$pdo->exec('DROP TABLE IF EXISTS t2');
$pdo->exec('CREATE TABLE t2 (id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');

$actual2 = $inspector->getTableDefinition('t2');
$expected2 = TableDefinition::fromArray([
    'name' => 't2',
    'columns' => [
        'id ' => ['type' => 'int', 'autoIncrement' => true],
        'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
    ],
    'primaryKey' => ['id'],
]);

echo "2. CURRENT_TIMESTAMP: ";
$diffs2 = $differ->diff($expected2, $actual2);
if (count($diffs2) === 0) {
    echo "OK\n";
    $testsPassed++;
} else {
    echo "FAILED (差分: " . count($diffs2) . ")\n";
    $testsFailed++;
}

// テスト3: 文字列デフォルト
$pdo->exec('DROP TABLE IF EXISTS t3');
$pdo->exec("CREATE TABLE t3 (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20) DEFAULT 'active') ENGINE=InnoDB");

$actual3 = $inspector->getTableDefinition('t3');
$expected3 = TableDefinition::fromArray([
    'name' => 't3',
    'columns' => [
        'id' => ['type' => 'int', 'autoIncrement' => true],
        'status' => ['type' => 'varchar', 'length' => 20, 'default' => 'active'],
    ],
    'primaryKey' => ['id'],
]);

echo "3. 文字列デフォルト (default: 'active'): ";
$diffs3 = $differ->diff($expected3, $actual3);
if (count($diffs3) === 0) {
    echo "OK\n";
    $testsPassed++;
} else {
    echo "FAILED (差分: " . count($diffs3) . ")\n";
    $testsFailed++;
}

// テスト4: コメント（nullable: trueを明示）
$pdo->exec('DROP TABLE IF EXISTS t4');
$pdo->exec("CREATE TABLE t4 (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) COMMENT 'メールアドレス') ENGINE=InnoDB");

$actual4 = $inspector->getTableDefinition('t4');
$expected4 = TableDefinition::fromArray([
    'name' => 't4',
    'columns' => [
        'id' => ['type' => 'int', 'autoIncrement' => true],
        'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => true, 'comment' => 'メールアドレス'],
    ],
    'primaryKey' => ['id'],
]);

echo "4. コメント（nullable: true明示）: ";
$diffs4 = $differ->diff($expected4, $actual4);
if (count($diffs4) === 0) {
    echo "OK\n";
    $testsPassed++;
} else {
    echo "FAILED (差分: " . count($diffs4) . ")\n";
    $testsFailed++;
}

// テスト5: NOT NULL DEFAULT（明示的にnullable: falseを指定）
$pdo->exec('DROP TABLE IF EXISTS t5');
$pdo->exec("CREATE TABLE t5 (id INT AUTO_INCREMENT PRIMARY KEY, score INT NOT NULL DEFAULT 100) ENGINE=InnoDB");

$actual5 = $inspector->getTableDefinition('t5');
$expected5 = TableDefinition::fromArray([
    'name' => 't5',
    'columns' => [
        'id' => ['type' => 'int', 'autoIncrement' => true],
        'score' => ['type' => 'int', 'nullable' => false, 'default' => 100],
    ],
    'primaryKey' => ['id'],
]);

echo "5. NOT NULL DEFAULT（nullable: false明示）: ";
$diffs5 = $differ->diff($expected5, $actual5);
if (count($diffs5) === 0) {
    echo "OK\n";
    $testsPassed++;
} else {
    echo "FAILED (差分: " . count($diffs5) . ")\n";
    $testsFailed++;
}

$pdo->exec('DROP DATABASE diff_test');

echo "\n=== テスト結果 ===\n";
echo "成功: $testsPassed\n";
echo "失敗: $testsFailed\n";
echo ($testsFailed === 0 ? "全テストパス！\n" : "テスト失敗があります\n");
