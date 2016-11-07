<?php

namespace socket;

use Entity\Chest;
use Entity\Item;
use Entity\Player;
use Map\Map;
use ZPHP\Socket\Callback\SwooleWebSocket as ZSwooleWebSocket;
use ZPHP\Core\Config as ZConfig;

require_once dirname(__DIR__) . '/Common/Constants.php';

class WebSocket extends ZSwooleWebSocket
{
    public $map;

    public $mobs = [];
    public $mobAreas = [];

    public $players = [];
    public $entities = [];
    public $outgoingQueues = [];
    public $groups = [];

    public function onStart(){
        //parent::onStart($this->serv);
        $ip = ZConfig::getField('socket', 'host');
        $port = ZConfig::getField('socket', 'port');
        echo 'server start:'. $ip . ':' . $port . PHP_EOL;
    }

    public function onOpen($server, $request)
    {

        $this->log($request->fd . "connect");

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


    public function sendAll($server, $data)
    {

    }


    public function log($msg)
    {
        if (!ZConfig::getField('socket', 'daemonize', 0)) {
            echo $msg . PHP_EOL;
        }
    }

    public function onWorkerStart($server, $workerId)
    {
        $self = $this;
        $this->map = new Map('Maps/world_server.json');
        $this->map->ready(function() use ($self){
            // Populate all mob "roaming" areas
            foreach($this->map->mobAreas as $a)
            {
                //var_dump($a);
//                $area = new MobArea($a->id, $a->nb, $a->type, $a->x, $a->y, $a->width, $a->height, $this);
//                $area->spawnMobs();
//                // @todo bind
//                //$area->onEmpty($self->handleEmptyMobArea->bind($self, area));
////                $area->onEmpty(function() use ($self, $area){
////                    call_user_func(array($self, 'handleEmptyMobArea'), $area);
////                });
//                $this->mobAreas[] =  $area;
            }
        });
        $this->map->initMap();

        if($server->taskworker){
            echo "task worker init : ". $workerId.PHP_EOL;
        }else{
            echo "normal worker init : ". $workerId.PHP_EOL;
        }
        parent::onWorkerStart($server, $workerId);
    }

}