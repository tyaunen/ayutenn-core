<?php

namespace tests\unit\requests;

use ayutenn\core\FrameworkPaths;
use ayutenn\core\requests\Controller;
use ayutenn\core\session\FlashMessage;
use ayutenn\core\utils\Redirect;
use PHPUnit\Framework\TestCase;

/**
 * テスト用の具象Controllerクラス
 */
class TestController extends Controller
{
    protected array $RequestParameterFormat = [
        'email' => [
            'name' => 'メールアドレス',
            'format' => [
                'type' => 'string',
                'conditions' => ['email'],
            ],
            'require' => true,
        ],
        'password' => [
            'name' => 'パスワード',
            'format' => [
                'type' => 'string',
                'min_length' => 8,
            ],
            'require' => true,
        ],
    ];

    protected string $redirectUrlWhenError = '/login';

    public bool $mainCalled = false;
    public array $receivedParameter = [];

    protected function main(): void
    {
        $this->mainCalled = true;
        $this->receivedParameter = $this->parameter;
    }
}

/**
 * remainRequestParameter が true のControllerクラス
 */
class RemainController extends Controller
{
    protected array $RequestParameterFormat = [];
    protected bool $remainRequestParameter = true;

    public bool $mainCalled = false;

    protected function main(): void
    {
        $this->mainCalled = true;
    }
}

/**
 * keepGetParameter が true のControllerクラス
 */
class KeepGetController extends Controller
{
    protected array $RequestParameterFormat = [
        'name' => [
            'name' => '名前',
            'format' => ['type' => 'string'],
            'require' => true,
        ],
    ];
    protected bool $keepGetParameter = true;
    protected string $redirectUrlWhenError = '/search';

    protected function main(): void {}
}

class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Redirect::$isTest = true;
        Redirect::$lastRedirectUrl = '';
        FrameworkPaths::reset();
        FrameworkPaths::init([
            'controllerDir' => __DIR__ . '/controllers',
            'viewDir' => __DIR__ . '/views',
            'apiDir' => __DIR__ . '/api',
            'pathRoot' => '/app',
            'validationRulesDir' => __DIR__ . '/rules',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = '';
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Redirect::$isTest = false;
        Redirect::$lastRedirectUrl = '';
        FrameworkPaths::reset();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    public function test_バリデーション成功時にmainが実行される(): void
    {
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $controller = new TestController();
        $controller->run();

        $this->assertTrue($controller->mainCalled);
        $this->assertEquals('test@example.com', $controller->receivedParameter['email']);
    }

    public function test_バリデーションエラー時はエラーページにリダイレクト(): void
    {
        $_POST = [];

        $controller = new TestController();
        $controller->run();

        $this->assertFalse($controller->mainCalled);
        $this->assertStringContainsString('/login', Redirect::$lastRedirectUrl);
    }

    public function test_バリデーションエラー時にFlashMessageが設定される(): void
    {
        $_POST = [];

        $controller = new TestController();
        $controller->run();

        $messages = FlashMessage::getMessages();
        $this->assertNotEmpty($messages);
    }

    public function test_GETリクエストの場合はGETパラメータを使用(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'email' => 'get@example.com',
            'password' => 'password123',
        ];

        $controller = new TestController();
        $controller->run();

        $this->assertTrue($controller->mainCalled);
        $this->assertEquals('get@example.com', $controller->receivedParameter['email']);
    }

    public function test_remainRequestParameterがtrueの場合セッションに保存される(): void
    {
        $_POST = ['field' => 'value'];

        $controller = new RemainController();
        $controller->run();

        $sessionKey = 'remain_' . RemainController::class;
        $this->assertEquals(['field' => 'value'], $_SESSION[$sessionKey]);
    }

    public function test_unsetRemainでセッションからデータを削除できる(): void
    {
        $_POST = ['field' => 'value'];

        $controller = new RemainController();
        $controller->run();

        $result = RemainController::unsetRemain();
        $this->assertTrue($result);

        $sessionKey = 'remain_' . RemainController::class;
        $this->assertArrayNotHasKey($sessionKey, $_SESSION);
    }

    public function test_getRemainRequestParameterでセッションデータを取得できる(): void
    {
        $_POST = ['field' => 'value'];

        $controller = new RemainController();
        $controller->run();

        $data = RemainController::getRemainRequestParameter();
        $this->assertEquals(['field' => 'value'], $data);
    }

    public function test_getRemainRequestParameterはセッションが空の場合空配列を返す(): void
    {
        $data = RemainController::getRemainRequestParameter();
        $this->assertEquals([], $data);
    }

    public function test_unsetRemainはセッションがない場合falseを返す(): void
    {
        $result = RemainController::unsetRemain();
        $this->assertFalse($result);
    }

    public function test_keepGetParameterがtrueの場合リダイレクト時にGETパラメータを保持(): void
    {
        $_GET = ['q' => 'search'];
        $_POST = [];

        $controller = new KeepGetController();
        $controller->run();

        $this->assertStringContainsString('q=search', Redirect::$lastRedirectUrl);
    }

    public function test_HTTPSの場合httpsスキームが使用される(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // エラーを発生させてリダイレクトを確認
        $_POST = [];
        $controller = new TestController();
        $controller->run();

        $this->assertStringStartsWith('https://', Redirect::$lastRedirectUrl);
    }

    public function test_メール形式が不正な場合バリデーションエラー(): void
    {
        $_POST = [
            'email' => 'invalid-email',
            'password' => 'password123',
        ];

        $controller = new TestController();
        $controller->run();

        $this->assertFalse($controller->mainCalled);
    }

    public function test_パスワードが短い場合バリデーションエラー(): void
    {
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'short',
        ];

        $controller = new TestController();
        $controller->run();

        $this->assertFalse($controller->mainCalled);
    }
}
