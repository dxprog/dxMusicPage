<?php

class Stats {

	public static function getTopUserTracks($vars) {
		$retVal = array();
		if (isset($vars['user'])) {
			db_Connect();
			$minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? $vars['minDate'] : strtotime('-30 days');
			$result = db_Query('SELECT c.*, COUNT(1) AS total FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.hit_user = "' . db_Escape($vars['user']) . '" AND h.hit_date >= ' . $minDate . ' AND h.hit_type = 2 GROUP BY h.content_id ORDER BY total DESC LIMIT 30');
			while ($row = db_Fetch($result)) {
				$obj = new stdClass;
				$obj->id = $row->content_id;
				$obj->title = $row->content_title;
				$obj->album = $row->content_parent;
				$obj->count = $row->total;
				$retVal[] = $obj;
			}
		}
		return $retVal;
	}
	
	public static function getTrackUsers($vars) {
		
		$id = isset($vars['id']) && is_numeric($vars['id']) ? $vars['id'] : false;
		$retVal = false;
		if ($id) {
			db_Connect();
			$result = db_Query('SELECT c.content_title AS title, COUNT(1) AS total, h.hit_user AS user FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.content_id = ' . $id . ' AND h.hit_type = 2 GROUP BY h.hit_user ORDER BY total DESC');
			$retVal = array();
			while ($row = db_Fetch($result)) {
				$row->total = (int)$row->total;
				$retVal[] = $row;
			}
			
		}
		
		return $retVal;
		
	}
	
	/**
	 * Returns the number of plays by day for a user with the option to narrow by song
	 */
	public static function getUserPlaysByDay($vars) {
		
		$retVal = null;
		
		$_user = isset($vars['user']) ? $vars['user'] : null;
		$_trackId = isset($vars['id']) && is_numeric($vars['id']) ? (int)$vars['id'] : null;
		$_minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? (int)$vars['minDate'] : time() - 86400 * 30;
		$_maxDate = isset($vars['maxDate']) && is_numeric($vars['maxDate']) ? (int)$vars['maxDate'] : time();
		
		if ($_user) {
			
			db_Connect();
			
			$_user = db_Escape($_user);
			$query = 'SELECT hit_date AS date, FLOOR(hit_date / 86400) AS day, SUM(CASE WHEN hit_user = "' . $_user . '" THEN 1 ELSE 0 END) AS user_plays, SUM(CASE WHEN hit_user != "' . $_user . '" THEN 1 ELSE 0 END) AS others_plays FROM hits WHERE';
			$query .= ' hit_date BETWEEN ' . $_minDate . ' AND ' . $_maxDate;
			if ($_trackId) {
				$query .= ' AND content_id = ' . $_trackId;
			}
			$query .= ' AND hit_type = 2 GROUP BY day';
			$result = db_Query($query);
			if (null != $result && $result->count > 0) {
				$retVal = [];
				while ($row = db_Fetch($result)) {
					$retVal[] = $row;
				}
			}
		
		}
		
		return $retVal;
	
	}
	
	/**
	 * Returns the most played songs for a given time period
	 */
	public static function getMostPlayedSongs($vars) {

		$_max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 25;
		$_minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? $vars['minDate'] : 0;
		$_maxDate = isset($vars['maxDate']) && is_numeric($vars['maxDate']) ? $vars['maxDate'] : time();
		$_week = isset($vars['week']) && is_numeric($vars['week']) ? $vars['week'] : false;
		$_year = isset($vars['year']) && is_numeric($vars['year']) ? $vars['year'] : false;
		$_user = isset($vars['user']) ? $vars['user'] : false;
		
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
		
		db_Connect();
		$_user = $_user ? ' AND l.hit_user = "' . db_Escape($_user) . '"' : '';
		$query = 'SELECT t.content_id AS track_id, t.content_title AS track_title, a.content_title AS album_title, a.content_meta, COUNT(1) AS total FROM hits l INNER JOIN content t ON t.content_id = l.content_id INNER JOIN content a ON a.content_id = t.content_parent WHERE l.hit_date >= ' . $_minDate . ' AND l.hit_date <= ' . $_maxDate . $_user . ' AND l.hit_type = 2 GROUP BY l.content_id ORDER BY total DESC LIMIT ' . $_max;
		$result = db_Query($query);
		while ($row = db_Fetch($result)) {
			
			$obj = new stdClass();
			$obj->id = $row->track_id;
			$obj->title = $row->track_title;
			$obj->count = $row->total;
			$meta = json_decode($row->content_meta);
			if (isset($meta->art)) {
				$obj->album_art = $meta->art;
			}
			$obj->album = $row->album_title;
			$out->songs[] = $obj;
			
		}
		
		return $out;
		
	}
	
