<?php
namespace Olinvas;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Olinvas\RoomInfo;
 
class OlinvasCore implements MessageComponentInterface {
	/*======================================================
	フィールド(2020/03/21: 分散するフィールド変数を統合しました)
	======================================================*/
	/*
		処理をなるべく定数オーダで完結させるため，メモリを惜しまずに使用するコードになっています．
		最適化は追々．
	*/
	
	//ルーム情報
	private $roomInfo;
	private $roomInfoNum = 0;
	
	//IPアドレスに起因するユーザ情報
	/*
		<IP-Address>(string-map) - IPアドレス
		=>
			sessionNum(integer) - セッション数
			hostRoomNum(integer) - ホストルーム数
			connectionList(string)
			=> <resourceId>(conn-list) - コネクション
			illegalPacketNum(Integer) - 無効パケットの検出数
			isBanned(boolean) - 接続規制中か
			banTime(Integer) - 規制された時間
		//コネクションをリストで管理しないのは，リストの処理を定数オーダで行うためです．その代わり，メモリが無駄になっていますが…
	*/
	private $userInfoByIPAddr;
	
	//リソースIDに起因するユーザ情報
	/*
		<resourceId>(integer-map) - リソースID
		=>
			roomId(string) - ルームID
	*/
	private $userInfoByResId;

	
	/*======================================================
	メソッド
	======================================================*/
	/*コンストラクタ*/
	public function __construct(){
		$this->roomInfo = [];
		$this->roomInfoNum = 0;
	}
 
