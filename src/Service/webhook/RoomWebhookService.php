<?php

namespace App\Service\webhook;

use App\Entity\CallerSession;
use App\Entity\Rooms;
use App\Entity\RoomStatus;
use App\Entity\RoomStatusParticipant;
use App\Service\Lobby\LobbyUtils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RoomWebhookService
{
    private $em;
    private $logger;
    private $paramterBag;
    private LobbyUtils $lobbyUtils;

    public function __construct(LobbyUtils $lobbyUtils, EntityManagerInterface $entityManager, LoggerInterface $logger, ParameterBagInterface $parameterBag)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->paramterBag = $parameterBag;
        $this->lobbyUtils = $lobbyUtils;
    }

    public function startWebhook($data): ?string
    {
        $res = 'No event defined';;
        if (isset($data['event_name'])) {
            switch ($data['event_name']) {
                case 'muc-room-created':
                    $res = $this->roomCreated($data);
                    break;
                case 'muc-room-destroyed':
                    $res = $this->roomDestroyed($data);
                    break;
                case 'muc-occupant-joined':
                    $res = $this->roomParticipantJoin($data);
                    break;
                case 'muc-occupant-left':
                    $res = $this->roomParticipantLeft($data);
                    break;
                default:
                    $this->logger->error('Wrong event_name', array('event_name' => $data['event_name']));
                    $res = 'Wrong event_name';
            }
        }
        return $res;
    }


    public function roomCreated($data): ?string
    {
        try {
            if ($data['event_name'] !== "muc-room-created") {
                $text = 'Wrong event_name';
                $this->logger->error($text, array('roomId' => $data['event_name']));
                return $text;
            }
            try {
                $room = $this->em->getRepository(Rooms::class)->findRoomByCaseInsensitiveUid($data['room_name']);
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }

            if (!$room) {
                $text = 'Room name not found';
                $this->logger->error($text, array('roomId' => $data['room_name']));
                return $text;
            }

            $roomStatus = $this->em->getRepository(RoomStatus::class)->findCreatedRooms($room);

            if ($roomStatus) {
                $text = 'Room already created';
                $this->logger->error($text, array('roomJidID' => $data['room_jid']));
                return $text;
            }

            if (!$roomStatus) {
                $roomStatus = new RoomStatus();
                $roomStatus->setCreatedAt(new \DateTime())
                    ->setJitsiRoomId($data['room_jid'])
                    ->setRoom($room);
            }

            $roomStatus->setRoomCreatedAt(\DateTime::createFromFormat('U', $data['created_at']))
                ->setUpdatedAt(new \DateTime())
                ->setCreated(true);
            $this->em->persist($roomStatus);
            $this->em->flush();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return 'ERROR';
        }
        return null;
    }

    public function roomDestroyed($data): ?string
    {
        try {
            if ($data['event_name'] !== "muc-room-destroyed") {
                $text = 'Wrong event_name';
                $this->logger->error($text, array('roomId' => $data['event_name']));
                return $text;
            }

            $roomStatus = $this->em->getRepository(RoomStatus::class)->findCreatedRoomsbyJitsiId($data['room_jid']);
            if (!$roomStatus) {
                $text = 'Room Jitsi ID not found';
                $this->logger->error($text, array('jitsiID' => $data['room_jid']));
                return $text;
            }
            if ($this->paramterBag->get('JITSI_EVENTS_HISTORY') == 0) {
                $statusOld = $this->em->getRepository(RoomStatus::class)->findBy(array('jitsiRoomId' => $data['room_jid']));
                foreach ($statusOld as $data) {
                    $this->em->remove($data);
                    $this->em->flush();
                }
                return null;
            }
            $roomStatus->setDestroyedAt(\DateTime::createFromFormat('U', $data['destroyed_at']))
                ->setUpdatedAt(new \DateTime())
                ->setDestroyed(true);
            $this->em->persist($roomStatus);
            $this->em->flush();
            $this->lobbyUtils->cleanLobby($roomStatus->getRoom());
            foreach ($roomStatus->getRoomStatusParticipants() as $data2) {
                $data2->setLeftRoomAt(\DateTime::createFromFormat('U', $data['destroyed_at']))
                    ->setInRoom(false);
                $this->em->persist($data2);

            }
            $this->em->flush();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return $exception->getMessage();
        }
        return null;
    }

    public function roomParticipantJoin($data): ?string
    {
        try {
            if ($data['event_name'] !== "muc-occupant-joined") {
                $text = 'Wrong event_name';
                $this->logger->error($text, array('roomId' => $data['event_name']));
                return $text;
            }
            $roomStatus = $this->em->getRepository(RoomStatus::class)->findCreatedRoomsbyJitsiId($data['room_jid']);
            if (!$roomStatus) {
                $text = 'Room Jitsi ID not found';
                $this->logger->error($text, array('jitsiID' => $data['room_jid']));
                return $text;
            }

            $roomPart = $this->em->getRepository(RoomStatusParticipant::class)->findOneBy(array('participantId' => $data['occupant']['occupant_jid']));
            if ($roomPart) {
                $text = 'The occupant already joind with the same occupant ID';
                $this->logger->error($text, array('occupantID' => $data['occupant']['occupant_jid']));
                return $text;
            }
            if (!isset($data['occupant']['name'])){
                return 'NO_DATA';
            }
            $roomPart = new RoomStatusParticipant();
            $roomPart->setEnteredRoomAt(\DateTime::createFromFormat('U', $data['occupant']['joined_at']))
                ->setInRoom(true)
                ->setParticipantId($data['occupant']['occupant_jid'])
                ->setParticipantName($data['occupant']['name'] ?? 'No Data')
                ->setRoomStatus($roomStatus);
            $this->em->persist($roomPart);
            $this->em->flush();
            $roomStatus->addRoomStatusParticipant($roomPart);
            $this->em->persist($roomStatus);
            $this->em->flush();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return 'ERROR';
        }
        return null;
    }

    public function roomParticipantLeft(array $data): ?string
    {
        try {
            if ($data['event_name'] !== "muc-occupant-left") {
                $text = 'Wrong event_name';
                $this->logger->error($text, array('roomId' => $data['event_name']));
                return $text;
            }

            $roomPart = $this->em->getRepository(RoomStatusParticipant::class)->findOneBy(array('participantId' => $data['occupant']['occupant_jid']));
            if (!$roomPart) {
                $text = 'Wrong occupant ID. The occupant is not in the database';
                $this->logger->error($text, array('occupantID' => $data['occupant']['occupant_jid']));
                return $text;
            }
            if ($roomPart->getInRoom() !== true) {
                $text = 'The occupant already left the room. It cannot left the room twice';
                $this->logger->error($text, array('occupantID' => $data['occupant']['occupant_jid']));
                return $text;
            }
            $roomPart->setLeftRoomAt(\DateTime::createFromFormat('U', $data['occupant']['left_at']))
                ->setInRoom(false)
                ->setDominantSpeakerTime($data['occupant']['total_dominant_speaker_time'] ?? null);
            $this->em->persist($roomPart);
            $this->em->flush();

        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return 'ERROR';
        }
        return null;
    }
}