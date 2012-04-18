/**
 * Player "class" definition
 * Copyright (c) 2010 Matt Hackmann
 **/

var Player = function() {
	
	// Reference to the player plug-in that will handle the media
	this.currentPlayer = null;
	
	// Sets the defualt player
	this.init = function() {
		this.setPlayer('html5');
	};
	
	// Returns the current playback status (from plug-in)
	this.getStatus = function() {
		return this.currentPlayer.getStatus();
	};
	
	// Plays the current media (from plug-in)
	this.playSong = function(a,b,c) {
		return this.currentPlayer.playSong(a,b,c);
	};
	
	// Plays a video
	this.playVideo = function(a,b) {
		return this.currentPlayer.playVideo(a,b);
	}
	
	// Pauses the current media (from plug-in)
	this.pause = function() {
		return this.currentPlayer.pause();
	};
	
	// Set's the player plug-in, kills the old on if necessary
	this.setPlayer = function(playerType) {
		if (this.currentPlayer !== null) {
			this.currentPlayer.kill();
		}
		if (typeof(this[playerType]) == 'object') {
			this.currentPlayer = this[playerType];
			return true;
		} else {
			return false;
		}
	};
	
	this.isPlaying = function() {
		return this.currentPlayer.isPlaying();
	}
	
};
