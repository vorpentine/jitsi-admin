<?php

namespace App\Tests;

use App\Repository\RoomsRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SheduleNewTest extends WebTestCase
{
    public function testCreate(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        // retrieve the test user
        $testUser = $userRepository->findOneByUsername('test@local.de');
        $client->loginUser($testUser);
        $server = $testUser->getServers()->toArray()[0];

        $crawler = $client->request('GET', '/room/schedule/new');
        $buttonCrawlerNode = $crawler->selectButton('Speichern');
        $form = $buttonCrawlerNode->form();
        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '';
        $form['room[duration]'] = "60";

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(json_encode(array('error' => true, 'messages' => array('Fehler, der Name darf nicht leer sein'))), $client->getResponse()->getContent());
        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '198273987321';
        $form['room[duration]'] = "60";
        $client->submit($form);
        $room = (static::getContainer()->get(RoomsRepository::class))->findOneBy(array('name' => 198273987321));
        $urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
        $modalUrl = base64_encode($urlGenerator->generate('schedule_admin', array('id' => $room->getId())));
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                array(
                    'error' => false,
                    'redirectUrl' => $urlGenerator->generate('dashboard',array('snack'=>'Terminplanung erfolgreich erstellt','modalUrl'=>$modalUrl)),
                    'cookie' => array(
                        'room_server' => $server->getId()
                    )
                )
            ),
            $client->getResponse()->getContent()
        );
    }
    public function testRemove(): void{
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        // retrieve the test user
        $testUser = $userRepository->findOneByUsername('test@local.de');
        $client->loginUser($testUser);
        $server = $testUser->getServers()->toArray()[0];

        $crawler = $client->request('GET', '/room/schedule/new');
        $buttonCrawlerNode = $crawler->selectButton('Speichern');
        $form = $buttonCrawlerNode->form();
        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '198273987321';
        $form['room[duration]'] = "60";
        $client->submit($form);
        $roomRepo =static::getContainer()->get(RoomsRepository::class);
        $room = $roomRepo->findOneBy(array('name' => '198273987321'));
        self::assertNotNull($room);
        $urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
        $modalUrl = base64_encode($urlGenerator->generate('schedule_admin', array('id' => $room->getId())));
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                array(
                    'error' => false,
                    'redirectUrl' => $urlGenerator->generate('dashboard',array('snack'=>'Terminplanung erfolgreich erstellt','modalUrl'=>$modalUrl)),
                    'cookie' => array(
                        'room_server' => $server->getId()
                    )
                )
            ),
            $client->getResponse()->getContent()
        );
        $client->request('GET',$urlGenerator->generate('room_remove',array('room'=>$room->getId())));
        $this->assertTrue($client->getResponse()->isRedirect('/room/dashboard?snack=Konferenz%20gel%C3%B6scht'));
        $room = $roomRepo->findOneBy(array('name' => '198273987321'));
        $this->assertEquals(0,sizeof($room->getUser()));
        $this->assertNull($room->getModerator());
    }

    public function testEdit(): void{
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        // retrieve the test user
        $testUser = $userRepository->findOneByUsername('test@local.de');
        $client->loginUser($testUser);
        $server = $testUser->getServers()->toArray()[0];

        $crawler = $client->request('GET', '/room/schedule/new');
        $buttonCrawlerNode = $crawler->selectButton('Speichern');
        $form = $buttonCrawlerNode->form();
        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '198273987321';
        $form['room[duration]'] = "60";
        $client->submit($form);
        $roomRepo =static::getContainer()->get(RoomsRepository::class);
        $room = $roomRepo->findOneBy(array('name' => '198273987321'));
        self::assertNotNull($room);
        $urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
        $modalUrl = base64_encode($urlGenerator->generate('schedule_admin', array('id' => $room->getId())));
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                array(
                    'error' => false,
                    'redirectUrl' => $urlGenerator->generate('dashboard',array('snack'=>'Terminplanung erfolgreich erstellt','modalUrl'=>$modalUrl)),
                    'cookie' => array(
                        'room_server' => $server->getId()
                    )
                )
            ),
            $client->getResponse()->getContent()
        );
        $crawler = $client->request('GET', $urlGenerator->generate('schedule_admin_new',array('id'=>$room->getId())));
        $buttonCrawlerNode = $crawler->selectButton('Speichern');
        $form = $buttonCrawlerNode->form();
        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '';
        $form['room[duration]'] = "60";
        $client->submit($form);
        $this->assertJsonStringEqualsJsonString(json_encode(array('error' => true, 'messages' => array( 'Fehler, der Name darf nicht leer sein'))), $client->getResponse()->getContent());

        $form['room[server]'] = $server->getId();
        $form['room[name]'] = '765456654456';
        $form['room[duration]'] = "60";
        $client->submit($form);
        $room = $roomRepo->findOneBy(array('name' => '198273987321'));
        $this->assertNull($room);
        $room = $roomRepo->findOneBy(array('name' => '765456654456'));
        $this->assertNotNull($room);
        $modalUrl = base64_encode($urlGenerator->generate('schedule_admin', array('id' => $room->getId())));
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                array(
                    'error' => false,
                    'redirectUrl' => $urlGenerator->generate('dashboard',array('snack'=>'Terminplanung erfolgreich bearbeitet','modalUrl'=>$modalUrl)),
                    'cookie' => array(
                        'room_server' => $server->getId()
                    )
                )
            ),
            $client->getResponse()->getContent()
        );
    }

}
