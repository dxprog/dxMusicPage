/* DxApi Lib */
var dx = {};

// Does a jsonp request to the API
dx.call = function(library, method, params, callback) {

	var qs = 'http://dxmp.us/api/?type=json&method=' + library + '.' + method, i;
	for (i in params) {
		if (params.hasOwnProperty(i)) {
			qs += '&' + i + '=' + params[i];
		}
	}
	$.ajax({
		url:qs,
		dataType:'jsonp',
		success:callback
	});

};