<?php

namespace socket;

use ctrl\Map;
use ctrl\MobArea;
use ctrl\Player;
use ZPHP\ZPHP;
use ZPHP\Protocol\Request;
use ZPHP\Protocol\Response;
use ZPHP\Socket\Callback\SwooleWebSocket as ZSwooleWebSocket;
use ZPHP\Core\Config as ZConfig;
use ZPHP\Core\Route as ZRoute;
use socket\TYPE_MESSAGE;

class WebSocket extends ZSwooleWebSocket
{
    public $map = [];

    public $mobs = [];
    public $mobAreas = [];

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

        $player = new Player($fd, $server);

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

        $data = json_decode($frame->data, true);
        var_dump($data);
        $type = $data[0];
        if ($type == TYPE_MESSAGE::HELLO) {
            $name = substr($data[1], 0, 30);

            $data = array(
                TYPE_MESSAGE::WELCOME,//type
                $frame->fd,//fd
                substr($data[1], 0, 30),//name
                80,  //x
                200,  //y
                20,//hitpoint
            );
            $this->sendMsg($server, $frame->fd, $data);

            $map_mobs_data = [
                TYPE_MESSAGE::SPAWN,//type

            ];
            $this->sendMsg($server, $frame->fd, $data);
        }

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
        parent::onWorkerStart($server, $workerId);
    }

    public function sendMsg($server, $fd, $array)
    {
        return $server->push($fd, json_encode($array));
    }

}