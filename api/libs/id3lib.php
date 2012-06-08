<?php

/**
 * ID3Lib - An MP3 Information Library for PHP
 * @author Matt Hackmann <matt@dxprog.com>
 * @version 1.0
 * @package ID3Lib
 * @copyright Copyright (C) 2009-2010 Matt Hackmann
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 **/
 

// Error codes
define ('ID3_OK', 0);
define ('ID3_BAD_FILE', 1);
define ('ID3_INVALID', 2);
define ('ID3_INVALID_MP3', 3);
define ('ID3_NO_PICTURE', 4);
define ('ID3_BAD_LENGTH', 5);

class ID3Lib {

	/**
	 * Object of all the tags within the MP3 file
	 * @var object
	 **/
	public $tags;
	
	/**
	 * Song Title
	 * @var string
	 **/
	public $title;
	
	/**
	 * Song Artist
	 * @var string
	 **/
	public $artist;

	/**
	 * Album Title
	 * @var string
	 **/
	public $album;
	
	/**
	 * Song Genre
	 * @var string
	 **/
	public $genre;
	
	/**
	 * Track number on disc
	 * @var int
	 **/
	public $track;
	
	/**
	 * Disc number within set
	 * @var int
	 **/
	public $disc;
	
	/**
	 * Year of song's release
	 * @var int
	 **/
	public $year;
	
	/**
	 * Song duration in seconds
	 * @var int
	 **/
	public $length;
	
	/**
	 * Average bitrate of song
	 * @var int
	 **/
	 public $bitrate;
	
	// Private members
	
	// Current error code
	private $_err;
	
	// Array of messages corresponding to the error code
	private $_errmsg = array ('OK', 'Unable to open file', 'Not a valid ID3v2 header', 'Invalid MP3 file', 'File does not contain a picture', 'Unable to calculate song duration');
	
	// Array of ID3v2 generic tags (title, album, etc.) by version
	private $_tags = array ('TIT2'=>'title', 'TALB'=>'album', 'TPE1'=>'artist', 'TCON'=>'genre', 'TYER'=>'year', 'TPOS'=>'disc', 'TRCK'=>'track', 'TT2'=>'title', 'TAL'=>'album', 'TP1'=>'artist', 'TCO'=>'genre', 'TYE'=>'year', 'TPA'=>'disc', 'TRK'=>'track'); // ID3v2.3
	
	// List of generic tags to save
	private $_common = array ('title', 'artist', 'album', 'genre', 'track', 'disc', 'year');
	
	// ID3v1 genre mappings
	private $_genres = array ('Blues', 'Classic Rock', 'Country', 'Dance', 'Disco', 'Funk', 'Grunge', 'Hip-Hop', 'Jazz', 'Metal', 'New Age', 'Oldies', 'Other', 'Pop', 'R&B', 'Rap', 'Reggae', 'Rock', 'Techno', 'Industrial', 'Alternative', 'Ska', 'Death Metal', 'Pranks', 'Soundtrack', 'Euro-Techno', 'Ambient', 'Trip-Hop', 'Vocal', 'Jazz+Funk', 'Fusion', 'Trance', 'Classical', 'Instrumental', 'Acid', 'House', 'Game', 'Sound Clip', 'Gospel', 'Noise', 'Alternative Rock', 'Bass', 'Soul', 'Punk', 'Space', 'Meditative', 'Instrumental Pop', 'Instrumental Rock', 'Ethnic', 'Gothic', 'Darkwave', 'Techno-Industrial', 'Electronic', 'Pop-Folk', 'Eurodance', 'Dream', 'Southern Rock', 'Comedy', 'Cult', 'Gangsta', 'Top 40', 'Christian Rap', 'Pop/Funk', 'Jungle', 'Native US', 'Cabaret', 'New Wave', 'Psychadelic', 'Rave', 'Showtunes', 'Trailer', 'Lo-Fi', 'Tribal', 'Acid Punk', 'Acid Jazz', 'Polka', 'Retro', 'Musical', 'Rock & Roll', 'Hard Rock', 'Folk', 'Folk-Rock', 'National Folk', 'Swing', 'Fast Fusion', 'Bebob', 'Latin', 'Revival', 'Celtic', 'Bluegrass', 'Avantgarde', 'Gothic Rock', 'Progressive Rock', 'Psychedelic Rock', 'Symphonic Rock', 'Slow Rock', 'Big Band', 'Chorus', 'Easy Listening', 'Acoustic', 'Humour', 'Speech', 'Chanson', 'Opera', 'Chamber Music', 'Sonata', 'Symphony', 'Booty Bass', 'Primus', 'Porn Groove', 'Satire', 'Slow Jam', 'Club', 'Tango', 'Samba', 'Folklore', 'Ballad', 'Power Ballad', 'Rhythmic Soul', 'Freestyle', 'Duet', 'Punk Rock', 'Drum Solo', 'Acapella', 'Euro-House', 'Dance Hall', 'Goa', 'Drum & Bass', 'Club - House', 'Hardcore', 'Terror', 'Indie', 'BritPop', 'Negerpunk', 'Polsk Punk', 'Beat', 'Christian Gangsta Rap', 'Heavy Metal', 'Black Metal', 'Crossover', 'Contemporary Christian', 'Christian Rock', 'Merengue', 'Salsa', 'Thrash Metal', 'Anime', 'JPop', 'Synthpop');
	
