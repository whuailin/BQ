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

class Item extends Entity
{
    public $isStatic = false;
    public $isFromChest = false;
    public $blinkTimeout = 0;
    public $despawnTimeout = 0;
    public $respawnCallback = null;
    public function __construct($id, $kind, $x, $y)
    {
        parent::__construct($id, 'item', $kind, $x, $y);
    }
    
    public function handleDespawn($params)
    {

    }
    
    public function destroy()
    {

    }
    
    public function scheduleRespawn($delay)
    {

    }
    
    public function onRespawn($callback)
    {
        $this->respawnCallback = $callback;
    }
}
