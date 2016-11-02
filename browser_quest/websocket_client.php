<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/2
 * Time: 23:04
 */
$host = '0.0.0.0';
$port = 8992;

$client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC); //异步非阻塞

$client->on("connect", function($cli) {
    $cli->send("hello world\n");
});

$client->on("receive", function($cli, $data = ""){
    var_dump($data);
/*    $data = $cli->recv(); //1.6.10+ 不需要
    if(empty($data)){
        $cli->close();
        echo "closed\n";
    } else {
        echo "received: $data\n";
        sleep(1);
        $cli->send("hello\n");
    }*/
});

$client->on("close", function($cli){
    $cli->close(); // 1.6.10+ 不需要
    echo "close\n";
});

$client->on("error", function($cli){
    exit("error\n");
});

$client->connect($host, $port, 0.5);