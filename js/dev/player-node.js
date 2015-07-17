import Player from './player';

// Remote node.js player
Player.register('node', (function($) {

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
  params = null,

  updateStatus = function(callback) {
    $.ajax({
      url:'http://' + params.address + '/?action=status',
      dataType:'jsonp',
      success:callback
    });
  },

  statusCallback = function(data) {

    switch (data.status) {
      case 'playing':
        // Set a timer to go off when the media is done playing
        playing = true;
        clearTimeout(mediaEndTimer);
        mediaEndTimer = setTimeout(function() { updateStatus(statusCallback); }, 3000);
        break;
      case 'idle':
        if (playing === true) {
          clearTimeout(mediaEndTimer);
          playing = false;
          mediaEndCallback();
        } else {
          clearTimeout(mediaEndTimer);
          mediaEndTimer = setTimeout(function() { updateStatus(statusCallback); }, 3000);
        }
        break;
    }

  },

  getStatus = function() {
    return status;
  },

  pause = function() {

  },

  positionCallback = function() {
    return { position:0 };
  },

  playMedia = function(id) {
    $.ajax({
      url:'http://' + params.address + '/?action=play&id=' + id,
      dataType:'jsonp',
      success:statusCallback
    });
  },

  playSong = function(id, callback, updateCallback) {

    if (typeof(callback) === 'function') {
      mediaEndCallback = callback;
      clearTimeout(mediaEndTimer);
      playing = false;
      playMedia(id);
    }

  },

  playVideo = function(episode, show) {
    playMedia(episode.id);
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
  },

  setParams = function(val) {
    params = val;
  };

  return { getStatus:getStatus, pause:pause, playSong:playSong, playVideo:playVideo, kill:kill, isPlaying:isPlaying, setParams:setParams };

}()));