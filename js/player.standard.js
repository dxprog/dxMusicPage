// HTML 5 Player
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
	
	playVideo = function(video, show) {
		var 
		formats = 'm4v|mp4',
		videoIndex = null,
		body = document.getElementsByTagName('body')[0];
		
		if (video.meta.hasOwnProperty('files')) {
			for (var i = 0, count = video.meta.files.length; i < count; i++) {
				if (formats.indexOf(video.meta.files[i].extension) > -1) {
					videoIndex = i;
					break;
				}
			}
		}
		
		if (null != videoIndex) {
			var
			container = document.createElement('div'),
			elVideo = document.createElement('video');
			container.setAttribute('id', 'video');
			elVideo.src = 'videos/' + show.meta.raw_path + '/' + video.meta.files[videoIndex].filename;
			elVideo.controls = 'controls';
			elVideo.autoplay = 'autoplay';
			container.appendChild(elVideo);
			body.appendChild(container);
		} else {
			alert('There is not format suitable for streaming');
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
	
	return { getStatus:getStatus, pause:pause, playSong:playSong, playVideo:playVideo, kill:kill, isPlaying:isPlaying };

})();

// VLC player
Player.prototype.vlc = (function($) {
	
	var
	mediaEndTimer = null,
	mediaEndCallback = null,
	mediaUpdateCallback = null,
	positionTimer = null,
	timerStarted = 0,
	paused = false,
	playing = false,
	loading = false,
	status = null,
	lastSync = 0,
	songId = 0,
	startTime = 0,
	updateStatus = function(callback) {
		$.ajax({
			url:"./api/?type=json&method=vlc.getStatus",
			dataType:"json",
			success:callback
		});
	},
	statusCallback = function(data) {
		
		status = data.body;
		lastSync = status.position;
		clearInterval(positionTimer);
		
		switch (data.body.state) {
			case "paused":
			case "opening\/connecting":
				// If VLC is still loading the media, wait a couple seconds and ping again
				setTimeout(function() { updateStatus(statusCallback); }, 2000);
				loading = true;
				break;
			case "playing":
				// Set a timer to go off when the media is done playing
				var timeRemaining = (data.body.length - data.body.position) * 1000;
				clearTimeout(mediaEndTimer);
				mediaEndTimer = setTimeout(function() { updateStatus(statusCallback); }, timeRemaining);
				positionTimer = setInterval(positionCallback, 10);
				playing = true;
				loading = false;
				break;
			case "stop":
				if (playing === true) {
					clearTimeout(mediaEndTimer);
					clearInterval(positionTimer);
					playing = false;
					paused = false;
					mediaEndCallback();
				}
				break;
		}
	},
	getStatus = function() {
		return status;
	},
	pause = function() {
		$.get("./api/?type=json&method=vlc.togglePause");
		paused = !paused;
		if (paused) {
			clearInterval(positionTimer);
			clearTimeout(mediaEndTimer);
		} else {
			updateStatus(statusCallback);
		}
	},
	positionCallback = function() {
		if (playing === true && paused === false && status != null) {
			status.position += 0.01;
			if (lastSync + 100 < status.position) {
				updateStatus(statusCallback);
				lastSync = status.position;
			}
			mediaUpdateCallback(status);
		}
	},
	playSong = function(id, callback, updateCallback) {
		if (typeof(callback) === "function") {
			mediaEndCallback = callback;
			clearTimeout(mediaEndTimer);
			playing = false;
			loading = true;
			songId = id;
			$.ajax({
				url:"./api/?type=json&method=vlc.playSong&id=" + id,
				dataType:"json",
				success:statusCallback
			});
			
			// If an update callback was provided, keep tabs on the playback position
			if (typeof(updateCallback) === "function") {
				status = null;
				lastSync = 0;
				clearInterval(positionTimer);
				positionTimer = setInterval(positionCallback, 10);
				mediaUpdateCallback = updateCallback;
			} else {
				mediaUpdateCallback = null;
			}
			
		}
	},
	
	playVideo = function(episode, show) {
	
	},
	
	kill = function() {
		clearInterval(positionTimer);
		clearTimeout(mediaEndTimer);
		if (playing && !paused) {
			pause();
		}
		return 0;
	},
	isPlaying = function() {
		return playing | loading;
	};
	
	return { getStatus:getStatus, pause:pause, playSong:playSong, playVideo:playVideo, kill:kill, isPlaying:isPlaying };
	
})(jQuery);
