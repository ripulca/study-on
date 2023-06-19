<?php

namespace App\Controller;

use LogicException;
use App\DTO\CourseDTO;
use App\Entity\Course;
use App\Form\CourseType;
use App\Enum\PaymentStatus;
use App\Service\ArrayService;
use App\Service\BillingClient;
use App\Repository\CourseRepository;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

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
        $courses = ArrayService::mapToKey($courseRepository->findAllArray(), 'code');
        $billingCourses = ArrayService::mapToKey($this->billingClient->getCourses(), 'code');
        foreach ($courses as $code => $course) {
            if (isset($billingCourses[$code])) {
                if ($billingCourses[$code]['type'] === PaymentStatus::RENT_NAME) {
                    $courses[$code]['type'] = PaymentStatus::RENT_NAME;
                    $courses[$code]['price_msg'] = $billingCourses[$code]['price'] . PaymentStatus::ROUBLES. PaymentStatus::PER_WEEK;
                } elseif ($billingCourses[$code]['type'] === PaymentStatus::BUY_NAME) {
                    $courses[$code]['type'] = PaymentStatus::BUY_NAME;
                    $courses[$code]['price_msg'] = $billingCourses[$code]['price'] . PaymentStatus::ROUBLES;
                } elseif ($billingCourses[$code]['type'] === PaymentStatus::FREE_NAME) {
                    $courses[$code]['type'] = PaymentStatus::FREE_NAME;
                    $courses[$code]['price_msg'] = PaymentStatus::FREE_RUS;
                }
            }
        }
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->security->getUser();
            $transactions = ArrayService::mapToKey($this->billingClient->getTransactions($user->getApiToken(), 'payment', null, true), 'code');
            foreach ($courses as $code => $course) {
                if (isset($transactions[$code])) {
                    if ($course['type'] === PaymentStatus::RENT_NAME) {
                        $expiresAt = $transactions[$code]['expires'];
                        if ($expiresAt != null) {
                            $courses[$code]['price_msg'] = PaymentStatus::RENT_TILL . date('d/m/y H:i:s', strtotime($expiresAt['date']));
                        }
                    } elseif ($course['type'] === PaymentStatus::BUY_NAME) {
                        $courses[$code]['price_msg'] = PaymentStatus::BOUGHT;
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
    public function new(Request $request, CourseRepository $courseRepository, SerializerInterface $serializer, ): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $type = $form->get('type')->getData();
            $price = $form->get('price')->getData();
            $code = $form->get('code')->getData();
            if ($courseRepository->count(['code' => $code]) > 0) {
                throw new LogicException('Курс с таким кодом уже существует');
            }
            if ($type == PaymentStatus::FREE) {
                $price = 0;
            } elseif ($price == 0) {
                throw new ResourceNotFoundException('Курс платный, укажите цену');
            }
            $user = $this->security->getUser();
            $courseDTO = CourseDTO::getCourseDTO($name, $code, $type, $price);

            $response = $this->billingClient->newCourse($user->getApiToken(), $courseDTO);
            if (isset($response['success'])) {
                $courseRepository->save($course, true);
            }

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, Course $course): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }
        $billingCourse = $this->billingClient->getCourse($course->getCode());
        $billingUser = $this->billingClient->getCurrentUser($user->getApiToken());
        $transactions = $this->billingClient->getTransactions($user->getApiToken(), 'payment', $course->getCode(), true);
        $course = [
            'id' => $course->getId(),
            'code' => $course->getCode(),
            'name' => $course->getName(),
            'description' => $course->getDescription(),
            'lessons' => $course->getLessons(),
            'type' => $billingCourse['type'],
            'isPaid' => false,
        ];
        if (isset($billingCourse['price'])) {
            $course['price'] = $billingCourse['price'];
        }
        if ($billingCourse['type'] === 'rent') {
            $course['price_msg'] = $billingCourse['price'] . PaymentStatus::ROUBLES. PaymentStatus::PER_WEEK;
        } elseif ($billingCourse['type'] === 'buy') {
            $course['price_msg'] = $billingCourse['price'] . PaymentStatus::ROUBLES;
        } elseif ($billingCourse['type'] === 'free') {
            $course['price_msg'] = PaymentStatus::FREE_RUS;
        }
        if (count($transactions) > 0) {
            $transaction = $transactions[count($transactions) - 1];
            $course['isPaid'] = true;
            if ($billingCourse['type'] === 'rent') {
                if ($transaction['expires'] != null) {
                    $course['price_msg'] = PaymentStatus::RENT_TILL . date('d/m/y H:i:s', strtotime($transaction['expires']['date']));
                }
            } elseif ($billingCourse['type'] === 'buy') {
                $course['price_msg'] = PaymentStatus::BOUGHT;
            }
        }
        $status = null;
        if ($request->query->get('status') != null) {
            $status = PaymentStatus::PAY_NAMES[$request->query->get('status')];
        }
        return $this->render('course/show.html.twig', [
            'course' => $course,
            'status' => $status,
            'billingUser' => $billingUser,
        ]);
    }

    #[Route('/{id}/pay', name: 'app_course_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function pay(Request $request, Course $course): Response
    {
        if (!$this->isCsrfTokenValid('pay' . $course->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }
        $user = $this->security->getUser();
        $status = null;
        try {
            $responce = $this->billingClient->payForCourse($user->getApiToken(), $course->getCode());
            if ($responce['success'] == true) {
                $status = PaymentStatus::OK;
            }
        } catch (LogicException $e) {
            $status = PaymentStatus::ALREADY_PAID;
        } catch (MissingResourceException $e) {
            $status = PaymentStatus::NO_MONEY;
        }
        return $this->redirectToRoute('app_course_show', ['id' => $course->getId(), 'status' => $status], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        $oldCode = $course->getCode();
        $billingCourse = $this->billingClient->getCourse($course->getCode());
        $oldType = $billingCourse['type'];
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            if ($oldType != PaymentStatus::BUY) {
                $type = $form->get('type')->getData();
            } else {
                $type = $oldType;
            }
            $price = $form->get('price')->getData();
            $code = $form->get('code')->getData();
            if ($oldCode != $code && $courseRepository->count(['code' => $code]) > 0) {
                throw new LogicException('Курс с таким кодом уже существует');
            }
            $user = $this->security->getUser();
            $courseDTO = CourseDTO::getCourseDTO($name, $code, $type, $price);
            $response = $this->billingClient->editCourse($user->getApiToken(), $oldCode, $courseDTO);
            if (isset($response['success'])) {
                $courseRepository->save($course, true);
            }

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