	/*セッションの確立*/
	public function onOpen(ConnectionInterface $conn){
		/*接続規制されていないか*/
		if($this->isBanned($conn)){
			//規制されている場合は接続禁止通知を送信して切断
			$response = json_encode(array(
				'response' => 'ConnectionBan-Echo',
				'pardonTime' => $this->getPardonTime($conn)
			));
			$conn->send($response);
			$conn->close();
		}
		if(!isset($this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'])){
			//最初のセッション
			//セッションカウンタのインクリメント
			$this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'] = 1;
			//ホストするルーム数の初期化
			$this->userInfoByIPAddr[$conn->remoteAddress]['hostRoomNum'] = 0;
		}elseif($this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'] < MAX_SESSION_NUM_SAME_IP){
			//二回目以降のセッション
			//セッションカウントのインクリメント
			++$this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'];
		}else{
			/*セッションカウンタがコンフィグで指定した上限を超えた場合，セッションを破棄*/
			//onCloseでセッションカウンタを減らす処理をしているので，下のインクリメント文がないと無限に接続できてしまいます．
			++$this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'];
			$response = json_encode(array(
				'response' => 'TooConnection-Echo'
			));
			$conn->send($response);
			$conn->close();
			return;
		}
		//コネクションリストへの登録
		$this->userInfoByIPAddr[$conn->remoteAddress]['connectionList'][$conn->resourceId] = $conn;
		//ログ
		$GLOBALS['logger']->printLog(LOG_INFO, "Connected: '{$conn->remoteAddress}' has connected.");
	}

	/*パケットの受信*/
	public function onMessage(ConnectionInterface $from, $message){
		//JSONパケットをデコード
		//構造上問題がある場合は処理中断
		if(($messages = json_decode($message, true)) === null){
			//無効パケット検出処理
			$this->detectIllegalPacket($from);
			return;
		}
		//リクエストパケットでなかった場合はレスポンスパケットの評価へ
		if(($type = $this->getJsonValueFromAttr($messages, 'request')) === null) goto __response;
		switch($type){
			case 'DrawLine':
				/*
				 * DrawLine
				 * 任意のクライアントから送信されたDrawLineパケットを同一ルーム内の全メンバーへ送信
				 * 
				 * [応答]
				 * 成功時： 同一ルーム内の全メンバーへDrawLine-Echoを送信
				 * 失敗時： 何もしない
				 * 特殊： Historyが一定値を超えた場合，ルームのホストに対してCheckPoint要求を送信
				 */
				//==================================
				// リクエスト解析
				// パラメータ欠損，もしくは，値がスカラー値でない場合は破棄
				try{
					list($prevX, $prevY, $nextX, $nextY, $color, $width) = $this->getParameters($messages, 'prevX', 'prevY', 'nextX', 'nextY', 'color', 'width');
				}catch(\Exception $e){
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//----------------------------------
				// リクエストチェック
				if(
					//パケット送信者はどこかのルームに所属しているか
					($roomId = $this->getRoomIdFromConn($from)) === null
					//フレンド権限を持っているか
				||	!$this->roomInfo[$roomId]->isFriendMember($from)
				)
				{
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				// 処理部
				
				$response = json_encode(array(
					'response' => 'DrawLine-Echo',
					'prevX' => $prevX,
					'prevY' => $prevY,
					'nextX' => $nextX,
					'nextY' => $nextY,
					'color' => $color,
					'width' => $width
				));
				
				/*Historyの更新*/
				if(!$this->roomInfo[$roomId]->isCheckPointRequest()){
					$this->roomInfo[$roomId]->registHistory($response);
				}else{
					//CheckPoint要求中は仮Historyに登録
					//仮Historyの上限は，History上限の半分
					if($this->roomInfo[$roomId]->getTmpHistoryNum() < MAX_HISTORY_NUM/2){
						$this->roomInfo[$roomId]->registTmpHistory($response);
					}
					return;
				}
				
				/*描画パケットをルームメンバ全員へ送信*/
				foreach((array)$this->roomInfo[$roomId]->getMember() as &$member){
					$member->send($response);
				}
				
				//History件数がコンフィグで指定した最大件数を超えた場合，ホストに対して現状のキャンバスを画像データとして送信してもらう(メモリ節約)
				if($this->roomInfo[$roomId]->getHistoryNum() > MAX_HISTORY_NUM){
					//CheckPoint要求をホストへ送信
					$this->roomInfo[$roomId]->setCheckPointRequest(true);
					$request = json_encode(array('request' => 'CheckPoint'));
					$this->roomInfo[$roomId]->getHost()->send($request);
				}
				break;
				
			case 'ClearBoard':
				/*
				 * ClearBoard
				 * ボードの内容を削除する
				 * 
				 * [応答]
				 * 成功時： 同一ルーム内の全メンバーへClearBoard-Echoを送信
				 * 失敗時： 何もしない
				 */
				//==================================
				// リクエストチェック
				if(
					//パケット送信者はどこかのルームに所属しているか
					($roomId = $this->getRoomIdFromConn($from)) === null
					//パケット送信者はホストか
				||	!$this->roomInfo[$roomId]->isHostMember($from)
				)
				{
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				// 処理部
				
				/*Historyの更新*/
				//CheckPointの更新
				$this->roomInfo[$roomId]->setCheckPoint(null);
				//Historyリセット
				$this->roomInfo[$roomId]->resetHistory();
				
				/*パケットをルームメンバ全員へ送信*/
				$response = json_encode(array(
					'response' => 'ClearBoard-Echo'
				));
				foreach((array)$this->roomInfo[$roomId]->getMember() as &$member){
					$member->send($response);
				}
				
				break;
			
			case 'CreateRoom':
				/*
				 * CreateRoom
				 * ルームを作成
				 * 
				 * [応答]
				 * 成功時： パケット送信者へCreateRoom-Acceptを通知
				 * 失敗時： パケット送信者へCreateRoom-Rejectを通知
				 */
				//==================================
				// リクエスト解析
				// パラメータ欠損，もしくは，値がスカラー値でない場合は破棄
				try{
					list($roomName, $roomPassword) = $this->getParameters($messages, 'roomName', 'roomPassword');
				}catch(\Exception $e){
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "CreateRoom-Reject: Request from '{$from->remoteAddress}'.");
					
					//CreateRoomは認証ではないので，構造が適切であれば無効パケットでも許す
					//無効パケット検出処理
					//$this->detectIllegalPacket($from);
					break;
				}
				//----------------------------------
				// リクエストチェック
				if(
					//パケット送信者はどこのルームにも所属していないか
					isset($this->userInfoByResId[$from->resourceId]['roomId'])
					//同時ホストルーム数が上限を超えていないか
				||	$this->roomInfoNum >= MAX_ROOM_NUM
					//パケット送信者がホストしているルーム数が上限を超えていないか
				||	$this->userInfoByIPAddr[$from->remoteAddress]['hostRoomNum'] >= MAX_HOST_ROOM_NUM_SAME_IP
					//ルーム名が空でないか
				||	$roomName === ''
					//空パスワードを禁止している場合，空パスワードではないか
				||	!ENABLE_NO_PASSWORD_ROOM && $roomPassword === ''
				)
				{
					/*パケット送信元へ拒否応答*/
					$response = json_encode(array('response' => 'CreateRoom-Reject'));
					$from->send($response);
					
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "CreateRoom-Reject: Request from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					//$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				// 処理部
				
				/*予測不可能なルームIDを生成*/
				$newRoomId = null;
				do{
					$newRoomId = md5(openssl_random_pseudo_bytes(8));
					//既存のルームIDと衝突しないように
				}while(isset($this->roomIndo[$newRoomId]));
				
				/*ルーム情報の初期化*/
				$this->roomInfo[$newRoomId] = new RoomInfo($from, $roomName, $roomPassword);
				//メンバ情報の登録
				$this->roomInfo[$newRoomId]->registMember($from);
				//ホストをフレンドメンバに追加
				$this->roomInfo[$newRoomId]->registFriendMember($from);
				//コネクションとルームIDを紐づけ
				//(コネクションのリソースIDから，パケットがどのルームに対して発行された物か特定できるようにする)
				$this->userInfoByResId[$from->resourceId]['roomId'] = $newRoomId;
				//ホストがホストしているルーム数をインクリメント
				++$this->userInfoByIPAddr[$from->remoteAddress]['hostRoomNum'];
				//ルーム情報数をインクリメント
				++$this->roomInfoNum;
				
				/*パケット送信元へ許可応答*/
				//ルームID, ルーム名, フレンドキーをホストへ通知
				$response = json_encode(array(
					'response' => 'CreateRoom-Accept',
					'roomId' => $newRoomId,
					'roomName' => $this->roomInfo[$newRoomId]->getName(),
					'roomFriendKey' => $this->roomInfo[$newRoomId]->getFriendKey()
				));
				$from->send($response);
				
				//ログ出力
				$GLOBALS['logger']->printLog(LOG_INFO, "CreateRoom-Accept: '{$from->remoteAddress}' has created a room-{$newRoomId}.");
				break;
				
			case 'JoinRoom':
				/*
				 * JoinRoom
				 * ルームに参加
				 * 
				 * [応答]
				 * 成功時： パケット送信者へJoinRoom-Acceptを通知
				 * 失敗時： パケット送信者へJoinRoom-Rejectを通知
				 */
				//==================================
				// リクエスト解析
				// パラメータ欠損，もしくは，値がスカラー値でない場合は破棄
				try{
					list($roomId, $roomPassword) = $this->getParameters($messages, 'roomId', 'roomPassword');
				}catch(\Exception $e){
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "JoinRoom-Reject: Request from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//----------------------------------
				// リクエストチェック
				if(
					//パケット送信者はどこのルームにも所属していないか
					isset($this->userInfoByResId[$from->resourceId]['roomId'])
					//ルームが存在しているか
				||	!$this->isRoomExist($roomId)
					//ルームの参加人数が上限を超えていないか
				||	$this->roomInfo[$roomId]->getMemberNum() >= MAX_ROOM_MEMBER_NUM
					//ルームのパスワードが誤っていないか
				||	$roomPassword !== $this->roomInfo[$roomId]->getPassword()
				)
				{
					/*パケット送信元へ拒否応答*/
					$response = json_encode(array('response' => 'JoinRoom-Reject'));
					$from->send($response);
					
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "JoinRoom-Reject: Request from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				// 処理部
				
				/*ルーム情報の更新*/
				//メンバ情報の登録
				$this->roomInfo[$roomId]->registMember($from);
				//コネクションとルームIDを紐づけ
				$this->userInfoByResId[$from->resourceId]['roomId'] = $roomId;
				
				/*パケット送信元へ許可応答*/
				$responseQuery = json_encode(array(
					'response' => 'JoinRoom-Accept',
					'roomName' => $this->roomInfo[$roomId]->getName()
				));
				$from->send($responseQuery);
				
				/*途中参加時用処理*/
				/*パケット送信元へCheckPointデータを送信*/
				if($this->roomInfo[$roomId]->getCheckPoint() !== null){
					$responseHistory = json_encode(array(
						'response' => 'DrawBase-Echo',
						'canvasInfo' => $this->roomInfo[$roomId]->getCheckPoint()
					));
					$from->send($responseHistory);
				}
				/*パケット送信元へHistoryを送信*/
				foreach((array)$this->roomInfo[$roomId]->getHistory() as &$history){
					$from->send($history);
				}
				
				//ログ出力
				$GLOBALS['logger']->printLog(LOG_INFO, "JoinRoom-Accept: '{$from->remoteAddress}' has joined a room-{$roomId}.");
				break;
				
			case 'FriendAuth':
				/*
				 * FriendAuth
				 * フレンド認証を行う
				 * 
				 * [応答]
				 * 成功時： パケット送信者へFriendAuth-Acceptを通知
				 * 失敗時： パケット送信者へFriendAuth-Rejectを通知
				 */
				//==================================
				// リクエスト解析
				// パラメータ欠損，もしくは，値がスカラー値でない場合は破棄
				try{
					list($roomFriendKey) = $this->getParameters($messages, 'roomFriendKey');
				}catch(\Exception $e){
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "FriendAuth-Reject: Request from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//----------------------------------
				// リクエストチェック
				if(
					//パケット送信者はどこかのルームに所属しているか
					($roomId = $this->getRoomIdFromConn($from)) === null
					//既にフレンド権限を持っていないか
				||	$this->roomInfo[$roomId]->isFriendMember($from)
					//フレンドキーが誤っていないか
				||	$roomFriendKey !== $this->roomInfo[$roomId]->getFriendKey()
				)
				{
					/*パケット送信元へ拒否応答*/
					$response = json_encode(array('response' => 'FriendAuth-Reject'));
					$from->send($response);
					
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_WARNING, "FriendAuth-Reject: Request from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				
				//フレンドメンバへ登録
				$this->roomInfo[$roomId]->registFriendMember($from);
				
				/*パケット送信元へ許可応答*/
				$response = json_encode(array('response' => 'FriendAuth-Accept'));
				$from->send($response);
				
				//ログ出力
				$GLOBALS['logger']->printLog(LOG_INFO, "FriendAuth-Accept: '{$from->remoteAddress}' has become friends with host of room-{$roomId}.");
				break;
				
			case 'ServerInfo':
				$response = json_encode(array(
					'response' => 'ServerInfo-Echo',
					'yourSessionNum' => $this->userInfoByIPAddr[$from->remoteAddress]['sessionNum'],
					'maxSessionNum' => MAX_SESSION_NUM_SAME_IP,
					'yourHostRoomNum' => $this->userInfoByIPAddr[$from->remoteAddress]['hostRoomNum'],
					'maxHostRoomNum' => MAX_HOST_ROOM_NUM_SAME_IP,
					'maxRoomMemberNum' => MAX_ROOM_MEMBER_NUM,
					'hostingRoomNum' => $this->roomInfoNum,
					'maxRoomNum' => MAX_ROOM_NUM
				));
				$from->send($response);
				break;
				
			default:
				//無効パケット検出処理
				$this->detectIllegalPacket($from);
				break;
		}
		return;
		
__response:
		if(($type = $this->getJsonValueFromAttr($messages, 'response')) === null){
			//無効パケット検出処理
			$this->detectIllegalPacket($from);
			return;
		}
		switch($type){
			/*
			 * CheckPoint-Echo
			 * ホストから送信されたCheckPoint-Echoを受け取り，チェックポイントを登録
			 * 
			 * [応答]
			 * 成功時： 何もしない
			 * 失敗時： 何もしない
			 */
			case 'CheckPoint-Echo':
				//==================================
				// リクエスト解析
				// パラメータ欠損，もしくは，値がスカラー値でない場合は破棄
				try{
					list($canvasInfo) = $this->getParameters($messages, 'canvasInfo');
				}catch(\Exception $e){
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_INFO, "CheckPoint-Echo: Unknown echo from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//----------------------------------
				// リクエストチェック
				if(
					//パケット送信者はどこかのルームに所属しているか
					($roomId = $this->getRoomIdFromConn($from)) === null
					//パケット送信者はホストか
				||	!$this->roomInfo[$roomId]->isHostMember($from)
					//CheckPoint要求中か
				||	!$this->roomInfo[$roomId]->isCheckPointRequest()
				)
				{
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_INFO, "CheckPoint-Echo: Unknown echo from '{$from->remoteAddress}'.");
					
					//無効パケット検出処理
					$this->detectIllegalPacket($from);
					break;
				}
				//==================================
				
				/*Historyの更新*/
				//CheckPointの更新
				$this->roomInfo[$roomId]->setCheckPoint($canvasInfo);
				//Historyリセット
				$this->roomInfo[$roomId]->resetHistory();
				//CheckPoint要求解除
				$this->roomInfo[$roomId]->setCheckPointRequest(false);
				
				/*仮Historyをルームメンバ全員へ送信*/
				foreach((array)$this->roomInfo[$roomId]->getTmpHistory() as &$history){
					foreach((array)$this->roomInfo[$roomId]->getMember() as &$member){
						$member->send($history);
					}
				}
				
				//仮Historyリセット
				$this->roomInfo[$roomId]->resetTmpHistory();
				
				//ログ出力
				$GLOBALS['logger']->printLog(LOG_INFO, "CheckPoint-Echo: The checkpoint of room-{$roomId} has updated.");
				break;
				
			default:
				//無効パケット検出処理
				$this->detectIllegalPacket($from);
				break;
		}
	}

	/*セッションの解放*/
	public function onClose(ConnectionInterface $conn){
		//ログ出力
		$GLOBALS['logger']->printLog(LOG_INFO, "Disconnected: '{$conn->remoteAddress}' has disconnected.");
		//どこかのルームに参加しているか
		if(isset($this->userInfoByResId[$conn->resourceId]['roomId'])){
			$roomId = $this->userInfoByResId[$conn->resourceId]['roomId'];
			//そのルームは存在するか(このif文不要かも)
			if(isset($this->roomInfo[$roomId])){
				if($this->roomInfo[$roomId]->getHost()->resourceId === $conn->resourceId){
					/*ホスト*/
					//ホストがホストしているルーム数をデクリメント
					--$this->userInfoByIPAddr[$conn->remoteAddress]['hostRoomNum'];
					//ルーム解放
					$this->releaseRoom($roomId);
					//ログ出力
					$GLOBALS['logger']->printLog(LOG_INFO, "ReleaseRoom-Echo: The room-{$roomId} has closed.");
				}else{
					/*メンバ*/
					//ルームメンバから抜ける
					$this->roomInfo[$roomId]->unregistMember($conn);
				}
			}
			//コネクションとルームIDの紐づけ解除
			unset($this->userInfoByResId[$conn->resourceId]['roomId']);
		}
		//コネクションリストから除去
		unset($this->userInfoByIPAddr[$conn->remoteAddress]['connectionList'][$conn->resourceId]);
		//セッションカウンタのデクリメント
		--$this->userInfoByIPAddr[$conn->remoteAddress]['sessionNum'];
	}

	/*例外発生*/
	public function onError(ConnectionInterface $conn, \Exception $e){
		$GLOBALS['logger']->printLog(LOG_ERR, "ServerError: {$e->getMessage()}");
		$conn->close();
	}
	
	/*引数チェック*/
	//json配列, <パラメータ>
	private function getParameters(){
		$result = [];
		
		$args = func_get_args();
		$argsNum = func_num_args();
		for($i = 1; $i < $argsNum; ++$i){
			if(($result[] = $this->getJsonValueFromAttr($args[0], $args[$i])) === null){
				throw new \Exception();
			}
		}
		
		return $result;
	}
	
	/*Json文字列から値を取得*/
	private function getJsonValueFromAttr($jsonString, $attr){
		//属性が存在し，かつ文字列か整数なら値を返す．
		if(isset($jsonString[$attr]) && (gettype($jsonString[$attr]) === 'string' || gettype($jsonString[$attr]) === 'integer')){
			return $jsonString[$attr];
		}else{
			return null;
		}
	}
	
	/*コネクションからルームIDを取得*/
	private function getRoomIdFromConn(ConnectionInterface $conn){
		if(isset($this->userInfoByResId[$conn->resourceId]['roomId'])){
			return $this->userInfoByResId[$conn->resourceId]['roomId'];
		}else{
			return null;
		}
	}
	
	/*ルーム解放*/
	//与えられたRoomIDが実在しているかどうかは評価していません．isRoomExistがtrueになるかを検証してからコールしてください．
	private function releaseRoom($roomId){
		/*ルームメンバ全員にルーム解放通知を送信*/
		$response = json_encode(array('response' => 'ReleaseRoom-Echo'));
		foreach((array)$this->roomInfo[$roomId]->getMember() as &$member){
			unset($this->userInfoByResId[$member->resourceId]['roomId']);
			$member->send($response);
		}
		//ルーム情報解放
		unset($this->roomInfo[$roomId]);
		//ルーム情報数をデクリメント
		--$this->roomInfoNum;
	}
	
	/*ルームが存在するか*/
	private function isRoomExist($roomId){
		return (isset($this->roomInfo[$roomId]));
	}
	
	/*接続規制されているかどうか*/
	private function isBanned(ConnectionInterface $conn){
		if(isset($this->userInfoByIPAddr[$conn->remoteAddress]['isBanned']) && $this->userInfoByIPAddr[$conn->remoteAddress]['isBanned']){
			/*接続規制解除可能な場合は解除する*/
			if($this->canUserPardon($conn)){
				$this->userPardon($conn);
				return false;
			}
			return true;
		}else{
			return false;
		}
	}
	
	/*接続規制解除までの時間*/
	//現在のユーザが接続規制されているかどうかは評価していません．isBannedがtrueになるかを検証してからコールしてください．
	private function getPardonTime(ConnectionInterface $conn){
		return __PARDON_TIME - (time() - $this->userInfoByIPAddr[$conn->remoteAddress]['banTime']);
	}
	
	/*接続規制解除可能かどうか*/
	//現在のユーザが接続規制されているかどうかは評価していません．isBannedがtrueになるかを検証してからコールしてください．
	private function canUserPardon(ConnectionInterface $conn){
		return ($this->getPardonTime($conn) <= 0);
	}
	
	/*接続規制処理*/
	private function detectIllegalPacket(ConnectionInterface $conn){
		if(AUTO_IP_BAN){
			/*無効パケット検出数の増加処理*/
			if(!isset($this->userInfoByIPAddr[$conn->remoteAddress]['illegalPacketNum'])){
				$this->userInfoByIPAddr[$conn->remoteAddress]['illegalPacketNum'] = 1;
			}else{
				++$this->userInfoByIPAddr[$conn->remoteAddress]['illegalPacketNum'];
			}
			/*Ban判定*/
			//無効パケット数がコンフィグで指定した上限を超えた場合，BANを実施
			if($this->userInfoByIPAddr[$conn->remoteAddress]['illegalPacketNum'] >= ALLOW_INVALID_PACKET_NUM){
				//Banフラグ
				$this->userInfoByIPAddr[$conn->remoteAddress]['isBanned'] = true;
				//Banを実施した時間を記録
				$this->userInfoByIPAddr[$conn->remoteAddress]['banTime'] = time();
				/*セッション全切断+通知*/
				$response = json_encode(array(
					'response' => 'ConnectionBan-Echo',
					'pardonTime' => __PARDON_TIME
				));
				foreach($this->userInfoByIPAddr[$conn->remoteAddress]['connectionList'] as $_conn){
					$_conn->send($response);
					$_conn->close();
				}
				
				//ログ出力
				$GLOBALS['logger']->printLog(LOG_WARNING, "BanConnection: '{$conn->remoteAddress}' has banned on this server.");
			}
		}
	}
	
	/*接続規制回復処理*/
	private function userPardon(ConnectionInterface $conn){
		//Banフラグ解除
		$this->userInfoByIPAddr[$conn->remoteAddress]['isBanned'] = false;
		//無効パケット数リセット
		$this->userInfoByIPAddr[$conn->remoteAddress]['illegalPacketNum'] = 0;
			
		//ログ出力
		$GLOBALS['logger']->printLog(LOG_INFO, "PardonConnection: '{$conn->remoteAddress}' has pardoned on this server.");
	}
}
?>