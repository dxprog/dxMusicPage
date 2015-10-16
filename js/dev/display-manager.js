import dataManager from './data-manager';
import domElements from './dom-elements';
import templates from './templating';
import { songClick, albumClick, playlistClick } from './events';
import dx from './dxapi';

const ART_LOCATION = 'http://dxmp.s3.amazonaws.com/images/';

function shows() {
  var out = '', item = null, art = '';

  for (var i in dataManager.shows) {
    item = dataManager.shows[i];
    if (item.children > 0) {
      art = item.meta.hasOwnProperty('art') ? item.meta.art : false;
      out += art ? templates.render('showWithArt', { id:item.id, art:art, title:item.title }) : templates.render('show', { id:item.id, title:item.title });
    }
  }

  out = templates.render('videoContent', { items:out });
  if (null === domElements.$videoList) {
    domElements.$videoList = domElements.$main.append(templates.render('videoPane')).find('#videoList').animate({ top:'0%' });
  }
  domElements.$videoList.html(out).undelegate('li.show', 'click').delegate('li.show', 'click', video.showClick);
  domElements.$videoList.html(out).undelegate('li.episode', 'click').delegate('li.episode', 'click', video.episodeClick);

}

function albums() {

  domElements.$mainList.empty();
  var out = '', art = '';

  for (var i in dataManager.albums) {
    var item = dataManager.albums[i];
    if (item.children > 0) {
      art = dataManager.getAlbumArt(item);
      out += templates.render('albumListItem', { id:item.id, art:art, title:item.title });
    }
  }

  $(out).appendTo(domElements.$mainList);
  domElements.$mainList.undelegate('li.album', 'click').delegate('li.album', 'click', albumClick);

}

function songChartTotal(data) {
  var chartData = [[]];
  $('.songInfo').append('<div id="playCountGraph"></div>');
  for (var i = 0, count = data.body.length; i < count; i++) {
    chartData[0].push({ value:data.body[i].total, label:data.body[i].user });
  }
  $('#playCountGraph').dxPlot(chartData, { animate:true, colors:['rgba(200, 209, 83, .6)', 'rgba(94, 224, 203, .6)'] });
}

function songChartTime(data) {

  var

    $body = $('body'),
    lastIndex = null,
    lastSeries = null,

    xTicks = function(data) {
      var date = new Date(data.min), retVal = [], hours = 0;
      for (var i = 0; i < 30; i++) {
        retVal.push(date.getTime());
        date.setDate(date.getDate() + 1);
      }
      return retVal;
    },

    tickFormat = function(data, axis) {
      var date = new Date(data), retVal = '';
      retVal = date.getDate();
      return retVal;
    },

    plotHover = function(event, pos, item) {
      if (item && (item.dataIndex != null || item.dataSeries != null)) {
        $('#graphTip').remove();
        var date = new Date(item.datapoint[0]);
        $body.append('<span id="graphTip" style="left:' + item.pageX + 'px; top:' + (item.pageY - 80) + 'px;"><strong>' + months[date.getMonth()].substr(0, 3) + ' ' + date.getDate() + '</strong><br />' + Math.round(item.datapoint[1]) + ' plays</span>');
        lastIndex = item.dataIndex;
      } else {
        $('#graphTip').remove();
      }
    },

    userPlays = {
      lines:{ show:true, fill:true },
                curvedLines:{ apply:true, fit:true },
      // points:{ show:true },
      color:'#c8d153',
      label:'My Plays',
      data:[]
    },

    othersPlays = {
      lines:{ show:true, fill:true },
                curvedLines:{ apply:true },
      // points:{ show:true },
      color:'#5ee0cb',
      label:'Others',
      data:[]
    };

  for (var i in data.body) {
    if (data.body.hasOwnProperty(i)) {

      var date = data.body[i].date * 1000;
      userPlays.data.push([date, data.body[i].user_plays]);
      othersPlays.data.push([date, data.body[i].others_plays]);

    }
  }

  var $graph = $('#dayGraph');

  var t = new Date();
  t = (new Date(t.getFullYear() + '-01-01')).getTime();

  var plot = $.plot(
    $graph,
    [ userPlays, othersPlays ],
    {
      yaxis:{ show:false },
      xaxis:{ mode:'time', ticks:xTicks, tickFormatter:tickFormat, show:false },
      series:{ lines:{ show:true }, curvedLines: {  active:true } },
      grid:{ borderWidth:0, color:'#fff', hoverable: true, mouseActiveRadius: 10, },
      legend:{ position:'nw', noColumns:2, backgroundColor:'rgb(0, 0, 0, 0)' }
    }
  );

  $graph.bind('plothover', plotHover);

}

