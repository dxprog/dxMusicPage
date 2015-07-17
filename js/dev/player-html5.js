import Player from './player';

// HTML 5 Player
Player.register('html5', (function() {

	var

	audio = document.createElement("audio"),
	fader = null,
	songId = null,
	playing = false,
	loading = false,
	paused = false,
	audioSupported = (typeof(audio.play) == "function"),
	positionTimer = null,
	fadeOutSeconds = 5,
	fadeOutTimer = null,
	fadeOutTimerInterval = 50,
	fadeOutTimerAdjust = fadeOutTimerInterval / (fadeOutSeconds * 1000),
	mediaEndCallback = null,
	mediaUpdateCallback = null,

	status = {
		state:"stop",
		length:0,
		position:0
	},

	fadeOutCallback = function() {

		if (fader.volume > 0) {
			fader.volume -= fadeOutTimerAdjust;
			if (null != audio) {
				audio.volume += fadeOutTimerAdjust;
			}
		} else {
			clearInterval(fadeOutTimer);
			fader = null;
			audio.volume = 1;
		}

	},

	getStatus = function() {

		if (!audioSupported) {
			status = null;
		} else {
			// Determine the current play state
			status.state = 'stopped';
			if (playing && !paused) {
				status.state = 'playing';
			}

			if (paused) {
				status.state = 'pause';
			}

			// Get position
			status.length = audio.duration;
			status.position = audio.currentTime;

			if (audio.currentTime >= audio.duration - fadeOutSeconds && audio.duration >= 30) {
				fader = audio;
				audio = null;
				fadeOutTimer = setInterval(fadeOutCallback, fadeOutTimerInterval);
				songComplete();
			} else if (audio.currentTime === audio.duration) {
				songComplete();
			}

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

		if (typeof(callback) === 'function') {
			songId = id;
			mediaEndCallback = callback;
			audio = document.createElement('audio');
			audio.src = 'http://dxmp.us/api/?type=json&method=dxmp.getTrackFile&id=' + id;
			audio.load();
			audio.play();

			if (null != fadeOutTimer) {
				audio.volume = 0;
			}

			playing = false;
			loading = true;
			positionTimer = setInterval(positionCallback, 125);
			audio.addEventListener('canplaythrough', beginPlayback);

			// Set up the song complete callback
			// audio.addEventListener("ended", songComplete);

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
			if ((/PLAYSTATION 3/ig).test(window.navigator.userAgent)) {
				window.location.href = 'http://dev.dxprog.com:8080/dxmpv2/videos/' + show.meta.raw_path + '/' + video.meta.files[videoIndex].filename;
			} else {
				var
					container = document.createElement('div'),
					elVideo = document.createElement('video');
				container.setAttribute('id', 'video');
				elVideo.src = 'http://dev.dxprog.com:8080/dxmpv2/videos/' + show.meta.raw_path + '/' + video.meta.files[videoIndex].filename;
				elVideo.controls = 'controls';
				elVideo.autoplay = 'autoplay';
				container.appendChild(elVideo);
				body.appendChild(container);
			}
		} else {
			alert('There is no format suitable for streaming');
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

}()));