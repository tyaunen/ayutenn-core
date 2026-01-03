<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE IF NOT EXISTS null_test');
$pdo->exec('USE null_test');

echo "=== MySQLのNULL/NOT NULL動作確認 ===\n\n";

// テスト1: DEFAULT指定あり、NULL/NOT NULL指定なし
$pdo->exec('DROP TABLE IF EXISTS t1');
$pdo->exec('CREATE TABLE t1 (id INT, col1 DATETIME DEFAULT CURRENT_TIMESTAMP)');
$stmt = $pdo->query("SHOW COLUMNS FROM t1 WHERE Field = 'col1'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "1. DEFAULT CURRENT_TIMESTAMP（NULL指定なし）\n";
echo "   IS_NULLABLE: {$col['Null']}\n\n";

// テスト2: DEFAULT指定あり、NOT NULL明示
$pdo->exec('DROP TABLE IF EXISTS t2');
$pdo->exec('CREATE TABLE t2 (id INT, col1 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$stmt = $pdo->query("SHOW COLUMNS FROM t2 WHERE Field = 'col1'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "2. NOT NULL DEFAULT CURRENT_TIMESTAMP\n";
echo "   IS_NULLABLE: {$col['Null']}\n\n";

// テスト3: DEFAULT指定あり、NULL明示
$pdo->exec('DROP TABLE IF EXISTS t3');
$pdo->exec('CREATE TABLE t3 (id INT, col1 DATETIME NULL DEFAULT CURRENT_TIMESTAMP)');
$stmt = $pdo->query("SHOW COLUMNS FROM t3 WHERE Field = 'col1'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "3. NULL DEFAULT CURRENT_TIMESTAMP\n";
echo "   IS_NULLABLE: {$col['Null']}\n\n";

$pdo->exec('DROP DATABASE null_test');
