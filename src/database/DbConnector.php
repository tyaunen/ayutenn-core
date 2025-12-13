<?php
namespace ayutenn\core\database;

use PDO;
use ayutenn\core\config\Config;

/**
 * 【概要】
 * データベース接続
 *
 * 【解説】
 * PDO接続を行い、接続を保持する
 * 同じリクエスト内で複数回接続する場合は、最初の接続を使いまわす
 *
 * 【無駄口】
 * シングルトンパターンだッ
 */
class DbConnector
{
    private static ?PDO $connection = null;

    /**
     * PDO接続
     *
     * @return PDO
     */
    static public function connectWithPdo(): PDO
    {
        if (self::$connection === null) {
            self::$connection = new PDO(
                Config::get("PDO_DSN"),
                Config::get("PDO_USERNAME"),
                Config::get("PDO_PASSWORD")
            );
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return self::$connection;
    }

    /**
     * 接続済み かつ トランザクションがある時のみロールバック
     *
     * @return bool ロールバックしたならtrue、接続かトランザクションがない場合はfalse
     */
    static public function rollbackIfInTransaction(): bool
    {
        if (self::$connection !== null) {
            if (self::$connection->inTransaction()) {
                self::$connection->rollback();
                return true;
            }
        }
        return false;
    }

    /**
     * 接続をリセットする（テスト用）
     */
    public static function reset(): void
    {
        self::$connection = null;
    }
}
