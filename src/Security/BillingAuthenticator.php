<?php

namespace App\Security;

use App\Service\BillingClient;
use Symfony\Bundle\SecurityBundle\Security;
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
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    public const SERVICE_TEMPORARILY_UNAVAILABLE = 'Сервис временно недоступен. Попробуйте авторизоваться позднее';
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
            $token = $this->billingClient->auth(['username' => $email, 'password' => $password]);
        } catch (BillingUnavailableException|JsonException $e) {
            throw new CustomUserMessageAuthenticationException(self::SERVICE_TEMPORARILY_UNAVAILABLE);
        }
        $request->getSession()->set(Security::LAST_USERNAME, $email);

        $userLoader = function ($token): UserInterface {
            try {
                $userDto = $this->billingClient->getCurrentUser($token);
            } catch (BillingUnavailableException|JsonException $e) {
                throw new CustomUserMessageAuthenticationException(self::SERVICE_TEMPORARILY_UNAVAILABLE);
            }
            return User::fromDto($userDto)
                ->setApiToken($token);
        };

        return new SelfValidatingPassport(
            new UserBadge($token, $userLoader),
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
