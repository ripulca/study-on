<?php

namespace App\Tests;


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
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $crawler = $client->request('GET', '/');

        // Входим как обычный пользователь
        $this->authorize($client, $this->fixture_user_email, $this->fixture_password);
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
        $this->authorize($client, $this->fixture_admin_email, $this->fixture_password);
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
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);
        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, '', $this->fixture_password);
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testWrongEmailAuth(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);
        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, 'eiwurtuef', $this->fixture_password);
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testNoPasswordAuth(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, $this->fixture_user_email, '');
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testWrongPasswordAuth(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $crawler = $client->request('GET', '/');
        $crawler=$this->authorize($client, $this->fixture_user_email, '12345');
        $this->assertEquals('Неправильные логин или пароль', $crawler->filter('.alert-danger')->text());
        
    }
    public function testSuccessRegisterAndAuth(): void
    {
        $client = static::getClient();

        $this->mockBillingClient($client);

        $email = 'test@example.com';
        $password = 'test_password';

        $client->request('GET', '/');
        $crawler = $client->clickLink('Регистрация');
        $client->followRedirects();
        $form = $crawler->selectButton(AbstractTest::TEST_REGISTER)->form([
            'register_form[email]' => $email,
            'register_form[password][first]' => $password,
            'register_form[password][second]' => $password,
        ]);
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

        // Входим еще раз
        $this->authorize($client, $email, $password);
        $client->clickLink('Выход');
        $this->assertResponseOk();
    }
    public function testPasswordsNotEqualRegister(): void
    {
        $client = static::getClient();

        $this->mockBillingClient($client);

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
        $client = static::getClient();

        $this->mockBillingClient($client);

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
        $client = static::getClient();

        $this->mockBillingClient($client);

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
}
