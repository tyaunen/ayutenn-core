<?php
declare(strict_types=1);
namespace ayutenn\core\database;

/**
 * 【概要】
 * DBに関わる処理結果を扱うデータストアクラス
 *
 * 【解説】
 * 以下を扱う。
 * ・処理の完了状態
 * ・処理が成功していた場合、それに関わるデータ（検索結果や、登録したオートインクリメントのキーなど）
 * ・処理が失敗していた場合、エラーコードとエラーメッセージ
 *
 *【無駄口】
 * DBアクセスのような、処理の結果に確証がないとき活躍するデータストア
 * 処理の終了状態と、それに伴う戻り値は別に取得したい みたいな時に使う
 *
 * 例えば検索機能では「nullが帰ってきたんだけど、これは検索結果がなかったってこと？それともエラー？」みたいなことがよく起こる
 * そういう時このクラスのインスタンスを返すようにすれば、「正常終了はしてるけど、データは0件だったよ」と直感的に書ける
 *
 */
class QueryResult
{
    // 結果タイプの定数
    public const CODE_SUCCESS = 0;
    public const CODE_ALERT = 100;
    public const CODE_ERROR = 900;

    // タイプ名のマッピング
    private const CODE_NAMES = [
        self::CODE_SUCCESS => '正常終了',
        self::CODE_ALERT => '警告',
        self::CODE_ERROR => 'エラー',
    ];

    // プロパティ
    private int $code;
    private string $message;
    private mixed $data;

    /**
     * コンストラクタ
     *
     * @param int $code 終了状態コード
     * @param string $message 処理結果メッセージ
     * @param mixed $data 処理で取得したデータ
     */
    public function __construct(
        int $code,
        string $message,
        $data = null
    ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * 終了状態タイプの日本語名を返す
     *
     * @return string
     */
    public function getCodeName(): string
    {
        return self::CODE_NAMES[$this->code] ?? '不明';
    }


    /**
     * 処理が成功しているかどうかを返す
     *
     * @return bool 成功している場合はtrue、失敗している場合はfalse
     */
    public function isSucceed(): bool
    {
        return $this->code === self::CODE_SUCCESS;
    }

    /**
     * データを取得する
     *
     * @return mixed 処理で取得したデータ
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * エラーメッセージを取得する
     *
     * @return string|null 処理が失敗した場合はエラーメッセージ、成功した場合はnull
     */
    public function getErrorMessage(): ?string
    {
        if (!$this->isSucceed()) {
            return sprintf('【%s】 %s', $this->getCodeName(), $this->message);
        }

        return null;
    }

    /**
     * 成功結果を生成するファクトリーメソッド
     *
     * @param mixed $data 処理結果データ
     * @param string $message 成功メッセージ
     * @return self
     */
    public static function success(string $message='処理が成功しました。', $data = null): self
    {
        return new self(self::CODE_SUCCESS, $message, $data);
    }

    /**
     * エラー結果を生成するファクトリーメソッド
     *
     * @param string $message エラーメッセージ
     * @param mixed $data 追加データ
     * @return self
     */
    public static function error(string $message='不明なエラーが発生しました。', $data = null): self
    {
        return new self(self::CODE_ERROR, $message, $data);
    }

    /**
     * 警告結果を生成するファクトリーメソッド
     *
     * @param string $message 警告メッセージ
     * @param mixed $data 追加データ
     * @return self
     */
    public static function alert(string $message='不明なアラートが発生しました。', $data = null): self
    {
        return new self(self::CODE_ALERT, $message, $data);
    }
}
