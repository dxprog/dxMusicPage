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