<?php
declare(strict_types=1);
namespace App\Tests\Mock;

use App\DTO\UserDTO;
use App\Security\User;
use App\Tests\AbstractTest;
use App\Service\BillingClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class BillingMock extends AbstractTest
{
    public const TEST_ADD = 'Добавить';
    public const TEST_UPDATE = 'Обновить';
    public const TEST_SAVE = 'Сохранить';
    public const TEST_EDIT = 'Редактировать';
    public const TEST_REGISTER = 'Зарегистрироваться';
    public const TEST_AUTH = 'Войти';
    public const TEST_ENTER = 'Вход';
    private static array $user = [
        'username' => 'user@studyon.com',
        'password' => 'password',
        'roles' => ['ROLE_USER'],
        'balance' => 100.0,
    ];

    private static array $admin = [
        'username' => 'user_admin@studyon.com',
        'password' => 'password',
        'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
        'balance' => 500.0,
    ];

    public function mockBillingClient(KernelBrowser $client)
    {
        $client->disableReboot();

        self::$user['token']=$this->generateToken(self::$user['roles'], self::$user['username']);
        self::$admin['token']=$this->generateToken(self::$admin['roles'], self::$admin['username']);
        $new_token=$this->generateToken(self::$user['roles'], 'test@example.com');
        self::$user['refresh_token']='user_refresh_token';
        self::$admin['refresh_token']='admin_refresh_token';

        $billingClientMock = $this->getMockBuilder(BillingClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request', 'jsonRequest', 'auth', 'register', 'refreshToken', 'getCurrentUser'])
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
                        return ['token'=>self::$user['token'], 'refresh_token'=>self::$user['refresh_token']];
                    }
                }
                if (self::$admin['username'] == $email) {
                    if (self::$admin['password'] == $password) {
                        return ['token'=>self::$admin['token'], 'refresh_token'=>self::$admin['refresh_token']];
                    }
                }
                throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
            });

        $billingClientMock->method('register')
            ->willReturnCallback(static function (array $credentials) use ($new_token) {
                $email = $credentials['username'];
                if (self::$user['username'] == $email || self::$admin['username'] == $email) {
                    throw new CustomUserMessageAuthenticationException('Email уже существует');
                }
                $tokens = ['token' => $new_token, 'refresh_token' => 'new_refresh_token', 'roles'=>['ROLE_USER']];
                return $tokens;
            });

        $billingClientMock->method('refreshToken')
            ->willReturnCallback(static function (string $refreshToken) {
                [$exp, $email, $roles] = User::jwtDecode($refreshToken);
                if (self::$user['username'] == $email) {
                    return ['token'=>self::$user['token'], 'refresh_token'=>self::$user['refresh_token']];
                }
                if (self::$admin['username'] == $email) {
                    return ['token'=>self::$admin['token'], 'refresh_token'=>self::$admin['refresh_token']];
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
                throw new CustomUserMessageAccountStatusException('Некорректный JWT токен');
            });

        static::getContainer()->set(BillingClient::class, $billingClientMock);
        return null;
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

    protected function authorize(AbstractBrowser $client, string $login, string $password): ?Crawler
    {
        $crawler = $client->clickLink('Вход');

        $form = $crawler->filter('form')->first()->form();
        $form['email'] = $login;
        $form['password'] = $password;

        $crawler = $client->submit($form);
        return $crawler;
    }

    public function beforeTesting($client)
    {
        $this->mockBillingClient($client);
        $crawler = $client->request('GET', '/');
        $this->authorize($client, self::$admin['username'], self::$admin['password']);
        $this->assertResponseRedirect();
        dd($crawler);
        return $crawler;
    }
}