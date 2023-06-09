<?php

namespace App\Tests;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingMock;
use App\DataFixtures\AppFixtures;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\BrowserKit\AbstractBrowser;

class LessonControllerTest extends AbstractTest
{
    public function testGetActionsResponseOk(): void
    {
        $client = $this->getClient();
        $this->beforeTestingAdmin($client);
        $lessons = $this->getEntityManager()->getRepository(Lesson::class)->findAll();
        foreach ($lessons as $lesson) {
            // детальная страница урока
            $client->request('GET', '/lesson/' . $lesson->getId());
            $this->assertResponseOk();

            // страница редактирования урока
            $client->request('GET', '/lesson/' . $lesson->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testSuccessfulLessonCreating(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton(BillingMock::TEST_SAVE)->form();
        // сохраняем id курса
        $courseId = $form['lesson[course_id]']->getValue();

        // заполняем форму создания урока корректными данными и отправляем
        $form['lesson[name]'] = 'Lesson for test';
        $form['lesson[content]'] = 'Lesson content for test';
        $form['lesson[serialNumber]'] = '12';
        $client->submit($form);

        // проверяем редирект
        $crawler = $client->followRedirect();
        $this->assertRouteSame('app_course_show', ['id' => $courseId]);
        $this->assertResponseOk();

        $this->assertResponseOk();
        $this->assertSame($crawler->filter('.lesson')->last()->text(), 'Lesson for test');

        $crawler = $client->click($crawler->filter('.lesson')->last()->link());
        $this->assertResponseOk();

        // проверим название и содержание
        $this->assertSame($crawler->filter('.lesson-name')->first()->text(), 'Lesson for test');
        $this->assertSame($crawler->filter('.content')->first()->text(), 'Lesson content for test');
    }

    public function testLessonFailedCreatingWithEmptyNum(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания урока с пустым порядковым номером
        $lessonCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'lesson[serialNumber]' => '',
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => 'Description lesson for test',
        ]);
        $client->submit($lessonCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Порядковый номер не может быть пустым'
        );
    }

    public function testLessonFailedCreatingWithEmptyName(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания урока с пустым названием
        $lessonCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'lesson[serialNumber]' => '1',
            'lesson[name]' => '',
            'lesson[content]' => 'Description lesson for test',
        ]);
        $client->submit($lessonCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не может быть пустым'
        );
    }

    public function testLessonFailedCreatingWithEmptyContent(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму с пустым контентом
        $lessonCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'lesson[serialNumber]' => '1',
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => '',
        ]);
        $client->submit($lessonCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Контент не может быть пустым'
        );
    }

    public function testLessonFailedCreatingNumMoreThanLimit(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму с порядковым номером больше 10000
        $lessonCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'lesson[serialNumber]' => 10001,
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => 'Description lesson for test',
        ]);
        $client->submit($lessonCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Порядковый номер урока должен быть между 1 и 10000'
        );
    }

    public function testLessonFailedCreatingNameMoreThanLimit(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму с названием больше 255 символов
        $lessonCreatingForm = $crawler->selectButton(BillingMock::TEST_SAVE)->form([
            'lesson[serialNumber]' => 1,
            'lesson[name]' => $this->getLoremIpsum()->words(50),
        ]);
        $client->submit($lessonCreatingForm);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не должно превышать 255 символов'
        );
    }

    public function testLessonSuccessfulEditing(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form();
        // сохраняем id курса
        $courseId = $this->getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy([
                'id' => $form['lesson[course_id]']->getValue(),
            ])->getId();

        // заполняем форму корректными данными
        $form['lesson[serialNumber]'] = '123';
        $form['lesson[name]'] = 'Test edit lesson';
        $form['lesson[content]'] = 'Test edit lesson content';
        $client->submit($form);

        // проверяем редирект
        $crawler = $client->followRedirect();
        $this->assertRouteSame('app_course_show', ['id' => $courseId]);
        $this->assertResponseOk();

        // проверяем, что урок отредактирован
        $this->assertSame($crawler->filter('.lesson')->last()->text(), 'Test edit lesson');
        $link = $crawler->filter('.lesson')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // проверим название и содержание
        $this->assertSame($crawler->filter('.lesson-name')->first()->text(), 'Test edit lesson');
        $this->assertSame($crawler->filter('.content')->first()->text(), 'Test edit lesson content');
    }

    public function testLessonFailedEditingWithEmptyNum(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // пробуем сохранить урок с пустым порядковым номером
        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form([
            'lesson[serialNumber]' => ' ',
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => 'Description lesson for test',
        ]);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Порядковый номер не может быть пустым'
        );
    }

    public function testLessonFailedEditingWithEmptyName(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания урока с пустым названием
        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form([
            'lesson[serialNumber]' => 1,
            'lesson[name]' => '',
            'lesson[content]' => 'Description lesson for test',
        ]);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не может быть пустым'
        );
    }

    public function testLessonFailedEditingWithEmptyContent(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму с пустым контентом
        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form([
            'lesson[serialNumber]' => 1,
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => '',
        ]);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Контент не может быть пустым'
        );
    }

    public function testLessonFailedEditingNumMoreThanLimit(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // пробуем сохранить урок с порядковым номером больше 10000
        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form([
            'lesson[serialNumber]' => 10001,
            'lesson[name]' => 'Course name for test',
            'lesson[content]' => 'test',
        ]);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Порядковый номер урока должен быть между 1 и 10000'
        );
    }

    public function testLessonFailedEditingNameMoreThanLimit(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink(BillingMock::TEST_EDIT)->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // пробуем сохранить урок с именем где символов больше 255
        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form([
            'lesson[serialNumber]' => 1,
            'lesson[name]' => $this->getLoremIpsum()->words(50),
            'lesson[content]' => 'test',
        ]);
        $client->submit($form);
        $this->assertResponseCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не должно превышать 255 символов'
        );
    }

    public function testLessonDeleting(): void
    {
        // от списка курсов переходим на страницу просмотра курса
        $client = $this->getClient();
        $crawler = $this->beforeTestingAdmin($client);

        // на детальную страницу курса
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // переходим к деталям урока
        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // сохраняем информацию о курсе
        $crawler = $client->click($crawler->selectLink(BillingMock::TEST_EDIT)->link());
        $this->assertResponseOk();

        $form = $crawler->selectButton(BillingMock::TEST_UPDATE)->form();
        $course = $this->getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course_id]']->getValue()]);
        // количество до удаления
        $countBeforeDeleting = count($course->getLessons());

        $link = $crawler->filter('.course')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $client->submitForm('Удалить');
        $this->assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();

        // сравнение количества уроков
        $this->assertCount($countBeforeDeleting - 1, $crawler->filter('.lesson'));
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