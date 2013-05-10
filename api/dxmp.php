<?php

namespace Api {
    
    use Lib;
    use stdClass;
    
    define('CACHE_CONTENT_UPDATE', 3600);

    class DXMP extends Content {

        private static $_maxArtWidth = 600;
        private static $_userTimeout = 300;
        
        /**
         * Retrieves all content needed to run the player
         */
        public static function getData($vars) {
        
            $cacheKey = 'dxmpContent';
            $cacheDateKey = 'dxmpContentDate';
            $lastUpdate = Lib\Cache::Get($cacheDateKey) ?: 0;
            $contentUpdate = Lib\Cache::Get(CACHE_CONTENT_UPDATE) ?: 1;

            $types = [ 'album', 'song', 'show', 'video' ];
            $content = false;
            
            if (!isset($vars['noCache']) && $lastUpdate > $contentUpdate) {
                $content = Lib\Cache::Get($cacheKey);
            }		
            
            if (!$content) {
                $content = new stdClass;
                foreach ($types as $type) {
                    $content->$type = null;
                    $noTags = !($type == 'song');
                    $select = $type == 'song' || $type == 'video' ? 'title,parent,meta,type' : 'title,meta,type';
                    $noCount = $type == 'song' || $type == 'video' ? 'true' : null;
                    $temp = Content::getContent([ 'contentType' => $type, 'max' => 0, 'noCount' => $noCount, 'select' => $select, 'noTags' => $noTags ]);
                    if (isset($temp->content)) {
                        $content->$type = $temp->content;
                    }
                }
            }

            Lib\Cache::Set($cacheKey, $content, 2592000);
            Lib\Cache::Set($cacheDateKey, time());
            return $content;
            
        }
        
        public static function savePlaylist($vars) {
            if (isset($vars['songs']) && isset($vars['name'])) {
                $content = new Content();
                $content->title = $vars['name'];
                $content->body = $vars['songs'];
                $content->type = 'list';
                return Content::syncContent(null, $content);
            }
            return false;
        }
        
        public static function buildSmartPlaylist($vars) {
            
            $retVal = false;
            
            $id = Lib\Url::getInt('id', null, $vars);
            if ($id) {
                
                $max = Lib\Url::getInt('max', 25, $vars);
                $tags = Content::getTags([ 'id' => $id ]);
                if (count($tags) > 0) {
                    
                    $params = [ ':id' => $id ];
                    $where = '';
                    $i = 0;
                    foreach ($tags as $tag) {
                        $params[':tag' . $i] = $tag->name;
                        $where .= ' OR tag_name = :tag' . $i++;
                    }
                    $where = substr($where, 4, strlen($where));
                    
                    $query = 'SELECT content_id FROM tags WHERE (' . $where . ') AND content_id != :id GROUP BY content_id ORDER BY COUNT(1) + (RAND() * ' . (count($tags) * 2) . ') DESC LIMIT ' . $max;
                    
                    $result = Lib\Db::Query($query, $params);
                    if ($result->count > 0) {
                        $retVal = array();
                        while ($row = Lib\Db::Fetch($result)) {
                            $retVal[] = (int) $row->content_id;
                        }
                    }
                    
                }
                
            }
            
            return $retVal;
            
        }
        
        /**
         * Returns the location of the track
         */
        public static function getTrackFile($vars) {
        
            $id = Lib\Url::getInt('id', null, $vars);
            
            if ($id) {
                
                $song = Content::getContent([ 'id' => $id, 'noTags' => true, 'noCount' => true ]);
                if (null != $song) {
                    $user = Lib\Url::get('userName', null, $_COOKIE);
                    if ($user) {
                        Content::logContentView([ 'id' => $id, 'hitType' => 1, 'user' => $user ]);
                        
                        // Send a global action to all other users about this play
                        $action = new stdClass;
                        $action->songId = $id;
                        $action->user = $user;
                        Actions::setGlobalAction([ 'action' => 'msg_user_play', 'param' => $action ]);
                        
                    }
                    header('Location: ' . AWS_LOCATION . 'songs/' . $song->content[0]->meta->filename);
                    exit;
                }
                
            }
        
        }
        
