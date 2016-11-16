<?php

namespace Entity;

use Common\FormatChecker;
use Common\Formulas;
use Common\Properties;
use Common\Types;
use Common\Utils;
use socket\WorldServer;
use ZPHP\Common\Debug;

class Player extends Character
{
    public $hasEnteredGame = false;
    public $isDead = false;
    public $haters = array();
    public $lastCheckpoint = array();
    /**
     * @var FormatChecker
     */
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
    public $weapon;

    public $zoneCallback;
    public $moveCallback;
    public $broadcastzoneCallback;
    public $lootmoveCallback;
    public $broadcastCallback;
    public $exitCallback;
    public $orientCallback;
    public $messageCallback;
    public $requestposCallback;

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
            case TYPES_MESSAGES_WHO:
                array_shift($data);
                $this->worldServ->pushSpawnsToPlayer($this, $data);
                break;
            case TYPES_MESSAGES_ZONE:
                call_user_func($this->zoneCallback);
                break;
            case TYPES_MESSAGES_CHAT:
                $msg = trim($data[1]);

                // Sanitized messages may become empty. No need to broadcast empty chat messages.
                if($msg) {
                    $this->broadcastToZone(new \Messages\Chat($this, $msg), false);
                }
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
            case TYPES_MESSAGES_LOOTMOVE:
                if($this->lootmoveCallback)
                {
                    $this->setPosition($data[1], $data[2]);

                    $item = $this->worldServ->getEntityById($data[3]);
                    if($item)
                    {
                        $this->clearTarget();
                        $this->broadcast(new \Messages\LootMove($this, $item));
                        call_user_func($this->lootmoveCallback, $this->x, $this->y);
                    }
                }
                break;
            case TYPES_MESSAGES_AGGRO:
                if($this->moveCallback)
                {
                    $this->worldServ->handleMobHate($data[1], $this->id, 5);
                }
                break;
            case TYPES_MESSAGES_ATTACK:
                $mob = $this->worldServ->getEntityById($data[1]);
                if($mob)
                {
                    $this->setTarget($mob);
                    $this->worldServ->broadcastAttacker($this);
                }
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
                            swoole_timer_clear($this->firepotionTimeout);
                            $this->firepotionTimeout = 0;
                        }
                    }
                }
                break;
            case TYPES_MESSAGES_LOOT:
                $item = $this->worldServ->getEntityById($data[1]);

                if($item)
                {
                    $kind = $item->kind;

                    if(Types::isItem($kind))
                    {
                        $this->broadcast($item->despawn());
                        $this->worldServ->removeEntity($item);

                        if($kind == TYPES_ENTITIES_FIREPOTION)
                        {
                            $this->updateHitPoints();
                            $this->broadcast($this->equip(TYPES_ENTITIES_FIREFOX));
                            //$this->firepotionTimeout = Timer::add(15, array($this, 'firepotionTimeoutCallback'), array(), false);
                            $this->firepotionTimeout = swoole_timer_after(15 * 1000, function(){
                                Debug::log('timeout:'.'firepotionTimeout');
                                call_user_func(array($this, 'firepotionTimeoutCallback'));
                            });
                            $hitpoints = new \Messages\HitPoints($this->maxHitPoints);
                            $data = $hitpoints->serialize();
                            $this->send(json_encode($data));
                        }
                        else if(Types::isHealingItem($kind))
                        {
                            $amount = 0;
                            switch($kind)
                            {
                                case TYPES_ENTITIES_FLASK:
                                    $amount = 40;
                                    break;
                                case TYPES_ENTITIES_BURGER:
                                    $amount = 100;
                                    break;
                            }

                            if(!$this->hasFullHealth())
                            {
                                $this->regenHealthBy($amount);
                                $this->worldServ->pushToPlayer($this, $this->health());
                            }
                        }
                        else if(Types::isArmor($kind) || Types::isWeapon($kind))
                        {
                            $this->equipItem($item);
                            $this->broadcast($this->equip($kind));
                        }
                    }
                }
                break;
            case TYPES_MESSAGES_TELEPORT:
                $x = $data[1];
                $y = $data[2];

                if($this->worldServ->isValidPosition($x, $y))
                {
                    $this->setPosition($x, $y);
                    $this->clearTarget();

                    $this->broadcast(new \Messages\Teleport($this));

                    $this->worldServ->handlePlayerVanish($this);
                    $this->worldServ->pushRelevantEntityListTo($this);
                }
                break;
            case TYPES_MESSAGES_OPEN:
                $chest = $this->worldServ->getEntityById($data[1]);
                if($chest && $chest instanceof Chest)
                {
                    $this->worldServ->handleOpenedChest($chest, $this);
                }
                break;
            case TYPES_MESSAGES_CHECK:
                $checkpoint = $this->worldServ->map->getCheckpoint($data[1]);
                if($checkpoint)
                {
                    $this->lastCheckpoint = $checkpoint;
                }
                break;
            default:
                Debug::error("unimplemented ation:{$action}" . PHP_EOL);
