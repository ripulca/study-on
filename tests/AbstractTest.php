<?php

declare(strict_types=1);

namespace App\Tests;

use Exception;
use App\DTO\UserDTO;
use joshtronic\LoremIpsum;
use App\Service\BillingClient;
use Doctrine\Common\DataFixtures\Loader;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

abstract class AbstractTest extends WebTestCase
{
    public const TEST_ADD = 'Добавить';
    public const TEST_UPDATE = 'Обновить';
    public const TEST_SAVE = 'Сохранить';
    public const TEST_EDIT = 'Редактировать';
    public const TEST_REGISTER = 'Зарегистрироваться';
    public const TEST_AUTH = 'Войти';
    public const TEST_USER_EMAIL = 'user@studyon.com';
    public const TEST_ADMIN_EMAIL = 'admin@studyon.com';
    public const TEST_PASSWORD = 'password';
    // private static $usersByUsername;
    private static $fixture_users_by_token;
    private static $fixture_users = [
        'user@studyon.com' => [
            'username' => 'user@studyon.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 100.0,
            'token' => 'user_token',
        ],
        'admin@studyon.com' => [
            'username' => 'admin@studyon.com',
            'password' => 'password',
            'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
            'balance' => 500.0,
            'token' => 'admin_token',
        ],
    ];

    /** @var LoremIpsum */
    private static $loremIpsum;

    /** @var Client */
    protected static $client;

    public function getLoremIpsum()
    {
        if (!static::$loremIpsum) {
            static::$loremIpsum = new LoremIpsum();
        }
        return static::$loremIpsum;
    }

    protected function setUp(): void
    {
        static::getClient();
        $this->loadFixtures($this->getFixtures());
    }

    final protected function tearDown(): void
    {
        parent::tearDown();
        static::$client = null;
    }

    public function assertResponseOk(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    /**
     * @return string
     */
    public function guessErrorMessageFromResponse(Response $response, string $type = 'text/html')
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if (!\count($crawler->filter('title'))) {
                $add = '';
                $content = $response->getContent();

                if ('application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' FORMATTED';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add . ' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (\Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    public function assertResponseRedirect(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(int $expectedCode, ?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }

    protected static function getClient($reinitialize = false, array $options = [], array $server = [])
    {
        if (!static::$client || $reinitialize) {
            static::$client = static::createClient($options, $server);
        }

        // core is loaded (for tests without calling of getClient(true))
        static::$client->getKernel()->boot();

        return static::$client;
    }

    /**
     * Load fixtures before test.
     */
    protected function loadFixtures(array $fixtures = [])
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!\is_object($fixture)) {
                $fixture = new $fixture();
            }

            if ($fixture instanceof ContainerAwareInterface) {
                $fixture->setContainer(static::getContainer());
            }

            $loader->addFixture($fixture);
        }

        $em = static::getEntityManager();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    /**
     * Shortcut.
     */
    protected static function getEntityManager()
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    /**
     * List of fixtures for certain test.
     */
    protected function getFixtures(): array
    {
        return [];
    }

    private function failOnResponseStatusCheck(
        Response $response = null,
        $func = null,
        ?string $message = null,
        string $type = 'text/html'
    ) {
        if (null === $func) {
            $func = 'isOk';
        }

        if (null === $response && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (\is_int($func)) {
                $this->assertEquals($func, $response->getStatusCode());
            } else {
                $this->assertTrue($response->{$func}());
            }

            return;
        } catch (\Exception $e) {
            // nothing to do
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . '. ';
        }

        if (is_int($func)) {
            $template = 'Failed asserting Response status code %s equals %s.';
        } else {
            $template = 'Failed asserting that Response[%s] %s.';
            $func = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
        }

        $message .= sprintf($template, $response->getStatusCode(), $func, $err);

        $max_length = 100;
        if (mb_strlen($err, 'utf-8') < $max_length) {
            $message .= ' ' . $this->makeErrorOneLine($err);
        } else {
            $message .= ' ' . $this->makeErrorOneLine(mb_substr($err, 0, $max_length, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        $this->fail($message);
    }

    private function makeErrorOneLine($text)
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }

    protected function mockBillingClient(KernelBrowser $client)
    {
        $client->disableReboot();
        $testUsername='test@example.com';
        $testToken='test@example.com.token';
        $fixture_users_by_token = [
            'user_token' => [
                'username' => 'user@studyon.com',
                'password' => 'password',
                'roles' => ['ROLE_USER'],
                'balance' => 100.0,
            ],
            'admin_token' => [
                'username' => 'admin@studyon.com',
                'password' => 'password',
                'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
                'balance' => 500.0,
            ],
            'new_user_token' => [
                'username' => '',
                'password' => '',
                'roles' => [],
                'balance' => 0.0,
            ],
        ];

        $billingClientMock = $this->getMockBuilder(BillingClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request', 'auth', 'register', 'getCurrentUser'])
            ->getMock();

        $billingClientMock->method('request')
            ->willThrowException(new Exception('Bad mock'));
        $billingClientMock->method('auth')
            ->willReturnCallback(static function (array $credentials) {
                $username = $credentials['username'];
                if (isset(self::$fixture_users[$username])) {
                    return $testtoken;
                }
                throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
            });

        $billingClientMock->method('register')
            ->willReturnCallback(static function (array $credentials) {
                $username = $credentials['username'];
                if (isset(self::$fixture_users[$username])) {
                    throw new CustomUserMessageAuthenticationException('Email уже существует');
                }
                self::$fixture_users[$username] = [
                    'username' => $username,
                    'password' => $credentials['password'],
                    'roles' => ['ROLE_USER'],
                    'balance' => 0.0,
                    'token' => 'new_user_token',
                ];
                self::$fixture_users_by_token['new_user_token']=self::$fixture_users[$username];
                return self::$fixture_users[$username]['token'];
            });

        $billingClientMock->method('getCurrentUser')
            ->willReturnMap([
                ['user_token', new UserDTO($fixture_users_by_token['user_token']['username'], $fixture_users_by_token['user_token']['roles'], $fixture_users_by_token['user_token']['balance'])],
                ['admin_token', new UserDTO($fixture_users_by_token['admin_token']['username'], $fixture_users_by_token['admin_token']['roles'], $fixture_users_by_token['admin_token']['balance'])],
                ['new_user_token', new UserDTO($testUsername, $fixture_users_by_token['new_user_token']['roles'], $fixture_users_by_token['new_user_token']['balance'])],
            ]);

        static::getContainer()->set(BillingClient::class, $billingClientMock);
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
        $this->authorize($client, self::TEST_ADMIN_EMAIL, self::TEST_PASSWORD);
        $this->assertResponseRedirect();
        return $crawler;
    }
}