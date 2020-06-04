<?php
namespace Olinvas;

class RoomInfo {
	
	private $host; //ルームホストのコネクション
	private $name; //ルーム名
	private $password; //ルームパスワード
	private $friendKey; //フレンドキー
	private $history; //History
	private $historyNum; //History数
	private $tmpHistory; //仮History
	private $tmpHistoryNum; //仮History数
	
	private $member; //メンバ
	private $friendMember; //フレンドメンバ
	private $memberNum; //ルームメンバ数
	private $memberId; //メンバID
	private $checkPoint; //HistoryのCheckPointデータ(Base64画像)
	private $checkPointRequest; //CheckPoint要求中か？
	
	public function __construct($host, $roomName, $roomPassword) {
		$this->host = $host;
		$this->name = $roomName;
		$this->password = $roomPassword;
		$this->friendKey = base64_encode(openssl_random_pseudo_bytes(9));
		$this->member = [];
		$this->friendMember = [];
		$this->memberNum = 0;
		$this->memberId = [];
		$this->history = [];
		$this->historyNum = 0;
		$this->tmpHistory = [];
		$this->tmpHistoryNum = 0;
		$this->checkPoint = null;
		$this->checkPointRequest = false;
	}
	
	//ホストを取得
	public function getHost(){
		return $this->host;
	}
	
	//ホストメンバかどうか
	public function isHostMember($conn){
		return $this->host->resourceId === $conn->resourceId;
	}
	
	//ルーム名を取得
	public function getName(){
		return $this->name;
	}
	
	//ルームパスワードを取得
	public function getPassword(){
		return $this->password;
	}
	
	//フレンドキーを取得
	public function getFriendKey(){
		return $this->friendKey;
	}
	
	//Historyを取得
	//Historyの内容を改変する場合は，以下のresetHistory, registHistoryを用いてください．
	public function getHistory(){
		return $this->history;
	}
	
	//History数を取得
	//Historyの内容を改変する場合は，以下のresetHistory, registHistoryを用いてください．
	public function getHistoryNum(){
		return $this->historyNum;
	}
	
	//Historyの登録
	public function registHistory(string $history){
		$this->history[] = $history;
		++$this->historyNum;
	}
	
	//Historyのリセット
	public function resetHistory(){
		$this->history = [];
		$this->historyNum = 0;
	}
	
	//仮Historyを取得
	//仮Historyの内容を改変する場合は，以下のresetTmpHistory, registTmpHistoryを用いてください．
	public function getTmpHistory(){
		return $this->tmpHistory;
	}
	
	//仮History数を取得
	//仮Historyの内容を改変する場合は，以下のresetTmpHistory, registTmpHistoryを用いてください．
	public function getTmpHistoryNum(){
		return $this->tmpHistoryNum;
	}
	
	//仮Historyの登録
	public function registTmpHistory(string $history){
		$this->tmpHistory[] = $history;
		++$this->tmpHistoryNum;
	}
	
	//仮Historyのリセット
	public function resetTmpHistory(){
		$this->tmpHistory = [];
		$this->tmpHistoryNum = 0;
	}
	
	//メンバ情報を取得
	public function getMember(){
		return $this->member;
	}
	
	//メンバ登録
	public function registMember($conn){
		//ルームメンバにホストを追加
		$this->member[$this->memberNum] = $conn;
		//ホストのルームメンバIDを登録(ルーム退室時に利用)
		$this->memberId[$conn->resourceId] = $this->memberNum;
		//ルームメンバ数のインクリメント
		++$this->memberNum;
	}
	
	//メンバ解除
	public function unregistMember($conn){
		//ルームメンバから抜ける
		unset($this->member[$this->memberId[$conn->resourceId]]);
		--$this->memberNum;
		
		//フレンドメンバだった場合，そちらも解除
		if(isset($this->friendMember[$conn->resourceId])){
			unset($this->friendMember[$conn->resourceId]);
		}
	}
	
	//フレンドメンバかどうか
	public function isFriendMember($conn){
		return isset($this->friendMember[$conn->resourceId]);
	}
	
	//フレンドメンバ登録
	public function registFriendMember($conn){
		//ルームメンバにホストを追加
		$this->friendMember[$conn->resourceId] = true;
	}
	
	//メンバ数を取得
	public function getMemberNum(){
		return $this->memberNum;
	}
	
	//チェックポイントを取得
	public function getCheckPoint(){
		return $this->checkPoint;
	}
	
	//チェックポイントを設定
	public function setCheckPoint($checkPoint){
		$this->checkPoint = $checkPoint;
	}
	
	//チェックポイント要求中かどうか
	public function isCheckPointRequest(){
		return $this->checkPointRequest;
	}
	
	//チェックポイント要求中フラグ
	public function setCheckPointRequest($bool){
		$this->checkPointRequest = $bool;
	}
}
?>