	/**
	 * Gets play trends
	 */
	public static function getTrends($vars) {
	
		global $_sort, $_max, $_minDate, $_maxDate;
	
		$_sort = isset($vars['sort']) ? $vars['sort'] : 'desc';
		$_user = isset($vars['user']) ? $vars['user'] : false;
		$_max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 15;
		$_minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? $vars['minDate'] : time() - 604800;
		$_maxDate = isset($vars['maxDate']) && is_numeric($vars['maxDate']) ? $vars['maxDate'] : time();

		db_Connect();
		
		$where = 'AND hit_type = 2 ';
		if ($_user) {
			$where = 'AND hit_user = "' . db_Escape($_user) . '" ';
		}

		//$query = 'SELECT FLOOR((MAX(q.total) - MIN(q.total)) / 4) AS AvgPlays FROM (SELECT COUNT(1) AS total FROM hits WHERE hit_date >= ' . $_minDate . ' AND hit_date <= ' . $_maxDate . ' ' . $where .'GROUP BY content_id) AS q';
		$query = db_Query('SELECT q.total AS value, COUNT(1) AS total FROM (SELECT COUNT(1) AS total FROM hits WHERE hit_date >= ' . $_minDate . ' AND hit_date <= ' . $_maxDate . ' ' . $where . ' GROUP BY content_id ORDER BY total) AS q GROUP BY q.total ORDER BY total DESC');
		$results = array();
		while ($row = db_Fetch($query)) {
			$results[] = $row->value;
		}
		$results = array_keys($results);
		$magicNumber = $results[floor(count($results) * .5)];

		$result = db_Query('SELECT content_id, MIN(hit_date) AS first_play, COUNT(1) AS total FROM hits WHERE hit_date >= ' . $_minDate . ' AND hit_date <= ' . $_maxDate . ' ' . $where . 'GROUP BY content_id HAVING total >= ' . $magicNumber);
		$weights = array();
		while ($row = db_Fetch($result)) {
			$obj = new stdClass();
			$obj->plays = $row->total;
			$obj->days = (($_maxDate - $row->first_play) / 86400);
			$obj->weight = $row->total / $obj->days;
			$obj->id = $row->content_id;
			$weights[] = $obj;
		}
		uasort($weights, function($a, $b) { return $a->weight < $b->weight ? -1 : $a->weight == $b->weight ? 0 : 1; });
		
		$songs = array();
		$songs = $weights;
		if ($_sort == 'desc') {
			$songs = array_reverse($songs);
		}
		$songs = array_slice($songs, 0, $_max);
		unset($weights);
		$query = 'SELECT t.content_id, t.content_title, a.content_meta, t.content_parent FROM content t LEFT OUTER JOIN content a ON a.content_id = t.content_parent WHERE t.content_id IN (';
		foreach ($songs as $song) {
			$query .= $song->id . ', ';
		}
		$query .= '0)';
		$result = db_Query($query);
		$titles = array();
		while ($row = db_Fetch($result)) {
			$obj = new stdClass();
			$obj->title = $row->content_title;
			$meta = json_decode($row->content_meta);
			$obj->meta = $meta;
			$obj->album = $row->content_parent;
			$titles[$row->content_id] = $obj;
		}
		
		foreach ($songs as &$song) {
			if (isset($titles[$song->id])) {
				$song->title = $titles[$song->id]->title;
				$song->meta = $titles[$song->id]->meta;
				$song->album = $titles[$song->id]->album;
			} else {
				$song->title = 'Error';
				$song->meta = new stdClass();
				$song->meta->art = 'no_art.png';
				$song->album = $song->id;
			}
		}
		
		return $songs;
	
	}
    
