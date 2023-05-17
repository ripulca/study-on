<?php

namespace App\Tests\Mock;

use App\DTO\UserDTO;
use App\Security\User;
use App\Service\BillingClient;
use App\Exception\BillingException;

class BillingMock extends BillingClient
{
    private array $user;

    private array $admin;

    public function __construct()
    {

        $this->user = [
            'username' => 'user@studyon.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 100.0,
        ];

        $this->admin = [
            'username' => 'admin@studyon.com',
            'password' => 'password',
            'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
            'balance' => 500.0,
        ];
    }

    public function auth($credentials)
    {
        $credentials = json_decode($credentials, true, 512, JSON_THROW_ON_ERROR);
        $username = $credentials['username'];
        $password = $credentials['password'];
        $refresh_token = 'refresh_token';
        if ($username === $this->user['username'] && $password === $this->user['password']) {
            $token = $this->generateToken($this->user['roles'], $username);
            return ['token'=>$token, 'refresh_token'=>$refresh_token];
        } elseif ($username === $this->admin['username'] && $password === $this->admin['password']) {
            $token = $this->generateToken($this->admin['roles'], $username);
            return ['token'=>$token, 'refresh_token'=>$refresh_token];
        } else {
            throw new BillingException('Ошибка авторизации. Проверьте правильность введенных данных!');
        }
    }

    public function register($credentials)
    {
        $credentials = json_decode($credentials, true, 512, JSON_THROW_ON_ERROR);
        $username = $credentials['username'];
        $password = $credentials['password'];
        if ($username === $this->admin['username'] || $username === $this->user['username']) {
            throw new BillingException('Email уже используется.');
        }
        $refresh_token = 'refresh_token';
        $token = $this->generateToken($this->user['roles'], $username);
        return ['token'=>$token, 'refresh_token'=>$refresh_token, 'roles'=>$this->user['roles']];
    }

    public function profile(string $jwtToken)
    {
        $token = $jwtToken;
        [$exp, $username, $roles] = User::jwtDecode($token);
        $userDto = new UserDTO($username, $roles, 0);
        if ($username === $this->user['username']) {
            $userDto->setBalance($this->user['balance']);
        } elseif ($username === $this->admin['username']) {
            $userDto->setBalance($this->admin['balance']);
        }
        return $userDto;
    }

    private function generateToken(array $roles, string $username): string
    {
        $data = [
            'email' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];
        $query = base64_encode(json_encode($data));

        return 'header.' . $query . '.signature';
    }
}