import dx from './dxapi';
import Player from './player';

(function($, undefined) {

	'use strict';

	var

	months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],

	// DOM objects
	$main = $('#main'),
	$mainList = $('#mainList'),
	$songList = $('#secondaryList'),
	$playHead = $('.playHead'),
	$playlist = $('#playlist'),
	$playIn = $('#playIn'),
	$playOut = $('#playOut'),
	$playPause = $('#playPause'),
	$nowPlaying = $('#nowPlaying'),
	$vlc = $('#vlc'),
	$search = $('#search'),
	$message = $('#message'),
	$videoList = null,
	$tagList = $('#tagList'),

	// Various objects and variables
	playerType = $.cookie('player') || 'html5',
	userName = $.cookie('userName') || false,
	player = Player,
	artLocation = 'http://dxmp.s3.amazonaws.com/images/',
	searchList = [],
	watched = $.cookie('watched') || '',
	actionTimer = null,

	isMobile = (/(iphone|sonyericsson|blackberry|iemobile|windows ce|windows phone|nokia|samsung|android)/ig).test(window.navigator.userAgent),

	// Templating
	templates = {

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
		albumListItem:'<li album_id="{id}" class="album"><img src="thumb.php?file={art}&width=50&height=50" /><p>{title}</p></li>',
		songWithArt:'<li song_id="{id}" album_id="{album}" class="song"><img src="thumb.php?file={art}&width=50&height=50" /><p>{title}</p></li>',
		songItem:'<li song_id="{song_id}" class="song">{title}</li>',
		searchItem:'<li song_id="{song_id}" class="song search">{track}. {title}</li>',
		songInfo:'<div class="songInfo new"><h3>{album_title}</h3><h4>{song_title}</h4><div class="artWrapper"><img src="{art}" alt="{album_title}" /><span class="editAlbum"></span></div><ul class="tags">{tags}<li><span>+</span></ul></div>',
		wallpaper:'<div style="background-image:url({src});" class="wallpaper new"></div>',
		songTags:'<li><span>{tag_name}</span></li>',
		songAddTag:'<li><input type="text" id="addTag" /></li>',
		songEditTag:'<li><input type="text" id="editTag" data-initial="{val}" value="{val}" /></li>',
		videoPane:'<div id="videoList"></div>',
		videoContent:'<div class="navigation"><span class="head">Shows &raquo; </span><span class="show"></span><span class="close">Close</span></div><ul>{items}</ul>',
		showWithArt:'<li show_id="{id}" class="show art"><img src="thumb.php?file={art}&width=280&height=140" alt="{title}" /><p>{title}</p></li>',
		show:'<li show_id="{id}" class="show"><p>{title}</p></li>',
		episode:'<li episode_id="{id}" class="episode{watched}"><img src="thumb.php?file=screens/{thumb}&height=135&width=240" alt="episode thumbnail" /><p>{title}</p></li>',
		season:'<li class="episode"><h3>{season}</h3></li>',
		episodes:'<ul>{list}</ul>',
		msgUserPlay:'<img src="thumb.php?file={album_art}&width=50&height=50" /><p><strong>{user}</strong> is listening to <span class="song">{title}</span></p>',
		tagListItem:'<li data-tag="{tag_name}">{tag_name}<span class="add">+</span><span class="remove">-</span></li>'

	},

	createPerma = function(val) {
		var remove = /(\'|\"|\.|,|~|!|\?|&lt;|&gt;|@|#|\$|%|\^|&amp;|\*|\(|\)|\+|=|\/|\\|\||\{|\}|\[|\]|-|--)/ig;
		if (null != val) {
			val = val.replace(remove, '');
			val = val.replace(/\s\s/g, ' ').replace(/\s/g, '-').toLowerCase();
		}
		return val;
	},

	// Data storage and handlers
	data = {

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
			for (var i in data[itemType]) {
				if (data[itemType].hasOwnProperty(i)) {
					if (data[itemType][i].parent === id) {
						retVal.push(data[itemType][i]);
					}
				}
			}
			return retVal;
		},

		getItemById:function(id, itemType) {
			var retVal = null;

			for (var i in data[itemType]) {
				if (data[itemType].hasOwnProperty(i)) {
					var item = data[itemType][i];
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

			for (i in data.songs) {
				if (data.songs.hasOwnProperty(i)) {
					var song = data.songs[i];
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
			for (var i = 0, count = data.songs.length; i < count; i++) {
				for (var j = 0, tagCount = data.songs[i].tags.length; j < tagCount; j++) {
					if ($.inArray(data.songs[i].tags[j].name, data.tags) === -1) {
						data.tags.push(data.songs[i].tags[j].name);
					}
				}
			}
			data.tags.sort();
		}

	},

	// Helpers
	helpers = {

		getTimeParts:function(seconds) {

			var
			h = Math.floor(seconds / 3600),
			m = Math.floor((seconds - h * 3600) / 60),
			s = seconds - h * 3600 - m * 60;
			return {hours:h, minutes:m, seconds:s};

		},

		padNumber:function(num) {
			return num < 10 ? '0' + num : num;
		},

		niceTime:function(seconds) {
			var parts = this.getTimeParts(seconds);
			parts.hours = parts.hours > 0 ? parts.hours + ':' : '';
			parts.minutes = parts.minutes > 0 ? this.padNumber(parts.minutes) + ':' : '';
			parts.seconds = this.padNumber(parts.seconds);
			return parts.hours + parts.minutes + parts.seconds;
		}

	},

	// Playlist handling
	playlist = (function() {

		var

		list = [],
		currentSong = 0,
		playing = false,
		infinite = false,
		random = false,
		time = 0,
		randomAll = false,
		songs = [],
		tags = [],

		queueSong = function(id) {
			var
			song = data.getItemById(id, 'songs'),
			album = data.getItemById(song.parent, 'albums'),
			art = data.getAlbumArt(album),
			out = templates.render('albumListItem', {id:list.length, art:art, title:song.title});

			// Recalculate the playlist time
			if (parseInt(song.meta.duration, 10) > 0) {
				time += parseInt(song.meta.duration);
				$('#playlistLength').html(helpers.niceTime(time));
			}

			$(out).appendTo($playlist);
			list.push(id);

			if (!playing) {
				playSong(currentSong);
			}

		},

		getEligibleSongs = function() {
			var retVal = [], randomTags = tags.join(',');
			$.cookie('random_tags', randomTags, { expires:90 });
			if (randomAll || randomTags == null || randomTags.length == 0) {
				retVal = data.songs;
			} else {
				for (var i in data.songs) {
					if (data.checkSongForTags(data.songs[i], ',' + randomTags + ',')) {
						retVal.push(data.songs[i]);
					}
				}
			}
			return retVal;
		},

		songComplete = function() {
			dx.call('content', 'logContentView', { 'id':list[currentSong], 'user':userName });
			nextSong();
		},

		nextSong = function() {
			playing = false;
			$playlist.find('li[album_id=' + currentSong + ']').removeClass('playing').addClass('played');
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
			$playPause.attr('title', 'Pause Song').removeClass('play').addClass('pause');
			player.playSong(list[index], songComplete, playProgress);
			playing = true;
			$playlist.find('li[album_id=' + index + ']').addClass('playing');
			display.songInfo(list[index]);
		},

		toggleRandom = function() {
			random = !random;
		},

		getPlayingSong = function() {
			return data.getItemById(list[currentSong], 'songs');
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
				songs = data.songs;
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

	}()),

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
			$progress.html(msg).css('background-position', (Math.abs(percent - 100) / 100 * progWidth * -1) + 'px 0');
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
				dx.call('dxmp', 'getData', { 'noCache':'true' }, load.content);
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
							var song = playlist.getPlayingSong();
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

	// Events
	playProgress = function(e) {

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
			$playHead.css('left', p + '%');
			$playIn.text(m + ':' + s);
			$playOut.text('-' + mLeft + ':' + sLeft);
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
				$vlc.addClass('disabled');
			} else {
				player.setPlayer('node', { address:address });
				$vlc.removeClass('disabled');
			}
			devices.$.slideUp();
			devices.listDown = false;

		}
	},

	vlcClick = function(e) {
		/* $vlc.toggleClass('disabled');
		playerType = playerType === 'vlc' ? 'html5' : 'vlc';
		player.setPlayer(playerType);
		$.cookie('player', playerType, {expires:90});
		*/
	},

	playPauseClick = function(e) {
		player.pause();
		var $this = $(e.target), status = player.getStatus();
		if (status.state === 'playing') {
			$this.removeClass('play').addClass('pause');
		} else {
			$this.removeClass('pause').addClass('play');
		}
	},

	optionClick = function(e) {
		$('#options').slideToggle();
	},

	optionsClick = function(e) {
		var type = $(e.target).text();
		display.listByType(type);
		$.cookie('list', type, {expires:90});
	},

	randomClick = function(e) {
		$(e.target).toggleClass('enabled');
		playlist.toggleRandom();
	},

	nextClick = function(e) {
		playlist.nextSong();
	},

	songClick = function(e) {

		var
		$this = $(e.target),
		songId = $this.attr('song_id');
		if (songId === undefined) {
			$this = $this.parents('li:first');
			songId = $this.attr('song_id');
		}

		switch (songId) {
			case 'back':
				$mainList.animate({left:"0"}, 200);
				$songList.animate({left:"298px"}, 200);
				break;
			case 'all':
				var
				album_id = $this.parents('ol:first').attr('album_id'),
				songs = data.getSongsByAlbumId(album_id);

				for (var i = 0, count = songs.length; i < count; i++) {
					playlist.queueSong(songs[i].id);
				}

				break;
			default:
				// player.playSong(songId, function(){}, playProgress);
				playlist.queueSong(songId);
				break;
		}

	},

	savePlaylistClick = function(e) {
		playlist.save();
	},

	playlistClick = function(e) {
		var list = e.currentTarget.getAttribute('data-list');
		if (list.length > 0) {
			list = list.split(',');
			for (var i = 0, count = list.length; i < count; i++) {
				playlist.queueSong(list[i]);
			}
		}
	},

	albumClick = function(e) {
		var
		$this = $(e.target),
		albumId = $this.attr('album_id');

		if (albumId === undefined) {
			$this = $this.parents('li:first');
			albumId = $this.attr('album_id');
		}

		var songs = data.getSongsByAlbumId(albumId), out = '<li song_id="back" class="song">Back</li><li song_id="all" class="song">Play All</li>';

		for (var i in songs) {
			if (songs.hasOwnProperty(i)) {
				var song = songs[i];
				out += '<li song_id="' + song.id + '" class="song">' + song.title + '</li>';
			}
		}

		$mainList.animate({left:"-298px"}, 200);
		$songList.attr('album_id', albumId).html(out).animate({left:"0"}, 200).undelegate('li.song', 'click').delegate('li.song', 'click', songClick);

	},

	tags = {
		click:function(e) {
			var
			$this = $(e.currentTarget),
			text = $this.text();

			if (e.target.tagName === 'SPAN') {
				if (text === '+') {
					tags.add($this);
				} else {
					tags.edit($this);
				}
			} else {
				e.stopPropagation();
			}
		},
		add:function($this) {
			var $parent = $this.parents('ul:first');

			$this.html(templates.render('songAddTag'));
			$parent.append(templates.render('songTags', {'tag_name':'+'}));
			$this.find('input').keypress(tags.post);
		},
		edit:function($this) {
			var val = $this.text();
			$this.html(templates.render('songEditTag', { val:val })).find('input').keypress(tags.post);
		},
		post:function(e) {
			var
			$this = $(e.currentTarget),
			initial = $this.attr('data-initial'),
			tag = $this.val();

			if (e.keyCode === 13 && tag.length > 0) {
				$this.attr('disabled', 'disabled');
				$.ajax({
					url:'/api/?type=json&method=content.syncTag&id=' + playlist.getPlayingSong().id + '&initial=' + initial,
					dataType:'json',
					type:'POST',
					data:{'tag':tag},
					success:function(data) {
						$this.parents('li:first').html('<span>' + tag + '</span>');
					}
				});
			} else if (e.keyCode === 27) {
				$this.parents('li:first').remove();
			}
		},
		load:function() {
			var
			out = '<li data-tag="done">Done<span class="remove">&times;</span></li>',
			cookieTags = $.cookie('random_tags');
			data.populateTags();
			for (var i = 0, count = data.tags.length; i < count; i++) {
				out += templates.render('tagListItem', { tag_name:data.tags[i] });
			}
			$tagList.html(out);
			$('#randomSettings').on('click', tags.listClick);
			$tagList.on('click', 'span', tags.changeTags);

			if (null != cookieTags && cookieTags.length > 0) {
				cookieTags = cookieTags.split(',');
				for (var i = 0, count = cookieTags.length; i < count; i++) {
					if (cookieTags[i].substr(0, 1) == '-') {
						$tagList.find('[data-tag="' + cookieTags[i].substr(1) + '"] .remove').addClass('selected');
					} else {
						$tagList.find('[data-tag="' + cookieTags[i] + '"] .add').addClass('selected');
					}
				}
			}

		},
		listClick:function() {
			$tagList.fadeIn();
		},
		changeTags:function(e) {
			var
			$target = $(e.currentTarget),
			$parent = $target.parent(),
			tag = $parent.attr('data-tag');

			if (tag !== 'done') {
				if (!$target.hasClass('selected')) {
					$parent.find('.selected').removeClass('selected');
					if ($target.hasClass('add')) {
						playlist.includeTag(tag);
					} else {
						playlist.excludeTag(tag);
					}
					$target.addClass('selected');
				} else {
					playlist.removeTag(tag);
					$target.removeClass('selected');
				}
			} else {
				$tagList.fadeOut();
			}

		}
	},

	music = {

		smartPlaylistClick:function(e) {
			var song = playlist.getPlayingSong();
			if (null != song) {
				dx.call('dxmp', 'buildSmartPlaylist', { id:song.id }, function(data) {
					if (data.body.length > 0) {
						for (var i = 0, count = data.body.length; i < count; i++) {
							playlist.queueSong(data.body[i]);
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
			show = data.getItemById(showId, 'shows'),
			videos = data.getItemsByParentId(showId, 'videos'),
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

			$videoList.html(templates.render('videoContent', { items:list }));
			$videoList.find('.navigation .show').html(show.title);

			if (show.meta.hasOwnProperty('wallpaper')) {
				$videoList.find('.wallpaper').fadeOut(500, function() { $(this).remove(); });
				$videoList.prepend('<img src="images/' + show.meta.wallpaper + '" class="wallpaper" alt="show wallpaper" />');
			}

		},

		closeClick:function(e) {
			$videoList = $('#videoList');
			if ($('#video').length > 0) {
				$('#video').remove();
			} else {
				$videoList.animate({ top:'100%' }, function() { $videoList.remove(); $videoList = null; });
			}
		},

		headClick:function(e) {
			display.shows();
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
					$.cookie('watched', watched, { expires:365});
				}
			}
		}

	},

	// List displays
	display = {

		shows:function() {
			var out = '', item = null, art = '';

			for (var i in data.shows) {
				item = data.shows[i];
				if (item.children > 0) {
					art = item.meta.hasOwnProperty('art') ? item.meta.art : false;
					out += art ? templates.render('showWithArt', { id:item.id, art:art, title:item.title }) : templates.render('show', { id:item.id, title:item.title });
				}
			}

			out = templates.render('videoContent', { items:out });
			if (null === $videoList) {
				$videoList = $main.append(templates.render('videoPane')).find('#videoList').animate({ top:'0%' });
			}
			$videoList.html(out).undelegate('li.show', 'click').delegate('li.show', 'click', video.showClick);
			$videoList.html(out).undelegate('li.episode', 'click').delegate('li.episode', 'click', video.episodeClick);

		},

		albums:function() {

			$mainList.empty();
			var out = '', art = '';

			for (var i in data.albums) {
				var item = data.albums[i];
				if (item.children > 0) {
					art = data.getAlbumArt(item);
					out += templates.render('albumListItem', { id:item.id, art:art, title:item.title });
				}
			}

			$(out).appendTo($mainList);
			$mainList.undelegate('li.album', 'click').delegate('li.album', 'click', albumClick);

		},

        songChartTotal:function(data) {
			var chartData = [[]];
			$('.songInfo').append('<div id="playCountGraph"></div>');
			for (var i = 0, count = data.body.length; i < count; i++) {
				chartData[0].push({ value:data.body[i].total, label:data.body[i].user });
			}
			$('#playCountGraph').dxPlot(chartData, { animate:true, colors:['rgba(200, 209, 83, .6)', 'rgba(94, 224, 203, .6)'] });
        },

		songChartTime:function(data) {

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

		},

		songInfo:function(songId) {
			var
			song = data.getItemById(songId, 'songs'),
			album = data.getItemById(song.parent, 'albums'),

			art = data.getAlbumArt(album),
			tags = '',
			info = '';

			for (var i in song.tags) {
				tags += templates.render('songTags', {'tag_name':song.tags[i].name});
			}

			info = templates.render('songInfo', {'album_title':album.title, 'song_title':song.title, 'art':artLocation + art, 'tags':tags});
			document.title = song.title + ' - dxMusicPage';
			$nowPlaying.find('.songInfo').animate({'top':'+=200px', 'opacity':'0'}, 500, function() { $(this).remove(); });
			$nowPlaying
				.append(info)
				.find('.songInfo.new')
				.css({'opacity':'0', 'left':'230px'})
				.animate({'left':'30px', 'opacity':'1'}, 500, function() {
					$(this).removeClass('new');
					dx.call('stats', 'getTrackUsers', { id:song.id }, display.songChartTotal);
					dx.call('stats', 'getUserPlaysByDay', { id:song.id, user:userName }, display.songChartTime);
					$main.find('.wallpaper').fadeOut(500, function() { $(this).remove(); });
					if (null != album.meta && typeof (album.meta.wallpaper) === 'string') {
						var
						wallpaper = templates.render('wallpaper', {'src':artLocation + album.meta.wallpaper}),
						$wallpaper = $(wallpaper);
						var image = new Image();
						image.onload = function() {
							$main.prepend($wallpaper);
							$main.find('.wallpaper.new').fadeIn(500, function() { $(this).removeClass('new'); });
						};
						image.src = artLocation + album.meta.wallpaper;
					}
				});

		},

		songList:function(json) {

			$mainList.empty();

			for (var i in json.body) {
				var
                    item = json.body[i],
                    album = data.getItemById(item.album, 'albums'),
                    art = data.getAlbumArt(album),
                    out = templates.render('songWithArt', { id:item.id, art:art, title:item.title, album:item.album });
				$(out).appendTo($mainList);
			}

			$mainList.undelegate('li.song', 'click').delegate('li.song', 'click', songClick);

		},

		playlists:function() {
			dx.call('content', 'getContent', { contentType:'list' }, function(data) {
				if ('content' in data.body && data.body.content.length > 0) {
					var out = '';
					$mainList.empty();
					for (var i in data.body.content) {
						if (data.body.content.hasOwnProperty(i)) {
							var list = data.body.content[i];
							if (list.body.length > 0) {
								out += '<li data-list="' + list.body + '" class="song">' + list.title + '</li>';
							}
						}
					}
					$mainList.animate({left:"-298px"}, 200);
					$songList.html(out).animate({left:"0"}, 200).undelegate('li.song', 'click').delegate('li.song', 'click', playlistClick);
				}
			});
		},

		trending:function() {

			$.ajax({
				url:'api/?type=json&method=stats.getBest',
				dataType:'json',
				success:this.songList
			});

			$mainList.animate({left:"0"}, 200);
			$songList.animate({left:"298px"}, 200);

		},

		userTracks:function() {
			$.ajax({
				url:'api/?type=json&method=stats.getTopUserTracks&user=' + userName,
				dataType:'json',
				success:this.songList
			});

			$mainList.animate({left:"0"}, 200);
			$songList.animate({left:"298px"}, 200);
		},

		latest:function() {
			$.ajax({
				url:'api/?type=json&method=dxmp.getLatest',
				dataType:'json',
				success:this.songList
			});

			$mainList.animate({left:"0"}, 200);
			$songList.animate({left:"298px"}, 200);

		},

		displayRandomPool:function() {
			var songs = playlist.randomPool;
		},

		listByType:function(type) {
			type = null != type ? type : '';
			switch (type.toLowerCase()) {
				case 'my most played':
					type = 'My Most Played';
					this.userTracks();
					break;
				case 'playlists':
					type = 'Playlists';
					display.playlists();
					break;
				case 'shows':
					type = 'Shows';
					this.shows();
					break;
				case 'trending':
					type = 'Trending';
					this.trending();
					break;
				case 'latest':
					type = 'Latest';
					this.latest();
					break;
				default:
					type = 'Albums';
					this.albums();
					break;
			}
			$('#option h2').text(type);
		}

	},

	searchKeyEvent = function(e) {

		var
		val = $.trim($search.val()).toLowerCase(),
		out = '',
		list = val.length === 1 || searchList.length == 0 ? data.songs : val.length > 1 ? searchList : null,
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
					album = data.getItemById(newList[i].parent, 'albums'),
					art = data.getAlbumArt(album);
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

			$mainList.animate({left:"-298px"}, 200);
			$songList
				.html(out)
				.animate({left:"0"}, 200)
				.undelegate('li.song', 'click')
				.undelegate('li.album', 'click')
				.delegate('li.song', 'click', songClick)
				.delegate('li.album', 'click', albumClick);

			$('#option h2').text('Search Results');

		} else {
			searchList = [];
			$mainList.animate({left:"0"}, 200);
			$songList.animate({left:"298px"}, 200);
		}

		$songList.html(out);

	},

	// Checks for new user actions
	actions = {

		timer:null,

		msgClick:function(e) {
			switch (e.currentTarget.getAttribute('data-action')) {
				case 'msg_user_play':
					playlist.queueSong(e.currentTarget.getAttribute('song_id'));
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
							playlist.queueSong(parseInt(item.param), 10);
							break;
						case 'msg_new_song':
							$message
								.attr('data-action', 'msg_new_song')
								.html('<p>A new song has been added!</p>')
								.stop().animate({ bottom:0 }, 400);
						case 'refresh':
							dx.call('dxmp', 'getData', {}, load.content);
							break;
						case 'msg_user_play':
							msgPlay = item;
							break;
					}

				}

				// We only need display the latest song play
				if (msgPlay) {
					var

					song = data.getItemById(item.param.songId, 'songs'),
					album = data.getItemById(song.parent, 'albums'),
					artwork = data.getAlbumArt(album);

					$message
						.attr('data-action', 'msg_user_play')
						.attr('song_id', song.id)
						.html(templates.render('msgUserPlay', { album_art:artwork, user:item.param.user, title:song.title }))
						.stop().animate({ bottom:0 }, 400);

					setTimeout(function() {
						$message.stop().animate({ bottom:'-' + $message.outerHeight(true) + 'px' });
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

	// Data loaders/initializers
	load = {
		albums:function(d) {

			data.albums.push(data.defaultAlbum);
			data.defaultAlbum.id = 0;
			data.albums.push(data.defaultAlbum);

			// Sort the albums alphabetically
			data.albums.sort(function(a, b) {
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
			data.songs.sort(function(a, b) {
				var
				x = parseInt(a.meta.track), // Track #1
				y = parseInt(b.meta.track), // Track #2
				i = parseInt(a.meta.disc), // Disc #1
				j = parseInt(b.meta.disc); // Disc #2
				return (i < j) ? -1 : (i == j) ? (x < y) ? -1 : (x == y) ? 0 : 1 : 1;
			});
			playlist.init();

		},

		shows:function() {

			// Sort the albums alphabetically
			data.shows.sort(function(a, b) {
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
			data.videos.sort(function(a, b) {
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
			data.albums = [];
			data.songs = [];
			data.shows = [];
			data.videos = [];

			for (var i = 0, count = d.body.length; i < count; i++) {
				item = d.body[i];
				if (data.hasOwnProperty(item.type + 's')) {
					data[item.type + 's'].push(item);
				}
			}

			// Have each content type perform whatever initial data actions need to be performed
			load.albums();
			load.songs();
			load.shows();
			load.videos();
			tags.load();

			if (window.location.hash) {
				playlist.queueSong(window.location.hash.replace('#', ''));
			}

			// Display whatever list needs to be displayed
			var list = $.cookie('list');
			display.listByType(list);

		}
	},

	init = function() {

		if (!userName) {
			userName = prompt('Identify yourself (10 characters max)');
			$.cookie('userName', userName, { expires:90 });
		}

		dx.call('dxmp', 'getData', {}, load.content);

		player.setPlayer(playerType);
		if (playerType == 'vlc') {
			$vlc.removeClass('disabled');
		}
		$nowPlaying.delegate('.tags li', 'click', tags.click);
		$playPause.click(playPauseClick);
		$('#random').click(randomClick);
		$('#next').click(nextClick);
		$('#smartPlaylist').click(music.smartPlaylistClick);
		$('#option').click(optionClick);
		$('#options li').click(optionsClick);
		$('#savePlaylist').click(savePlaylistClick);
		$('body').delegate('#videoList .close', 'click', video.closeClick);
		$('body').delegate('#videoList .head', 'click', video.headClick);
		$search.keyup(searchKeyEvent);
		devices.init();
		drag.init();
		$('#settings').click(data.getSongGenres);
		actions.init();

	};

	$(init);

}(jQuery));