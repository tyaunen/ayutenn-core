<?php
namespace ayutenn\core\migration;

/**
 * 【概要】
 * DDL生成クラス
 *
 * 【解説】
 * 差分情報からMySQL用のDDL（CREATE TABLE, ALTER TABLE等）を生成する。
 */
class DDLGenerator
{
    /**
     * 差分情報からDDL文を生成
     *
     * @param array $diffs 差分情報の配列
     * @return string SQL文
     */
    public function generate(array $diffs): string
    {
        if (empty($diffs)) {
            return '';
        }

        $statements = [];

        foreach ($diffs as $diff) {
            $sql = $this->generateStatement($diff);
            if ($sql !== null) {
                $statements[] = $sql;
            }
        }

        return implode("\n\n", $statements);
    }

    /**
     * 個別の差分からSQL文を生成
     */
    private function generateStatement(array $diff): ?string
    {
        return match ($diff['type']) {
            SchemaDiffer::CREATE_TABLE => $this->generateCreateTable($diff),
            SchemaDiffer::DROP_TABLE => $this->generateDropTable($diff),
            SchemaDiffer::ADD_COLUMN => $this->generateAddColumn($diff),
            SchemaDiffer::MODIFY_COLUMN => $this->generateModifyColumn($diff),
            SchemaDiffer::DROP_COLUMN => $this->generateDropColumn($diff),
            SchemaDiffer::ADD_INDEX => $this->generateAddIndex($diff),
            SchemaDiffer::DROP_INDEX => $this->generateDropIndex($diff),
            SchemaDiffer::ADD_FOREIGN_KEY => $this->generateAddForeignKey($diff),
            SchemaDiffer::DROP_FOREIGN_KEY => $this->generateDropForeignKey($diff),
            default => null,
        };
    }

    /**
     * CREATE TABLE文を生成
     */
    private function generateCreateTable(array $diff): string
    {
        /** @var TableDefinition $definition */
        $definition = $diff['definition'];
        $comment = "-- Table: {$diff['table']} (新規作成)";
        return $comment . "\n" . $definition->toCreateSQL();
    }

    /**
     * DROP TABLE文を生成
     */
    private function generateDropTable(array $diff): string
    {
        $table = $diff['table'];
        return "-- Table: {$table} (削除)\nDROP TABLE IF EXISTS `{$table}`;";
    }

    /**
     * ADD COLUMN文を生成
     */
    private function generateAddColumn(array $diff): string
    {
        $table = $diff['table'];
        /** @var Column $column */
        $column = $diff['column'];

        $sql = "ALTER TABLE `{$table}` ADD COLUMN " . $column->toSQL();

        // AFTER句
        if ($column->getAfter() !== null) {
            $sql .= " AFTER `{$column->getAfter()}`";
        }

        $comment = "-- Table: {$table} - カラム追加: {$column->getName()}";
        return $comment . "\n" . $sql . ';';
    }

    /**
     * MODIFY COLUMN文を生成
     */
    private function generateModifyColumn(array $diff): string
    {
        $table = $diff['table'];
        /** @var Column $column */
        $column = $diff['column'];

        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN " . $column->toSQL();

        $comment = "-- Table: {$table} - カラム変更: {$column->getName()}";
        return $comment . "\n" . $sql . ';';
    }

    /**
     * DROP COLUMN文を生成
     */
    private function generateDropColumn(array $diff): string
    {
        $table = $diff['table'];
        $columnName = $diff['columnName'];

        $comment = "-- Table: {$table} - カラム削除: {$columnName}";
        return $comment . "\nALTER TABLE `{$table}` DROP COLUMN `{$columnName}`;";
    }

    /**
     * ADD INDEX文を生成
     */
    private function generateAddIndex(array $diff): string
    {
        $table = $diff['table'];
        $indexName = $diff['indexName'];
        $index = $diff['index'];

        $columns = implode('`, `', $index['columns']);
        $keyType = $index['unique'] ? 'UNIQUE INDEX' : 'INDEX';

        $comment = "-- Table: {$table} - インデックス追加: {$indexName}";
        return $comment . "\nCREATE {$keyType} `{$indexName}` ON `{$table}` (`{$columns}`);";
    }

    /**
     * DROP INDEX文を生成
     */
    private function generateDropIndex(array $diff): string
    {
        $table = $diff['table'];
        $indexName = $diff['indexName'];

        $comment = "-- Table: {$table} - インデックス削除: {$indexName}";
        return $comment . "\nDROP INDEX `{$indexName}` ON `{$table}`;";
    }

    /**
     * ADD FOREIGN KEY文を生成
     */
    private function generateAddForeignKey(array $diff): string
    {
        $table = $diff['table'];
        $fkName = $diff['fkName'];
        $fk = $diff['foreignKey'];

        $columns = implode('`, `', $fk['columns']);
        $refTable = $fk['references']['table'];
        $refColumns = implode('`, `', $fk['references']['columns']);
        $onDelete = $fk['onDelete'];
        $onUpdate = $fk['onUpdate'];

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` ";
        $sql .= "FOREIGN KEY (`{$columns}`) REFERENCES `{$refTable}` (`{$refColumns}`) ";
        $sql .= "ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        $comment = "-- Table: {$table} - 外部キー追加: {$fkName}";
        return $comment . "\n" . $sql . ';';
    }

    /**
     * DROP FOREIGN KEY文を生成
     */
    private function generateDropForeignKey(array $diff): string
    {
        $table = $diff['table'];
        $fkName = $diff['fkName'];

        $comment = "-- Table: {$table} - 外部キー削除: {$fkName}";
        return $comment . "\nALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`;";
    }
}
