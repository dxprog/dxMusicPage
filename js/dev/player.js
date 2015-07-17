/**
 * Player "class" definition
 * Copyright (c) 2010 Matt Hackmann
 **/

export default Fiber.extend(function() {

  // Reference to the player plug-in that will handle the media
  var currentPlayer = null;

  return {
    // Sets the defualt player
    init: function() {
      this.setPlayer('html5');
    },

    // Returns the current playback status (from plug-in)
    getStatus: function() {
      return this.currentPlayer.getStatus();
    },

    // Plays the current media (from plug-in)
    playSong: function(a,b,c) {
      return this.currentPlayer.playSong(a,b,c);
    },

    // Plays a video
    playVideo: function(a,b) {
      return this.currentPlayer.playVideo(a,b);
    },

    // Pauses the current media (from plug-in)
    pause: function() {
      return this.currentPlayer.pause();
    },

    // Set's the player plug-in, kills the old on if necessary
    setPlayer: function(playerType, params) {

      if (this.currentPlayer !== null) {
        this.currentPlayer.kill();
      }

      if (typeof(this[playerType]) == 'object') {
        this.currentPlayer = this[playerType];
        if (this.currentPlayer.hasOwnProperty('setParams')) {
          this.currentPlayer.setParams(params);
        }
        return true;
      } else {
        return false;
      }

    },

    isPlaying: function() {
      return this.currentPlayer.isPlaying();
    }
  };

});