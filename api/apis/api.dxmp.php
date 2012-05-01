<?php

require('apis/api.content.php');
require('libs/S3.php');
require('libs/id3lib.php');

class DXMP extends Content {

	private static $_awsLocation = 'http://dxmp.s3.amazonaws.com/';
	private static $_localSongCache = '/var/www/dxmp/cache';
	private static $_localImageCache = '/var/www/dxmp/images';
	private static $_awsKey = 'AKIAI6QG5IZ7SYVCXPWA';
	private static $_awsSecret = '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';
	private static $_useAWS = true;
	private static $_maxArtWidth = 600;
	private static $_userTimeout = 300;
	private static $_userArrayCacheKey = 'DXMP_Users';
	
	public static function getData($vars) {
	
		$cacheKey = 'dxmpContent';
		$types = array('album', 'song', 'show', 'video');
		$content = false;
		
		if (!isset($vars['noCache'])) {
			$content = DxCache::Get($cacheKey);
		}		
		
		if (!$content) {
			$content = array();
			foreach ($types as $type) {
				$noTags = !($type == 'song');
				$select = $type == 'song' || $type == 'video' ? 'title,parent,meta,type' : 'title,meta,type';
				$noCount = $type == 'song' || $type == 'video' ? 'true' : null;
				$temp = Content::getContent(array( 'contentType' => $type, 'max' => 0, 'noCount' => $noCount, 'select' => $select, 'noTags' => $noTags ));
				$content = count($temp->content) > 0 ? array_merge($content, $temp->content) : $content;
				DxCache::Set($cacheKey, $content, 2592000);
			}
		}

		return $content;
		
	}
	