	// Frequency/Samples per second lookup table
	private $_freq = array(44100, 48000, 32000, 0);
	
	// Bitrate lookup table
	private $_bitrates = array(0, 32000, 40000, 48000, 56000, 64000, 80000, 96000, 112000, 128000, 160000, 192000, 224000, 256000, 320000);
	
	// Position of ID3v2 end
	private $_id3v2End = 0;
	
	/**
	 * Constructor
	 * @param string $file Optional path of MP3 file to read
	 **/
	public function __construct ($file = null)
	{
		
		if (null != $file && strlen($file) > 0) {
			$this->readFile($file);
		}
		
	}
	
	/**
	 * Reads in ID3 information of an MP3 file
	 * @param string $fileName Path and filename of MP3 file to read
	 * @return boolean Returns true on success, false on error. Errors can be retrieved with getErr
	 **/
	public function readFile($fileName) {
		
		// Clear out old data
		$this->tags = null;
		$this->title = null;
		$this->artist = null;
		$this->album = null;
		$this->genre = null;
		$this->track = null;
		$this->disc = null;
		$this->year = null;
		$this->length = null;
		$this->bitrate = null;
		$hSize = 0;
		$this->_err = 0;

		// Open the file
		$file = @fopen ($fileName, 'rb');
		if (!$file) {
			$this->_err = ID3_BAD_FILE;
			return false;
		}
		
		// Read the first four bytes. This will tell us if we've song data or an ID3v2 tag
		$ident = $this->_toInt(fread ($file, 4));
		if ($ident >> 21 & 0x7ff == 0x7ff) {
			$this->_readID3v1($file);
		} elseif (($ident >> 8 & 0xffffff) == 0x494433) {
			$hSize = $this->_readID3v2($file);
		} else {
			$this->_err = ID3_INVALID_MP3;
			return false;		
		}
		
		// Get the song length and bitrate information
		$this->_calcLength($file, $hSize);
		
		// Close the file
		fclose ($file);
		return true;
		
	}
	
	/**
	 * Saves JPEG picture data present in the MP3 file
	 * @param string $file Path and filename of picture to save
	 * @return boolean Returns true on success, false on error. Errors can be retrieved with getErr
	 **/
	public function savePicture ($file)
	{
	
		$retVal = false;
	
		// If APIC is present, get the JPEG data and save it
		if (isset ($this->tags->APIC) || isset($this->tags->PIC)) {
			$f = fopen ($file, 'wb');
			if (!$f) {
				$this->_err = ID3_BAD_FILE;
			} else {
				$data = isset($this->tags->APIC) ? $this->tags->APIC : $this->tags->PIC;
				fwrite ($f, substr($data, strpos($data, 0xff)));
				unset($data);
				fclose ($f);
				$retVal = true;
			}
		} else {
			$this->_err = ID3_NO_PICTURE;
		}
		
		return $retVal;
	
	}
	
	/**
	 * Returns an error message should something have occured
	 * @return string The error message
	 **/
	public function getError () {
		return $this->_err > 0 ? $this->_errmsg[$this->_err] : false;
	}
	
	/**
	 * Converts and MSB value to LSB
	 * @param string The byte data to convert
	 * @return int The data in LSB format
	 **/
	private function _toInt ($val) {
		$retVal = null;
		$val = strlen($val) == 3 ? chr(0) . $val : $val;
		$o = unpack('N*', $val);
		if (count($o) > 0) {
			$retVal = $o[1];
		}
		return $retVal;
	}
	
	/**
	 * Reads and populates information from an ID3v1 tag
	 * @param handle $file Handle to the MP3 file
	 **/
	private function _readID3v1($file) {
	
		// Seek to the last 128 bytes of the file and get the data (the beginning of the actual tag data)
		fseek($file, -125, SEEK_END);
		
		// Check for the ID3v1 tag before reading in the tag info
		$tag = fread($file, 3);
		if ($tag == 'TAG') {		
			$this->title = fread($file, 30); // 30 bytes for title info
			$this->artist = fread($file, 30); // 30 bytes for artist info
			$this->album = fread($file, 30); // 30 bytes for album info
			$this->year = fread($file, 4); // 4 bytes for year info
			$garbage = fread($file, 30); // 30 bytes for comment. Don't really care here
			$this->genre = $this->_genres[ord(fread($file, 1))];
		}
	
		return 0;
	
	}
	
