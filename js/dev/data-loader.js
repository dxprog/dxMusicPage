import dataManager from './data-manager';
import playlistManager from './playlist-manager';
import tagManager from './tag-manager';
import displayManager from './display-manager';

// Data loaders/initializers
let dataLoader = {
  albums:function(d) {

    dataManager.albums.push(dataManager.defaultAlbum);
    var albumCopy = JSON.parse(JSON.stringify(dataManager.defaultAlbum));
    albumCopy.id = 0;
    dataManager.albums.push(dataManager.albumCopy);

    // Sort the albums alphabetically
    dataManager.albums.sort(function(a, b) {
      var retVal = 0;
      if (null != a.title && null != b.title) {
        var x = a.title.toLowerCase(), y = b.title.toLowerCase();

        // Sort around articles
        if (x.substr(0, 4) === "the ") { x = x.substr(4); }
        if (x.substr(0, 3) === "an ") { x = x.substr(3); }
        if (x.substr(0, 2) === "a ") { x = x.substr(2); }
        if (y.substr(0, 4) === "the ") { y = y.substr(4); }
        if (y.substr(0, 3) === "an ") { y = y.substr(3); }
        if (y.substr(0, 2) === "a ") { y = y.substr(2); }

        retVal = (x < y) ? -1 : (x == y) ? 0 : 1;
      }
      return retVal;
    });

  },

  songs:function() {

    // Sort the songs by track and disc number
    dataManager.songs.sort(function(a, b) {
      var
      x = parseInt(a.meta.track), // Track #1
      y = parseInt(b.meta.track), // Track #2
      i = parseInt(a.meta.disc), // Disc #1
      j = parseInt(b.meta.disc); // Disc #2
      return (i < j) ? -1 : (i == j) ? (x < y) ? -1 : (x == y) ? 0 : 1 : 1;
    });
    playlistManager.init();

  },

  shows:function() {

    // Sort the albums alphabetically
    dataManager.shows.sort(function(a, b) {
      var retVal = 0;
      if (null != a.title && null != b.title) {
        var x = a.title.toLowerCase(), y = b.title.toLowerCase();

        // Sort around articles
        if (x.substr(0, 4) === "the ") { x = x.substr(4); }
        if (x.substr(0, 3) === "an ") { x = x.substr(3); }
        if (x.substr(0, 2) === "a ") { x = x.substr(2); }
        if (y.substr(0, 4) === "the ") { y = y.substr(4); }
        if (y.substr(0, 3) === "an ") { y = y.substr(3); }
        if (y.substr(0, 2) === "a ") { y = y.substr(2); }

        retVal = (x < y) ? -1 : (x == y) ? 0 : 1;
      }
      return retVal;
    });

  },

  videos:function() {
    dataManager.videos.sort(function(a, b) {
      var
      i = a.meta.hasOwnProperty('episode') ? parseInt(a.meta.episode) : 0,
      j = b.meta.hasOwnProperty('episode') ? parseInt(b.meta.episode) : 0,
      x = a.meta.hasOwnProperty('season') ? parseInt(a.meta.season) : 0,
      y = b.meta.hasOwnProperty('season') ? parseInt(b.meta.season) : 0;
      return (x < y) ? -1 : (x == y) ? (i < j) ? -1 : (i == j) ? 0 : 1 : 1;
    });
  },

  content:function(d) {
    var item = null;

    // Blank any existing data
    dataManager.albums = [];
    dataManager.songs = [];
    dataManager.shows = [];
    dataManager.videos = [];

    for (var i = 0, count = d.body.length; i < count; i++) {
      item = d.body[i];
      if (dataManager.hasOwnProperty(item.type + 's')) {
        dataManager[item.type + 's'].push(item);
      }
    }

    // Have each content type perform whatever initial data actions need to be performed
    dataLoader.albums();
    dataLoader.songs();
    dataLoader.shows();
    dataLoader.videos();
    tagManager.load();

    if (window.location.hash) {
      playlistManager.queueSong(window.location.hash.replace('#', ''));
    }

    // Display whatever list needs to be displayed
    var list = $.cookie('list');
    displayManager.listByType(list);

  }
};

export default dataLoader;