<?php

namespace App\Tests;

use App\Entity\LobbyWaitungUser;
use App\Repository\LobbyWaitungUserRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Service\Lobby\LobbyUtils;
use App\Service\RoomGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LobbyUtilsTest extends KernelTestCase
{
    public function testSomething(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());
        $roomGen = self::getContainer()->get(RoomGeneratorService::class);
        $userRepo = self::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('username'=>'test@local.de'));
        $user2 = $userRepo->findOneBy(array('username'=>'test@local2.de'));
        $serverRepo = self::getContainer()->get(ServerRepository::class);
        $server = $serverRepo->findOneBy(array('url'=>'meet.jit.si'));
        $room = $roomGen->createRoom($user,$server);
        $room->setName('kjdshfhds');
        $lobbUtils = self::getContainer()->get(LobbyUtils::class);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lobbyWaitingUSerRepo = self::getContainer()->get(LobbyWaitungUserRepository::class);
        $lobbyWaitinguser = new LobbyWaitungUser();
        $lobbyWaitinguser->setUser($user2)->setRoom($room)->setShowName('test 123')->setCreatedAt(new \DateTime())->setUid('lkjsdjfjdskjf')->setType('a');
        $em->persist($room);
        $em->persist($lobbyWaitinguser);
        $em->flush();
        self::assertEquals(1,sizeof($lobbyWaitingUSerRepo->findBy(array('room'=>$room))));
        $lobbUtils->cleanLobby($room);
        self::assertEquals(0,sizeof($lobbyWaitingUSerRepo->findBy(array('room'=>$room))));
        $this->assertSame('test', $kernel->getEnvironment());
        //$routerService = static::getContainer()->get('router');
        //$myCustomService = static::getContainer()->get(CustomService::class);
    }
}