	/**
	 * Reads an ID3v2.x tag and populates the appropriate data
	 * @param handle $file Handle of the opened MP3 file
	 * @return int Size of the ID3v2 tag
	 */
	private function _readID3v2($file) {
		
		// Seek to the beginning of the file
		fseek($file, 0);
		
		// Read in the header (first ten bytes)
		$fb = fread ($file, 10);
		
		// Get the information from the header
		$ver = ord($fb{3});
		$hFlags = $this->_toInt($fb{5});
		$hSize = $this->_id3v2FrameSize(substr($fb, 6));

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
				$ver = 3;
				$idLen = 4;
				$sizeLen = 4;
				$extraOffset = $idLen + $sizeLen;
				$extraSize = 2;
				$frameSize = 10;
				break;
			default:
				return false;
		}
		
		// Read in the entire ID3 head and parse all the frames
		$fb = fread ($file, $hSize); $i = 0;
		$tags = array();
		while ($i < $hSize) {
			
			// Read the frame header
			$id = substr ($fb, $i, $idLen);
			$size = $this->_toInt (substr ($fb, $i + $idLen, $sizeLen));
			if (!$size || !$id) {
				break;
			}
			$flags = $this->_toInt (substr ($fb, $i + $extraOffset, $extraSize));
			$data = substr ($fb, $i + $frameSize, $size);
			
			// If this is a text frame and there's a text encoding byte, strip it out
			if ($id{0} == 'T' && (ord ($data{0}) == 0 || ord ($data{0}) == 1)) {
				$data = substr ($data, 1);
			}
			
			// Check for Unicode encoding
			if ((ord($data{0}) == 0xff || ord($data{0}) == 0xfe) && (ord($data{1}) == 0xff || ord($data{1}) == 0xfe)) {
				$data = mb_convert_encoding ($data, 'UTF-8', 'UCS-2');
			}
			$i += $frameSize + $size;
			$tags[trim($id)] = $data;
			
		}
		
		// Save the common tags
		foreach ($this->_tags as $tag=>$map) {
			if (isset($tags[$tag])) {
				$this->$map = $tags[$tag];
			}
		}
		
		// Format the genre tag
		if (preg_match ('/\((\d+)\)/', $this->genre, $match)) {
			$this->genre = $this->_genres [$match[1]];
		}
		
		// Format the disc tag
		if (preg_match ('@(\d+)/(\d+)@', $this->disc, $match)) {
			$this->disc = $match[1];
		}
		if (!$this->disc) {
			$this->disc = 1;
		}
		
		// Format the track tag if it's not numeric
		if (!is_numeric($this->track)) {
			$e = explode('/', $this->track);
			$this->track = $e[0];
		}
		
		// Save the entire tag list to the public object and the current file pointer position
		$this->tags = (object)$tags;
		$this->_id3v2End = ftell($file);
		
		return $hSize;
	
	}
	
	/**
	 * Calculates the length of the song in seconds
	 * @param handle $file Handle to the mp3 file
	 * @param int $hSize Size of the ID3v2 header. 0 if not present
	 */
	private function _calcLength($file, $hSize) {
		
		$length = 0;
		$avgBitRate = 0;
		$frameCount = 0;
		
		// Seek to the beginning of the MPG data and read it
		if ($hSize !== false && $hSize > 0) {
			fseek($file, $hSize + 10);
		} else {
			fseek($file, 0);
		}
		$mpgHead = $this->_toInt(fread($file, 4));
		
		// Loop through all the frames in the file
		while (($mpgHead >> 21 & 0x7ff) == 0x7ff) {
			
			// Get the MPEG information
			$bitRate = isset($this->_bitrates[$mpgHead >> 12 & 0x0f]) ? $this->_bitrates[$mpgHead >> 12 & 0x0f] : null;
			if (null != $bitRate) {
				$frequency = $this->_freq[$mpgHead >> 10 & 0x03];
				$padBit = ($mpgHead & 0x200) > 0 ? 1 : 0;
				$frameSize = 0;
				if ($frequency != 0) {
					$frameSize = (int)(144 * $bitRate / $frequency + $padBit);
					$avgBitRate += $bitRate;
					$frameCount++;
				} else {
					$this->_err = ID3_BAD_LENGTH;
					return;
				}
				
				// Calculate how many seconds this frame is and add to the running total
				$length += $frameSize;
				
				// Seek to the end of the frame and read in the next header
				fseek($file, $frameSize - 4, SEEK_CUR);
				$mpgHead = $this->_toInt(fread($file, 4));
				
			} else {
				$this->_err = ID3_BAD_LENGTH;
				return;
			}
			
		}
		
		// Set the average bitrate
		if ($frameCount > 0) {
			$this->bitrate = $avgBitRate / $frameCount;
			$this->length = floor($length / ($this->bitrate / 8));
		} else {
			$this->_err = ID3_BAD_LENGTH;
		}
		
	}
	
	/**
	 * Calculates the size of an ID3v2 frame
	 * @param string, int The frame header
	 * @return int The size of the frame in bytes
	 */
	private function _id3v2FrameSize($header) {
		if (is_string($header)) {
			$num = $this->_toInt ($header);
		} else {
			$num = $header;
		}
		return ($num & 0x7f) | (($num & 0x7f00) >> 1) | (($num & 0x7f0000) >> 2) | (($num & 0x7f000000) >> 3);
	}
	
}

?>