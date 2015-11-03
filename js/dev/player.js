import Fiber from 'fiber';

/**
 * Player "class" definition
 * Copyright (c) 2010 Matt Hackmann
 **/

let Player = Fiber.extend(function() {

  return {
    // Sets the defualt player
    init: function() {
      this._players = {};
      this._currentPlayer = null;
    },

    register: function(name, player) {
      this._players[name] = player;
      if (!this._currentPlayer) {
        this.setPlayer(name);
      }
    },

    // Returns the current playback status (from plug-in)
    getStatus: function() {
      return this._currentPlayer.getStatus();
    },

    // Plays the current media (from plug-in)
    playSong: function(a,b,c) {
      return this._currentPlayer.playSong(a,b,c);
    },

    // Plays a video
    playVideo: function(a,b) {
      return this._currentPlayer.playVideo(a,b);
    },

    // Pauses the current media (from plug-in)
    pause: function() {
      return this._currentPlayer.pause();
    },

    // Set's the player plug-in, kills the old one if necessary
    setPlayer: function(playerType, params) {

      if (this._currentPlayer !== null) {
        this._currentPlayer.kill();
      }

      if (typeof(this._players[playerType]) == 'object') {
        this._currentPlayer = this._players[playerType];
        if (this._currentPlayer.hasOwnProperty('setParams')) {
          this._currentPlayer.setParams(params);
        }
        return true;
      } else {
        return false;
      }

    },

    isPlaying: function() {
      return this._currentPlayer.isPlaying();
    }
  };

});

if (!window._playerInstance) {
  window._playerInstance = new Player();
}

export default window._playerInstance;