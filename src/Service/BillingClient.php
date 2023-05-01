<?php

namespace App\Service;

use App\DTO\UserDTO;
use JMS\Serializer\Serializer;
use App\Exception\BillingException;
use JMS\Serializer\SerializerBuilder;
use App\Exception\BillingUnavailableException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class BillingClient
{
    private ValidatorInterface $validator;
    private Serializer $serializer;
    protected const GET = 'GET';
    protected const POST = 'POST';
    protected const AUTH_PATH = '/auth';
    protected const REGISTER_PATH = '/register';
    protected const GET_CURRENT_USER_PATH = '/users/current';

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->serializer = SerializerBuilder::create()->build();
    }

    public function auth($credentials)
    {
        $response = $this->jsonRequest(
            self::POST,
            self::AUTH_PATH,
            $credentials,
        );
        if ($response['code'] === 401) {
            throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }
        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    public function register($credentials)
    {
        $response = $this->request(
            self::POST,
            self::REGISTER_PATH,
            $credentials,
        );

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (isset($result['code'])) {
            if (409 === $result['code']) {
                throw new CustomUserMessageAuthenticationException($result['message']);
            }
            if (400 === $result['code']) {
                throw new BillingException(json_decode($result['errors']));
            }
        }
        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    public function getCurrentUser(string $token)
    {
        $response = $this->jsonRequest(
            self::GET,
            self::GET_CURRENT_USER_PATH,
            '',
            ['Authorization' => 'Bearer ' . $token]
        );
        if ($response['code'] === 401) {
            throw new CustomUserMessageAuthenticationException('Некорректный JWT токен');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        $userDto = $this->serializer->deserialize($response['body'], UserDTO::class, 'json');
        $errors = $this->validator->validate($userDto);
        if (count($errors) > 0) {
            throw new BillingUnavailableException('User data is not valid');
        }
        return $userDto;
    }

    public function jsonRequest($method, string $path, $body, array $headers = []){
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        return $this->request($method, $path, json_encode($body, JSON_THROW_ON_ERROR), $headers);
    }

    public function request($method, string $path, $body, array $headers = [])
    {
        $route=$_ENV['BILLING_URL'] . $path;
        $query = curl_init($route);
        $options=[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CUSTOMREQUEST=>$method,
        ];

        if ($method === self::POST) {
            $options[CURLOPT_POSTFIELDS]=$body;
        }

        if (count($headers) > 0) {
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = $name . ': ' . $value;
            }
            $options[CURLOPT_HTTPHEADER]= $curlHeaders;
        }
        curl_setopt_array($query, $options);

        $response = curl_exec($query);
        if (curl_error($query)) {
            throw new BillingUnavailableException(curl_error($query));
        }
        $responseCode = curl_getinfo($query, CURLINFO_RESPONSE_CODE);
        curl_close($query);
        return [
            'code' => $responseCode,
            'body' => $response,
        ];
    }
}