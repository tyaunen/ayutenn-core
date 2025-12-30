<?php
declare(strict_types=1);

namespace ayutenn\core\utils;

/**
 * 【概要】
 * CSRFトークン管理クラス
 *
 * 【解説】
 * CSRFトークンの作成、破棄を行うクラスです。
 *
 * 【無駄口】
 * 副タブで操作を受け付けたい場合が結構あるだろうから、
 * 結構長い時間同じトークンを保持することにしている
 * もしかしたらもっと短期間で破棄したり毎回作り直したりしたほうが良いのかも
 * 全然もしかしなくてもそっちの方がいいな
 *
 */
class CsrfTokenManager
{
    // トークンをセッションに保存する際のキー
    private const SESSION_TOKEN_KEY = 'csrf_token';

    // タイムスタンプをセッションに保存する際のキー
    private const SESSION_TIMESTAMP_KEY = 'csrf_token_timestamp';

    // トークンの有効期限（秒） 12(時間) * 60(分) * 60(秒) = 43200
    private const EXPIRATION_SECONDS = 43200;

    /**
     * CSRFトークンを取得する
     * 期限が切れている場合は新しいトークンを生成する
     *
     * @return string 現在の有効なCSRFトークン
     */
    public function getToken(): string
    {
        // トークンが存在しない、または期限切れの場合は再生成
        if (!isset($_SESSION[self::SESSION_TOKEN_KEY]) || $this->isExpired()) {
            $this->generateNewToken();
        }

        // 最終アクセス時刻を更新
        // isExpired() の判定後に更新することで、「最終アクセスから1時間」という条件を満たす
        $_SESSION[self::SESSION_TIMESTAMP_KEY] = time();

        return $_SESSION[self::SESSION_TOKEN_KEY];
    }

    /**
     * 渡されたトークンが有効かどうかを検証する
     * 成功した場合でも、トークンを破棄しない
     * 気が変わったらここで破棄する
     *
     * @param string $token フォームなどから渡されたトークン
     * @return bool 有効な場合は true、そうでない場合は false
     */
    public function validateToken(string $token): bool
    {
        // セッションにトークンが存在しない、または期限切れの場合は無効
        if (!isset($_SESSION[self::SESSION_TOKEN_KEY]) || $this->isExpired()) {
            return false;
        }

        // トークンが一致しない場合は無効
        if ($token !== $_SESSION[self::SESSION_TOKEN_KEY]) {
            return false;
        }

        // トークン破棄
        // unset($_SESSION[self::SESSION_TOKEN_KEY]);
        // unset($_SESSION[self::SESSION_TIMESTAMP_KEY]);

        return true;
    }

    /**
     * トークンが有効期限切れかどうかの判定
     *
     * @return bool 期限切れの場合は true、そうでない場合は false
     */
    private function isExpired(): bool
    {
        // タイムスタンプが存在しない場合は初回アクセス、期限切れではない
        if (!isset($_SESSION[self::SESSION_TIMESTAMP_KEY])) {
            return true;
        }

        $lastAccessTime = $_SESSION[self::SESSION_TIMESTAMP_KEY];
        $currentTime = time();

        // 現在時刻 - 最終アクセス時刻 が 有効期限より大きいか
        return ($currentTime - $lastAccessTime) > self::EXPIRATION_SECONDS;
    }

    /**
     * 新しいCSRFトークンを生成し、セッションに保存します。
     */
    private function generateNewToken(): void
    {
        // 強力な乱数生成器を使ってトークンを生成
        $_SESSION[self::SESSION_TOKEN_KEY] = bin2hex(random_bytes(32));
        // タイムスタンプもリセット
        $_SESSION[self::SESSION_TIMESTAMP_KEY] = time();
    }
}