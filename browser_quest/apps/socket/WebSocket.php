<?php

namespace socket;

use Common\DataCenter;
use Common\Utils;
use Entity\Chest;
use Entity\Item;
use Entity\Player;
use Map\Map;
use ZPHP\Common\Debug;
use ZPHP\Socket\Callback\SwooleWebSocket as ZSwooleWebSocket;
use ZPHP\Core\Config as ZConfig;

require_once dirname(__DIR__) . '/Common/Constants.php';

class WebSocket extends ZSwooleWebSocket
{
    public $worderServer;
    public $id;
    public $map;

    public $mobs = [];
    public $mobAreas = [];

    public $players = [];
    public $playerCount;
    public $entities = [];
    public $outgoingQueues = [];
    public $groups = [];

    public $enterCallback;
    public function __construct()
    {
        $self = $this;

//        $this->onPlayerEnter(
//        /**
//         * @param Player $player
//         */
//            function($player) use ($self)
//            {
//                echo $player->name . " has joined ". $self->id."\n";
//
//                if(!$player->hasEnteredGame)
//                {
//                    $self->incrementPlayerCount();
//                }
//
//                // Number of players in this world
//                $self->pushToPlayer($player, new Messages\Population($self->playerCount, 1));
//                $self->pushRelevantEntityListTo($player);
//
//                $moveCallback = function($x, $y) use($player, $self)
//                {
//                    echo $player->name . " is moving to (" . $x . ", " . $y . ")\n";
//
//                    $player->forEachAttacker(function($mob) use($player, $self)
//                    {
//                        $target = $self->getEntityById($mob->target);
//                        if($target)
//                        {
//                            $pos = $self->findPositionNextTo($mob, $target);
//                            if($mob->distanceToSpawningPoint($pos['x'], $pos['y']) > 50)
//                            {
//                                $mob->clearTarget();
//                                $mob->forgetEveryone();
//                                $player->removeAttacker($mob);
//                            }
//                            else
//                            {
//                                $self->moveEntity($mob, $pos['x'], $pos['y']);
//                            }
//                        }
//                    });
//                };
//
//                $player->onMove($moveCallback);
//                $player->onLootMove($moveCallback);
//
//                $player->onZone(function() use($self, $player)
//                {
//                    $hasChangedGroups = $self->handleEntityGroupMembership($player);
//
//                    if($hasChangedGroups)
//                    {
//                        $self->pushToPreviousGroups($player, new Messages\Destroy($player));
//                        $self->pushRelevantEntityListTo($player);
//                    }
//                });
//
//                $player->onBroadcast(function($message, $ignoreSelf) use($self, $player)
//                {
//                    $self->pushToAdjacentGroups($player->group, $message, $ignoreSelf ? $player->id : null);
//                });
//
//                $player->onBroadcastToZone(function($message, $ignoreSelf) use($self, $player)
//                {
//                    $self->pushToGroup($player->group, $message, $ignoreSelf ? $player->id : null);
//                });
//
//                $player->onExit(function() use($self, $player)
//                {
//                    echo $player->name . " has left the game.\n";
//                    $self->removePlayer($player);
//                    $self->decrementPlayerCount();
//
//                    if(isset($self->removedCallback))
//                    {
//                        call_user_func($self->removedCallback);
//                    }
//                });
//
//                if(isset($self->addedCallback))
//                {
//                    call_user_func($self->addedCallback);
//                }
//            }
//        );
    }

    public function onStart(){
        swoole_set_process_name("BQ: master server process"); //master进程名称
        $params = func_get_args();
        /** @var \swoole_server $server */
        $server = $params[0];

        //parent::onStart($this->serv);
        $ip = ZConfig::getField('socket', 'host');
        $port = ZConfig::getField('socket', 'port');
        Debug::info("server master start ip[{$ip}] port[{$port}] pid[" . posix_getpid() . "]version " . SWOOLE_VERSION . "... \n");

    }

    public function onOpen($server, $request)
    {
        $player = new Player($request->fd, $server, $this);
        $this->players[$request->fd] = $player;
    }

    public function addPlayer($player)
    {
        $this->addEntity($player);
        $this->players[$player->id] = $player;
        $this->outgoingQueues[$player->id] = array();
    }

    public function addEntity($entity)
    {
        $this->entities[$entity->id] = $entity;
        $this->handleEntityGroupMembership($entity);
    }

    public function handleEntityGroupMembership($entity)
    {
        $hasChangedGroups = false;
        if($entity)
        {
            $groupId = $this->map->getGroupIdFromPosition($entity->x, $entity->y);
            if(empty($entity->group) || ($entity->group && $entity->group != $groupId))
            {
                $hasChangedGroups = true;
                $this->addAsIncomingToGroup($entity, $groupId);
                $oldGroups = $this->removeFromGroups($entity);
                $newGroups = $this->addToGroup($entity, $groupId);

                if(count($oldGroups) > 0)
                {
                    $entity->recentlyLeftGroups = array_diff($oldGroups, $newGroups);
                    //echo "group diff: " . json_encode($entity->recentlyLeftGroups);
                }
            }
        }
        return $hasChangedGroups;
    }

