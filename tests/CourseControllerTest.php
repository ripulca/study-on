<?php

namespace App\Tests;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\Mock\BillingMock;
use App\DataFixtures\AppFixtures;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\BrowserKit\AbstractBrowser;

class CourseControllerTest extends AbstractTest
{
    public function urlProviderIsSuccessful(): \Generator
    {
        yield ['/'];
        yield ['/courses/'];
        yield ['/courses/new'];
    }

    /**
     * @dataProvider urlProviderIsSuccessful
     */
    public function testPageIsSuccessful($url): void
    {
        $client = $this->getClient();
        $this->beforeTestingAdmin($client);
        $client->request('GET', $url);
        $this->assertResponseOk();
    }

    public function urlProviderNotFound(): \Generator
    {
        yield ['/not-found/'];
        yield ['/courses/-1'];
        yield ['/abvgd'];
    }

    /**
     * @dataProvider urlProviderNotFound
     */
    public function testPageIsNotFound($url): void
    {
        $client = $this->getClient();
        $this->beforeTestingAdmin($client);
        $this->assertResponseOk();
    }

    public function testGetActionsResponseOk(): void
    {
        $client = $this->getClient();
        $this->beforeTestingAdmin($client);
        $courses = $this->getEntityManager()->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            // детальная страница курса
            $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();

            // страница редактирования
            $client->request('GET', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();
        }
    }
    public function testSuccessfulCourseCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);
        
        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course = $this->getEntityManager()->getRepository(Course::class)->findOneBy([
            'code' => 'unique-code1',
        ]);

        // проверяем редирект
        $this->assertSame($client->getResponse()->headers->get('location'), '/courses/');
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $crawler = $client->request('GET', '/courses/' . $course->getId());
        $this->assertResponseOk();

        // проверяем корректность отображения данных
        $this->assertSame($crawler->filter('.course-name')->text(), $course->getName());
        $this->assertSame($crawler->filter('.card-text')->text(), $course->getDescription());
    }

    public function testCourseWithEmptyCodeCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса с пустым кодом и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => '',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Код не может быть пустым'
        );
    }

    public function testCourseWithEmptyNameCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса данными с пустым названием
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'PHP-TEST',
            'course[name]' => '',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не может быть пустым'
        );
    }

    public function testCourseWithNotUniqueCodeCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        //получаем имеющиеся курсы
        $courses = $this->getEntityManager()->getRepository(Course::class)->findAll();
        $last_course = $courses[count($courses) - 1];
        // заполнили форму и отправили с не уникальным кодом
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => $last_course->getCode(),
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Данный код уже существует'
        );
    }

    public function testCourseWithTooMuchSymbolsInCodeCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму и отправили с кодом, превышающим 255 символов
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => $this->getLoremIpsum()->words(50),
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Код не должен превышать 255 символов'
        );
    }

    public function testCourseWithTooMuchSymbolsInNameCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму и отправили с названием, превышающим 255 символов
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'Course name for test',
            'course[name]' => $this->getLoremIpsum()->words(50),
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не должно превышать 255 символов'
        );
    }

    public function testCourseWithTooMuchSymbolsInDescriptionCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму и отправили с описанием, превышающим 1000 символов
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'Code',
            'course[name]' => 'Course name for test',
            'course[description]' => $this->getLoremIpsum()->words(1000),
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Описание не должно превышать 1000 символов'
        );
    }

    public function testCourseSuccessfulEditing(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // на детальной странице курса
        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form();

        // сохраняем id редактируемого курса
        $courseId = $this->getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['code' => $form['course[code]']->getValue()])->getId();

        // заполняем форму корректными данными
        $form['course[code]'] = 'successEdit';
        $form['course[name]'] = 'Course name for test';
        $form['course[description]'] = 'Description course for test';
        $client->submit($form);

        // проверяем редирект
        $crawler = $client->followRedirect();
        $this->assertRouteSame('app_course_show', ['id' => $courseId]);
        $this->assertResponseOk();

        // проверяем изменение данных
        $this->assertSame($crawler->filter('.course-name')->text(), 'Course name for test');
        $this->assertSame($crawler->filter('.card-text')->text(), 'Description course for test');
    }

    public function testCourseWithEmptyCodeEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();

        // пробуем сохранить курс без кода
        $form['course[code]'] = '';
        $form['course[name]'] = 'Course name for test';
        $form['course[description]'] = 'Description course for test';
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Код не может быть пустым'
        );
    }

    public function testCourseWithNotUniqueCodeEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();
        $courses = $this->getEntityManager()->getRepository(Course::class)->findAll();
        $first_course = $courses[0];

        // пробуем сохранить курс с существующим кодом
        $form['course[code]'] = $first_course->getCode();
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Данный код уже существует'
        );
    }

    public function testCourseWithEmptyNameEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();

        // пробуем сохранить курс с пустым именем
        $form['course[code]'] = 'exampleuniqcode';
        $form['course[name]'] = '';
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не может быть пустым'
        );
    }

    public function testCourseWithTooMuchSymbolsInCodeEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();

        // пробуем сохранить курс с кодом где символов больше 255
        $form['course[code]'] = $this->getLoremIpsum()->words(50);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Код не должен превышать 255 символов'
        );
    }

    public function testCourseWithTooMuchSymbolsInNameEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();

        // пробуем сохранить курс с именем где символов больше 255
        $form['course[code]'] = 'exampleuniqcode';
        $form['course[name]'] = $this->getLoremIpsum()->words(50);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не должно превышать 255 символов'
        );
    }

    public function testCourseWithTooMuchSymbolsInDescriptionEditing(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->selectLink(BillingMock::TEST_ADD)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $courseCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course_id = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'unique-code1'])->getId();

        // со страницы списка курсов
        $client = $this->getClient();
        $crawler = $client->request('GET', '/courses/' . $course_id);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton(BillingMock::TEST_UPDATE);
        $form = $submitButton->form();

        // пробуем сохранить курс с описанием где символов больше 1000
        $form['course[name]'] = 'Course name for test';
        $form['course[description]'] = $this->getLoremIpsum()->words(1000);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Описание не должно превышать 1000 символов'
        );
    }

    public function testCourseDeleting(): void
    {
        // страница со списком курсов
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        // подсчитываем количество курсов
        $allCourses = $this->getEntityManager()->getRepository(Course::class)->findAll();
        $allCoursesCount = count($allCourses);
        $courseToDelete = $allCourses[$allCoursesCount - 1];

        $crawler = $client->request('GET', '/courses/' . $courseToDelete->getId());
        $courseLessonsCount = count($courseToDelete->getLessons());
        // количество до удаления
        $allLessonsCount = count($this->getEntityManager()
            ->getRepository(Lesson::class)
            ->findAll());
        $countThatShouldStayAfterDeleting = $allLessonsCount - $courseLessonsCount;

        $client->submitForm('Удалить');
        $this->assertSame($client->getResponse()->headers->get('location'), '/courses/');
        $crawler = $client->followRedirect();
        // количество после удаления
        $allLessonsCountAfterDeleting = count($this->getEntityManager()
            ->getRepository(Lesson::class)
            ->findAll());

        $coursesCountAfterDelete = count($this->getEntityManager()->getRepository(Course::class)->findAll());

        // проверка соответствия кол-ва курсов
        $this->assertSame($allCoursesCount - 1, $coursesCountAfterDelete);
        $this->assertSame($countThatShouldStayAfterDeleting, $allLessonsCountAfterDeleting);
        $this->assertCount($coursesCountAfterDelete, $crawler->filter('.card-body'));
    }

    protected function authorize(AbstractBrowser $client, string $login, string $password): ?Crawler
    {
        $crawler = $client->clickLink('Вход');

        $form = $crawler->filter('form')->first()->form();
        $form['email'] = $login;
        $form['password'] = $password;

        $crawler = $client->submit($form);
        return $crawler;
    }

    public function beforeTestingAdmin($client)
    {
        $mock= new BillingMock();
        $client=$mock->mockBillingClient($client);
        $crawler = $client->request('GET', '/');
        $crawler = $this->authorize($client, BillingMock::$admin['username'], BillingMock::$admin['password']);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        return $crawler;
    }

    public function beforeTestingUser($client)
    {
        $mock= new BillingMock();
        $client=$mock->mockBillingClient($client);
        $crawler = $client->request('GET', '/');
        $crawler = $this->authorize($client, BillingMock::$user['username'], BillingMock::$user['password']);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        return $crawler;
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
}