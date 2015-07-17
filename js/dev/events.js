import domElements from './dom-elements';
import dataManager from './data-manager';
import playlistManager from './playlist-manager';

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

};

export function albumClick(e) {
  var
  $this = $(e.target),
  albumId = $this.attr('album_id');

  if (albumId === undefined) {
    $this = $this.parents('li:first');
    albumId = $this.attr('album_id');
  }

  var songs = dataManager.getSongsByAlbumId(albumId), out = '<li song_id="back" class="song">Back</li><li song_id="all" class="song">Play All</li>';

  for (var i in songs) {
    if (songs.hasOwnProperty(i)) {
      var song = songs[i];
      out += '<li song_id="' + song.id + '" class="song">' + song.title + '</li>';
    }
  }

  domElements.$mainList.animate({left:"-298px"}, 200);
  domElements.$songList.attr('album_id', albumId).html(out).animate({left:"0"}, 200).undelegate('li.song', 'click').delegate('li.song', 'click', songClick);

};

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
};