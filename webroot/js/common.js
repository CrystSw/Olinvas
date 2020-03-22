/*Screen lock*/
function lockScreen(id){
	var hideScreenTag = $('<div>').attr('id', id);
	hideScreenTag.css('z-index', '1030')
	.css('position', 'fixed')
	.css('top', '0px')
	.css('left', '0px')
	.css('right', '0px')
	.css('bottom', '0px')
	.css('background-color', 'gray')
	.css('opacity', '0.5');
	$('body').append(hideScreenTag);
}

function unlockScreen(id){
	$("#"+id).remove();
}

/*get query parameter*/
function getParam(name, url) {
	if (!url) url = window.location.href;
	name = name.replace(/[\[\]]/g, "\\$&");
	var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
		results = regex.exec(url);
	if (!results) return null;
	if (!results[2]) return '';
	return decodeURIComponent(results[2].replace(/\+/g, " "));
}

/*sanitize*/
function sanitize(string){
	return string.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/*copy to clipboard*/
function copyTextToClipboard(textVal){
	var copyFrom = document.createElement("textarea");
	copyFrom.textContent = textVal;
	var bodyElm = document.getElementsByTagName("body")[0];
	bodyElm.appendChild(copyFrom);
	copyFrom.select();
	var retVal = document.execCommand('copy');
	bodyElm.removeChild(copyFrom);
	return retVal;
}