<?php

namespace App\Controller;

use App\Enum\PaymentStatus;
use App\Service\BillingClient;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfileController extends AbstractController
{
    private BillingClient $billingClient;
    private Security $security;

    public function __construct(Security $security, BillingClient $billingClient)
    {
        $this->security = $security;
        $this->billingClient = $billingClient;
    }

    #[Route(path: '/profile', name: 'app_profile_show')]
    public function profile(): Response
    {
        $user = $this->billingClient->getCurrentUser($this->getUser()->getApiToken());
        $transactions=$this->billingClient->getTransactions($this->getUser()->getApiToken(), null, null, true);
        foreach($transactions as &$transaction){
            $transaction['type']=PaymentStatus::TYPE_NAMES[$transaction['type']];
        }
        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'transactions' => $transactions,
        ]);
    }
}