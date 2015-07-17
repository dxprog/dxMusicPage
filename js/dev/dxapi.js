/* DxApi Lib */
export default {

	// Does a jsonp request to the API
	call: function(library, method, params, callback) {

		var qs = 'http://dxmp.us/api/?type=json&method=' + library + '.' + method, i;
		for (i in params) {
			if (params.hasOwnProperty(i)) {
				qs += '&' + i + '=' + params[i];
			}
		}

		return $.ajax({
			url:qs,
			dataType:'jsonp',
			success:callback
		});

	}

};