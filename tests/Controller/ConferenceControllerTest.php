<?php


namespace App\Tests\Controller;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Panther\PantherTestCase;

class ConferenceControllerTest extends PantherTestCase
{
    public function testIndex()
    {
//        $client = static::createClient();
        $client = static ::createPantherClient(['external_base_uri' => $_SERVER['SYMFONY_DEFAULT_ROUTE_URL']]);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Give your feedback');
    }

    public function testConferencePage()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertCount(2, $crawler->filter('h4'));

        $client->clickLink('View');
        //une autre façon de prendre un lien avec le crawler
//        $client->click('h4 + p a')->link();

        $this->assertPageTitleContains('Amsterdam');
        $this->assertSelectorTextContains('h2', 'Amsterdam 2019');
        $this->assertSelectorExists('div:contains("There are 1 comments")');
    }

    public function testCommentSubmission()
    {
        $client = static ::createClient();
        $client->request('GET','/conference/amsterdam-2019');
        $client->submitForm('Submit',[
            'comment_form[author]' => 'Sylvain',
            'comment_form[text]' => 'This conference was really great !!!',
            'comment_form[email]' => 'hello@world.com',
            'comment_form[photo]' => dirname(__DIR__, 2).'/public/images/under-construction.gif'
        ]);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorExists('div:contains("There are 2 comments")');
    }
}