    /**
     * Trending items, but using an updated algorithm
     */
    public static function getHot($vars) {
    
        $retVal = null;
        $since = isset($vars['since']) && is_numeric($vars['since']) ? $vars['since'] : time() - 604800;
        $user = isset($vars['user']) ? $vars['user'] : false;
        $max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 15;
        
        db_Connect();
        
        // Get the average number of plays for the time period. Anything on the hot list has to have that many plays at least
        $query = 'SELECT COUNT(1) / COUNT(DISTINCT content_id) AS average_plays FROM hits WHERE hit_date >= ' . $since . ' AND hit_type = 2';
        if ($user) {
            $query .= ' AND hit_user = "' . db_Escape($user) . '"';
        }
        $row = db_Fetch(db_Query($query));
        $average = round($row->average_plays);
        
        $query = 'SELECT c.content_id, c.content_parent, c.content_title, a.content_meta, '
               . 'LOG10(COUNT(1)) + (AVG(h.hit_date) - ' . $since. ') / 45000 AS score, '
               . 'COUNT(1) AS plays, FROM_UNIXTIME(MIN(h.hit_date)) AS first_play, FROM_UNIXTIME(MAX(h.hit_date)) AS last_play '
               . 'FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE '
               . 'h.hit_type = 2 AND h.hit_date >= ' . $since;
        if ($user) {
            $query .= ' AND h.hit_user = "' . db_Escape($user) . '"';
        }
        
        $query .= ' GROUP BY h.content_id HAVING plays > ' . $average . ' ORDER BY score DESC LIMIT ' . $max;
        
        $result = db_Query($query);
        if ($result && $result->count > 0) {
            while ($row = db_Fetch($result)) {
                $obj = new stdClass;
                $obj->id = $row->content_id;
                $obj->title = $row->content_title;
                $obj->meta = json_decode($row->content_meta);
                $obj->album = $row->content_parent;
                $obj->score = $row->score;
                $obj->plays = $row->plays;
                $obj->firstPlay = $row->first_play;
                $obj->lastPlay = $row->last_play;
                $retVal[] = $obj;
            }
        }
    
        return $retVal;
    
    }
    
    /**
     * Trending items, but using an updated algorithm
     */
    public static function getBest($vars) {
    
        $retVal = null;
        $since = isset($vars['since']) && is_numeric($vars['since']) ? $vars['since'] : time() - 604800;
        $user = isset($vars['user']) ? $vars['user'] : false;
        $max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 15;
        
        db_Connect();
        
        // Get the average number of plays for the time period. Anything on the hot list has to have that many plays at least
        $query = 'SELECT COUNT(1) / COUNT(DISTINCT content_id) AS average_plays FROM hits WHERE hit_date >= ' . $since . ' AND hit_type = 2';
        if ($user) {
            $query .= ' AND hit_user = "' . db_Escape($user) . '"';
        }
        $row = db_Fetch(db_Query($query));
        $average = round($row->average_plays);
        
        $query = 'SELECT c.content_id, c.content_parent, c.content_title, c.content_meta, '
               . 'COUNT(1) AS plays, FROM_UNIXTIME(MIN(h.hit_date)) AS first_play, FROM_UNIXTIME(MAX(h.hit_date)) AS last_play '
               . 'FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE '
               . 'h.hit_type = 2 AND h.hit_date >= ' . $since;
        if ($user) {
            $query .= ' AND h.hit_user = "' . db_Escape($user) . '"';
        }
        
        $query .= ' GROUP BY h.content_id HAVING plays > ' . $average;
        
        $phat = 1;
        $z = 1.0;
        $z2 = $z * $z;
        
        $wrap = 'SELECT q.*, SQRT(' . $phat . ' + ' . $z2 . ' / (2 * q.plays) - ' . $z . ' * ((' . $phat . ' * (1 - ' . $phat . ') + ' . $z2 . ' / (4 * q.plays)) / q.plays)) / ( 1 + ' . $z2 . ' / q.plays) AS score';
        $wrap .= ' FROM (' . $query . ') AS q ORDER BY score DESC LIMIT ' . $max;
        
        $result = db_Query($wrap);
        if ($result && $result->count > 0) {
            while ($row = db_Fetch($result)) {
                $obj = new stdClass;
                $obj->id = (int) $row->content_id;
                $obj->title = $row->content_title;
                $obj->meta = json_decode($row->content_meta);
                $obj->album = (int) $row->content_parent;
                $obj->score = (float) $row->score;
                $obj->plays = (int) $row->plays;
                $obj->firstPlay = $row->first_play;
                $obj->lastPlay = $row->last_play;
                $retVal[] = $obj;
            }
        }
    
        return $retVal;
    
    }

}
