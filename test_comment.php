<?php
require 'vendor/autoload.php';

use ayutenn\core\migration\DatabaseInspector;
use ayutenn\core\migration\TableDefinition;

$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE IF NOT EXISTS diff_test');
$pdo->exec('USE diff_test');

$pdo->exec('DROP TABLE IF EXISTS t4');
$pdo->exec("CREATE TABLE t4 (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) COMMENT 'メールアドレス') ENGINE=InnoDB");

$inspector = new DatabaseInspector($pdo);
$actual4 = $inspector->getTableDefinition('t4');
$expected4 = TableDefinition::fromArray([
    'name' => 't4',
    'columns' => [
        'id' => ['type' => 'int', 'autoIncrement' => true],
        'email' => ['type' => 'varchar', 'length' => 255, 'comment' => 'メールアドレス'],
    ],
    'primaryKey' => ['id'],
]);

echo "=== コメントテスト詳細 ===\n";
$actualCol = $actual4->getColumn('email');
$expectedCol = $expected4->getColumn('email');

echo "DBカラム:\n";
print_r($actualCol->toArray());
echo "\nJSONカラム:\n";
print_r($expectedCol->toArray());

echo "\n差分項目:\n";
foreach ($expectedCol->toArray() as $key => $val) {
    $actualVal = $actualCol->toArray()[$key];
    if ($val !== $actualVal) {
        echo "$key: expected=" . var_export($val, true) . ", actual=" . var_export($actualVal, true) . "\n";
    }
}

$pdo->exec('DROP DATABASE diff_test');
