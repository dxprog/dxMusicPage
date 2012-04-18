Player.prototype.html5 = (function() {

	var
	audio = document.createElement("audio"),
	songId = null,
	playing = false,
	loading = false,
	paused = false,
	audioSupported = (typeof(audio.play) == "function"),
	positionTimer = null,
	status = {
		state:"stop",
		length:0,
		position:0	
	},
	getStatus = function() {
				
		if (!audioSupported) {
			status = null;
		} else {
			// Determine the current play state
			status.state = 'stopped';
			if (playing && !paused) {
				status.state = "playing";
			}
			if (paused) {
				status.state = "pause";
			}
			
			// Get position
			status.length = audio.duration;
			status.position = audio.currentTime;
			
		}
		
		return status;
		
	},
	positionCallback = function() {
		if (null !== mediaUpdateCallback) {
			mediaUpdateCallback(getStatus());
		}
	},
	pause = function() {
		if (audioSupported) {
			if (paused) {
				audio.play();
			} else {
				audio.pause();
			}
			paused = !paused;
		}
	},
	beginPlayback = function() {
		audio.play();
		loading = false;
		playing = true;
	},
	songComplete = function() {
		playing = false;
		paused = false;
		dx.call('content', 'logContentView', {'id':songId});
		clearInterval(positionTimer);
		mediaEndCallback();
	},
	playSong = function(id, callback, updateCallback) {

		if (typeof(callback) === "function") {
			songId = id;
			mediaEndCallback = callback;
			audio.src = './api/?type=json&method=dxmp.getTrackFile&id=' + id;
			audio.load();
			audio.play();
			playing = false;
			loading = true;
			positionTimer = setInterval(positionCallback, 125);
			audio.addEventListener('canplaythrough', beginPlayback);
			
			// Set up the song complete callback
			audio.addEventListener("ended", songComplete);
			
			// If an update callback was provided, keep tabs on the playback position
			if (typeof(updateCallback) === "function") {
				mediaUpdateCallback = updateCallback;
			} else {
				mediaUpdateCallback = null;
			}
			
		}
	},
	kill = function() {
		clearInterval(positionTimer);
		if (playing && !paused) {
			pause();
		}
		audio = document.createElement("audio");
	},
	isPlaying = function() {
		return playing | loading;
	};
	
	return { getStatus:getStatus, pause:pause, playSong:playSong, kill:kill, isPlaying:isPlaying };

})();