# Olinvas  
Olinvasはオンラインで利用可能な仮想ホワイトボードです．  
ユーザは遠隔にいる人と自由にボードを編集することができます．  

## 実行
OlinvasはWebSocketと呼ばれる技術を利用しています．  
Olinvasでは，WebSocketサーバの作成にRatchet+PHPを利用しています．  
実行には，Ratchet, PHP7及び任意のHTTPサーバが必要となります．  
※(5.3以降のバージョン5でも動くと思いますが，動作確認は行っておりません．)

### 環境構築(簡易版)
1. Apache HTTP ServerなどのHTTPサーバの導入
    1. Linuxの場合，パッケージ管理システムを用いると簡単にインストールできます．
    1. その他のHTTPサーバでも構いません．
1. PHP7の導入
    1. パッケージ管理システムで導入する場合，PHP7でない場合がありますのでご注意ください．
3. composer及びRatchetの導入
    1. Ratchetは0.4.2をご利用ください．
4. config.phpの編集
    1. config.phpのrequire_onceを編集し，composerのauto_load.phpのパスを指定してください．
    2. その他，必要に応じてconfig.phpの設定を行ってください．
        1. ポート番号を変更した場合，後述するwebrootのjs/olinvas.jsのポート番号も同じ値に変更してください．
        2. ポート番号はデフォルトで13181です．
5. webrootの配置
    1. webrootディレクトリの中身を，HTTPサーバのルートディレクトリに配置してください．
    2. ルートディレクトリでなくとも，その他の適切な場所でも構いません．
6. srcを配置し，start.batもしくはstart.shを実行
    1. srcディレクトリを **HTTPサーバのディレクトリとは関係のない場所(/usr/local/binなど)** に配置してください．
    2. 同梱されているstart.bat(Windows)もしくはstart.sh(Linux)を起動してください．

### その他の留意事項
- ポート開放は忘れずに行ってください．
