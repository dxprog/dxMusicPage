var

/**
 * Config
 */
config = {
	port:1337, // Port to listen on
	name:'Beta Server', // Name of the server. This is what appears in the devices list
	server:{ // Path to DXMP host
		host:'beta.dxmp.us',
		port:8080
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

init = (function() {
	
	// Register this client with the DXMP server
	http.get({
		host:config.server.host,
		port:config.server.port,
		path:'/api/?type=json&method=device.register&port=' + config.port + '&name=' + config.name + '&status=' + DEVICE_STATUS_BORN
	});
	
	// Upon exit, deregister this device with the server
	process.on('exit', function() {
		console.log('Exiting. Deregistering device');
		http.get({
			host:config.server.host,
			port:config.server.port,
			path:'/api/?type=json&method=device.register&port=' + config.port + '&name=' + config.name + '&status=' + DEVICE_STATUS_DEAD
		});
	});
	
	// Create the response server
	http.createServer(function(request, response) {
		var
		qs = querystring.parse(url.parse(request.url).query),
		retVal = 'null';
		response.writeHead(200, { 'Content-Type':'text/javascript' });
		
		if (qs.hasOwnProperty('action')) {
			
			switch (qs.action) {
				case 'ping': // A ping from the server to see if this device is still alive
					retVal = '{alive:true}';
					break;
			}
		}
		
		response.end(retVal);
		
	}).listen(config.port, '10.0.0.4');

	console.log('Server is running');
}());