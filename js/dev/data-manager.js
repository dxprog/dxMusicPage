let dataManager = {

  // Data arrays
  albums:[],
  songs:[],
  shows:[],
  videos:[],
  tags:[],

  defaultAlbum:{
    id:null,
    title:'No Album',
    meta:{art:'no_art.png'},
    children:1
  },

  // Helper methods
  getSongsByAlbumId:function(id) {
    return this.getItemsByParentId(id, 'songs');
  },

  getItemsByParentId:function(id, itemType) {
    var retVal = [], item = null;
    for (var i in dataManager[itemType]) {
      if (dataManager[itemType].hasOwnProperty(i)) {
        if (dataManager[itemType][i].parent === id) {
          retVal.push(dataManager[itemType][i]);
        }
      }
    }
    return retVal;
  },

  getItemById:function(id, itemType) {
    var retVal = null;

    for (var i in dataManager[itemType]) {
      if (dataManager[itemType].hasOwnProperty(i)) {
        var item = dataManager[itemType][i];
        if (parseInt(item.id) === parseInt(id)) {
          retVal = item;
          break;
        }
      }
    }

    return retVal;
  },

  getSongGenres:function() {

    var genres = {}, retVal = [], i = null;

    for (i in dataManager.songs) {
      if (dataManager.songs.hasOwnProperty(i)) {
        var song = dataManager.songs[i];
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

  checkSongForTags:function(song, tags) {
    var retVal = false, tag = null;

    if (typeof(song) === 'object' && null != song && ((typeof(song.tags) === 'object' && null != song.tags && song.tags.length > 0) || tags.indexOf('all') === 0)) {
      if (!(typeof(song.tags) === 'object' && null != song.tags && song.tags.length > 0) && tags.indexOf('all') === 0) {
        retVal = true;
      } else {
        for (var i = 0, count = song.tags.length; i < count; i++) {
          tag = song.tags[i].name;
          if (tags.indexOf(',' + tag + ',') > -1) {
            retVal = true;
          } else if (tags.indexOf(',-' + tag + ',') !== -1) {
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
    for (var i = 0, count = dataManager.songs.length; i < count; i++) {
      for (var j = 0, tagCount = dataManager.songs[i].tags.length; j < tagCount; j++) {
        if ($.inArray(dataManager.songs[i].tags[j].name, dataManager.tags) === -1) {
          dataManager.tags.push(dataManager.songs[i].tags[j].name);
        }
      }
    }
    dataManager.tags.sort();
  }

};

export default dataManager;