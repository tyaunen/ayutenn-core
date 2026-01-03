<?php
require 'vendor/autoload.php';

use ayutenn\core\migration\TableDefinition;

// JSON定義（nullableを省略）
$expectedDef = [
    'name' => 't1',
    'columns' => [
        'created_at' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
    ],
];
$expected = TableDefinition::fromArray($expectedDef);

echo "=== JSON定義からのSQL生成 ===\n";
echo $expected->toCreateSQL() . "\n\n";

$col = $expected->getColumn('created_at');
echo "created_atカラムのnullable: " . ($col->isNullable() ? 'true' : 'false') . "\n";