function songInfo(songId) {
  var
  song = dataManager.getItemById(songId, 'songs'),
  album = dataManager.getItemById(song.parent, 'albums'),

  art = dataManager.getAlbumArt(album),
  tags = '',
  info = '';

  for (var i in song.tags) {
    tags += templates.render('songTags', {'tag_name':song.tags[i].name});
  }

  info = templates.render('songInfo', {'album_title':album.title, 'song_title':song.title, 'art':ART_LOCATION + art, 'tags':tags});
  document.title = song.title + ' - dxMusicPage';
  domElements.$nowPlaying.find('.songInfo').animate({'top':'+=200px', 'opacity':'0'}, 500, function() { $(this).remove(); });
  domElements.$nowPlaying
    .append(info)
    .find('.songInfo.new')
    .css({'opacity':'0', 'left':'230px'})
    .animate({'left':'30px', 'opacity':'1'}, 500, function() {
      $(this).removeClass('new');
      dx.call('stats', 'getTrackUsers', { id:song.id }, songChartTotal);
      // dx.call('stats', 'getUserPlaysByDay', { id:song.id, user:userName }, displayManager.songChartTime);
      domElements.$main.find('.wallpaper').fadeOut(500, function() { $(this).remove(); });
      if (null != album.meta && typeof (album.meta.wallpaper) === 'string') {
        var
        wallpaper = templates.render('wallpaper', {'src':ART_LOCATION + album.meta.wallpaper}),
        $wallpaper = $(wallpaper);
        var image = new Image();
        image.onload = function() {
          domElements.$main.prepend($wallpaper);
          domElements.$main.find('.wallpaper.new').fadeIn(500, function() { $(this).removeClass('new'); });
        };
        image.src = ART_LOCATION + album.meta.wallpaper;
      }
    });

}

function songList(json) {

  var item,
      album,
      art,
      out;

  domElements.$mainList.empty();

  for (var i in json.body) {
    item = json.body[i];
    album = dataManager.getItemById(item.album, 'albums');
    art = dataManager.getAlbumArt(album);
    out = templates.render('songWithArt', { id:item.id, art:art, title:item.title, album:item.album });
    $(out).appendTo(domElements.$mainList);
  }

  domElements.$mainList.undelegate('li.song', 'click').delegate('li.song', 'click', songClick);

}

function playlists() {
  dx.call('content', 'getContent', { contentType:'list' }, function(data) {
    if ('content' in data.body && data.body.content.length > 0) {
      var out = '';
      domElements.$mainList.empty();
      for (var i in data.body.content) {
        if (data.body.content.hasOwnProperty(i)) {
          var list = data.body.content[i];
          if (list.body.length > 0) {
            out += '<li data-list="' + list.body + '" class="song">' + list.title + '</li>';
          }
        }
      }
      domElements.$mainList.animate({left:"-298px"}, 200);
      domElements.$songList.html(out).animate({left:"0"}, 200).undelegate('li.song', 'click').delegate('li.song', 'click', playlistClick);
    }
  });
}

function trending() {
  dx.call('stats', 'getBest', null, songList);
  domElements.$mainList.animate({left:"0"}, 200);
  domElements.$songList.animate({left:"298px"}, 200);
}

function userTracks() {
  dx.call('stats', 'getTopUserTracks', { user:userName }, songList);
  domElements.$mainList.animate({left:"0"}, 200);
  domElements.$songList.animate({left:"298px"}, 200);
}

function latest() {
  dx.call('dxmp', 'getLatest', null, songList);
  domElements.$mainList.animate({left:"0"}, 200);
  domElements.$songList.animate({left:"298px"}, 200);
}

function displayRandomPool() {
  var songs = playlistManager.randomPool;
}

function listByType(type) {
  type = null != type ? type : '';
  switch (type.toLowerCase()) {
    case 'my most played':
      type = 'My Most Played';
      userTracks();
      break;
    case 'playlists':
      type = 'Playlists';
      playlists();
      break;
    case 'shows':
      type = 'Shows';
      shows();
      break;
    case 'trending':
      type = 'Trending';
      trending();
      break;
    case 'latest':
      type = 'Latest';
      latest();
      break;
    default:
      type = 'Albums';
      albums();
      break;
  }
  $('#option h2').text(type);
}

// List displays
export default {
  shows: shows,
  albums: albums,
  songChartTotal: songChartTotal,
  songChartTime: songChartTime,
  songInfo: songInfo,
  songList: songList,
  playlists: playlists,
  trending: trending,
  userTracks: userTracks,
  latest: latest,
  displayRandomPool: displayRandomPool,
  listByType: listByType
};