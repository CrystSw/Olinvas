<?php
//=================================
//コンフィグ
//(環境に応じて書き換えてください)
//---------------------------------
//[Core File]
// Composerのインストールディレクトリ
// ('autoload.php'のパスを設定してください)
require_once '';
// Olinvasのディレクトリ
// ('OlinvasLoadManager.php'のパスを設定してください)
require_once __DIR__.'/corefiles/OlinvasLoadManager.php';

//[Listen]
// ListenするIPv4アドレス
// (default: 0.0.0.0)
define('LISTEN_ADDRESS', '0.0.0.0');
// Listenするポート番号
// (変更した場合，/webroot/js/olinvas.js内の変数"serverPort"の値を同じものに変更してください)
// (default: 13181)
define('LISTEN_PORT', 13181);

//[一般]
// 同一IPアドレスからの最大同時接続数
// (default: 5)
define('MAX_SESSION_NUM_SAME_IP', 5);
// 同一IPアドレスによる最大作成可能ルーム数
// (default: 3)
define('MAX_HOST_ROOM_NUM_SAME_IP', 3);
// 各ルームの最大接続可能人数
// (default: 50)
define('MAX_ROOM_MEMBER_NUM', 50);
// 同時ホスト可能な最大ルーム数
// (default: 50)
define('MAX_ROOM_NUM', 50);
// 空パスワードのルームの作成を許可するか？
// (default: false)
define('ENABLE_NO_PASSWORD_ROOM', false);
// Historyの最大サイズ
// 値を増やすほど，チェックポイント要求の回数が増えるため，ホストのコンピュータの負荷が増えます．
// 値を減らすほど，サーバで管理するHistoryの量が増えるため，より多くのメモリが必要になります．また，ルーム参加時に参加者に送信されるパケット数がより多くなります．
// (default: 2500)
define('MAX_HISTORY_NUM', 2500);

//[Security]
// 無効パケットを送信してきたクライアントを自動的に接続拒否するか？
// (default: true) 
define('AUTO_IP_BAN', true);
// 許容する無効パケット数
// (AUTO_IP_BANがfalseの場合，このオプションは無視されます)
// (累積した無効パケット数がこの値を超えたクライアントは，自動的に接続拒否します．(既存のセッションも全て停止します．))
// (default: 100)
define('ALLOW_INVALID_PACKET_NUM', 100);
// 接続拒否されたクライアントが回復するまでの時間(分)
// (AUTO_IP_BANがfalseの場合，このオプションは無視されます)
// (default: 30)
define('PARDON_TIME', 30);

//[Log]
// 0:ファイル, 1:syslogへ転送
// (default: 0)
define('LOG_TYPE', 0);
// ログファイルの出力場所
// (syslogへ転送する場合，このオプションは無視されます)
// (default: /var/log)
// Windows環境で動作させる場合，適切なパスを設定してください．
define('LOG_OUTPUT_DIRECTORY', '/var/log');

//[SSL/TLS]
// SSL/TLSモード(暗号化モード)を有効にするか
// (SSL/TLSモードの利用には，サーバの秘密鍵及び，正式な認証局によって発行されたSSL/TLS証明書が必要となります．)
define('ENABLE_SSL', false);
// 認証局が発行したSSL/TLS証明書(CERTファイル)のパス
define('SSL_CERT_PATH', '');
// 発行した秘密鍵のパス
define('SSL_PRIV_KEY_PATH', '');
//=================================
?>