        /**
         * Uploads a song, distributes to CDN, and creates entry in the database
         */
        public static function postUploadSong($vars, $obj) {
            
            $retVal = false;
            
            $uploadId = Lib\Url::get('upload', null, $vars);
            
            if (is_uploaded_file($_FILES['file']['tmp_name']) && $uploadId) {
            
                // Move the song over to the local directory
                $fileName = parent::_createPerma(substr($_FILES['file']['name'], 0, strlen($_FILES['file']['name']) - 4)) . '.mp3';
                $filePath = LOCAL_CACHE_SONGS . '/' . $fileName;
                $data = file_get_contents($_FILES['file']['tmp_name']);
                $file = is_writable(LOCAL_CACHE_SONGS) ? fopen($filePath, 'wb') : false;
                if ($file) {
                    
                    // Write the new file, decoding the base64 stuff and delete the temp file
                    fwrite($file, base64_decode($data));
                    fclose($file);
                    unlink($_FILES['file']['tmp_name']);
                    
                    $id3 = new Lib\ID3Lib($filePath);
                    if (strlen($id3->title) > 0) {
                    
                        // Upload to AWS
                        if (AWS_ENABLED) {
                            $s3 = new S3(AWS_KEY, AWS_SECRET);
                            $data = $s3->inputFile($filePath);
                            $s3->putObject($data, 'dxmp', 'songs/' . $fileName, S3::ACL_PUBLIC_READ);
                            unlink($filePath); // Delete the original file
                        }
                    
                        $song = new Content();
                        $song->parent = self::_getAlbumId($id3->album, $id3);
                        if ($song->parent !== false) {
                            $song->title = strlen($id3->title) > 0 ? $id3->title : $fileName;
                            $song->type = 'song';
                            $song->date = time();
                            $song->meta = new stdClass();
                            $song->meta->track = $id3->track;
                            $song->meta->disc = $id3->disc;
                            $song->meta->year = $id3->year;
                            $song->meta->genre = $id3->genre;
                            $song->meta->duration = $id3->length;
                            $song->meta->filename = $fileName;
                            $retVal = Content::syncContent(null, $song);
                            $retVal->content = $uploadId;
                            Actions::setGlobalAction([ 'action'=>'msg_new_song' ]);
                        } else {
                            $retVal = [ 'id' => $uploadId, 'message' => 'Invalid album title' ];
                        }
                        
                    } else {
                        $retVal = [ 'id' => $uploadId, 'message' => 'No ID3 tags' ];
                    }
                    
                } else {
                    $retVal = [ 'id' => $uploadId, 'message' => 'Unable to open destination file: ' . $filePath ];
                }
            
            } else {
                $retVal = [ 'id' => $uploadId, 'message' => 'No file' ];
            }
            
            return $retVal;
            
        }
        
