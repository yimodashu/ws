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
use \Workerman\Worker;
use \GatewayWorker\Gateway;
use \Workerman\Autoloader;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);

// gateway 进程
$gateway = new Gateway("Websocket://0.0.0.0:8080");
// 设置名称，方便status时查看
$gateway->name = 'ChatGateway';
// 设置进程数，gateway进程数建议与cpu核数相同
$gateway->count = 1;
// 分布式部署时请设置成内网ip（非127.0.0.1）
$gateway->lanIp = '127.0.0.1';
// 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
// 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 
$gateway->startPort = 2300;
// 心跳检测
$gateway->pingInterval = 29;
$gateway->pingNotResponseLimit = 2;
$gateway->pingData = '';
// 服务注册地址
$gateway->registerAddress = '127.0.0.1:1258';

/*
// 当客户端连接上来时，设置连接的onWebSocketConnect，即在websocket握手时的回调
// onWebSocketConnect 里面$_GET $_SERVER是可用的
$gateway->onConnect = function($connection) {
    $connection->onWebSocketConnect = function($connection , $http_header) {
        if($_SERVER['HTTP_ORIGIN'] != 'http://chat.workerman.net') {
            $connection->close();
        }
    };
};
*/

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

