<?php
declare(strict_types=1);
namespace App\Tests\Mock;

use App\DTO\UserDTO;
use App\DTO\CourseDTO;
use App\Security\User;
use DateTimeInterface;
use App\Tests\AbstractTest;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class BillingMock extends WebTestCase
{
    public const TEST_ADD = 'Добавить';
    public const TEST_UPDATE = 'Обновить';
    public const TEST_SAVE = 'Сохранить';
    public const TEST_EDIT = 'Редактировать';
    public const TEST_REGISTER = 'Зарегистрироваться';
    public const TEST_AUTH = 'Войти';
    public const TEST_ENTER = 'Вход';
    public const TEST_EXIT = 'Выход';
    public static array $user = [
        'username' => 'user@studyon.com',
        'password' => 'password',
        'roles' => ['ROLE_USER'],
        'balance' => 100.0,
    ];

    public static array $admin = [
        'username' => 'admin@studyon.com',
        'password' => 'password',
        'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
        'balance' => 500.0,
    ];

    private static array $new_user = [
        'username' => 'test@example.com',
        'password' => 'test_password',
        'roles' => ['ROLE_USER'],
        'balance' => 0.0,
    ];

    private static array $courses = [
        [
            'code' => 'php_1',
            'type' => 'free'
        ], [
            'code' => 'js_1',
            'type' => 'rent',
            'price' => 10
        ], [
            'code' => 'figma_1',
            'type' => 'buy',
            'price' => 20
        ], [
            'code' => 'test_buy',
            'type' => 'buy',
            'price' => 20
        ], [
            'code' => 'test_rent',
            'type' => 'rent',
            'price' => 20
        ]
    ];

    private static array $transactions = [
        [
            "id" => 2,
            "type" => "payment",
            "code" => "js_1",
            "amount" => 10
        ], [
            "id" => 3,
            "type" => "payment",
            "code" => "figma_1",
            "amount" => 20
        ]
    ];

    public function mockBillingClient(KernelBrowser $client)
    {
        $client->disableReboot();
        $created=(new \DateTime())->format(DateTimeInterface::ATOM);
        $expires=(new \DateTime())->sub(new \DateInterval('P7D'))->format(DateTimeInterface::ATOM);

        self::$user['token'] = $this->generateToken(self::$user['roles'], self::$user['username']);
        self::$admin['token'] = $this->generateToken(self::$admin['roles'], self::$admin['username']);
        self::$new_user['token'] = $this->generateToken(self::$user['roles'], 'test@example.com');
        self::$new_user['refresh_token'] = $this->generateRefreshToken(self::$user['roles'], 'test@example.com');
        self::$user['refresh_token'] = $this->generateRefreshToken(self::$user['roles'], self::$user['username']);
        self::$admin['refresh_token'] = $this->generateRefreshToken(self::$admin['roles'], self::$admin['username']);
        foreach(self::$transactions as &$transaction){
            $transaction['created']['date'] = $created;
            $transaction['expires']['date'] = $expires;
        }
        // $code=self::$courses[0]['code'];

        $billingClientMock = $this->getMockBuilder(BillingClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request', 'jsonRequest', 'auth', 'register', 'refreshToken', 'getCurrentUser', 'getCourse', 'getCourses', 'newCourse', 'editCourse', 'payForCourse', 'getTransactions'])
            ->getMock();

        // Гарантия, что заглушка не обратится к биллингу
        $billingClientMock->method('request')
            ->willThrowException(new \Exception('Bad mock'));
        $billingClientMock->method('jsonRequest')
            ->willThrowException(new \Exception('Bad mock'));

        $billingClientMock->method('auth')
            ->willReturnCallback(static function (array $credentials) {
                $email = $credentials['username'];
                $password = $credentials['password'];
                if (self::$user['username'] == $email) {
                    if (self::$user['password'] == $password) {
                        return ['token' => self::$user['token'], 'refresh_token' => self::$user['refresh_token']];
                    }
                }
                if (self::$admin['username'] == $email) {
                    if (self::$admin['password'] == $password) {
                        return ['token' => self::$admin['token'], 'refresh_token' => self::$admin['refresh_token']];
                    }
                }
                throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
            });

        $billingClientMock->method('register')
            ->willReturnCallback(static function (array $credentials) {
                $email = $credentials['username'];
                if (self::$user['username'] == $email || self::$admin['username'] == $email) {
                    throw new CustomUserMessageAuthenticationException('Email уже существует');
                }
                $tokens = ['token' => self::$new_user['token'], 'refresh_token' => self::$new_user['refresh_token'], 'roles' => ['ROLE_USER']];
                return $tokens;
            });

        $billingClientMock->method('refreshToken')
            ->willReturnCallback(static function (string $refreshToken) {
                [$exp, $email, $roles] = User::jwtDecode($refreshToken);
                if (self::$user['username'] == $email) {
                    return ['token' => self::$user['token'], 'refresh_token' => self::$user['refresh_token']];
                }
                if (self::$admin['username'] == $email) {
                    return ['token' => self::$admin['token'], 'refresh_token' => self::$admin['refresh_token']];
                }
            });

        $billingClientMock->method('getCurrentUser')
            ->willReturnCallback(static function (string $refreshToken) {
                [$exp, $email, $roles] = User::jwtDecode($refreshToken);
                if (self::$user['username'] == $email) {
                    return new UserDTO($email, $roles, self::$user['balance']);
                }
                if (self::$admin['username'] == $email) {
                    return new UserDTO($email, $roles, self::$admin['balance']);
                }
                if (self::$new_user['username'] == $email) {
                    return new UserDTO($email, $roles, self::$new_user['balance']);
                }
                throw new CustomUserMessageAccountStatusException('Некорректный JWT токен');
            });

        $billingClientMock->method('getCourse')
            ->willReturnCallback(static function (string $code) {
                foreach(self::$courses as $course){
                    if ($course['code'] == $code) {
                        $result=['code'=>$course['code'], 'type'=>$course['type'],];
                        if($course['type']!='free'){
                            $result['price']=$course['price'];
                        }
                        return $result;
                    }
                }
                throw new \RuntimeException('Нет курса с таким кодом', Response::HTTP_NOT_FOUND);
            });

        $billingClientMock->method('getCourses')
            ->willReturnCallback(static function () {
                return self::$courses;
            });

        $billingClientMock->method('newCourse')
            ->willReturnCallback(static function () {
                return ['success'=>true];
            });

        $billingClientMock->method('editCourse')
            ->willReturnCallback(static function (string $code) {
                foreach(self::$courses as $course){
                    if ($course['code'] == $code) {
                        return ['success'=>true];
                    }
                }
                throw new \RuntimeException('Нет курса с таким кодом', Response::HTTP_NOT_FOUND);
            });

        $billingClientMock->method('payForCourse')
            ->willReturnCallback(static function (string $refreshToken, string $code) {
                [$exp, $email, $roles] = User::jwtDecode($refreshToken);
                $pay_course=[];
                foreach(self::$courses as $course){
                    if ($course['code'] == $code) {
                        $pay_course= ['code'=>$course['code'], 'type'=>$course['type'], 'price'=>$course['price']];
                    }
                }
                if (self::$user['username'] == $email) {
                    self::$user['balance']-=$pay_course['balance'];
                }
                if (self::$admin['username'] == $email) {
                    self::$admin['balance']-=$pay_course['balance'];
                }
                if (self::$new_user['username'] == $email) {
                    throw new CustomUserMessageAccountStatusException('Недостаточно денег');
                }
                return ['success'=>true];
            });

        $billingClientMock->method('getTransactions')
            ->willReturnCallback(static function (string $refreshToken) {
                [$exp, $email, $roles] = User::jwtDecode($refreshToken);
                return self::$transactions;
            });
        AbstractTest::getContainer()->set(BillingClient::class, $billingClientMock);
        return $client;
    }

    private function generateToken($roles, $username): string
    {
        $data = [
            'email' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $query = base64_encode(json_encode($data));

        return 'header.' . $query . '.signature';
    }

    private function generateRefreshToken($roles, $username): string
    {
        $data = [
            'email' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 month'))->getTimestamp(),
        ];
        $query = base64_encode(json_encode($data));

        return 'header.' . $query . '.signature';
    }
}