<?php
declare(strict_types=1);

namespace ayutenn\core\utils;

/**
 * 【概要】
 * リダイレクトクラス
 *
 * 【解説】
 * リダイレクトを行うためのクラスです。
 *
 * 【無駄口】
 * テストコードを書く時直接リダイレクトすると邪魔なので、ここに外出しする。
 *
 */
class Redirect
{
    /**
     * テストモードフラグ
     * true の場合、実際のリダイレクトを行う代わりに、リダイレクト先のパスを返す
     */
    public static $isTest = false;
    public static $lastRedirectUrl = '';
    public static $lastApiResponse = [];

    /**
     * 指定されたURLにリダイレクトする
     *
     * @param string $path リダイレクト先のパス
     * @param array $get_parameter GETパラメータ（連想配列）
     */
    public static function redirect(string $path, array $get_parameter=[]): void
    {
        // クエリパラメータがある場合、URLに追加
        $url = $path;
        if (!empty($get_parameter)) {
            $queryString = http_build_query($get_parameter);
            $url .= (strpos($path, '?') === false) ? "?{$queryString}" : "&{$queryString}";
        }

        if(self::$isTest) {
            self::$lastRedirectUrl = $url;
        } else {
            header("Location: {$url}");
            exit;
        }
    }

    /**
     * JSONレスポンスを返し、処理を終了する
     *
     * @param array $response レスポンスデータ
     */
    public static function apiResponse(array $response=['status'=>0, 'payload'=>'']): void
    {
        if(self::$isTest) {
            self::$lastApiResponse = $response;
        } else {
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 指定されたPHPファイルを読み込み、処理を終了する
     *
     * @param string $file_path 読み込むPHPファイルのパス
     */
    public static function show(string $file_path): void
    {
        if(self::$isTest) {
            self::$lastRedirectUrl = $file_path;
        } else {
            require_once $file_path;
            exit;
        }
    }
}
