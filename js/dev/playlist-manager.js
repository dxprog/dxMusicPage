import dataManager from './data-manager';
import displayManager from './display-manager';
import templates from './templating';
import helpers from './helpers';
import domElements from './dom-elements';
import player from './player';
import { playProgress } from './events';
import dx from './dxapi';

let playlistManager = function() {

  var

  list = [],
  currentSong = 0,
  playing = false,
  infinite = false,
  random = false,
  time = 0,
  songs = [],
  tags = [],

  queueSong = function(id) {
    var
    song = dataManager.getItemById(id, 'songs'),
    album = dataManager.getItemById(song.parent, 'albums'),
    art = dataManager.getAlbumArt(album),
    out = templates.render('albumListItem', {id:list.length, art:art, title:song.title});

    // Recalculate the playlist time
    if (parseInt(song.meta.duration, 10) > 0) {
      time += parseInt(song.meta.duration);
      $('#playlistLength').html(helpers.niceTime(time));
    }

    $(out).appendTo(domElements.$playlist);
    list.push(id);

    if (!playing) {
      playSong(currentSong);
    }

  },

  getEligibleSongs = function() {
    var retVal = [];
    $.cookie('random_tags', tags.join(','), { expires:90 });
    if (!tags || !tags.length) {
      retVal = dataManager.songs;
    } else {
      for (var i in dataManager.songs) {
        if (dataManager.songMatchesTags(dataManager.songs[i], tags)) {
          retVal.push(dataManager.songs[i]);
        }
      }
    }
    console.log(retVal);
    return retVal;
  },

  songComplete = function() {
    // dx.call('content', 'logContentView', { 'id':list[currentSong], 'user':userName });
    nextSong();
  },

  nextSong = function() {
    playing = false;
    domElements.$playlist.find('li[album_id=' + currentSong + ']').removeClass('playing').addClass('played');
    currentSong++;
    if (currentSong + 1 <= list.length) {
      playSong(currentSong);
    } else if (random) {
      if (songs.length > 0) {
        var rand = Math.floor(Math.random() * songs.length);
        while (typeof(songs[rand]) === 'undefined' || null == songs[rand].meta) {
          rand = Math.floor(Math.random() * songs.length);
        }
        queueSong(songs[rand].id);
      }
    }
  },

  excludeTag = function(tag) {
    var index = tags.indexOf(tag) === -1 ? tags.indexOf('-' + tag) : tags.indexOf(tag);
    if (index === -1) {
      tags.push('-' + tag);
    } else {
      tags[index] = '-' + tag;
    }
    songs = getEligibleSongs();
  },

  includeTag = function(tag) {
    var index = tags.indexOf(tag) === -1 ? tags.indexOf('-' + tag) : tags.indexOf(tag);
    if (index === -1) {
      tags.push(tag);
    } else {
      tags[index] = tag;
    }
    songs = getEligibleSongs();
  },

  removeTag = function(tag) {
    var index = tags.indexOf(tag);
    if (index !== -1) {
      tags.splice(index, 1);
    }

    index = tags.indexOf('-' + tag);
    if (index !== -1) {
      tags.splice(index, 1);
    }
    songs = getEligibleSongs();
  },

  playSong = function(index) {
    console.log('playing ', index);
    domElements.$playPause.attr('title', 'Pause Song').removeClass('play').addClass('pause');
    player.playSong(list[index], songComplete, playProgress);
    playing = true;
    domElements.$playlist.find('li[album_id=' + index + ']').addClass('playing');
    displayManager.songInfo(list[index]);
  },

  toggleRandom = function() {
    random = !random;
  },

  getPlayingSong = function() {
    return dataManager.getItemById(list[currentSong], 'songs');
  },

  toggleInfinite = function() {
    infinite = !infinite;
  },

  save = function() {

    var name = prompt('Name of playlist');
    if (name.length && list.length) {
      var ids = [];
      for (var i = 0, count = list.length; i < count; i++) {
        ids.push(list[i]);
      }
      $.ajax({
        url:'api/?type=json&method=dxmp.savePlaylist&songs=' + escape(ids.join(',')) + '&name=' + escape(name),
        dataType:'json'
      });
    }

  },

  init = function() {
    var tagCookie = $.cookie('random_tags');
    if (null != tagCookie && tagCookie.length > 0) {
      tags = tagCookie.split(',');
      songs = getEligibleSongs();
    } else {
      songs = dataManager.songs;
    }
  };

  return {
    queueSong:queueSong,
    nextSong:nextSong,
    toggleRandom:toggleRandom,
    toggleInfinite:toggleInfinite,
    getPlayingSong:getPlayingSong,
    excludeTag:excludeTag,
    includeTag:includeTag,
    removeTag:removeTag,
    save:save,
    init:init,
    randomPool:songs
  };

};

if (!window._playlistManager) {
  window._playlistManager = playlistManager();
}

export default window._playlistManager;