var

/**
 * Config
 */
config = {
	ip:'10.0.0.13',
	port:1337, // Port to listen on
	name:'Matt Living Room', // Name of the server. This is what appears in the devices list
	server:{ // Path to DXMP host
		host:'dxmp.us',
		port:80
	}
},

/**
 * Includes
 */
http = require('http'),
url = require('url'),
exec = require('child_process').exec,
querystring = require('querystring'),

/**
 * Constants
 */
DEVICE_STATUS_BORN = 0,
DEVICE_STATUS_LIVE = 1,
DEVICE_STATUS_DEAD = 2,

status = 'idle',
mediaProc = null,

parseQueryString = function(qs) {
	
	var params = [], retVal = {};
	
	if (typeof qs === 'string') {
		params = qs.split('&');
		for (var i = 0, count = params.length; i < count; i++) {
			var
			bits = params[i].split('='),
			key = bits[0],
			val = bits.length > 1 ? bits[1] : true;
			retVal[key] = val;
		}
	}

	return retVal;
	
},

http_get = function(host, port, path, callback) {
	
	var request = http.request(
		{
			host:host,
			port:port,
			path:path,
			method:'GET'
		}, 
		function(response) {
			if (typeof callback === 'function') {
				response.setEncoding('utf8');
				response.on('data', callback);
			}
		});
	request.end();
	
},

api_call = function(library, method, params, callback) {
	
	var
		path = '/api/?type=json&method=' + library + '.' + method,
		i = null,
		request = null;
	
	if (typeof params === 'object') {
		for (i in params) {
			if (params.hasOwnProperty(i)) {
				path += '&' + i + '=' + escape(params[i]);
			}
		}
	}
	
	request = http.request(
		{
			host:config.server.host,
			port:config.server.port,
			path:path,
			method:'GET'
		}, 
		function(response) {
			if (typeof callback === 'function') {
				response.setEncoding('utf8');
				response.on('data', callback);
			}
		});
	request.end();
	
},

contentComplete = function(error, stdout, stderr) {
	console.log('Media finished');
	status = 'idle';
	mediaProc = null;
},

contentPlay = function(id) {
	
	api_call('content', 'getContent', { id:id }, function(data) {
		
		var
			content = JSON.parse(data),
			item = null,
			file = null,
			i = 0,
			count = 0;
			
		if (content.body.count > 0) {
			item = content.body.content[0];
			
			console.log('Playing ' + item.type + ' "' + item.title + '"');
			
			if (status !== 'idle' || null != mediaProc) {
				mediaProc.kill();
				mediaProc = null;
			}
			
			switch (item.type) {
				case 'song': // 5978
					mediaProc = exec('/usr/bin/omxplayer "http://dxmp.s3.amazonaws.com/songs/' + item.meta.filename + '"');
					status = 'playing';
					mediaProc.on('exit', contentComplete);
					break;
				case 'video': // 5523
					for (i = 0, count = item.meta.files.length; i < count; i++) {
						file = item.meta.files[i];
						if (file.extension === 'm4v' || file.extension === 'mkv') {
							mediaProc = exec('/usr/bin/omxplayer "http://dev.dxprog.com/dxmpv2/videos/' + item.meta.path + '/' + file.filename + '"');
							status = 'playing';
							mediaProc.on('exit', contentComplete);
						}
					}
					break;
			}
		}
		
	});
},

keepAlive = function() {
	api_call('device', 'register', { port:config.port, status:DEVICE_STATUS_LIVE });
	setTimeout(keepAlive, 300000);
},

init = (function() {
	
	// Register this client with the DXMP server
	api_call('device', 'register', { port:config.port, name:config.name, status:DEVICE_STATUS_BORN });
	
	// Upon exit, deregister this device with the server
	process.on('exit', function() {
		console.log('Exiting. Deregistering device');
		api_call('device', 'register', { port:config.port, name:config.name, status:DEVICE_STATUS_DEAD });
	});
	
	// Create the response server
	http.createServer(function(request, response) {
		var
		qs = querystring.parse(url.parse(request.url).query),
		callback = typeof qs.callback === 'string' ? qs.callback : false,
		retVal = 'null';
		response.writeHead(200, { 'Content-Type':'text/javascript' });
		
		if (qs.hasOwnProperty('action')) {
			
			console.log('Incoming request: ' + qs.action);
			
			switch (qs.action) {
				case 'ping': // A ping from the server to see if this device is still alive
					retVal = '{ "alive":true}';
					setTimeout(keepAlive, 300000);
					break;
				case 'play':
					if (qs.hasOwnProperty('id')) {
						contentPlay(qs.id);
						retVal = '{ "status":"' + status + '" }';
					}
					break;
				case 'status':
					retVal = '{ "status":"' + status + '" }';
					break;
			}
		}
		
		retVal = callback ? callback + '(' + retVal + ');' : retVal;
		response.end(retVal);
		
	}).listen(config.port, config.ip);

	console.log('Server is running');
}());