import domElements from './dom-elements';
import dataManager from './data-manager';
import playlistManager from './playlist-manager';
import displayManager from './display-manager';
import player from './player';

// More stuff that will be refactored into oblivion
export function songClick(e) {

  var
  $this = $(e.target),
  songId = $this.attr('song_id');
  if (songId === undefined) {
    $this = $this.parents('li:first');
    songId = $this.attr('song_id');
  }

  switch (songId) {
    case 'back':
      domElements.$mainList.animate({left:"0"}, 200);
      domElements.$songList.animate({left:"298px"}, 200);
      break;
    case 'all':
      var
      album_id = $this.parents('ol:first').attr('album_id'),
      songs = dataManager.getSongsByAlbumId(album_id);

      for (var i = 0, count = songs.length; i < count; i++) {
        playlistManager.queueSong(songs[i].id);
      }

      break;
    default:
      // player.playSong(songId, function(){}, playProgress);
      playlistManager.queueSong(songId);
      break;
  }

}

export function albumClick(e) {
  var
  $this = $(e.target),
  albumId = $this.attr('album_id');

  if (albumId === undefined) {
    $this = $this.parents('li:first');
    albumId = $this.attr('album_id');
  }

  var songs = dataManager.getSongsByAlbumId(albumId), out = '<li song_id="back" class="song">Back</li><li song_id="all" class="song">Play All</li>';

  songs.forEach(song => {
    out += '<li song_id="' + song.id + '" class="song">' + song.title + '</li>';
  });

  domElements.$mainList.animate({left:"-298px"}, 200);
  domElements.$songList.attr('album_id', albumId).html(out).animate({left:"0"}, 200).undelegate('li.song', 'click').delegate('li.song', 'click', songClick);

}

export function playProgress(e) {
  if ('playing' === e.state) {
    var
    p = e.position / e.length * 100,
    left = e.length - e.position,
    m = Math.floor(e.position / 60),
    s = Math.floor(e.position) % 60,
    mLeft = Math.floor(left / 60),
    sLeft = Math.floor(left) % 60;
    s = s.toString().length === 1 ? '0' + s : s;
    sLeft = sLeft.toString().length === 1 ? '0' + sLeft : sLeft;
    domElements.$playHead.css('left', p + '%');
    domElements.$playIn.text(m + ':' + s);
    domElements.$playOut.text('-' + mLeft + ':' + sLeft);
  }
}

export function playlistClick(e) {
  var list = e.currentTarget.getAttribute('data-list');
  if (list.length > 0) {
    list = list.split(',');
    for (var i = 0, count = list.length; i < count; i++) {
      playlistManager.queueSong(list[i]);
    }
  }
}

export function vlcClick(e) {
  /* $vlc.toggleClass('disabled');
  playerType = playerType === 'vlc' ? 'html5' : 'vlc';
  player.setPlayer(playerType);
  $.cookie('player', playerType, {expires:90});
  */
}

export function playPauseClick(e) {
  player.pause();
  var $this = $(e.target), status = player.getStatus();
  if (status.state === 'playing') {
    $this.removeClass('play').addClass('pause');
  } else {
    $this.removeClass('pause').addClass('play');
  }
}

export function optionClick(e) {
  $('#options').slideToggle();
}

export function optionsClick(e) {
  var type = $(e.target).text();
  displayManager.listByType(type);
  $.cookie('list', type, {expires:90});
}

export function randomClick(e) {
  $(e.target).toggleClass('enabled');
  playlistManager.toggleRandom();
}

export function nextClick(e) {
  playlistManager.nextSong();
}

export function savePlaylistClick(e) {
  playlistManager.save();
}