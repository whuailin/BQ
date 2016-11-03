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
namespace ctrl;

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
    
    public function __construct($fd, $serv)
    {
        $this->server = $serv;

        $this->server->push($this->fd, "go");

    }
    
    public function onClientMessage($connection, $data)
    {
        $message = json_decode($data, true);
        $action = $message[0];
        

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
