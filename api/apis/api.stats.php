<?php

class Stats {

	public static function getTopUserTracks($vars) {
		$retVal = array();
		if (isset($vars['user'])) {
			db_Connect();
			$minDate = isset($vars['minDate']) && is_numeric($vars['minDate']) ? $vars['minDate'] : strtotime('-30 days');
			$result = db_Query('SELECT c.*, COUNT(1) AS total FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.hit_user = "' . db_Escape($vars['user']) . '" AND h.hit_date >= ' . $minDate . ' GROUP BY h.content_id ORDER BY total DESC LIMIT 30');
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
			$result = db_Query('SELECT c.content_title AS title, COUNT(1) AS total, h.hit_user AS user FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.content_id = ' . $id . ' GROUP BY h.hit_user ORDER BY total DESC');
			$retVal = array();
			while ($row = db_Fetch($result)) {
				$row->total = (int)$row->total;
				$retVal[] = $row;
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
		
		$query = 'SELECT t.content_id AS track_id, t.content_title AS track_title, a.content_title AS album_title, a.content_meta, COUNT(1) AS total FROM hits l INNER JOIN content t ON t.content_id = l.content_id INNER JOIN content a ON a.content_id = t.content_parent WHERE l.hit_date >= ' . $_minDate . ' AND l.hit_date <= ' . $_maxDate . ' GROUP BY l.content_id ORDER BY total DESC LIMIT ' . $_max;
		db_Connect();
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
		
		$query = 'SELECT (SELECT COUNT(1) FROM hits WHERE hit_date >= ' . $_minDate . ' AND hit_date <= ' . $_maxDate . ') / (SELECT COUNT(DISTINCT content_id) FROM hits WHERE hit_date >= ' . $_minDate .' AND hit_date <= ' . $_maxDate . ') AS AvgPlays';
		$row = db_Fetch(db_Query($query));
		$avgPlays = $row->AvgPlays != null ? $row->AvgPlays : 0;

		$where = '';
		if ($_user) {
			$where = 'AND hit_user = "' . db_Escape($_user) . '" ';
		}

		$result = db_Query('SELECT content_id, MIN(hit_date) AS first_play, COUNT(1) AS total FROM hits WHERE hit_date >= ' . $_minDate . ' AND hit_date <= ' . $_maxDate . ' ' . $where . 'GROUP BY content_id HAVING total >= ' . $avgPlays);
		$weights = array();
		while ($row = db_Fetch($result)) {
			$obj = new stdClass();
			$obj->days = (($_maxDate - $row->first_play) / 86400);
			$obj->weight = $row->total / $obj->days;
			$obj->id = $row->content_id;
			$weights[] = $obj;
		}
		uasort($weights, 'self::_trendSort');
		
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
	 * Sorts trending songs based upon the trend weight
	 */
	private static function _trendSort($a, $b) {
		return $a->weight < $b->weight ? -1 : $a->weight == $b->weight ? 0 : 1;
	}

}