<?php

namespace App\Security;

use App\Service\BillingClient;
use Symfony\Component\HttpFoundation\Request;
use App\Exception\BillingUnavailableException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    private UrlGeneratorInterface $urlGenerator;
    private BillingClient $billingClient;

    public function __construct(UrlGeneratorInterface $urlGenerator, BillingClient $billingClient)
    {
        $this->urlGenerator = $urlGenerator;
        $this->billingClient = $billingClient;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

        try {
            $token_data = $this->billingClient->auth(['username' => $email, 'password' => $password]);
        } catch (BillingUnavailableException|JsonException $e) {
            throw new BillingUnavailableException();
        }
        $refreshToken = $token_data['refresh_token'];

        $userLoader = function ($token) use ($refreshToken): UserInterface {
            try {
                $userDto = $this->billingClient->getCurrentUser($token);
            } catch (BillingUnavailableException|JsonException $e) {
                throw new BillingUnavailableException();
            }
            return User::fromDto($userDto)
                ->setApiToken($token)
                ->setRefreshToken($refreshToken);
        };

        return new SelfValidatingPassport(
            new UserBadge($token_data['token'], $userLoader),
            [
                new CsrfTokenBadge('authenticate', $request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_course_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
