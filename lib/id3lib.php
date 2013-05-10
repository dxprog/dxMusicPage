<?php

// Error codes
define (ID3_OK, 0);
define (ID3_BAD_FILE, 1);
define (ID3_INVALID, 2);

class ID3Lib {

	public $tags; // The object in which we store all the tags
	public $title, $artist, $album, $genre, $track, $disc, $year; // Easy access to the generic tags
	private $_err; // Error code
	private $_errmsg = array (null, "Unable to open file", "Not a valid ID3 v2 header");
	private $_tags = array (
						array ("title"=>"TT2", "album"=>"TAL", "artist"=>"TP1", "genre"=>"TCO", "year"=>"TYE", "disc"=>"TPA", "track"=>"TRK"), // ID3v2
						array ("title"=>"TIT2", "album"=>"TALB", "artist"=>"TPE1", "genre"=>"TCON", "year"=>"TYER", "disc"=>"TPOS", "track"=>"TRCK") // ID3v2.3
					);
	private $_genres = array ("Blues", "Classic Rock", "Country", "Dance", "Disco", "Funk", "Grunge", "Hip-Hop", "Jazz", "Metal", "New Age", "Oldies", "Other", "Pop", "R&B", "Rap", "Reggae", "Rock", "Techno", "Industrial", "Alternative", "Ska", "Death Metal", "Pranks", "Soundtrack", "Euro-Techno", "Ambient", "Trip-Hop", "Vocal", "Jazz+Funk", "Fusion", "Trance", "Classical", "Instrumental", "Acid", "House", "Game", "Sound Clip", "Gospel", "Noise", "Alternative Rock", "Bass", "Soul", "Punk", "Space", "Meditative", "Instrumental Pop", "Instrumental Rock", "Ethnic", "Gothic", "Darkwave", "Techno-Industrial", "Electronic", "Pop-Folk", "Eurodance", "Dream", "Southern Rock", "Comedy", "Cult", "Gangsta", "Top 40", "Christian Rap", "Pop/Funk", "Jungle", "Native US", "Cabaret", "New Wave", "Psychadelic", "Rave", "Showtunes", "Trailer", "Lo-Fi", "Tribal", "Acid Punk", "Acid Jazz", "Polka", "Retro", "Musical", "Rock & Roll", "Hard Rock", "Folk", "Folk-Rock", "National Folk", "Swing", "Fast Fusion", "Bebob", "Latin", "Revival", "Celtic", "Bluegrass", "Avantgarde", "Gothic Rock", "Progressive Rock", "Psychedelic Rock", "Symphonic Rock", "Slow Rock", "Big Band", "Chorus", "Easy Listening", "Acoustic", "Humour", "Speech", "Chanson", "Opera", "Chamber Music", "Sonata", "Symphony", "Booty Bass", "Primus", "Porn Groove", "Satire", "Slow Jam", "Club", "Tango", "Samba", "Folklore", "Ballad", "Power Ballad", "Rhythmic Soul", "Freestyle", "Duet", "Punk Rock", "Drum Solo", "Acapella", "Euro-House", "Dance Hall", "Goa", "Drum & Bass", "Club - House", "Hardcore", "Terror", "Indie", "BritPop", "Negerpunk", "Polsk Punk", "Beat", "Christian Gangsta Rap", "Heavy Metal", "Black Metal", "Crossover", "Contemporary Christian", "Christian Rock", "Merengue", "Salsa", "Thrash Metal", "Anime", "JPop", "Synthpop");
	private $_common = array ("title", "artist", "album", "genre", "track", "disc", "year"); // Common tags to be saved

	// The constructor, reads the MP3 file and gets the tags
	public function __construct ($file)
	{
		
		// Open the file
		$file = @fopen ($file, "rb");
		if (!$file) {
			$this->_err = ID3_BAD_FILE;
			return;
		}
		
		// Read in the header (first ten bytes)
		$fb = fread ($file, 10);
		
		// Check for valid ID3 signature
		if (substr ($fb, 0, 3) != "ID3") {
			$this->_err = ID3_INVALID;
			return;
		}
		
		// Get the information from the header
		$ver = $this->toInt(substr ($fb, 3, 1));
		$hFlags = $this->toInt($fb{5});
		$hSize = $this->calcSize (substr ($fb, 6));
		
		// Set some info based on the revision number
		switch ($ver) {
			case 2:
				$idLen = 3;
				$sizeLen = 3;
				$extraOffset = $idLen + $sizeLen;
				$extraSize = 1;
				$frameSize = 6;
				break;
			case 3:
			case 4:
				$idLen = 4;
				$sizeLen = 4;
				$extraOffset = $idLen + $sizeLen;
				$extraSize = 2;
				$frameSize = 10;
				break;
		}
		
		// Read in the entire ID3 head and parse all the frames
		$fb = fread ($file, $hSize); $i = 0;
		while ($i < $hSize) {
			
			// Read the frame header
			$id = substr ($fb, $i, $idLen);
			$size = $this->toInt (substr ($fb, $i + $idLen, $sizeLen));
			if (!$size || !$id)
				break;
			$flags = $this->toInt (substr ($fb, $i + $extraOffset, $extraSize));
			$data = substr ($fb, $i + $frameSize, $size);
			// If this is a text frame and there's a text encoding byte, strip it out
			if ($id{0} == "T" && (ord ($data{0}) == 0 || ord ($data{0}) == 1))
				$data = substr ($data, 1);
			$i += $frameSize + $size;
			$this->tags->$id = $data;
			
		}
		
		// Save the common tags
		foreach ($this->_common as $tag) {
			$t = $this->_tags[$ver - 2][$tag];
			$this->$tag = $this->tags->$t;
		}
		
		// Format the genre tag
		if (preg_match ("/\((\d+)\)/", $this->genre, $match))
			$this->genre = $this->_genres [$match[1]];
		
		// Format the disc tag
		if (preg_match ("@(\d+)/(\d+)@", $this->disc, $match))
			$this->disc = $match[1];
		if (!$this->disc)
			$this->disc = 1;
		
		// Close the file
		fclose ($file);
		
	}
	
	// Saves APIC if present
	public function savePicture ($file)
	{
	
		// If APIC is present, get the JPEG data and save it
		if (isset ($this->tags->APIC)) {
			$f = fopen ($file, "wb");
			fwrite ($f, substr ($this->tags->APIC, strpos ($this->tags->APIC, 0xff)));
			fclose ($f);
			return true;
		}
		
		return false;
	
	}
	
	// Converts a string value to an integer
	private function toInt ($val) {
		$o = 0; $l = strlen ($val) - 1;
		for ($i = 0; $i < $l + 1; $i++) {
			$v = ord($val{$i});
			$o |= $v << (($l - $i) * 8);
		}
		return $o;
	}
	
	// Returns an error message based on any error that may have occured
	public function getErr () {
		return $this->_errmsg[$this->err];
	}
	
	// Takes a 16-bit MSB integer and returns it in LSB
	private function lsb16 ($num) {
		return ($num >> 8) | ($num << 8);
	}
	
	// Takes a 32-bit MSB integer and returns it in LSB
	private function lsb32 ($num) {
		return (($num & 0xff) << 24) | (($num & 0xff00) << 8) | (($num & 0xff0000) >> 8) | (($num & 0xff000000) >> 24);
	}

	// Calculates the size used in the ID3 header
	private function calcSize ($num) {
		$num = $this->toInt ($num);
		return ($num & 0x7f) | (($num & 0x7f00) >> 1) | (($num & 0x7f0000) >> 2) | (($num & 0x7f000000) >> 3);
	}
	
}

?>