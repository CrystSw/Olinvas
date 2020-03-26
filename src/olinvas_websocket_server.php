<?php
require_once './config.php';

//=================================
//サーバプロセス
//---------------------------------
use \React\EventLoop\Factory;
use \React\Socket\Server;
use \React\Socket\SecureServer;
use \Ratchet\Server\IoServer;
use \Ratchet\Http\HttpServer;
use \Ratchet\WebSocket\WsServer;
use \Olinvas\OlinvasCore;
use \Olinvas\Logger;

//BAN時間再計算
define('__PARDON_TIME', PARDON_TIME*60);

//ロガーの初期化
$GLOBALS['logger'] = new Logger(LOG_TYPE, LOG_OUTPUT_DIRECTORY);
$GLOBALS['logger']->printLog(LOG_INFO, "###Olinvas websocket server has started.###");
$GLOBALS['logger']->printLog(LOG_INFO, "###Server Version: 1.1.1 (for Client Version: 1.1.1)###");
$GLOBALS['logger']->printLog(LOG_INFO, "###Protocol Version: 1.1###");

//サーバスタートアップ
$server = null;
$secureServer = null;
$listen = LISTEN_ADDRESS.':'.LISTEN_PORT;

$loop = Factory::create();
$wsServer = new WsServer(new OlinvasCore());
$app = new HttpServer($wsServer);
if(ENABLE_SSL){
	//SSLモード(WSSプロトコル)
	$secureServer = new Server($listen, $loop);
	$secureServer = new SecureServer($secureServer, $loop, [
		'local_cert' => SSL_CERT_PATH,
		'local_pk' => SSL_PRIV_KEY_PATH,
		'verify_peer' => false
	]);
	$server = new IoServer($app, $secureServer, $loop);
}else{
	//通常モード(WSプロトコル)
	$GLOBALS['logger']->printLog(LOG_WARNING, "This server is NOT ssl-mode.");
	$normalServer = new Server($listen, $loop);
	$server = new IoServer($app, $normalServer, $loop);
}

//サーバ開始
$GLOBALS['logger']->printLog(LOG_INFO, "Server is listening on {$listen}.");
$wsServer->enableKeepAlive($server->loop, 30);
$server->run();
//=================================
?>