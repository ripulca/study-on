<?php

namespace App\Tests;

use App\Tests\AbstractTest;
use App\Tests\Mock\BillingMock;
use App\DataFixtures\AppFixtures;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\AbstractBrowser;

class UserTest extends AbstractTest
{
    private string $fixture_user_email="user@studyon.com";
    private float $fixture_user_balance=100.0;
    private const user_role="ROLE_USER";
    private string $fixture_admin_email="admin@studyon.com";
    private float $fixture_admin_balance=500.0;
    private const admin_role="ROLE_SUPER_ADMIN";
    private string $fixture_password="password";

    public function testSuccessAuthAndProfile(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();

        $client=$mock->mockBillingClient($client);

        $crawler = $client->request('GET', '/');

        // Входим как обычный пользователь
        $crawler = $this->authorize($client, $this->fixture_user_email, $this->fixture_password);
        $crawler = $client->clickLink('Профиль');
        $this->assertResponseOk();

        $email=$crawler->filter('.username')->text();
        $balance=$crawler->filter('.balance')->text();
        $roles = $crawler->filter('.role')->each(function ($node, $i) {
            return $node->text();
        });

        $this->assertEquals($email, $this->fixture_user_email);
        $this->assertEquals($roles[0], self::user_role);
        $this->assertEquals($balance, $this->fixture_user_balance);

        $client->clickLink('Выход');
        $this->assertResponseOk();

        // Входим как админ
        $crawler = $this->authorize($client, $this->fixture_admin_email, $this->fixture_password);
        $crawler = $client->clickLink('Профиль');
        $this->assertResponseOk();

        $email=$crawler->filter('.username')->text();
        $balance=$crawler->filter('.balance')->text();
        $roles = $crawler->filter('.role')->each(function ($node, $i) {
            return $node->text();
        });

        $this->assertEquals($email, $this->fixture_admin_email);
        $this->assertEquals($roles[0], self::user_role);
        $this->assertEquals($roles[1], self::admin_role);
        $this->assertEquals($balance, $this->fixture_admin_balance);

        $client->clickLink('Выход');
        $this->assertResponseOk();
    }
    public function testNoEmailAuth(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, '', $this->fixture_password);
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testWrongEmailAuth(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, 'eiwurtuef', $this->fixture_password);
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testNoPasswordAuth(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, $this->fixture_user_email, '');
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testWrongPasswordAuth(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, $this->fixture_user_email, '12345');
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testSuccessRegister(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $email = 'test@example.com';
        $password = 'test_password';

        $client->request('GET', '/');
        $crawler = $client->clickLink('Регистрация');
        $client->followRedirects();
        $form = $crawler->filter('form')->first()->form();
        $form['register_form[email]'] = $email;
        $form['register_form[password][first]'] = $password;
        $form['register_form[password][second]'] = $password;
        $crawler = $client->submit($form);
        
        $crawler = $client->clickLink('Профиль');
        $this->assertResponseOk();
        $check_email=$crawler->filter('.username')->text();
        $check_balance=$crawler->filter('.balance')->text();
        $check_roles = $crawler->filter('.role')->each(function ($node, $i) {
            return $node->text();
        });

        $this->assertEquals($check_email, $email);
        $this->assertEquals($check_roles[0], 'ROLE_USER');
        $this->assertEquals($check_balance, 0);

        $client->clickLink('Выход');
        $this->assertResponseOk();
    }
    public function testPasswordsNotEqualRegister(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $client->request('GET', '/');
        $crawler = $client->clickLink('Регистрация');
        $form = $crawler->filter('form')->first()->form();

        $email = 'test@example.com';
        $password = 'test_password';

        // Пароли не совпали
        $form['register_form[email]'] = $email;
        $form['register_form[password][first]'] = $password;
        $form['register_form[password][second]'] = $password . '1';
        $crawler = $client->submit($form);
        $this->assertEquals('Пароли должны совпадать', $crawler->filter('.invalid-feedback')->text());
    }
    public function testNoPasswordRegister(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $client->request('GET', '/');
        $crawler = $client->clickLink('Регистрация');
        $form = $crawler->filter('form')->first()->form();

        $email = 'test@example.com';

        // Нет пароля
        $form['register_form[email]'] = $email;
        $crawler = $client->submit($form);
        $this->assertEquals('Пожалуйста, придумайте пароль', $crawler->filter('.invalid-feedback')->text());
    }
    public function testEmailNotUniqueRegister(): void
    {
        $mock= new BillingMock();
        $client = static::getClient();
        $client->followRedirects();
        $client=$mock->mockBillingClient($client);

        $client->request('GET', '/');
        $crawler = $client->clickLink('Регистрация');
        $form = $crawler->filter('form')->first()->form();

        $password = 'test_password';

        // Такой логин уже существует
        $form['register_form[email]'] = $this->fixture_user_email;
        $form['register_form[password][first]'] = $password;
        $form['register_form[password][second]'] = $password;
        $crawler = $client->submit($form);
        $this->assertEquals('Email уже существует', $crawler->filter('.alert')->text());
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

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
}