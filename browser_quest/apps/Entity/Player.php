<?php

namespace Entity;

use Common\FormatChecker;
use Common\Formulas;
use Common\Utils;
use socket\WorldServer;
use ZPHP\Common\Debug;

class Player extends Character
{
    public $hasEnteredGame = false;
    public $isDead = false;
    public $haters = array();
    public $lastCheckpoint = array();
    public $formatChecker;
    public $disconnectTimeout = 0;
    public $armor = 0;
    public $armorLevel = 0;
    public $fd;
    public $server;

    /**
     * @var WorldServer
     */
    public $worldServ;
    public $weaponLevel = 0;
    public $name;

    public $zoneCallback;
    public $moveCallback;

    public function __construct($fd, $serv, $worldServ)
    {
        $this->server = $serv;
        $this->worldServ = $worldServ;
        $this->fd = $fd;
        parent::__construct($fd, 'player', TYPES_ENTITIES_WARRIOR, 0, 0, '');

        $this->hasEnteredGame = false;
        $this->isDead = false;
        $this->haters = array();
        $this->lastCheckpoint = null;
        $this->formatChecker = new FormatChecker();
        $this->disconnectTimeout = 0;

        $this->server->push($this->fd, "go");

//        swoole_timer_tick(2000, function ($timer_id) {
//            echo "tick-2000ms $timer_id \n";
//        });

    }

    public function onClientMessage($data)
    {
        $data = json_decode($data, true);
        $action = $data[0];

        Debug::log($data);

        switch ($action) {
            case TYPES_MESSAGES_HELLO:
                $this->actionHello($data);
                break;
            case TYPES_MESSAGES_MOVE:
                if ($this->moveCallback) {
                    $x = $data[1];
                    $y = $data[2];

                    if ($this->worldServ->isValidPosition($x, $y)) {
                        $this->setPosition($x, $y);
                        $this->clearTarget();

                        $this->broadcast(new \Messages\Move($this));
                        call_user_func($this->moveCallback, $this->x, $this->y);
                    }
                }
                break;
            case TYPES_MESSAGES_WHO:
                array_shift($data);
                $this->worldServ->pushSpawnsToPlayer($this, $data);
                break;
            case TYPES_MESSAGES_ZONE:
                call_user_func($this->zoneCallback);
                break;
            case TYPES_MESSAGES_HIT:
                $mob = $this->worldServ->getEntityById($data[1]);
                if($mob)
                {
                    $dmg = Formulas::dmg($this->weaponLevel, $mob->armorLevel);

                    if($dmg > 0 && is_callable(array($mob, 'receiveDamage')))
                    {
                        $mob->receiveDamage($dmg, $this->id);
                        $this->worldServ->handleMobHate($mob->id, $this->id, $dmg);
                        $this->worldServ->handleHurtEntity($mob, $this, $dmg);
                    }
                }
                break;
            case TYPES_MESSAGES_HURT:
                $mob = $this->worldServ->getEntityById($data[1]);
                if ($mob && $this->hitPoints > 0) {
                    $this->hitPoints -= Formulas::dmg($mob->weaponLevel, $this->armorLevel);
                    $this->worldServ->handleHurtEntity($this);

                    if ($this->hitPoints <= 0) {
                        $this->isDead = true;
                        if (!empty($this->firepotionTimeout)) {
                            //Timer::del($this->firepotionTimeout);
                            //$this->firepotionTimeout = 0;
                        }
                    }
                }
                break;
            default:
                Debug::error("unimplemented ation:{$action}" . PHP_EOL);
        }
    }

    function actionHello($message)
    {
        $name = $message[1];
        $this->name = $name === "" ? "lorem ipsum" : $name;
        $this->kind = TYPES_ENTITIES_WARRIOR;
        $this->equipArmor($message[2]);
        $this->equipWeapon($message[3]);
        $this->orientation = Utils::randomOrientation();
        $this->updateHitPoints();
        $this->updatePosition();

        $this->x = 12;
        $this->y = 200;
        $this->worldServ->addPlayer($this);
        call_user_func($this->worldServ->enterCallback, $this);

        $message = array(
            TYPES_MESSAGES_WELCOME,//type
            $this->fd,//fd
            $name,//name
            $this->x,  //x
            $this->y,  //y
            $this->hitPoints,//hitpoint
        );
        $this->sendMsg($message);
        $this->hasEnteredGame = true;
        $this->isDead = false;

        //$map_mobs_data = [[2,"818209",45,18,209],[2,"815222",43,15,222],[2,"810235",42,10,235],[2,"12121",2,25,235,2],[2,"1221",2,47,223,3],[2,"1322",2,21,223,1],[2,"1020",2,15,214,3],[2,"1320",2,21,223,4],[2,"1022",2,14,214,4],[2,"1222",2,47,223,4],[2,"1021",2,12,214,2],[2,"12120",2,22,239,3],[2,"1220",2,47,222,4],[2,"1121",2,48,212,3],[2,"1321",2,6,229,3],[2,"929",61,19,233],[2,"11921",2,38,237,null],[2,"11920",2,43,232,4],[2,"927",61,34,210]];
        //$this->sendMsg($map_mobs_data);
    }

    public function sendMsg($array)
    {
        return $this->server->push($this->fd, json_encode($array));
    }

    public function onClientClose()
    {

    }

    public function firepotionTimeoutCallback()
    {

    }

    public function destroy()
    {

    }

    public function forEachHaterCallback($mob)
    {
    }

    public function getState()
    {

    }

    public function send($message)
    {
    }

    public function broadcast($message, $ignoreSelf = true)
    {

    }

    public function broadcastToZone($message, $ignoreSelf = true)
    {

    }

    public function onExit($callback)
    {
    }

    public function onMove($callback)
    {
        $this->moveCallback = $callback;
    }

    public function onLootMove($callback)
    {
    }

    public function onZone($callback)
    {
        $this->zoneCallback = $callback;
    }

    public function onOrient($callback)
    {
    }

    public function onMessage($callback)
    {
    }

    public function onBroadcast($callback)
    {
    }

    public function onBroadcastToZone($callback)
    {
    }

    public function equip($item)
    {
    }

    public function addHater($mob)
    {

    }

    public function removeHater($mob)
    {

    }

    public function forEachHater($callback)
    {

    }

    public function equipArmor($kind)
    {
    }

    public function equipWeapon($kind)
    {
    }

    public function equipItem($item)
    {

    }

    public function updateHitPoints()
    {
        $this->resetHitPoints(Formulas::hp($this->armorLevel));
    }

    public function updatePosition()
    {

    }

    public function onRequestPosition($callback)
    {
    }

    public function resetTimeout()
    {
    }

    public function timeout()
    {
    }
}