        /**
         * Uploads an image and sets either the album art or wallpaper for an album (depending on size)
         */
        public static function postImage($vars, $obj) {
            
            $retVal = false;
            
            $id = Lib\Url::getInt('id', null, $vars);
            $uploadId = Lib\Url::get('upload', null, $vars);
            
            if (is_uploaded_file($_FILES['file']['tmp_name']) && $id) {
            
                // Get the name of the album
                $content = Content::getContent([ 'id' => $id ]);
                if (count($content->content) == 1) {
                    
                    $content = $content->content[0];				
                        
                    // Move the song over to the local directory
                    $ext = strtolower(end(explode('.', $_FILES['file']['name'])));
                    $fileName = $content->perma . '-tmp.' . $ext;
                    $filePath = LOCAL_CACHE_IMAGES . '/' . $fileName;
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
                            $fileName = $content->perma . '-' . $content->type . '-' . $type . '.' . $ext;
                            $fileOldPath = $filePath;
                            $filePath = LOCAL_CACHE_IMAGES . '/' . $fileName;
                            rename($fileOldPath, $filePath);
                            
                            // Upload to AWS
                            if (AWS_ENABLED) {
                                $s3 = new S3(AWS_KEY, AWS_SECRET);
                                $data = $s3->inputFile($filePath);
                                $s3->putObject($data, 'dxmp', 'images/' . $fileName, S3::ACL_PUBLIC_READ);
                            }
                        
                            switch ($type) {
                                case 'wallpaper':
                                    $content->meta->wallpaper = $fileName;
                                    break;
                                case 'art':
                                    $content->meta->art = $fileName;
                                    break;
                            }
                            $retVal = Content::syncContent(null, $content);
                            $retVal->title .= ' (' . $type . ')';
                            $retVal->content = $id;
                                
                        } else {
                            $retVal = [ 'id' => $uploadId, 'message' => 'Invalid image' ];
                        }
                    } else {
                        $retVal = [ 'id' => $uploadId, 'message' => 'Unable to open destination file (' . $filePath . ')' ];
                    }
                } else {
                    $retVal = [ 'id' => $uploadId, 'message' => 'Invalid content ID' ];
                }
            
            } else {
                $retVal = [ 'id' => $uploadId, 'message' => 'No file or invalid album' ];
            }
            
            return $retVal;
            
        }
        
        /**
         * Returns the latest added tracks and their respective albums
         */
        public static function getLatest($vars) {
            
            $max = Lib\Url::getInt('max', 25, null);
            
            $query = Lib\Db::Query('SELECT s.content_id, s.content_date, s.content_title, s.content_parent, a.content_meta FROM content s LEFT OUTER JOIN content a ON a.content_id = s.content_parent WHERE s.content_type = "song" ORDER BY s.content_id DESC LIMIT ' . $max);
            $retVal = [];
            while ($row = Lib\Db::Fetch($query)) {
                $obj = new stdClass;
                $obj->id = (int) $row->content_id;
                $obj->title = $row->content_title;
                $obj->meta = json_decode($row->content_meta);
                $obj->meta = null == $obj->meta ? new stdClass : $obj->meta;
                $obj->meta->art = isset($obj->meta->art) ? $obj->meta->art : 'no_art.png';
                $obj->album = $row->content_parent;
                $retVal[] = $obj;
            }
            
            return $retVal;
        }
        
        private static function _getAlbumId($title, $id3 = null) {
            
            $retVal = false;
            $album = Content::getContent([ 'title' => $title, 'contentType' => 'album' ]);
            if ($album->count > 0) {
                $retVal = $album->content[0]->id;
            } else if (strlen(trim($title)) > 0) {
                $album = new Content();
                $album->title = $title;
                $album->type = 'album';
                $album = Content::syncContent(null, $album);
                if ($album) {
                    $retVal = $album->id;
                    if (null !== $id3) {
                        $fileName = md5($album->perma . '-album-art') . '.jpg';
                        $filePath = LOCAL_CACHE_IMAGES . '/' . $fileName;
                        
                        if ($id3->savePicture($filePath)) {
                            if (AWS_ENABLED) {
                                $s3 = new S3(AWS_KEY, AWS_SECRET);
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
            
            $result = Lib\Db::Query('SELECT s.content_title AS song_title, a.content_title AS album_title, a.content_meta, h.hit_date AS date_played FROM hits h INNER JOIN content s ON s.content_id = h.content_id INNER JOIN content a ON a.content_id = s.content_parent ORDER BY h.hit_date DESC LIMIT 1');
            while ($row = Lib\Db::Fetch($result)) {
                $retVal = $row;
                $retVal->images = json_decode($row->content_meta);
                if (isset($retVal->images->art)) {
                    $retVal->images->art = AWS_LOCATION . 'images/' . $retVal->images->art;
                }
                if (isset($retVal->images->wallpaper)) {
                    $retVal->images->wallpaper = AWS_LOCATION . 'images/' . $retVal->images->wallpaper;
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
        
    }

}