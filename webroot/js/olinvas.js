/*==============================
Olinvas Client
--------------------------------
client ver.1.1.3
protocol ver.1.1
==============================*/

/*Global variable*/
var serverPort = 13181;

/*Websocket*/
(function($){
	var info = {};
	var serverHost = location.hostname+":"+serverPort;
	var sessionActive = false;
	var forceDisconnect = false;
	var isEditable = false;
	
	//canvas
	var canvas;
	var ctx;
	
	//color, linewidth
	var lineColorId = 0;
	var lineWidthId = 0;
	
	var methods = {
		init : function(){
			//init canvas
			canvas = document.getElementById('main_canvas');
			ctx = canvas.getContext('2d');
			
			let w = $('#main_canvas').attr('width');
			let h = $('#main_canvas').attr('height');
			
			//init websocket
			info = $.extend({
				'uri'	: (location.protocol === 'https:' ? 'wss://'+serverHost : 'ws://'+serverHost),
				'conn'	: null,
			});
			$(this).olinvas_client('connect');
		},
		
		connect : function(){
			if(info['conn'] === null){
				info['conn'] = new WebSocket(info['uri']);
				info['conn'].onopen = methods['onOpen'];
				info['conn'].onmessage = methods['onMessage'];
				info['conn'].onclose = methods['onClose'];
				info['conn'].onerror = methods['onError'];
			}
		},
		
		onOpen : function(event){
			sessionActive = true;
			
			//room master renderer
			$('#room_manager').fadeIn('fast');
			
			//get status
			$(this).olinvas_client('serverInfo');
		},
		
		onMessage : function(event){
			var responses = JSON.parse(event.data);
			var type = responses['response'];
			switch(type){
				case 'CreateRoom-Accept':
					info['roomId'] = responses['roomId'];
					info['roomName'] = responses['roomName'];
					info['roomFriendKey'] = responses['roomFriendKey'];
	
					isEditable = true;
					$('#main_line-color').removeAttr('hidden');
					$('#main_line-width-selector').removeAttr('hidden');
					$('#main_canvas-all-erase').removeAttr('hidden');
					info['roomHost'] = true;
					roomInit();
					break;
				
				case 'CreateRoom-Reject':
					$('#sminfo_server-response-create').text('ルームの作成に失敗しました．');
					break;
				
				case 'ReleaseRoom-Echo':
					lockScreen('lock_room-close');
					$('#info_room-close').fadeIn('fast');
					
					isEditable = false;
					break;
				
				case 'JoinRoom-Accept':
					info['roomName'] = responses['roomName'];
					
					info['roomHost'] = false;
					roomInit();
					break;
				
				case 'JoinRoom-Reject':
					$('#sminfo_server-response-join').text('ルームの参加に失敗しました．');
					break;
					
				case 'FriendAuth-Accept':
					$('#ri_your-status').html('<font color="#e00000"><b>Friend</b></font>');
					
					isEditable = true;
					$('#main_line-color').removeAttr('hidden');
					$('#main_line-width-selector').removeAttr('hidden');
					break;
				
				case 'FriendAuth-Reject':
					break;
					
					
				case 'DrawBase-Echo':
					let canvasBase = new Image();
					
					canvasBase.onload = function(){
						ctx.drawImage(canvasBase, 0, 0, canvas.width, canvas.height);
					}
					console.log(responses['canvasInfo']);
					canvasBase.src = responses['canvasInfo'];
					break;
					
				case 'DrawLine-Echo':
					ctx.beginPath();
					ctx.moveTo(responses['prevX'], responses['prevY']);
					ctx.lineTo(responses['nextX'], responses['nextY']);
					switch(responses['color']){
						case 0:
							ctx.strokeStyle = 'rgb(0,0,0)';
							break;
						case 1:
							ctx.strokeStyle = 'rgb(0,0,255)';
							break;
						case 2:
							ctx.strokeStyle = 'rgb(255,0,0)';
							break;
						case 3:
							ctx.strokeStyle = 'rgb(0,255,0)';
							break;
						case 4:
							ctx.strokeStyle = 'rgb(255,255,0)';
							break;
						case 5:
							ctx.strokeStyle = 'rgb(255,255,255)';
							break;
						default:
							ctx.strokeStyle = 'rgb(0,0,0)';
							break;
					}
					switch(responses['width']){
						case 0:
							ctx.lineWidth = 1;
							break;
						case 1:
							ctx.lineWidth = 5;
							break;
						case 2:
							ctx.lineWidth = 10;
							break;
						case 3:
							ctx.lineWidth = 15;
							break;
						case 4:
							ctx.lineWidth = 20;
							break;
						default:
							ctx.lineWidth = 1;
							break;
					}
					ctx.stroke();
					break;
					
				case 'ClearBoard-Echo':
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					break;
					
				case 'TooConnection-Echo':
					forceDisconnect = true;
					
					lockScreen('lock_too-connection');
					$('#info_too-connection').fadeIn('fast');
					
					isEditable = false;
					break;
					
				case 'ConnectionBan-Echo':
					forceDisconnect = true;
					
					lockScreen('lock_connection-ban');
					$('#pardon-time').text(Math.ceil(responses['pardonTime']/60));
					$('#info_client-ban').fadeIn('fast');
					
					isEditable = false;
					break;
					
				case 'ServerInfo-Echo':
					$('#ss_hostroom-num').text(responses['hostingRoomNum']);
					$('#ss_max-hostroom-num').text(responses['maxRoomNum']);
					$('#ss_max-member').text(responses['maxRoomMemberNum']);
					$('#ss_ysession-num').text(responses['yourSessionNum']);
					$('#ss_max-ysession-num').text(responses['maxSessionNum']);
					$('#ss_yhostroom-num').text(responses['yourHostRoomNum']);
					$('#ss_max-yhostroom-num').text(responses['maxHostRoomNum']);
					break;
				
				default:
					break;
			}
			
			type = responses['request'];
			switch(type){
				case 'CheckPoint':
					let response = JSON.stringify(
						{
							response: "CheckPoint-Echo",
							canvasInfo: canvas.toDataURL()
						}
					);
					info['conn'].send(response);
					break;
					
				default:
					break;
			}
		},
		
		onClose : function(event){
			//connection reset renderer
			if(sessionActive && !forceDisconnect){
				lockScreen('lock_server-access');
				$('#info_connection-reset').fadeIn('fast');
			}
			
			isEditable = false;
		},
		
		onError : function(event){
			//error renderer
			lockScreen('lock_server-access');
			$('#info_server-error').fadeIn('fast');
			
			isEditable = false;
		},
		
		createRoom : function(roomName, roomPassword){
			var request = JSON.stringify(
				{
					request: "CreateRoom",
					roomName: roomName,
					roomPassword: roomPassword
				}
			);
			info['conn'].send(request);
		},
		
		joinRoom : function(roomId, roomPassword){
			var request = JSON.stringify(
				{
					request: "JoinRoom",
					roomId: roomId,
					roomPassword: roomPassword
				}
			);
			info['conn'].send(request);
		},
		
		friendAuth : function(friendKey){
			var request = JSON.stringify(
				{
					request: "FriendAuth",
					roomFriendKey: friendKey
				}
			);
			info['conn'].send(request);
		},
		
		drawLine : function(prevX, prevY, nextX, nextY, color, lineWidth){
			var request = JSON.stringify(
				{
					request: "DrawLine",
					prevX: prevX,
					prevY: prevY,
					nextX: nextX,
					nextY: nextY,
					color: color,
					width: lineWidth
				}
			);
			info['conn'].send(request);
		},
		
		clearBoard : function(){
			var request = JSON.stringify(
				{
					request: "ClearBoard"
				}
			);
			info['conn'].send(request);
		},
		
		changeLineColor : function(colorId){
			lineColorId = colorId;
		},
		
		changeLineWidth : function(widthId){
			lineWidthId = widthId;
		},
		
		serverInfo : function(){
			var request = JSON.stringify(
				{
					request: "ServerInfo"
				}
			);
			info['conn'].send(request);
		}
	};
	
	$.fn.olinvas_client = function(method){
		if(methods[method]){
			return methods[method].apply(this, Array.prototype.slice.call(arguments,1));
		}else{
			console.log('Undefined Method: '+method);
		}
	}
	
	/*initialize room*/
	function roomInit(){
		//canvas renderer
		$('#room_manager').fadeOut('fast');
		$('#server-status').fadeOut('fast');
		$('#main').fadeIn('fast');
		
		$('#room-name').append(sanitize(info['roomName']));
		if(info['roomHost']){
			$('#ri_your-status').append('<font color="#00e000"><b>Host</b></font>');
		}else{
			$('#ri_your-status').append('<font color="#0000e0"><b>Guest</b></font>'
			+'<span class="float-right">フレンドキー:<input id="ri_friend_key_auth" type="password"><button type="button" placeholder="フレンドキーを入力" onclick="$(this).olinvas_client(\'friendAuth\', $(\'#ri_friend_key_auth\').val())">認証</button></input></span>');
		}
		if(info['roomHost']){
			$('#ri_fk_list').removeAttr('hidden');
			$('#ri_su_list').removeAttr('hidden');
			$('#ri_room-friend-key').append('<a href="javascript:copyTextToClipboard(\''+info['roomFriendKey']+'\')">クリップボードへコピー</a>');
			$('#ri_share-url').append('<a href="javascript:copyTextToClipboard(\''+location.href+'?id='+info['roomId']+'\')">クリップボードへコピー</a>');
		}
		
		//canvas init
		canvasInit();
	}
	
	/*initialize cavas*/
	function canvasInit(){
		canvas.addEventListener('pointerdown', drawStart, false);
		canvas.addEventListener('pointermove', drawing, false);
		canvas.addEventListener('pointerup', drawEnd, false);
		canvas.addEventListener('pointerout', drawEnd, false);
		
		var mouseX = null;
		var mouseY = null;
		var pointerId = null;
		
		function drawStart(event){
			if(event.pointerType === 'pen' && pointerId === null){
				event.preventDefault();
				pointerId = event.pointerId;
				var rect = event.target.getBoundingClientRect();
				var X = ~~(event.clientX-rect.left);
				var Y = ~~(event.clientY-rect.top);
				drawLine(X,Y);
				return;
			}
			if(event.pointerType === 'mouse' && event.button === 0){
				var rect = event.target.getBoundingClientRect();
				var X = ~~(event.clientX-rect.left);
				var Y = ~~(event.clientY-rect.top);
				drawLine(X,Y);
				return;
			}
		}
		
		function drawing(event){
			if(event.pointerType === 'pen' && pointerId === event.pointerId){
				event.preventDefault();
				var rect = event.target.getBoundingClientRect();
				var X = ~~(event.clientX-rect.left);
				var Y = ~~(event.clientY-rect.top);
				drawLine(X,Y);
				return;
			}
			if(event.pointerType === 'mouse' && (event.buttons === 1 || event.witch === 1)){
				var rect = event.target.getBoundingClientRect();
				var X = ~~(event.clientX-rect.left);
				var Y = ~~(event.clientY-rect.top);
				drawLine(X,Y);
				return;
			}
		}
		
		function drawEnd(event){
			mouseX = null;
			mouseY = null;
			pointerId = null;
		}
		
		function drawLine(X, Y){
			if(isEditable){
				var aspectRatioOfWidth = $('#main_canvas').width() / canvas.width;
				var aspectRatioOfHeight = $('#main_canvas').height() / canvas.height;
				
				$(this).olinvas_client(
					'drawLine',
					(mouseX !== null ? ~~(mouseX/aspectRatioOfWidth) : ~~(X/aspectRatioOfWidth)),
					(mouseY !== null ? ~~(mouseY/aspectRatioOfHeight) : ~~(Y/aspectRatioOfHeight)),
					~~(X/aspectRatioOfWidth),
					~~(Y/aspectRatioOfHeight),
					lineColorId,
					lineWidthId
				);
				
				mouseX = X;
				mouseY = Y;
			}
		}
	}
})(jQuery);

$(function(){
	//div remove
	$('#info_server-error').hide().removeAttr('hidden');
	$('#info_connection-reset').hide().removeAttr('hidden');
	$('#info_room-close').hide().removeAttr('hidden');
	$('#info_too-connection').hide().removeAttr('hidden');
	$('#info_client-ban').hide().removeAttr('hidden');
	$('#main').hide().removeAttr('hidden');
	$('#room_manager').hide().removeAttr('hidden');
	
	//query parameter
	var isJoinMode = (location.search.indexOf('?id=') !== -1 ? true : false);
	if(isJoinMode){
		$('#rm_create-room').hide();
	}else{
		$('#rm_join-room').hide();
	}
	
	//server access
	$(this).olinvas_client('init');
});
