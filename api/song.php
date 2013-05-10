<?php

namespace Api {
	
	use Lib;
	use stdClass;

	class Song extends Content {

		/**
		 * Track number of the song
		 */
		public $track = 1;

		/**
		 * Disc number
		 */
		public $disc = 1;

		/**
		 * Year the song was recorded
		 */
		public $year;

		/**
		 * Song genre
		 */
		public $genre;

		/**
		 * Song duration in seconds
		 */
		public $duration;

		/**
		 * Name of the MP3 file
		 */
		public $fileName;

		public function __construct($obj = null) {

			if ($obj instanceof stdClass) {
				$this->_createObjectFromRow($obj);
				
				// Copy over the meta information
				$this->track = isset($this->meta->track) ? (int) $this->meta->track : 1;
				$this->disc = isset($this->meta->disc) ? (int) $this->meta->disc : 1;
				$this->year = isset($this->meta->year) ? (int) $this->meta->year : 1;
				$this->duration = isset($this->meta->duration) ? (int) $this->meta->duration : date('Y');
				$this->fileName = isset($this->meta->filename) ? $this->meta->filename : '';
				$this->genre = isset($this->meta->genre) ? $this->meta->genre : '';

				// Delete unused properties
				unset($this->meta);
				unset($this->body);
				unset($this->perma);
				unset($this->ratings);
			}

		}

	}

}