import $ from 'jquery';

// Singleton
if (!window._data) {
  console.log('creating data');
  window._data = {
    albums:[],
    songs:[],
    shows:[],
    videos:[],
    tags:[]
  };
}

let dataManager = {

  // Pointers for now
  albums: (function() { return window._data.albums; }()),
  songs: window._data.songs,
  shows: window._data.shows,
  videos: window._data.videos,
  tags: window._data.tags,

  defaultAlbum:{
    id:null,
    title:'No Album',
    meta:{art:'no_art.png'},
    children:1
  },

  // Helper methods
  getSongsByAlbumId:function(id) {
    return dataManager.getItemsByParentId(id, 'songs');
  },

  getItemsByParentId:function(id, itemType) {
    var retVal = [],
        item = null,
        data = dataManager[itemType];

    for (var i in data) {
      if (data.hasOwnProperty(i)) {
        if (data[i].parent === id) {
          retVal.push(data[i]);
        }
      }
    }
    return retVal;
  },

  getItemById:function(id, itemType) {
    var retVal = null,
        data = dataManager[itemType];

    for (var i in data) {
      if (data.hasOwnProperty(i)) {
        var item = data[i];
        if (parseInt(item.id) === parseInt(id)) {
          retVal = item;
          break;
        }
      }
    }

    return retVal;
  },

  getSongGenres:function() {

    var genres = {}, retVal = [], i = null,
        data = dataManager.songs;

    for (i in data) {
      if (data.hasOwnProperty(i)) {
        var song = data[i];
        if (null != song.meta && null != song.meta.genre) {
          genres[song.meta] = true;
        }
      }
    }

    for (i in genres) {
      if (genres.hasOwnProperty(i)) {
        retVal.push(i);
      }
    }

    return retVal;

  },

  songMatchesTags:function(song, tags) {
    let retVal = tags instanceof Array && tags.length === 0;
    let songTags = song.tags instanceof Array ? song.tags.map((tag) => tag.name) : null;

    if (!retVal && tags instanceof Array && typeof song === 'object') {
      if (song.hasOwnProperty('tags') && songTags) {

        // The song is considered matching until one tag conflicts
        retVal = true;

        for (let i = 0, count = tags.length; i < count; i++) {
          let tag = tags[i];
          let isExclude = tag.charAt(0) === '-';
          tag = isExclude ? tag.substr(1) : tag;

          if ((isExclude && songTags.indexOf(tag) > -1) || (!isExclude && songTags.indexOf(tag) === -1)) {
            retVal = false;
            break;
          }

        }
      }
    }

    return retVal;
  },

  getAlbumArt:function(album) {
    var retVal = 'no_art.png';
    if (null != album && null != album.meta && typeof(album.meta.art) !== 'undefined') {
      retVal = album.meta.art;
    }
    return retVal;
  },

  populateTags:function() {
    var data = dataManager.songs,
        tags = dataManager.tags;
    for (var i = 0, count = data.length; i < count; i++) {
      for (var j = 0, tagCount = data[i].tags.length; j < tagCount; j++) {
        if ($.inArray(data[i].tags[j].name, tags) === -1) {
          tags.push(data[i].tags[j].name);
        }
      }
    }
    tags.sort();
  }

};

if (!window._dataManager) {
  window._dataManager = dataManager;
}

export default window._dataManager;