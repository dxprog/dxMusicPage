export default {

  // Template renderer
  render:function(tpl, vars) {
    var retVal = this[tpl];

    if (typeof(retVal) === 'string') {
      for (var i in vars) {
        if (vars.hasOwnProperty(i)) {
          var regEx = new RegExp('\{' + i + '\}', 'g');
          retVal = retVal.replace(regEx, vars[i]);
        }
      }
    } else {
      retVal = null;
    }

    return retVal;
  },

  // Templates
  albumListItem:'<li album_id="{id}" class="album"><img src="http://dxmp.us/thumb.php?file={art}&width=50&height=50" /><p>{title}</p></li>',
  songWithArt:'<li song_id="{id}" album_id="{album}" class="song"><img src="http://dxmp.us/thumb.php?file={art}&width=50&height=50" /><p>{title}</p></li>',
  songItem:'<li song_id="{song_id}" class="song">{title}</li>',
  searchItem:'<li song_id="{song_id}" class="song search">{track}. {title}</li>',
  songInfo:'<div class="songInfo new"><h3>{album_title}</h3><h4>{song_title}</h4><div class="artWrapper"><img src="{art}" alt="{album_title}" /><span class="editAlbum"></span></div><ul class="tags">{tags}<li><span>+</span></ul></div>',
  wallpaper:'<div style="background-image:url({src});" class="wallpaper new"></div>',
  songTags:'<li><span>{tag_name}</span></li>',
  songAddTag:'<li><input type="text" id="addTag" /></li>',
  songEditTag:'<li><input type="text" id="editTag" data-initial="{val}" value="{val}" /></li>',
  videoPane:'<div id="videoList"></div>',
  videoContent:'<div class="navigation"><span class="head">Shows &raquo; </span><span class="show"></span><span class="close">Close</span></div><ul>{items}</ul>',
  showWithArt:'<li show_id="{id}" class="show art"><img src="http://dxmp.us/thumb.php?file={art}&width=280&height=140" alt="{title}" /><p>{title}</p></li>',
  show:'<li show_id="{id}" class="show"><p>{title}</p></li>',
  episode:'<li episode_id="{id}" class="episode{watched}"><img src="http://dxmp.us/thumb.php?file=screens/{thumb}&height=135&width=240" alt="episode thumbnail" /><p>{title}</p></li>',
  season:'<li class="episode"><h3>{season}</h3></li>',
  episodes:'<ul>{list}</ul>',
  msgUserPlay:'<img src="thumb.php?file={album_art}&width=50&height=50" /><p><strong>{user}</strong> is listening to <span class="song">{title}</span></p>',
  tagListItem:'<li data-tag="{tag_name}">{tag_name}<span class="add">+</span><span class="remove">-</span></li>'

};