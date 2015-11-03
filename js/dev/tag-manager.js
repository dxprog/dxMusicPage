import $ from 'jquery';

import cookie from './cookie';
import dataManager from './data-manager';
import templates from './templating';
import domElements from './dom-elements';
import playlistManager from './playlist-manager';

let tagManager = {
  click:function(e) {
    var
    $this = $(e.currentTarget),
    text = $this.text();

    if (e.target.tagName === 'SPAN') {
      if (text === '+') {
        tagManager.add($this);
      } else {
        tagManager.edit($this);
      }
    } else {
      e.stopPropagation();
    }
  },
  add:function($this) {
    var $parent = $this.parents('ul:first');

    $this.html(templates.render('songAddTag'));
    $parent.append(templates.render('songTags', {'tag_name':'+'}));
    $this.find('input').keypress(tagManager.post);
  },
  edit:function($this) {
    var val = $this.text();
    $this.html(templates.render('songEditTag', { val:val })).find('input').keypress(tagManager.post);
  },
  post:function(e) {
    var
    $this = $(e.currentTarget),
    initial = $this.attr('data-initial'),
    tag = $this.val();

    if (e.keyCode === 13 && tag.length > 0) {
      $this.attr('disabled', 'disabled');
      $.ajax({
        url:'/api/?type=json&method=content.syncTag&id=' + playlistManager.getPlayingSong().id + '&initial=' + initial,
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
    cookieTags = cookie.eat('random_tags');
    dataManager.populateTags();
    for (var i = 0, count = dataManager.tags.length; i < count; i++) {
      out += templates.render('tagListItem', { tag_name:dataManager.tags[i] });
    }
    domElements.$tagList.html(out).on('click', 'span', tagManager.changeTags);
    $('#randomSettings').on('click', tagManager.listClick);

    if (null != cookieTags && cookieTags.length > 0) {
      cookieTags = cookieTags.split(',');
      for (var i = 0, count = cookieTags.length; i < count; i++) {
        if (cookieTags[i].substr(0, 1) == '-') {
          domElements.$tagList.find('[data-tag="' + cookieTags[i].substr(1) + '"] .remove').addClass('selected');
        } else {
          domElements.$tagList.find('[data-tag="' + cookieTags[i] + '"] .add').addClass('selected');
        }
      }
    }

  },
  listClick:function() {
    domElements.$tagList.fadeIn();
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
          playlistManager.includeTag(tag);
        } else {
          playlistManager.excludeTag(tag);
        }
        $target.addClass('selected');
      } else {
        playlistManager.removeTag(tag);
        $target.removeClass('selected');
      }
    } else {
      $tagList.fadeOut();
    }

  }
};

export default tagManager;