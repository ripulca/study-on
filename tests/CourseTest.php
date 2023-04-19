<?php

namespace App\Tests;

use App\Tests\AbstractTest;

class CourseTest extends AbstractTest
{
    public function testRedirect(): void
    {
        $client=static::getClient();
        $client->request('GET', '/');
        $this->assertResponseRedirect();
        $client->followRedirects();
        $this->assertResponseOk();
    }

    public function getSuccessfulURLs(): \Generator
    {
        yield['/'];
        yield['/courses'];
        yield['/courses/new'];
    }

    /**
     * @dataProvider getSuccessfulURLs
     */
    public function testSuccessfulURLs($url): void
    {
        $client=static::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();
    }



}