//                if(isset($this->messageCallback))
//                {
//                    call_user_func($this->messageCallback, $data);
//                }
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
    }

    public function sendMsg($array)
    {
        return $this->server->push($this->fd, json_encode($array));
    }

    public function onClientClose()
    {
        if(!empty($this->firepotionTimeout))
        {
            //Timer::del($this->firepotionTimeout);
            swoole_timer_clear($this->firepotionTimeout);
            $this->firepotionTimeout = 0;
        }
        //Timer::del($this->disconnectTimeout);
        swoole_timer_clear($this->disconnectTimeout);
        $this->disconnectTimeout = 0;
        if(isset($this->exitCallback))
        {
            call_user_func($this->exitCallback);
        }
    }

    public function firepotionTimeoutCallback()
    {
        $this->broadcast($this->equip($this->armor)); // return to normal after 15 sec
        $this->firepotionTimeout = 0;
    }

    public function destroy()
    {
        $this->forEachAttacker(function($mob){
            $mob->clearTarget();
        });
        $this->attackers = array();

        $this->forEachHater(array($this, 'forEachHaterCallback'));
        $this->haters = array();
    }

    /**
     * @param $mob Mob
     */
    public function forEachHaterCallback($mob)
    {
        $mob->forgetPlayer($this->id);
    }

    public function getState()
    {
        $basestate = $this->_getBaseState();
        $state = array($this->name, $this->orientation, $this->armor, $this->weapon);

        if($this->target)
        {
            $state[] =$this->target;
        }
        return array_merge($basestate, $state);
    }

    public function send($message)
    {
        //$this->connection->send($message);
        $this->server->push($this->fd, $message);
    }

    public function broadcast($message, $ignoreSelf = true)
    {
        if($this->broadcastCallback)
        {
            call_user_func($this->broadcastCallback, $message, $ignoreSelf);
        }
    }

    public function broadcastToZone($message, $ignoreSelf = true)
    {
        if($this->broadcastzoneCallback)
        {
            call_user_func($this->broadcastzoneCallback, $message, $ignoreSelf);
        }
    }

    public function onExit($callback)
    {
        $this->exitCallback = $callback;
    }

    public function onMove($callback)
    {
        $this->moveCallback = $callback;
    }

    public function onLootMove($callback)
    {
        $this->lootmoveCallback = $callback;
    }

    public function onZone($callback)
    {
        $this->zoneCallback = $callback;
    }

    public function onOrient($callback)
    {
        $this->orientCallback = $callback;
    }

    public function onMessage($callback)
    {
        $this->messageCallback = $callback;
    }

    public function onBroadcast($callback)
    {
        $this->broadcastCallback = $callback;
    }

    public function onBroadcastToZone($callback)
    {
        $this->broadcastzoneCallback = $callback;
    }

    public function equip($item)
    {
        return new \Messages\EquipItem($this, $item);
    }

    public function addHater($mob)
    {
        if($mob) {
            if(!(isset($this->haters[$mob->id])))
            {
                $this->haters[$mob->id] = $mob;
            }
        }
    }

    public function removeHater($mob)
    {
        if($mob)
        {
            unset($this->haters[$mob->id]);
        }
    }

    public function forEachHater($callback)
    {
        array_walk($this->haters, function($mob) use ($callback)
        {
            call_user_func($callback, $mob);
        });
    }

    public function equipArmor($kind)
    {
        $this->armor = $kind;
        $this->armorLevel = Properties::getArmorLevel($kind);
    }

    public function equipWeapon($kind)
    {
        $this->weapon = $kind;
        $this->weaponLevel = Properties::getWeaponLevel($kind);
    }

    public function equipItem($item)
    {
        if($item) {
            if(Types::isArmor($item->kind))
            {
                $this->equipArmor($item->kind);
                $this->updateHitPoints();
                $obj = new \Messages\HitPoints($this->maxHitPoints);
                $data = $obj->serialize();
                $this->send(json_encode($data));
            }
            else if(Types::isWeapon($item->kind))
            {
                $this->equipWeapon($item->kind);
            }
        }
    }

    public function updateHitPoints()
    {
        $this->resetHitPoints(Formulas::hp($this->armorLevel));
    }

    public function updatePosition()
    {
        if($this->requestposCallback)
        {
            $pos = call_user_func($this->requestposCallback);
            $this->setPosition($pos['x'], $pos['y']);
        }
    }

    public function onRequestPosition($callback)
    {
        $this->requestposCallback = $callback;
    }

    public function resetTimeout()
    {
        Debug::error('TODO : player->resetTimeout');
        //Timer::del($this->disconnectTimeout);
        swoole_timer_clear($this->disconnectTimeout);
        // 15分钟
        //$this->disconnectTimeout = Timer::add(15*60, array($this, 'timeout'), false);
        $this->disconnectTimeout = swoole_timer_after(15 * 60 * 1000, function(){
            Debug::log('timeout:'.'disconnectTimeout');
            call_user_func(array($this, 'timeout'));
        });
    }

    public function timeout()
    {
        Debug::error('TODO : player->timeout');
        //$this->connection->send('timeout');
        //$this->connection->close('Player was idle for too long');
    }
}
