<?php
declare(strict_types=1);

namespace ayutenn\core\requests;

use ayutenn\core\FrameworkPaths;
use ayutenn\core\session\FlashMessage;
use ayutenn\core\utils\Redirect;
use ayutenn\core\validation\Validator;

/**
 * 【概要】
 * コントローラの抽象クラス
 *
 * 【解説】
 * viewのformから送られてきた入力を保存したり、
 * formの入力をバリデートした上で必要な形にキャストしたり、
 * リダイレクトの共通処理を提供したりする。
 *
 * 【無駄口】
 * とくになし
 *
 */
abstract class Controller
{
    // リダイレクト先に、同じGETパラメタを付与するかどうか
    // 検索処理などURLに繰り返し使いたいパラメタがある時に便利
    protected bool $keepGetParameter = false;

    // リクエストパラメタを保存するかどうか
    // trueの場合、インスタンス化した時点でのリクエストパラメタをセッションに退避する
    protected bool $remainRequestParameter = false;

    // リクエストパラメタに期待するフォーマット
    // 書式はdocs/validation.mdを参照
    protected array $RequestParameterFormat = [];

    // リクエストパラメタのバリデーションエラーがあった場合など、
    // エラーが起こったときにリダイレクトするパス
    protected string $redirectUrlWhenError = '/error';

    // 型変換されたリクエストパラメータ
    protected array $parameter = [];

    /**
     * form remain用のセッションキー取得
     *
     * @return string
     */
    private static function getSessionKey(): string
    {
        $controller_name = static::class;
        return "remain_{$controller_name}";
    }

    /**
     * form remainデータ削除
     * 登録成功時など
     *
     * @return boolean
     */
    public static function unsetRemain(): bool
    {
        $session_key = self::getSessionKey();
        if (isset($_SESSION[$session_key])) {
            unset($_SESSION[$session_key]);
            return true;
        }
        return false;
    }

    /**
     * form remainデータ取得
     * viewが、フォームへの入力保存を取得するために使う
     *
     * @return array
     */
    public static function getRemainRequestParameter(): array
    {
        $session_key = self::getSessionKey();
        if (isset($_SESSION[$session_key])) {
            return $_SESSION[$session_key];
        }
        return [];
    }

    /**
     * 指定されたパスにリダイレクトする
     *
     * @param string $path リダイレクト先のパス
     * @param array $parameter クエリパラメータの配列 [key => value]
     */
    protected function redirect(string $path, array $parameter = []): void
    {
        $url_root = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . FrameworkPaths::getPathRoot();
        $url = "{$url_root}{$path}";

        // リダイレクトヘッダを送信
        Redirect::redirect($url, $parameter);
    }

    /**
     * コントローラのメイン処理
     */
    abstract protected function main(): void;

    /**
     * コントローラの実行
     * リクエストパラメタのバリデート、保存を行い、
     * バリデートエラーがあった場合はリダイレクトする。
     */
    public function run(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $request_parameters = $_GET;
        } else {
            $request_parameters = $_POST;
        }

        // エラー時などに備えた、リクエストパラメタ保存
        if ($this->remainRequestParameter) {
            $session_key = self::getSessionKey();
            $_SESSION[$session_key] = $request_parameters;
        }

        // リクエストパラメタのバリデート
        try {
            $rulesDir = FrameworkPaths::getValidationRulesDir();
            $validator = new Validator($this->RequestParameterFormat, $rulesDir);
            $result = $validator->validate($request_parameters);
        } catch (\Exception $e) {
            FlashMessage::error('バリデートに関するサーバーエラーが発生しました。');
            $this->redirect($this->redirectUrlWhenError, $this->keepGetParameter ? $_GET : []);
            return;
        }

        // バリデートエラーがあった場合、セッションにエラーメッセージを保存してリダイレクト
        if ($result->hasErrors()) {
            foreach ($result->getErrors() as $error_text) {
                FlashMessage::alert($error_text);
            }
            $this->redirect($this->redirectUrlWhenError, $this->keepGetParameter ? $_GET : []);
            return;
        }

        // バリデート成功時は、型変換されたパラメタを保存
        $this->parameter = $result->getCastedValues();

        // メイン処理実行
        $this->main();
    }
}
