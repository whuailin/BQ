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
namespace Messages;

class Population
{
    public $world = 0;
    public $total = 0;
    public function __construct($world, $total)
    {
        $this->world = $world;
        $this->total = $total;
    }
    
    public function serialize()
    {
        return array(TYPES_MESSAGES_POPULATION, 
                $this->world,
                $this->total, 
        );
    }
}
