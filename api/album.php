<?php

namespace Api {
	
	use Lib;
	use stdClass;

	class Album extends Content {

		/**
		 * Album art
		 */
		public $art;

		/**
		 * Album wallpaper
		 */
		public $wallpaper;

		public function __construct($obj) {

			if ($obj instanceof stdClass) {

				$this->_createObjectFromRow($obj);
				$this->art = isset($this->meta->art) ? $this->art : null;
				$this->wallpaper = isset($this->meta->wallpaper) ? $this->wallpaper : null;

				// Delete unused properties
				unset($this->perma);
				unset($this->body);
				unset($this->parent);
				unset($this->meta);
				unset($this->tags);
				unset($this->ratings);
				unset($this->date);

			}

		}

	}

}