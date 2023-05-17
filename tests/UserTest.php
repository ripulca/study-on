<?php

namespace App\Tests;

use App\Tests\Mock\BillingMock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserTest extends AbstractTest
{
    private ValidatorInterface $validator;

    private array $adminCredentials = [
        'username' => 'admin@studyon.com',
        'password' => 'password',
    ];
    private array $userCredentials = [
        'username' => 'user@studyon.com',
        'password' => 'password',
    ];
    
    public function authAdmin()
    {
        $client = $this->billingClient();
        $crawler = $client->request('GET', '/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Авторизация')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => $this->adminCredentials['username'],
            'password' => $this->adminCredentials['password'],
        ]);
        $client->submit($login);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        return $crawler;
    }
    public function authUser()
    {
        $client = $this->billingClient();
        $crawler = $client->request('GET', '/');
        $this->assertResponseOk();

        $link = $crawler->selectLink(AbstractTest::TEST_ENTER)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitBtn = $crawler->selectButton(AbstractTest::TEST_AUTH);
        $login = $submitBtn->form([
            'email' => $this->userCredentials['username'],
            'password' => $this->userCredentials['password'],
        ]);
        $client->submit($login);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        // self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        dd($crawler);
        return [$crawler, $client];
    }

    public function testAuthAdminAndLogout(): void
    {
        [$crawler, $client] = $this->authAdmin();

        $link = $crawler->selectLink(AbstractTest::TEST_EXIT)->link();
        $crawler = $client->click($link);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }

    public function testAuthUserAndLogout(): void
    {
        [$crawler, $client]=$this->authUser();

        $link = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($link);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }

    // public function testRegisterAndLogout(): void
    // {
    //     $client = $this->billingClient();
    //     $crawler = $client->request('GET', '/');
    //     $this->assertResponseOk();

    //     $link = $crawler->selectLink('Регистрация')->link();
    //     $crawler = $client->click($link);
    //     $this->assertResponseOk();

    //     $login = $crawler->selectButton('Сохранить')->form([
    //         'register[username]' => $this->userCredentials['username'],
    //         'register[password][first]' => 'password',
    //         'register[password][second]' => 'password',
    //     ]);
    //     $client->submit($login);

    //     $this->assertResponseOk();

    //     self::assertEquals('/register', $client->getRequest()->getPathInfo());

    //     self::assertSelectorTextContains(
    //         '.notification.symfony-notification.error',
    //         'Email уже используется.'
    //     );

    //     $login = $crawler->selectButton('Сохранить')->form([
    //         'register[username]' => 'test@study-on.ru',
    //         'register[password][first]' => 'password',
    //         'register[password][second]' => 'password',
    //     ]);
    //     $client->submit($login);

    //     $this->assertResponseRedirect();
    //     $crawler = $client->followRedirect();
    //     self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
    // }
}