	/**
	 * Registers a user in the cache so that commands may be sent
	 */
	public static function registerUser() {
		
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
		
			// Get the users array from cache
			$users = DxCache::Get(self::$_userArrayCacheKey);
			if (false === $users) {
				$users = array();
			}
			
			// Generate the ID for this user
			$id = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
			
			// Iterate through all the users to see if this one is already registered
			$obj = false;
			foreach ($users as &$user) {
				if ($user->id === $id) {
					$obj = $user;
					$user->last_seen = time();
					break;
				}
			}
			
			// If the user object was blank, create a new one
			if (false === $obj) {
				$obj = new stdClass();
				$obj->id = $id;
				$obj->ip = $_SERVER['REMOTE_ADDR'];
				$obj->user_agent = $_SERVER['HTTP_USER_AGENT'];
				$obj->actions = array();
				$obj->last_seen = time();
				$users[] = $obj;
			}
			
			// Update the users array and save back to cache
			DxCache::Set(self::$_userArrayCacheKey, $users);
			return $obj;
			
		}
		
	}
	
	/**
	 * Returns the most played songs for a given time period
	 */
	function getMostPlayedAlbums($vars) {

		$_max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 25;
		$_minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? $vars['minDate'] : 0;
		$_maxDate = isset($vars['maxDate']) && is_numeric($vars['maxDate']) ? $vars['maxDate'] : time();
		$_week = isset($vars['week']) && is_numeric($vars['week']) ? $vars['week'] : false;
		$_year = isset($vars['year']) && is_numeric($vars['year']) ? $vars['year'] : false;
		
		if (false !== $_year && false !== $_week) {
			
			// Calculate what day the first week started on. First week begins on the first Sunday of the year
			$_minDate = mktime(0, 0, 0, 1, 1, $_year);
			$dayOfWeek = date('w', $_minDate);
			if ($dayOfWeek > 0) {
				$_minDate += (7 - $dayOfWeek) * 86400;
			}
			$_minDate += 604800 * ($_week - 1);
			$_maxDate = $_minDate + 604799; // 12:59:59 the following Saturday
			
		}
		
		$out = new stdClass();
		$out->startDate = $_minDate;
		$out->endDate = $_maxDate;
		$out->songs = array();
		
		$query = 'SELECT a.content_id AS album_id, a.content_title AS album_title, a.content_meta, COUNT(1) AS total FROM hits l INNER JOIN content t ON t.content_id = l.content_id INNER JOIN content a ON a.content_id = t.content_parent WHERE l.hit_date >= ' . $_minDate . ' AND l.hit_date <= ' . $_maxDate . ' GROUP BY t.content_parent ORDER BY total DESC LIMIT ' . $_max;
		db_Connect();
		$result = db_Query($query);
		while ($row = db_Fetch($result)) {
			
			$obj = new stdClass();
			$obj->id = $row->album_id;
			$obj->title = $row->album_title;
			$obj->count = $row->total;
			$meta = json_decode($row->content_meta);
			if (isset($meta->art)) {
				$obj->album_art = $meta->art;
			}
			$out->songs[] = $obj;
			
		}
		
		return $out;
		
	}
	
	/**
	 * Sets an action for a user
	 */
	public static function setUserAction($vars) {
	
		$retVal = false;
	
		// Validate the ID
		if (preg_match('/[a-f0-9]{32}/', $vars['id']) && isset($vars['action'])) {
		
			$users = self::_getUserArray();
			
			// Find the user in question and add the action to the queue
			foreach ($users as &$user) {
				if ($user->id === $vars['id']) {
					$action = new stdClass();
					$action->name = $vars['action'];
					$action->param = isset($vars['param']) ? $vars['param'] : null;
					$action->timestamp = time();
					$user->actions[] = $action;
					$retVal = true;
					DxCache::Set(self::$_userArrayCacheKey, $users);
					break;
				}
			}
			
		}
		
		return $retVal;
	
	}
	
	/**
	 * Gets a list of waiting actions for the current user
	 */
	public static function getCurrentActions($vars) {
		
		$id = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
		$retVal = false;
		$users = self::_getUserArray();
		foreach ($users as &$user) {
			if ($user->id === $id) {
				$retVal = $user->actions;
				$user->actions = array();
				break;
			}
		}
		
		// Update the cache
		DxCache::Set(self::$_userArrayCacheKey, $users);
		
		return $retVal;
		
	}
	
	/**
	 * Returns all the currently active users
	 */
	public static function getActiveUsers() {
		return self::_getUserArray();
	}
	
	/**
	 * Returns the location of the track
	 */
	public static function getTrackFile($vars) {
	
		$id = isset($vars['id']) && is_numeric($vars['id']) ? $vars['id'] : false;
		
		if (false !== $id) {
			
			$song = Content::getContent(array('id'=>$id, 'noTags'=>true, 'noCount'=>true));
			if (null != $song) {
				header('Location: ' . self::$_awsLocation . 'songs/' . $song->content[0]->meta->filename);
				exit;
			}
			
		}
	
	}
	
	public static function postUploadSong($vars, $obj) {
		
		$retVal = false;
		
		if (is_uploaded_file($_FILES['file']['tmp_name']) && isset($vars['id'])) {
		
			// Move the song over to the local directory
			$fileName = parent::_createPerma(substr($_FILES['file']['name'], 0, strlen($_FILES['file']['name']) - 4)) . '.mp3';
			$filePath = self::$_localSongCache . '/' . $fileName;
			$data = file_get_contents($_FILES['file']['tmp_name']);
			$id = $vars['id'];
			$file = fopen($filePath, 'wb');
			if ($file) {
				
				// Write the new file, decoding the base64 stuff and delete the temp file
				fwrite($file, base64_decode($data));
				fclose($file);
				unlink($_FILES['file']['tmp_name']);
				
				$id3 = new ID3Lib($filePath);
				if (strlen($id3->title) > 0) {
				
					// Upload to AWS
					if (self::$_useAWS) {
						$s3 = new S3(self::$_awsKey, self::$_awsSecret);
						$data = $s3->inputFile($filePath);
						$s3->putObject($data, 'dxmp', 'songs/' . $fileName, S3::ACL_PUBLIC_READ);
						unlink($filePath); // Delete the original file
					}
				
					$song = new Content();
					$song->title = strlen($id3->title) > 0 ? $id3->title : $fileName;
					$song->type = 'song';
					$song->date = time();
					$song->parent = self::_getAlbumId($id3->album, $id3);
					$song->meta = new stdClass();
					$song->meta->track = $id3->track;
					$song->meta->disc = $id3->disc;
					$song->meta->year = $id3->year;
					$song->meta->genre = $id3->genre;
					$song->meta->duration = $id3->length;
					$song->meta->filename = $fileName;
					$retVal = Content::syncContent(null, $song);
					$retVal->content = $id;
					
				} else {
					$retVal = array('id' => $id, 'message' => 'No ID3 tags');
				}
				
			} else {
				$retVal = array('id' => $id, 'message' => 'Unable to open destination file');
			}
		
		} else {
			$retVal = array('id' => $id, 'message' => 'No file');
		}
		
		return $retVal;
		
	}
	
	/**
	 * Uploads an image and sets either the album art or wallpaper for an album (depending on size)
	 */
	public static function postImage($vars, $obj) {
		
		$retVal = false;
		
		$album = isset($vars['album']) && is_numeric($vars['album']) ? $vars['album'] : false;
		$id = $vars['id'];
		
		if (is_uploaded_file($_FILES['file']['tmp_name']) && isset($vars['id']) && false !== $album) {
		
			// Get the name of the album
			$album = Content::getContent(array('id'=>$album, 'contentType'=>'album'));
			if (count($album->content) == 1) {
				
				$album = $album->content[0];				
					
				// Move the song over to the local directory
				$ext = strtolower(end(explode('.', $_FILES['file']['name'])));
				$fileName = $album->perma . '-tmp.' . $ext;
				$filePath = self::$_localImageCache . '/' . $fileName;
				$data = file_get_contents($_FILES['file']['tmp_name']);
				$file = fopen($filePath, 'wb');
				
				if ($file) {
				
					// Write the new file, decoding the base64 stuff and delete the temp file
					fwrite($file, base64_decode($data));
					fclose($file);
					unlink($_FILES['file']['tmp_name']);
					
					// Load in the image so we can get its width (what we'll base wallpaper/art on)
					$img = null;
					switch ($ext) {
						case 'jpg':
						case 'jpeg':
							$img = imagecreatefromjpeg($filePath);
							break;
						case 'png':
							$img = imagecreatefrompng($filePath);
							break;
						case 'gif':
							$img = imagecreatefromgif($filePath);
							break;
					}
					
					if (null != $img) {
						
						$type = imagesx($img) > self::$_maxArtWidth ? 'wallpaper' : 'art';
						
						// Rename the file taking into account the type
						$fileName = $album->perma . '-' . $type . '.' . $ext;
						$fileOldPath = $filePath;
						$filePath = self::$_localImageCache . '/' . $fileName;
						rename($fileOldPath, $filePath);
						
						// Upload to AWS
						if (self::$_useAWS) {
							$s3 = new S3(self::$_awsKey, self::$_awsSecret);
							$data = $s3->inputFile($filePath);
							$s3->putObject($data, 'dxmp', 'images/' . $fileName, S3::ACL_PUBLIC_READ);
						}
					
						switch ($type) {
							case 'wallpaper':
								$album->meta->wallpaper = $fileName;
								break;
							case 'art':
								$album->meta->art = $fileName;
								break;
						}
						$retVal = Content::syncContent(null, $album);
						$retVal->title .= ' (' . $type . ')';
						$retVal->content = $id;
							
					} else {
						$retVal = array('id' => $id, 'message' => 'Invalid image');
					}
				} else {
					$retVal = array('id' => $id, 'message' => 'Unable to open destination file (' . $filePath . ')');
				}
			} else {
				$retVal = array('id' => $id, 'message' => 'Invalid album ID');
			}
		
		} else {
			$retVal = array('id' => $id, 'message' => 'No file or invalid album');
		}
		
		return $retVal;
		
	}
	
	/**
	 * Returns the latest added tracks and their respective albums
	 */
	public static function getLatest($vars) {
		
		$max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 25;
		
		db_Connect();
		$query = db_Query('SELECT s.content_id, s.content_date, s.content_title, s.content_parent, a.content_meta FROM content s LEFT OUTER JOIN content a ON a.content_id = s.content_parent WHERE s.content_type = "song" ORDER BY s.content_id DESC LIMIT ' . $max);
		$retVal = array();
		while ($row = db_Fetch($query)) {
			$obj = new stdClass();
			$obj->id = $row->content_id;
			$obj->title = $row->content_title;
			$obj->meta = json_decode($row->content_meta);
			$obj->meta->art = isset($obj->meta->art) ? $obj->meta->art : 'no_art.png';
			$obj->album = $row->content_parent;
			$retVal[] = $obj;
		}
		
		return $retVal;
	}
	
	private static function _getAlbumId($title, $id3 = null) {
		
		$retVal = false;
		$album = Content::getContent(array('title' => $title, 'contentType' => 'album'));
		if ($album->count > 0) {
			$retVal = $album->content[0]->id;
		} else {
			$album = new Content();
			$album->title = $title;
			$album->type = 'album';
			$album = Content::syncContent(null, $album);
			if ($album) {
				$retVal = $album->id;
				if (null !== $id3) {
					$fileName = md5($album->perma . '-art') . '.jpg';
					$filePath = self::$_localImageCache . '/' . $fileName;
					
					if ($id3->savePicture($filePath)) {
						if (self::$_useAWS) {
							$s3 = new S3(self::$_awsKey, self::$_awsSecret);
							$data = $s3->inputFile($filePath);
							$s3->putObject($data, 'dxmp', 'images/' . $fileName, S3::ACL_PUBLIC_READ);
						}
						$album->meta = new stdClass;
						$album->meta->art = $fileName;
						Content::syncContent(null, $album);
					}
					
				}
			}
			
		}

		return $retVal;
		
	}
	
	/**
	 * Returns information about the last song played and it's album information
	 */
	public static function getLastSongPlayed() {
		$retVal = false;
		
		db_Connect();
		$result = db_Query('SELECT s.content_title AS song_title, a.content_title AS album_title, a.content_meta, h.hit_date AS date_played FROM hits h INNER JOIN content s ON s.content_id = h.content_id INNER JOIN content a ON a.content_id = s.content_parent ORDER BY h.hit_date DESC LIMIT 1');
		while ($row = db_Fetch($result)) {
			$retVal = $row;
			$retVal->images = json_decode($row->content_meta);
			if (isset($retVal->images->art)) {
				$retVal->images->art = self::$_awsLocation . 'images/' . $retVal->images->art;
			}
			if (isset($retVal->images->wallpaper)) {
				$retVal->images->wallpaper = self::$_awsLocation . 'images/' . $retVal->images->wallpaper;
			}
			unset($retVal->content_meta);
		}
		
		return $retVal;
	}
	
	/**
	 * Sorts trending songs based upon the trend weight
	 */
	private static function _trendSort($a, $b) {
		return $a->weight < $b->weight ? -1 : $a->weight == $b->weight ? 0 : 1;
	}

	/**
	 * Gets the cached users array or creates a new one if none exists
	 */
	private static function _getUserArray() {
		$retVal = DxCache::Get(self::$_userArrayCacheKey);
		return false === $retVal ? array() : $retVal;
	}
	
}

DXMP::registerUser();
