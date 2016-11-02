<?php
use ZPHP\ZPHP;
$rootPath = dirname(__DIR__);
require '/home/vagrant/Code/zphp'. DIRECTORY_SEPARATOR . 'ZPHP' . DIRECTORY_SEPARATOR . 'ZPHP.php';
ZPHP::run($rootPath);