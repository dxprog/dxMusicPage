import $ from 'jquery';

import dx from './dxapi';
import cookie from './cookie';
import Player from './player';
import templates from './templating';
import dataManager from './data-manager';
import displayManager from './display-manager';
import dataLoader from './data-loader';
import playlistManager from './playlist-manager';
import tagManager from './tag-manager';
import domElements from './dom-elements';
import {
  savePlaylistClick,
  nextClick,
  songClick,
  albumClick,
  playPauseClick,
  optionClick,
  optionsClick,
  randomClick } from './events';
import helpers from './helpers';

var

months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],

// Various objects and variables
playerType = cookie.eat('player') || 'html5',
userName = cookie.eat('userName') || false,
player = Player,
searchList = [],
watched = cookie.eat('watched') || '',
actionTimer = null,

isMobile = (/(iphone|sonyericsson|blackberry|iemobile|windows ce|windows phone|nokia|samsung|android)/ig).test(window.navigator.userAgent),

createPerma = function(val) {
  var remove = /(\'|\"|\.|,|~|!|\?|&lt;|&gt;|@|#|\$|%|\^|&amp;|\*|\(|\)|\+|=|\/|\\|\||\{|\}|\[|\]|-|--)/ig;
  if (null != val) {
    val = val.replace(remove, '');
    val = val.replace(/\s\s/g, ' ').replace(/\s/g, '-').toLowerCase();
  }
  return val;
},

upload = function(file, contentId, uploadId) {

  // Ugh... we'll do this manually
  var

  $info = $('#file' + uploadId),
  $progress = $info.find('span'),
  progWidth = $progress.width(),
  boundary = '------DxmpFileUploadBoundary' + (new Date()).getTime(),
  post = '--' + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="' + (file.fileName || file.name) + '"\r\nContent-Type: application/octet-stream\r\n\r\n',
  xhr = new XMLHttpRequest(),
  reader = new FileReader(),

  progress = function(e) {
    var
    percent = Math.round(e.loaded / e.total * 100),
    msg = percent === 0 ? 'Waiting' : percent === 100 ? 'Syncing' : percent + '%';
    domElements.$progress.html(msg).css('background-position', (Math.abs(percent - 100) / 100 * progWidth * -1) + 'px 0');
  },

  readComplete = function(e) {
    post += btoa(reader.result) + '\r\n--' + boundary + '\r\n--' + boundary + '--\r\n';

    var url = '';
    switch (file.type) {
      case 'audio/mp3':
        url = '/api/?type=json&method=dxmp.postUploadSong';
        break;
      case 'image/jpg':
      case 'image/jpeg':
      case 'image/gif':
      case 'image/png':
        url = '/api/?type=json&method=dxmp.postImage&id=' + contentId;
        break;
    }

    xhr.open('POST', url + '&upload=' + uploadId, true);
    xhr.setRequestHeader('content-type', 'multipart/form-data; boundary=' + boundary);
    xhr.upload.addEventListener('progress', progress);
    xhr.send(post);
    xhr.onload = uploadComplete;
  },

  uploadComplete = function(e) {
    var data = $.parseJSON(xhr.responseText);
    if (typeof(data.body.message) !== 'string') {
      $info.removeClass('uploading').addClass('complete').html(data.body.title);
      dx.call('dxmp', 'getData', { 'noCache':'true' }, dataLoader.content);
    } else {
      $info.removeClass('uploading').addClass('error').append('<span>' + data.body.message + '</span>');
    }
  };

  reader.onload = readComplete;
  reader.readAsBinaryString(file);

},

// Drag events
drag = {

  over:function(e) {
    e.preventDefault();
  },

  drop:function(e) {
    var

    $target = $(e.target),
    files = e.dataTransfer.files,
    albumId = null,
    uploadId = (new Date()).getTime();
    $('.fader').fadeIn();
    $('#uploads').slideDown();

    for (var i = 0, f; typeof (f = files[i]) !== 'undefined'; i++) {
      uploadId += i;
      switch (files[i].type) {
        case 'image/jpg':
        case 'image/jpeg':
        case 'image/gif':
        case 'image/png':
          if ($target.parents('li[album_id]').length > 0) {
            albumId = $target.parents('li[album_id]').attr('album_id');
          } else if ($target.attr('show_id')) {
            albumId = $target.attr('show_id');
          } else {
            var song = playlistManager.getPlayingSong();
            if (null == song || song.parent <= 0 || song.parent == undefined) {
              return;
            }
            albumId = song.parent;
          }
        case 'audio/mp3':
          $('#uploads ul').append('<li id="file' + uploadId + '" class="uploading">' + (files[i].fileName || files[i].name) + '<span class="progress">0%</span></li>');
          upload(files[i], albumId, uploadId);
          break;
        default:
          alert('Only MP3s and images can be uploaded');
          break;
      }
    }
    e.preventDefault();
  },

  close:function(e) {
    $('#uploads').slideUp();
    $('.fader').fadeOut();
  },

  init:function() {
    var body = document.getElementsByTagName('body')[0];
    body.addEventListener('dragover', this.over, false);
    body.addEventListener('drop', this.drop, false);
    $('#uploads .close').click(this.close);
  }

},

editAlbumClick = function(e) {

},

devices = {
  $:$('#devices'),
  listDown:false,
  init:function() {
    $('#devices').on('click', 'li', devices.listItemClick);
    $('#vlc').on('click', devices.listDisplay);
  },
  listDisplay:function() {
    dx.call('device', 'getDevices', {}, devices.listCallback);
  },
  listCallback:function(data) {
    var
      i,
      device,
      out = '';

    if (!devices.listDown) {
      for (i in data.body) {
        if (data.body.hasOwnProperty(i)) {
          device = data.body[i];
          out += '<li data-address="' + device.ip + ':' + device.port + '">' + device.name + '</li>';
        }
      }
      out += '<li data-address="local">Browser</li>';
      devices.$.html(out).slideDown();
      devices.listDown = true;
    } else {
      devices.listDown = false;
      devices.$.slideUp();
    }
  },
  listItemClick:function(e) {
    var address = e.currentTarget.getAttribute('data-address');
    if (address === 'local') {
      player.setPlayer('html5');
      domElements.$vlc.addClass('disabled');
    } else {
      player.setPlayer('node', { address:address });
      domElements.$vlc.removeClass('disabled');
    }
    devices.$.slideUp();
    devices.listDown = false;

  }
},

music = {

  smartPlaylistClick:function(e) {
    var song = playlistManager.getPlayingSong();
    if (null != song) {
      dx.call('dxmp', 'buildSmartPlaylist', { id:song.id }, function(data) {
        if (data.body.length > 0) {
          for (var i = 0, count = data.body.length; i < count; i++) {
            playlistManager.queueSong(data.body[i]);
          }
        }
      });
    }
  }

},

// Video related shit
video = {

  showClick:function(e) {
    var
    $target = e.currentTarget,
    showId = $target.getAttribute('show_id'),
    show = dataManager.getItemById(showId, 'shows'),
    videos = dataManager.getItemsByParentId(showId, 'videos'),
    video = null,
    noSeason = [],
    lastSeason = null,
    list = '',
    out = '',
    hasSeen = false;

    for (var i = 0, count = videos.length; i < count; i++) {
      video = videos[i];
      if (video.meta.hasOwnProperty('season') && null != video.meta.season) {
        if (lastSeason != video.meta.season) {
          list += templates.render('season', { season:'Season ' + video.meta.season });
        }
        hasSeen = watched.indexOf(',' + video.id) > -1 ? ' watched' : '';
        list += templates.render('episode', { thumb:video.id + '_' + createPerma(video.title) + '_3.jpg', title:video.title, id:video.id, episode:video.meta.episode, watched:hasSeen });
        lastSeason = video.meta.season;
      } else {
        noSeason.push(video);
      }
    }

    if (noSeason.length > 0) {
      list += templates.render('season', { season:'Other Episodes' });
      for (var i = 0, count = noSeason.length; i < count; i++) {
        video = noSeason[i];
        hasSeen = watched.indexOf(',' + video.id) > -1 ? ' watched' : '';
        list += templates.render('episode', { thumb:video.id + '_' + createPerma(video.title) + '_3.jpg', title:video.title, id:video.id, episode:video.meta.episode, watched:hasSeen });
      }
    }

    domElements.$videoList.html(templates.render('videoContent', { items:list }));
    domElements.$videoList.find('.navigation .show').html(show.title);

    if (show.meta.hasOwnProperty('wallpaper')) {
      domElements.$videoList.find('.wallpaper').fadeOut(500, function() { $(this).remove(); });
      domElements.$videoList.prepend('<img src="images/' + show.meta.wallpaper + '" class="wallpaper" alt="show wallpaper" />');
    }

  },

  closeClick:function(e) {
    domElements.$videoList = $('#videoList');
    if ($('#video').length > 0) {
      $('#video').remove();
    } else {
      domElements.$videoList.animate({ top:'100%' }, function() { $videoList.remove(); $videoList = null; });
    }
  },

  headClick:function(e) {
    displayManager.shows();
  },

  episodeClick:function(e) {
    var
    $target = e.currentTarget,
    episodeId = $target.getAttribute('episode_id'),
    episode = null,
    show = null;
    if (episodeId) {
      episode = data.getItemById(episodeId, 'videos');
      show = data.getItemById(episode.parent, 'shows');
      player.playVideo(episode, show);
      if (watched.indexOf(',' + episodeId) === -1) {
        watched += ',' + episodeId;
        cookie.bake('watched', watched, 365);
      }
    }
  }

},

searchKeyEvent = function(e) {

  var
  val = $.trim(domElements.$search.val()).toLowerCase(),
  out = '',
  list = val.length === 1 || searchList.length == 0 ? dataManager.songs : val.length > 1 ? searchList : null,
  newList = [],
  lastAlbum = 0;

  if (val.length > 0) {

    // out = '<li song_id="back" class="song">Back</li><li song_id="all" class="song">Play All</li>';

    // Find any song titles matching the query
    for (var i in list) {
      var title = list[i].title;
      if (null != title && title.toLowerCase().indexOf(val) > -1) {
        newList.push(list[i]);
      }
    }

    // Resort the list be album
    newList.sort(function(a, b) {
      var x = a.title, y = b.title;
      return x < y ? -1 : x == y ? 0 : 1;
    });

    // Build the display code
    for (var i in newList) {
      if (newList[i].parent != lastAlbum) {
        var
        album = dataManager.getItemById(newList[i].parent, 'albums'),
        art = dataManager.getAlbumArt(album);
        if (null != album) {
          out += templates.render('albumListItem', {id:newList[i].parent, art:art, title:album.title});
        }
        lastAlbum = newList[i].parent;
      }
      var
      find = new RegExp(val, 'gi'),
      match = find.exec(newList[i].title),
      title = newList[i].title.replace(match[0], '<strong>' + match[0] + '</strong>');
      out += templates.render('searchItem', {'song_id':newList[i].id, 'title':title, 'track':newList[i].meta.track});
    }

    if (val.length === 1) {
      searchList = newList;
    }

    domElements.$mainList.animate({left:"-298px"}, 200);
    domElements.$songList
      .html(out)
      .animate({left:"0"}, 200)
      .undelegate('li.song', 'click')
      .undelegate('li.album', 'click')
      .delegate('li.song', 'click', songClick)
      .delegate('li.album', 'click', albumClick);

    $('#option h2').text('Search Results');

  } else {
    searchList = [];
    domElements.$mainList.animate({left:"0"}, 200);
    domElements.$songList.animate({left:"298px"}, 200);
  }

  domElements.$songList.html(out);

},

// Checks for new user actions
actions = {

  timer:null,

  msgClick:function(e) {
    switch (e.currentTarget.getAttribute('data-action')) {
      case 'msg_user_play':
        playlistManager.queueSong(e.currentTarget.getAttribute('song_id'));
        break;
    }
    e.stopPropagation();
  },

  ajaxCallback:function(feedData) {

    clearTimeout(actions.timer);
    actions.timer = setTimeout(actions.timerCallback, 30000);

    if (feedData && feedData.body.length > 0) {

      var msgPlay = null;

      for (var i in feedData.body) {
        var item = feedData.body[i];

        switch (item.name) {
          case 'queue':
            playlistManager.queueSong(parseInt(item.param), 10);
            break;
          case 'msg_new_song':
            domElements.$message
              .attr('data-action', 'msg_new_song')
              .html('<p>A new song has been added!</p>')
              .stop().animate({ bottom:0 }, 400);
          case 'refresh':
            dx.call('dxmp', 'getData', {}, dataLoader.content);
            break;
          case 'msg_user_play':
            msgPlay = item;
            break;
        }

      }

      // We only need display the latest song play
      if (msgPlay) {
        var

        song = dataManager.getItemById(item.param.songId, 'songs'),
        album = dataManager.getItemById(song.parent, 'albums'),
        artwork = dataManager.getAlbumArt(album);

        domElements.$message
          .attr('data-action', 'msg_user_play')
          .attr('song_id', song.id)
          .html(templates.render('msgUserPlay', { album_art:artwork, user:item.param.user, title:song.title }))
          .stop().animate({ bottom:0 }, 400);

        setTimeout(function() {
          domElements.$message.stop().animate({ bottom:'-' + domElements.$message.outerHeight(true) + 'px' });
        }, 6400);

      }

    }

  },

  timerCallback:function() {
    if (!isMobile) {
      dx.call('actions', 'getCurrentActions', {}, actions.ajaxCallback);
    }
  },

  init:function() {
    $('body').on('click', '#message', actions.msgClick);
    actions.timerCallback();
  }
},

init = function() {

  if (!userName) {
    userName = prompt('Identify yourself (10 characters max)');
    cookie.bake('userName', userName, 90);
  }

  dx.call('dxmp', 'getData', {}, dataLoader.content);

  player.setPlayer(playerType);
  if (playerType == 'vlc') {
    domElements.$vlc.removeClass('disabled');
  }
  domElements.$nowPlaying.delegate('.tags li', 'click', tagManager.click);
  domElements.$playPause.click(playPauseClick);
  $('#random').click(randomClick);
  $('#next').click(nextClick);
  $('#smartPlaylist').click(music.smartPlaylistClick);
  $('#option').click(optionClick);
  $('#options li').click(optionsClick);
  $('#savePlaylist').click(savePlaylistClick);
  $('body').delegate('#videoList .close', 'click', video.closeClick);
  $('body').delegate('#videoList .head', 'click', video.headClick);
  domElements.$search.keyup(searchKeyEvent);
  devices.init();
  drag.init();
  $('#settings').click(dataManager.getSongGenres);
  actions.init();

};

$(init);
