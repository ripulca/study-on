<?php

namespace App\Controller;

use Exception;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class RegisterController extends AbstractController
{
    private BillingClient $billingClient;
    private Security $security;
    public const SERVICE_TEMPORARILY_UNAVAILABLE = 'Сервис временно недоступен. Попробуйте авторизоваться позднее';

    public function __construct(BillingClient $billingClient, Security $security)
    {
        $this->billingClient = $billingClient;
        $this->security = $security;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        AuthenticatorInterface $loginAuthenticator,
        AuthenticationUtils $authenticationUtils
    ): Response {

        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile_show', [], Response::HTTP_SEE_OTHER);
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $token = $this->billingClient->register([
                    'username' => $form->get('email')->getData(),
                    'password' => $form->get('password')->getData()
                ]);
            } catch (Exception $e) {
                if ($e instanceof CustomUserMessageAuthenticationException) {
                    $error = $e->getMessage();
                } else {
                    $error = self::SERVICE_TEMPORARILY_UNAVAILABLE;
                }
                return $this->render('register/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'error' => $error,
                ]);
            }
            $user->setApiToken($token);

            return $userAuthenticator->authenticateUser(
                $user,
                $loginAuthenticator,
                $request
            );
        }
        return $this->render('register/register.html.twig', [
            'registrationForm' => $form->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError()
        ]);
    }
}