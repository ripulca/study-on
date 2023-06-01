<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Service\ArrayService;
use App\Service\BillingClient;
use App\Repository\CourseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/courses')]
class CourseController extends AbstractController
{
    private BillingClient $billingClient;
    private Security $security;

    public function __construct(BillingClient $billingClient, Security $security)
    {
        $this->billingClient = $billingClient;
        $this->security = $security;
    }

    #[Route('/', name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        // $courses = $courseRepository->findAll();
        $courses = ArrayService::arrayByKey($courseRepository->findAllArray(), 'code');
        $billingCourses = ArrayService::arrayByKey($this->billingClient->getCourses(), 'code');
        foreach ($courses as $code => $course) {
            if (isset($billingCourses[$code])) {
                if ($billingCourses[$code]['type'] === 'rent') {
                    $courses[$code]['type']='rent';
                    $courses[$code]['price_msg'] = $billingCourses[$code]['price'] . '₽ в неделю';
                } elseif ($billingCourses[$code]['type'] === 'buy') {
                    $courses[$code]['type']='buy';
                    $courses[$code]['price_msg'] = $billingCourses[$code]['price'] . '₽';
                } elseif ($billingCourses[$code]['type'] === 'free') {
                    $courses[$code]['type']='free';
                    $courses[$code]['price_msg'] = 'Бесплатный';
                }
            }
        }
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->security->getUser();
            $transactions = ArrayService::arrayByKey($this->billingClient->getTransactions($user->getApiToken(), 'payment', null, true), 'code');
            dd($transactions);
            foreach ($courses as $code => $course) {
                if (isset($transactions[$code])) {
                    if ($course['type'] === 'rent') {
                        $expiresAt = $transactions[$code]['expires_at'];
                        $expiresAt = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $expiresAt);
                        $courses[$code]['price_msg'] = 'Арендовано до ' . $expiresAt->format('H:i:s d.m.Y');
                    } elseif ($course['type'] === 'buy') {
                        $courses[$code]['price_msg'] = 'Куплено';
                    }
                }
            }
        }
        return $this->render('course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, CourseRepository $courseRepository): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $courseRepository->save($course, true);

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Course $course): Response
    {
        return $this->render('course/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $courseRepository->save($course, true);

            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $courseRepository->remove($course, true);
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}