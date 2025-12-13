<?php
namespace ayutenn\core\database;

use PDO, PDOStatement;

/**
 * 【概要】
 * マネージャー抽象クラス
 *
 * 【解説】
 * 接続を保持するなど、マネージャーの共通処理を実装する
 *
 * 【無駄口】
 * 今んとこぜんぜん共通処理ないので地味なやつ
 * クエリビルダみたいなこと始めたらここがえらいことになるかも
 *
 */
abstract class DataManager
{
    protected PDO $pdo;

    /**
     * コンストラクタ
     *
     * @param PDO $pdo
     */
    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * SQLを実行し、結果ステートメントを返す
     *
     * @param string $sql SQL文
     * @param array $params [プレースホルダ => [値, データ型定数]]
     * @return PDOStatement 実行結果のPDOStatementオブジェクト
     */
    protected function executeStatement(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $placeholder => $data) {
            $value = $data[0];
            $type = $data[1];
            $stmt->bindValue($placeholder, $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * SQLを実行し、結果を連想配列の配列で返す
     *
     * @param string $sql SQL文
     * @param array $params [プレースホルダ => [値, データ型定数]]
     * @return array 実行結果の連想配列の配列 [[カラム名 => 値, ...], ...]
     */
    protected function executeAndFetchAll(string $sql, array $params): array
    {
        $stmt = $this->executeStatement($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results ?: [];
    }

    /**
     * SQLを実行し、結果を連想配列で1行だけ返す
     *
     * @param string $sql SQL文
     * @param array $params [プレースホルダ => [値, データ型定数]]
     * @return array|null 結果が1行ある場合は連想配列、ない場合はnull
     */
    protected function executeAndFetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->executeStatement($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
