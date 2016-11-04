<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Entity;

use Common\Formulas;
use Common\Utils;
use socket\TYPE_MESSAGE;

class Player extends Character
{
    public $hasEnteredGame = false;
    public $isDead = false;
    public $haters = array();
    public $lastCheckpoint = array();
    public $disconnectTimeout = 0;
    public $armor = 0;
    public $armorLevel = 0;
    public $fd;
    public $server;
    public $weaponLevel = 0;
    public $name;

    public function __construct($fd, $serv)
    {
        $this->server = $serv;
        $this->fd = $fd;
        $this->server->push($this->fd, "go");

//        swoole_timer_tick(2000, function ($timer_id) {
//            echo "tick-2000ms $timer_id \n";
//        });

    }
    
    public function onClientMessage($data)
    {
        $data = json_decode($data, true);
        $action = $data[0];

        var_dump($data);

        switch ($action){
            case TYPES_MESSAGES_HELLO:
                $this->actionHello($data);
                break;
            default:
                echo 'TODO : action '.$action;
        }
    }

    function actionHello($message){
        $name = $message[1];
        $this->name = $name === "" ? "lorem ipsum" : $name;
        $this->kind = TYPES_ENTITIES_WARRIOR;
        $this->equipArmor($message[2]);
        $this->equipWeapon($message[3]);
        $this->orientation = Utils::randomOrientation();
        $this->updateHitPoints();
        $this->updatePosition();

        $message = array(
            TYPES_MESSAGES_WELCOME,//type
            $this->fd,//fd
            $name,//name
            12,  //x
            200,  //y
            20,//hitpoint
        );
        $this->sendMsg($message);
        echo 'send map data'.PHP_EOL;
        $map_mobs_data = [[2,"818209",45,18,209],[2,"815222",43,15,222],[2,"810235",42,10,235],[2,"12121",2,25,235,2],[2,"1221",2,47,223,3],[2,"1322",2,21,223,1],[2,"1020",2,15,214,3],[2,"1320",2,21,223,4],[2,"1022",2,14,214,4],[2,"1222",2,47,223,4],[2,"1021",2,12,214,2],[2,"12120",2,22,239,3],[2,"1220",2,47,222,4],[2,"1121",2,48,212,3],[2,"1321",2,6,229,3],[2,"929",61,19,233],[2,"11921",2,38,237,null],[2,"11920",2,43,232,4],[2,"927",61,34,210]];
        $this->sendMsg($map_mobs_data);
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
    }
    
    public function onLootMove($callback)
    {
    }
    
    public function onZone($callback) 
    {
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
