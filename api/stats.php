<?php

namespace Api {
    
    use Lib;
    use stdClass;
    
    class Stats {

        public static function getTopUserTracks($vars) {
            
            $retVal = [];
            $user = Lib\Url::get('user', null, $vars);
            
            if (isset($vars['user'])) {
                $minDate = Lib\Url::get('minDate', strtotime('-30 days'), $vars);
                $params = [ ':user' => $user, ':minDate' => $minDate ];
                $result = Lib\Db::Query('SELECT c.*, COUNT(1) AS total FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.hit_user = :user AND h.hit_date >= :minDate AND h.hit_type = 2 GROUP BY h.content_id ORDER BY total DESC LIMIT 30', $params);
                while ($row = Lib\Db::Fetch($result)) {
                    $obj = new Content($row);
                    $obj->count = (int) $row->total;
                    $retVal[] = $obj;
                }
            }
            return $retVal;
        }
        
        public static function getTrackUsers($vars) {
            
            $id = Lib\Url::getInt('id', null, $vars);
            $retVal = null;
            
            if ($id) {
                
                $result = Lib\Db::Query('SELECT c.content_title AS title, COUNT(1) AS total, h.hit_user AS user FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE h.content_id = :id AND h.hit_type = 2 GROUP BY h.hit_user ORDER BY total DESC', [ ':id' => $id ]);
                $retVal = array();
                while ($row = Lib\Db::Fetch($result)) {
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
            
            $user = Lib\Url::get('user', null, $vars);
            $id = Lib\Url::getInt('id', null, $vars);
            $minDate = Lib\Url::getInt('minDate', strtotime('-30 days'), $vars);
            $maxDate = Lib\Url::getInt('maxDate', time(), $vars);
            
            if ($user) {
                
                $params = [ ':user' => $user, ':minDate' => $minDate, ':maxDate' => $maxDate ];
                $query = 'SELECT hit_date AS date, FLOOR(hit_date / 86400) AS day, SUM(CASE WHEN hit_user = :user THEN 1 ELSE 0 END) AS user_plays, SUM(CASE WHEN hit_user != :user THEN 1 ELSE 0 END) AS others_plays FROM hits WHERE';
                $query .= ' hit_date BETWEEN :minDate AND :maxDate';
                if ($id) {
                    $query .= ' AND content_id = :id';
                    $params[':id'] = $id;
                }
                $query .= ' AND hit_type = 2 GROUP BY day';
                $result = Lib\Db::Query($query, $params);
                if (null != $result && $result->count > 0) {
                    $retVal = [];
                    while ($row = Lib\Db::Fetch($result)) {
                        $row->date = (int) $row->date;
                        $row->day = (int) $row->day;
                        $row->user_plays = (int) $row->user_plays;
                        $row->others_plays = (int) $row->others_plays;
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

            $max = Lib\Url::getInt('max', 25, $vars);
            $minDate = Lib\Url::getInt('minDate', 0, $vars);
            $maxDate = Lib\Url::getInt('maxDate', time(), $vars);
            $week = Lib\Url::getInt('week', null, $vars);
            $year = Lib\Url::getInt('year', null, $vars);
            $user = Lib\Url::get('user', null, $vars);
            
            if ($year && $week) {
                
                // Calculate what day the first week started on. First week begins on the first Sunday of the year
                $minDate = mktime(0, 0, 0, 1, 1, $year);
                $dayOfWeek = date('w', $minDate);
                if ($dayOfWeek > 0) {
                    $minDate += (7 - $dayOfWeek) * 86400;
                }
                $minDate += 604800 * ($week - 1);
                $maxDate = $minDate + 604799; // 12:59:59 the following Saturday
                
            }
            
            
            $out = new stdClass;
            $out->startDate = $minDate;
            $out->endDate = $maxDate;
            $out->songs = [];
            
            $params = [ ':minDate' => $minDate, ':maxDate' => $maxDate ];
            if ($user) {
                $params[':user'] = $user;
                $user = ' AND l.hit_user = :user';
            } else {
                $user = '';
            }
            
            $query = 'SELECT t.content_id AS track_id, t.content_title AS track_title, a.content_title AS album_title, a.content_meta, COUNT(1) AS total FROM hits l INNER JOIN content t ON t.content_id = l.content_id INNER JOIN content a ON a.content_id = t.content_parent WHERE l.hit_date >= :minDate AND l.hit_date <= :maxDate' . $user . ' AND l.hit_type = 2 GROUP BY l.content_id ORDER BY total DESC LIMIT ' . $max;
            $result = Lib\Db::Query($query, $params);
            while ($row = Lib\Db::Fetch($result)) {
                
                $obj = new stdClass();
                $obj->id = (int) $row->track_id;
                $obj->title = $row->track_title;
                $obj->count = (int) $row->total;
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
        
            $sort = Lib\Url::get('sort', 'desc', $vars);
            $user = Lib\Url::get('user', null, $vars);
            $max = Lib\Url::getInt('max', 15, $vars);
            $minDate = Lib\Url::getInt('minDate', strtotime('-7 days'), $vars);
            $maxDate = Lib\Url::getInt('maxDate', time(), $vars);
            
            $params = [ ':minDate' => $minDate, ':maxDate' => $maxDate ];
            $where = 'AND hit_type = 2 ';
            if ($user) {
                $where = 'AND hit_user = :user ';
                $params[':user'] = $user;
            }

            $result = Lib\Db::Query('SELECT q.total AS value, COUNT(1) AS total FROM (SELECT COUNT(1) AS total FROM hits WHERE hit_date >= :minDate AND hit_date <= :maxDate ' . $where . ' GROUP BY content_id ORDER BY total) AS q GROUP BY q.total ORDER BY total DESC', $params);
            $results = [];
            while ($row = Lib\Db::Fetch($result)) {
                $results[] = $row->value;
            }
            $results = array_keys($results);
            $magicNumber = $results[floor(count($results) * .5)];

            $result = Lib\Db::Query('SELECT content_id, MIN(hit_date) AS first_play, COUNT(1) AS total FROM hits WHERE hit_date BETWEEN :minDate AND :maxDate ' . $where . ' GROUP BY content_id HAVING total >= ' . $magicNumber, $params);
            $weights = [];
            while ($row = Lib\Db::Fetch($result)) {
                $obj = new stdClass();
                $obj->plays = $row->total;
                $obj->days = (($maxDate - $row->first_play) / 86400);
                $obj->weight = $row->total / $obj->days;
                $obj->id = $row->content_id;
                $weights[] = $obj;
            }
            uasort($weights, function($a, $b) { return $a->weight < $b->weight ? -1 : $a->weight == $b->weight ? 0 : 1; });
            
            $songs = array();
            $songs = $weights;
            if ($sort == 'desc') {
                $songs = array_reverse($songs);
            }
            $songs = array_slice($songs, 0, $max);
            unset($weights);
            $query = 'SELECT t.content_id, t.content_title, a.content_meta, t.content_parent FROM content t LEFT OUTER JOIN content a ON a.content_id = t.content_parent WHERE t.content_id IN (';
            foreach ($songs as $song) {
                $query .= $song->id . ', ';
            }
            $query .= '0)';
            $result = Lib\Db::Query($query);
            $titles = [];
            while ($row = Lib\Db::Fetch($result)) {
                $obj = new stdClass;
                $obj->title = $row->content_title;
                $meta = json_decode($row->content_meta);
                $obj->meta = $meta;
                $obj->album = (int) $row->content_parent;
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
            $since = Lib\Url::getInt('since', strtotime('-7 days'), $vars);
            $user = Lib\Url::get('user', null, $vars);
            $max = Lib\Url::getInt('max', 15, $vars);
            
            // Get the average number of plays for the time period. Anything on the hot list has to have that many plays at least
            $params = [ ':since' => $since ];
            $query = 'SELECT COUNT(1) / COUNT(DISTINCT content_id) AS average_plays FROM hits WHERE hit_date >= :since AND hit_type = 2';
            if ($user) {
                $params[':user'] = $user;
                $query .= ' AND hit_user = :user';
            }
            $row = Lib\Db::Fetch(Lib\Db::Query($query, $params));
            $average = round($row->average_plays);
            
            $query = 'SELECT c.content_id, c.content_parent, c.content_title, c.content_meta, '
                   . 'LOG10(COUNT(1)) + (AVG(h.hit_date) - :since) / 45000 AS score, '
                   . 'COUNT(1) AS plays, FROM_UNIXTIME(MIN(h.hit_date)) AS first_play, FROM_UNIXTIME(MAX(h.hit_date)) AS last_play '
                   . 'FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE '
                   . 'h.hit_type = 2 AND h.hit_date >= :since';
            if ($user) {
                $query .= ' AND h.hit_user = :user';
            }
            
            $query .= ' GROUP BY h.content_id HAVING plays > ' . $average . ' ORDER BY score DESC LIMIT ' . $max;
            
            $result = Lib\Db::Query($query, $params);
            if ($result && $result->count > 0) {
                while ($row = Lib\Db::Fetch($result)) {
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
        
        /**
         * Trending items, but using an updated algorithm
         */
        public static function getBest($vars) {
        
            $retVal = null;
            $since = Lib\Url::getInt('since', strtotime('-7 days'), $vars);
            $user = Lib\Url::get('user', null, $vars);
            $max = Lib\Url::getInt('max', 15, null);
            
            // Get the average number of plays for the time period. Anything on the hot list has to have that many plays at least
            $params = [ ':since' => $since ];
            $query = 'SELECT COUNT(1) / COUNT(DISTINCT content_id) AS average_plays FROM hits WHERE hit_date >= :since AND hit_type = 2';
            if ($user) {
                $params[':user'] = $user;
                $query .= ' AND hit_user = :user';
            }
            $row = Lib\Db::Fetch(Lib\Db::Query($query, $params));
            $average = round($row->average_plays);
            
            $query = 'SELECT c.content_id, c.content_parent, c.content_title, c.content_meta, '
                   . 'COUNT(1) AS plays, FROM_UNIXTIME(MIN(h.hit_date)) AS first_play, FROM_UNIXTIME(MAX(h.hit_date)) AS last_play '
                   . 'FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE '
                   . 'h.hit_type = 2 AND h.hit_date >= :since';
            if ($user) {
                $query .= ' AND h.hit_user = :user';
            }
            
            $query .= ' GROUP BY h.content_id HAVING plays > ' . $average;
            
            $phat = 1;
            $z = 1.0;
            $z2 = $z * $z;
            
            $wrap = 'SELECT q.*, SQRT(' . $phat . ' + ' . $z2 . ' / (2 * q.plays) - ' . $z . ' * ((' . $phat . ' * (1 - ' . $phat . ') + ' . $z2 . ' / (4 * q.plays)) / q.plays)) / ( 1 + ' . $z2 . ' / q.plays) AS score';
            $wrap .= ' FROM (' . $query . ') AS q ORDER BY score DESC LIMIT ' . $max;
            
            $result = Lib\Db::Query($wrap, $params);
            if ($result && $result->count > 0) {
                while ($row = Lib\Db::Fetch($result)) {
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
        
        /**
         * Returns the most played songs for a given time period
         */
        function getMostPlayedAlbums($vars) {

            $max = Lib\Url::getInt('max', 25, $vars);
            $minDate = Lib\Url::getInt('minDate', 0, $vars);
            $maxDate = Lib\Url::getInt('maxDate', time(), $vars);
            $week = Lib\Url::getInt('week', null, $vars);
            $year = Lib\Url::getInt('year', null, $vars);
            
            if ($year && $week) {
                
                // Calculate what day the first week started on. First week begins on the first Sunday of the year
                $minDate = mktime(0, 0, 0, 1, 1, $year);
                $dayOfWeek = date('w', $minDate);
                if ($dayOfWeek > 0) {
                    $minDate += (7 - $dayOfWeek) * 86400;
                }
                $minDate += 604800 * ($week - 1);
                $maxDate = $minDate + 604799; // 12:59:59 the following Saturday
               
            }
            
            $out = new stdClass;
            $out->startDate = $minDate;
            $out->endDate = $maxDate;
            $out->songs = [];
            
            $params = [ ':minDate' => $minDate, ':maxDate' => $maxDate ];
            $query = 'SELECT a.content_id AS album_id, a.content_title AS album_title, a.content_meta, COUNT(1) AS total FROM hits l INNER JOIN content t ON t.content_id = l.content_id INNER JOIN content a ON a.content_id = t.content_parent WHERE l.hit_date BETWEEN :minDate AND :maxDate GROUP BY t.content_parent ORDER BY total DESC LIMIT ' . $max;
            $result = Lib\Db::Query($query, $params);
            while ($row = Lib\Db::Fetch($result)) {
                
                $obj = new stdClass;
                $obj->id = (int) $row->album_id;
                $obj->title = $row->album_title;
                $obj->count = (int) $row->total;
                $meta = json_decode($row->content_meta);
                if (isset($meta->art)) {
                    $obj->album_art = $meta->art;
                }
                $out->songs[] = $obj;
                
            }
            
            return $out;
            
        }

    }

}