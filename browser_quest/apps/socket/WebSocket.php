<?php

namespace socket;

use ctrl\Map;
use Entity\Player;
use ZPHP\Socket\Callback\SwooleWebSocket as ZSwooleWebSocket;
use ZPHP\Core\Config as ZConfig;

require_once dirname(__DIR__) . '/Common/Constants.php';

class WebSocket extends ZSwooleWebSocket
{
    public $map = [];

    public $mobs = [];
    public $mobAreas = [];

    public $players = [];
    public function onStart(){
        //parent::onStart($this->serv);
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

        $ip = ZConfig::getField('socket', 'host');
        $port = ZConfig::getField('socket', 'port');
        echo 'server start:'. $ip . ':' . $port . PHP_EOL;
    }

    public function onOpen($server, $request)
    {
        $this->log($request->fd . "connect");

        $player = new Player($request->fd, $server);

        $this->players[$request->fd] = $player;
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
        if($server->taskworker){
            echo "task worker init : ". $workerId.PHP_EOL;
        }else{
            echo "normal worker init : ". $workerId.PHP_EOL;
        }
        parent::onWorkerStart($server, $workerId);
    }

}