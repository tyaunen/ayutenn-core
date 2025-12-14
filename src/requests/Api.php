<?php
namespace ayutenn\core\requests;

use Exception;
use ayutenn\core\config\Config;
use ayutenn\core\utils\Redirect;
use ayutenn\core\validation\Validator;

/**
 * 【概要】
 * APIの抽象クラス
 *
 * 【解説】
 * APIの抽象クラスです。
 *
 * 【無駄口】
 * とくにいうことなし
 *
 */
abstract class Api
{
    // リクエストパラメタに期待するフォーマット
    // 書式はdocs/validation.mdを参照
    protected array $RequestParameterFormat = [];

    // 型変換されたリクエストパラメータ
    protected array $parameter = [];

    // メイン処理
    abstract public function main(): array;

    // API実行
    public function run(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $request_parameters = $_GET;
        } else {
            $request_parameters = $_POST;
        }

        // パラメータのバリデート
        try {
            $rulesDir = Config::get('VALIDATION_RULES_DIR');
            $validator = new Validator($this->RequestParameterFormat, $rulesDir);
            $result = $validator->validate($request_parameters);
        } catch (Exception $e) {
            $response = $this->createResponse(
                succeed: false,
                payload: [
                    'message' => 'バリデートに関するサーバーエラーが発生しました。',
                    'errors' => [$e->getMessage()],
                ]
            );
            Redirect::apiResponse($response);
            return;
        }

        // バリデートエラーがあった場合、エラーメッセージを返す
        if ($result->hasErrors()) {
            $response = $this->createResponse(
                succeed: false,
                payload: [
                    'message' => 'リクエストパラメータにエラーがあります。',
                    'errors' => $result->getErrors(),
                ]
            );
            Redirect::apiResponse($response);
            return;
        }

        $this->parameter = $result->getCastedValues();

        Redirect::apiResponse($this->main());
    }

    /**
     * JSONレスポンスを返す
     *
     * @param bool $succeed 成功したかどうか
     * @param array $payload レスポンスデータ
     * @return array レスポンスの連想配列
     */
    protected function createResponse(bool $succeed, array $payload = []): array
    {
        return [
            'status' => $succeed ? 0 : 9,
            'payload' => $payload
        ];
    }
}