    public function removeFromGroups($entity)
    {
        $self = $this;
        $oldGroups = array();

        if($entity && isset($entity->group))
        {
            $group = $this->groups[$entity->group];
            if($entity instanceof Player)
            {
                $group->players = Utils::reject($group->players, function($id) use($entity) { return $id == $entity->id; });
            }

            $this->map->forEachAdjacentGroup($entity->group, function($id) use ($entity, &$oldGroups, $self)
            {
                if(isset($self->groups[$id]->entities[$entity->id]))
                {
                    unset($self->groups[$id]->entities[$entity->id]);
                    $oldGroups[] = $id;
                }
            });
            $entity->group = null;
        }
        return $oldGroups;
    }

    public function addToGroup($entity, $groupId)
    {
        $self = $this;
        $newGroups = array();

        if($entity && $groupId && (isset($this->groups[$groupId])))
        {
            $this->map->forEachAdjacentGroup($groupId, function($id) use ($self, &$newGroups, $entity, $groupId)
            {
                $self->groups[$id]->entities[$entity->id] = $entity;
                $newGroups[] = $id;
            });
            $entity->group = $groupId;

            if($entity instanceof Player)
            {
                $self->groups[$groupId]->players[] = $entity->id;
            }
        }
        return $newGroups;
    }

    /**
     * Registers an entity as "incoming" into several groups, meaning that it just entered them.
     * All players inside these groups will receive a Spawn message when WorldServer.processGroups is called.
     */
    public function addAsIncomingToGroup($entity, $groupId)
    {
        $self = $this;
        $isChest = $entity && $entity instanceof Chest;
        $isItem = $entity && $entity instanceof Item;
        $isDroppedItem =  $entity && $isItem && !$entity->isStatic && !$entity->isFromChest;

        if($entity && $groupId)
        {
            $this->map->forEachAdjacentGroup($groupId, function($id) use ($self, $isChest, $isItem, $isDroppedItem, $entity)
            {
                $group = $self->groups[$id];
                if($group)
                {
                    if(!isset($group->entities[$entity->id])
                        //  Items dropped off of mobs are handled differently via DROP messages. See handleHurtEntity.
                        && (!$isItem || $isChest || ($isItem && !$isDroppedItem)))
                    {
                        $group->incoming[] = $entity;
                    }
                }
            });
        }
    }

    public function initZoneGroups()
    {
        $self = $this;
        $self->map->forEachGroup(function($id) use ($self)
        {
            $self->groups[$id] = (object)array('entities'=> array(),
                'players' => array(),
                'incoming'=> array()
            );
        });
        $this->zoneGroupsReady = true;
    }

    public function onClose()
    {
        list($server, $fd, $fromId) = func_get_args();
        $this->log("{$fd} close" . PHP_EOL);

    }

    public function onRequest($request, $response)
    {

    }

    public function onMessage($server, $frame)
    {
        $this->players[$frame->fd]->onClientMessage($frame->data);
    }

    public function onTask($server, $taskId, $fromId, $data)
    {

    }

    public function onWorkerStart($server, $workerId)
    {

        //初始化相关数据
        if($server->taskworker){
            Debug::info("task worker init : ". $workerId . PHP_EOL);
        }else{
            $world = new WorldServer('world_1', 9999, $workerId);
            $this->initData();
            Debug::info("normal worker init : ". $workerId . PHP_EOL);
        }
        parent::onWorkerStart($server, $workerId);
    }

    function initData(){
        $self = $this;
        $self->map = new Map('Maps/world_server.json');
        $self->map->ready(function() use ($self){
            $self->initZoneGroups();
            $self->map->generateCollisionGrid();

            // Populate all mob "roaming" areas
            foreach($self->map->mobAreas as $a)
            {
//                $area = new MobArea($a->id, $a->nb, $a->type, $a->x, $a->y, $a->width, $a->height, $self);
//                $area->spawnMobs();
//                // @todo bind
//                //$area->onEmpty($self->handleEmptyMobArea->bind($self, area));
//                $area->onEmpty(function() use ($self, $area){
//                    call_user_func(array($self, 'handleEmptyMobArea'), $area);
//                });
//                $self->mobAreas[] =  $area;
            }
//
//            // Create all chest areas
//            foreach($self->map->chestAreas as $a)
//            {
//                $area = new ChestArea($a->id, $a->x, $a->y, $a->w, $a->h, $a->tx, $a->ty, $a->i, $self);
//                $self->chestAreas[] = $area;
//                // @todo bind
//                $area->onEmpty(function()use($self, $area){
//                    call_user_func(array($self, 'handleEmptyChestArea'), $area);
//                });
//            }
//
//            // Spawn static chests
//            foreach($self->map->staticChests as $chest)
//            {
//                $c = $self->createChest($chest->x, $chest->y, $chest->i);
//                $self->addStaticItem($c);
//            }
//
            // Spawn static entities
//            $self->spawnStaticEntities();
//
//            // Set maximum number of entities contained in each chest area
//            foreach($self->chestAreas as $area)
//            {
//                $area->setNumberOfEntities(count($area->entities));
//            }
        });
        $self->map->initMap();
    }

    public function setPlayerCount($count)
    {
        $this->playerCount = $count;
    }

    public function incrementPlayerCount()
    {
        $this->setPlayerCount($this->playerCount + 1);
    }

    public function onPlayerEnter($callback) {
        $this->enterCallback = $callback;
    }
}