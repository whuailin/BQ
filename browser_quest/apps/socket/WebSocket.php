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

    public function onStart(){
        swoole_set_process_name("BQ: master server process"); //master进程名称
        $params = func_get_args();
        /** @var \swoole_server $server */
        $server = $params[0];

        //parent::onStart($this->serv);
        $ip = ZConfig::getField('socket', 'host');
        $port = ZConfig::getField('socket', 'port');
        Debug::info("server master start ip[{$ip}] port[{$port}] pid[" . posix_getpid() . "]version " . SWOOLE_VERSION);

    }

    public function onOpen($server, $request)
    {
        $player = new Player($request->fd, $server, $this->worderServer);
        $this->worderServer->players[$request->fd] = $player;
    }

    public function onClose()
    {
        list($server, $fd, $fromId) = func_get_args();
        Debug::info("{$fd} close" . PHP_EOL);
    }

    public function onRequest($request, $response)
    {

    }

    public function onMessage($server, $frame)
    {
        $this->worderServer->players[$frame->fd]->onClientMessage($frame->data);
    }

    public function onTask($server, $taskId, $fromId, $data)
    {

    }

    public function onWorkerStart($server, $workerId)
    {

        //初始化相关数据
        if($server->taskworker){
            Debug::info("task worker init : ". $workerId);
        }else{
            $worldServer = new WorldServer('world_1', 9999, $workerId);
            $worldServer->run('Maps/world_server.json');
            $this->worderServer = $worldServer;
            //$this->initData();
            Debug::info("normal worker init : ". $workerId);
        }
        parent::onWorkerStart($server, $workerId);
    }
}