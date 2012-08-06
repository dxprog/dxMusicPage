<?php

/**
 * DxApi
 * @author Matt Hackmann <matt@dxprog.com>
 * @package DxApi
 * @license GPLv3
 */

define('HITS_CACHE_THRESHOLD', 50);
define('HITS_CACHE_TIMEOUT', 0);
define('CACHE_CONTENT_UPDATE', 'ContentLastUpdated');
 
class Content {
 
	/**
	 * Content ID
	 */
	public $id;
	
	/**
	 * Title of content
	 */
	public $title;
	
	/**
	 * Perma ID of content
	 */
	public $perma;
	
	/**
	 * Content type identifier
	 */
	public $type;
	
	/**
	 * Date of content
	 */
	public $date;
	
	/**
	 * Content body
	 */
	public $body;
	
	/**
	 * Content parent
	 */
	public $parent;
	
	/**
	 * Meta information
	 */
	public $meta;
	
	/**
	 * Array of tags associated with this content item
	 */
	public $tags;
	
	/**
	 * Ratings associated with this item
	 */
	public $ratings;
 
	public static function getContent($vars) {

		// Get the properties passed
		$retVal = null;
		$select = isset($vars['select']) ? strlen($vars['select']) > 0 ? $vars['select'] : 'c.*' : 'c.*';
		$id = isset($vars['id']) ? $vars['id'] : null;
		$perma = isset($vars['perma']) ? $vars['perma'] : null;
		$parent = isset($vars['parent']) ? $vars['parent'] : null;
		$max = isset($vars['max']) ? is_numeric($vars['max']) ? $vars['max'] : 15 : 15;
		$mindate = isset($vars['mindate']) ? $vars['mindate'] : null;
		$maxdate = isset($vars['maxdate']) ? $vars['maxdate'] : null;
		$type = isset($vars['contentType']) ? $vars['contentType'] : null;
		$offset = isset($vars['offset']) ? is_numeric($vars['offset']) ? $vars['offset'] : 0 : 0;
		$tag = isset($vars['tag']) ? $vars['tag'] : null;
		$order = isset($vars['order']) ? $vars['order'] : null;
		$meta = isset($vars['meta']) ? json_encode($vars['meta']) : null;
		$noTags = isset($vars['noTags']) ? $vars['noTags'] === 'true' || $vars['noTags'] === true : false;
		$title = isset($vars['title']) ? $vars['title'] : null;
		
		// Build the query
		db_Connect();
		$join = '';
		$where = '';
		if (is_numeric($id)) {
			$where .= 'c.content_id=' . $id . ' AND ';
		}
		if (strlen($perma) > 0) {
			$where .= 'c.content_perma="' . db_Escape($perma) . '" AND ';
		}
		if ($select != 'c.*') {
			$items = explode(',', $select);
			// The ID always gets returned
			$select = 'c.content_id,';
			
			// Build the returns based upon the comma delimeted string passed in
			foreach ($items as $item) {
				switch ($item) {
					case 'title':
						$select .= 'c.content_title,';
						break;
					case 'body':
						$select .= 'c.content_body,';
						break;
					case 'date':
						$select .= 'c.content_date,';
						break;
					case 'meta':
						$select .= 'c.content_meta,';
						break;
					case 'type':
						$select .= 'c.content_type,';
						break;
					case 'parent':
						$select .= 'c.content_parent,';
						break;
					case 'perma':
						$select .= 'c.content_perma,';
						break;
				}
			}
			$select = substr($select, 0, strlen($select) - 1);
		}
		if (is_numeric($parent)) {
			$where .= 'c.content_parent=' . $parent . ' AND ';
		}
		if (is_numeric($mindate) || ($mindate = strtotime($mindate)) !== false) {
			$where .= 'c.content_date >= ' . $mindate . ' AND ';
		}
		if (is_numeric($maxdate) || ($maxdate = strtotime($maxdate)) !== false) {
			$maxdate = $maxdate > time() ? time() : $maxdate;
			$where .= 'c.content_date <= ' . $maxdate . ' AND ';
		} else {
			$where .= 'c.content_date <= ' . time() . ' AND ';
		}
		if (null != $type) {
			$types = explode(',', $type);
			$t = '(';
			foreach ($types as $item) {
				$t .= 'c.content_type="' . db_Escape($item) . '" OR ';
			}
			$where .= substr($t, 0, strlen($t) - 4) . ') AND ';
		}
		if (strlen($tag) > 0) {
			$join .= 'INNER JOIN tags t ON t.content_id=c.content_id ';
			$where .= 't.tag_name="' . db_Escape($tag) . '" AND ';
		}
		if (null != $meta) {
			preg_match('/\{(.*?)\}/', $meta, $m);
			$where .= 'c.content_meta LIKE "%' . db_Escape($m[1]) . '%" AND ';
		}
		if (null != $title) {
			$where .= 'c.content_title="' . db_Escape($title) . '" AND ';
		}
		switch (strtolower($order)) {
			case 'asc':
			case 'ascending':
				$order = 'ASC';
				break;
			case 'desc':
			case 'descending':
			default:
				$order = 'DESC';
				break;
		}
		$noCount = isset($vars['noCount']) && $vars['noCount'] === 'true';
		$count = $noCount ? '' : ', (SELECT COUNT(1) FROM content WHERE content_parent=c.content_id) AS children_count';
		
		// Get the item count
		if (!$noCount) {
			$retVal->count = db_Fetch(db_Query('SELECT COUNT(1) AS total FROM content c ' . $join . 'WHERE ' . $where . '1'))->total;
		}
		
		// If there isn't anything to get, don't get it
		if ($noCount || $retVal->count > 0) {
			$query = 'SELECT ' . $select . $count . ' FROM content c ';
			$where .= '1 ORDER BY c.content_date ' . $order . ' ';
			$query .= $join . 'WHERE ' . $where;

			if ($max > 0) {
				 $query .= 'LIMIT ' . $offset . ', ' . $max;
			}
			
			// Execute the query and lump the results into the outgoing object
			$result = db_Query($query);
			$retVal->content = array();
			while ($row = db_Fetch($result)) {
				$obj = null;
				$obj->id = isset($row->content_id) ? $row->content_id : null;
				if (isset($row->content_title)) {
					$obj->title = $row->content_title;
				}
				
				if (isset($row->content_perma)) {
					$obj->perma = $row->content_perma;
				}
				
				if (isset($row->content_perma)) {
					$obj->date = $row->content_date;
				}
				
				if (isset($row->content_body)) {
					$obj->body = $row->content_body;
				}
				
				if (isset($row->content_type)) {
					$obj->type = $row->content_type;
				}
				
				if (isset($row->content_parent)) {
					$obj->parent = $row->content_parent;
				}
				
				if ($noCount === false) {
					$obj->children = $row->children_count;
				}
				
				if (isset($row->content_meta)) {
					$obj->meta = json_decode($row->content_meta);
				}
				
				if (!$noTags) {
					$obj->tags = self::getTags(array('id'=>$obj->id, 'noCount'=>true));
				}
				$retVal->content[] = $obj;
			}
		}
		
		return $retVal;

	}

	/**
	 * Returns a list of unique tags to the given content filters
	 */
	public static function getTags($vars)
	{

		// Split out the variables
		$retVal = array();
		$id = isset($vars['id']) ? $vars['id'] : null;
		$perma = isset($vars['perma']) ? $vars['perma'] : null;
		$parent = isset($vars['parent']) ? $vars['parent'] : null;
		$max = isset($vars['max']) ? $vars['max'] : 25;
		$mindate = isset($vars['mindate']) ? $vars['mindate'] : 0;
		$maxdate = isset($vars['maxdate']) ? $vars['maxdate'] : time();
		$type = isset($vars['type']) ? $vars['type'] : '';
		$noCount = isset($vars['noCount']) ? $count = false : $count = 'count(*) AS tag_count, ';
		
		// Build the query
		db_Connect();
		$query = 'SELECT ' . $count . 't.tag_name FROM content c INNER JOIN tags t ON t.content_id=c.content_id WHERE ';
		if (is_numeric($id)) {
			$query .= 'c.content_id=' . $id . ' AND ';
		}
		if (strlen($perma) > 0) {
			$query .= 'c.content_perma="' . db_Escape($perma) . '" AND ';
		}
		if (is_numeric($parent)) {
			$query .= 'c.content_parent=' . $parent . ' AND ';
		}
		if (is_numeric($mindate) || ($mindate = strtotime($mindate)) !== false) {
			$query .= 'c.content_date >= ' . $mindate . ' AND ';
		}
		if (is_numeric($maxdate) || ($maxdate = strtotime($maxdate)) !== false) {
			$query .= 'c.content_date <= ' . $maxdate . ' AND ';
		} else {
			$query .= 'c.content_date <= ' . time() . ' AND ';
		}
		if (strlen($type) > 0) {
			$query .= 'c.content_type="' . db_Escape($type) . '" AND ';
		}
		$query .= '1 GROUP BY t.tag_name ' . ($noCount ? 'ORDER BY tag_count DESC ' : '');
		if (is_numeric($max)) {
			$query .= $max > 0 ? 'LIMIT ' . $max : '';
		} else {
			$query .= 'LIMIT 25';
		}
		
		// Round up all the returned tags into an array for output
		$result = db_Query($query);
		while ($row = db_Fetch($result)) {
			$t = null;
			$t->name = $row->tag_name;
			if ($noCount) {
				$t->count = isset($row->tag_count) ? $row->tag_count : 0;
			}
			$retVal[] = $t;
		}

		return $retVal;
		
	}

	/**
	 * Returns a list of months/years where there are blog posts
	 **/
	public static function getArchives($vars) {
		
		db_Connect();
		$retVal = array();
		
		// Get a date for every month/year there was a post
		$result = db_Query("SELECT MONTH(FROM_UNIXTIME(content_date)) AS month, YEAR(FROM_UNIXTIME(content_date)) AS year FROM content GROUP BY year, month ORDER BY year DESC, month DESC");
		while ($row = db_Fetch($result)) {
			$t = null;
			$t->timestamp = mktime(0, 0, 0, $row->month, 1, $row->year) + 3600;
			$t->text = date("F Y", $t->timestamp);
			$retVal[] = $t;
		}
		
		return $retVal;
		
	}
	
	/**
	 * Logs a view of content into the database
	 */
	public static function logContentView($vars) {
		
		// Fancy caching happens here
		global $_apiPath;
		$cacheKey = 'ContentHits';
		$forceWrite = true; isset($vars['forceWrite']) ? $vars['forceWrite'] : false;
		
		// Sniff for bots
		$botString = "/(Slurp!|Googlebot|AdsBot|msnbot|bingbot|crawler|Spinn3r|spider|robot|yandex|slurp|dotbot)/i";
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		if (!$userAgent || preg_match($botString, $userAgent) > 0) {
			return false;
		}
		
		// Make sure the values are good and log the view
		if (is_numeric($vars['id'])) {
			
			// Set up an object with all the info and stuff it into the cached array
			$obj = null;
			$ip = $_SERVER['REMOTE_ADDR'];
			$id = intVal($vars['id']);
			$type = isset($vars['hitType']) && is_numeric($vars['hitType']) ? $vars['hitType'] : 2;
			$user = isset($vars['user']) ? $vars['user'] : 'anon';

			db_Connect();
			$query = 'INSERT INTO hits VALUES (' . $id . ', "' . $ip . '", ' . time() . ', "' . $user . '", ' . $type . ')';
			db_Query($query);
			
			
		} else {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Gets the most popular posts based on page views
	 * @param string $type Output format. Valid options: rss, json, xml, php. Required
	 * @param int $mindate Unix timestamp of how far back to calculate page views. Default: 1 week prior
	 * @param int $mac Number of results to return
	 */
	public static function getPopular($vars) {

		$retVal = array();
		
		// Set content type to filter by
		$where = '';
		if (isset($vars['contentType'])) {
			$where = ' AND c.content_type = "' . db_Escape($vars['contentType']) . '" ';
		}
		
		// If no valid timestamp was passed, we'll default to one week
		$vars['mindate'] = isset($vars['mindate']) ? $vars['mindate'] : time() - 604800;
		if (!is_numeric($vars['mindate'])) {
			$vars['mindate'] = time() - 604800;
		}
		
		// If no valid max value was passed, default to 5
		if (!isset($vars['max']) || !is_numeric($vars['max'])) {
			$vars['max'] = 5;
		}
		
		// Get the most viewed posts within the time frame
		db_Connect();
		$result = db_Query('SELECT count(DISTINCT h.hit_ip) AS total, c.content_id, c.content_title, c.content_perma FROM hits h INNER JOIN content c ON c.content_id=h.content_id WHERE h.hit_date >= ' . $vars['mindate'] . $where . ' GROUP BY h.content_id ORDER BY total DESC, c.content_date DESC LIMIT ' . $vars['max']);
		while ($row = db_Fetch($result)) {
			$t = null;
			$t->count = $row->total;
			$t->id = $row->content_id;
			$t->title = $row->content_title;
			$t->perma = $row->content_perma;
			$retVal[] = $t;
		}

		return $retVal;
		
	}

	/**
	 * Returns all of the comments associated with a post
	 * @param string $type Output format. Valid options: rss, json, xml, php. Required
	 * @param int $max Maximum number of posts to return
	 * @param string $perma Perma-link of post to get related items from
	 */
	public static function getRelated($vars) {
		
		$retVal = array();
		db_Connect();
		
		// Get the five pieces of content with the most similar tags. Sort by most relevant and most recent
		if (is_numeric($vars['id'])) {
			
			$result = db_Query('SELECT COUNT(*) AS total, c.content_id, c.content_title, c.content_perma FROM tags t INNER JOIN content c ON c.content_id=t.content_id WHERE t.tag_name IN (SELECT tag_name FROM tags WHERE content_id=' . $vars['id'] . ') AND c.content_parent=0 AND t.content_id != ' . $vars['id'] . ' GROUP BY content_id ORDER BY total DESC, c.content_date DESC LIMIT 5');
			while ($row = db_Fetch($result)) {
				$obj = null;
				$obj->title = $row->content_title;
				$obj->perma = $row->content_perma;
				$retVal[] = $obj;
			}
			
		}
		
		return $retVal;
		
	}
	
	public static function syncContent($vars, $obj) {
	
		$id = isset($obj->id) && is_numeric($obj->id) ? intVal($obj->id) : null;
		
		// Fix up any parameters should the be missing. Note - title (and subsequently, perma) and type are REQUIRED
		if (isset($obj->title) && isset($obj->type)) {
			$obj->date = isset($obj->date) ? $obj->date : time();
			$obj->perma = isset($obj->perma) && strlen($obj->perma) > 0 ? $obj->perma : self::_createPerma($obj->title);
			$obj->body = isset($obj->body) ? $obj->body : null;
			$obj->parent = isset($obj->parent) ? $obj->parent : 0;
			$obj->meta = isset($obj->meta) ? $obj->meta : null;
			db_Connect();
			
			if ($obj->meta && !isset($vars['metaEncoded'])) {
				$obj->meta = json_encode($obj->meta);
			}
			
			if ($id !== null && $id > 0) {
				// If there is an ID set, do an UPDATE
				$query = 'UPDATE content SET content_title="' . db_Escape($obj->title) . '", content_perma="' . $obj->perma . '", content_body="' . db_Escape($obj->body) . '", content_date=' . intVal($obj->date) . ', content_meta="' . db_Escape($obj->meta) . '" WHERE content_id=' . $id;
				db_Query($query);
			} else {
				// Otherwise, do an INSERT
				$query = 'INSERT INTO content (content_title, content_perma, content_body, content_date, content_type, content_parent, content_meta) VALUES ';
				$query .= '("' . db_Escape($obj->title) . '", "' . db_Escape($obj->perma) . '", "' . db_Escape($obj->body) . '", ' . intVal($obj->date) . ', "' . db_Escape($obj->type) . '", ' . intVal($obj->parent) . ', "' . db_Escape($obj->meta) . '")';
				$id = $obj->id = db_Query($query);
			}
		
			// Sync the tags
			if ($id !== true && isset($obj->tags)) {
				self::_syncTags($vars, $obj);
			}

			// Update the content changed time for cache invalidation elsewhere
			DxCache::Set(CACHE_CONTENT_UPDATE, time());

		}
	
		$retVal = null;
		if ($id !== null) {
			$retVal = self::getContent(array('id'=>$id))->content[0];
		}

		return $retVal;
	
	}
	
	/**
	 * Performs a content search on the database
	 */
	public static function search($vars) {
		
		db_Connect();
		
		// Stop words
		$stop = '/\b(a|able|about|above|abroad|according|accordingly|across|actually|adj|after|afterwards|again|against|ago|ahead|ain\'t|all|allow|allows|almost|alone|along|alongside|already|also|although|always|am|amid|amidst|among|amongst|an|and|another|any|anybody|anyhow|anyone|anything|anyway|anyways|anywhere|apart|appear|appreciate|appropriate|are|aren\'t|around|as|a\'s|aside|ask|asking|associated|at|available|away|awfully|b|back|backward|backwards|be|became|because|become|becomes|becoming|been|before|beforehand|begin|behind|being|believe|below|beside|besides|best|better|between|beyond|both|brief|but|by|c|came|can|cannot|cant|can\'t|caption|cause|causes|certain|certainly|changes|clearly|c\'mon|co|co.|com|come|comes|concerning|consequently|consider|considering|contain|containing|contains|corresponding|could|couldn\'t|course|c\'s|currently|d|dare|daren\'t|definitely|described|despite|did|didn\'t|different|directly|do|does|doesn\'t|doing|done|don\'t|down|downwards|during|e|each|edu|eg|eight|eighty|either|else|elsewhere|end|ending|enough|entirely|especially|et|etc|even|ever|evermore|every|everybody|everyone|everything|everywhere|ex|exactly|example|except|f|fairly|far|farther|few|fewer|fifth|first|five|followed|following|follows|for|forever|former|formerly|forth|forward|found|four|from|further|furthermore|g|get|gets|getting|given|gives|go|goes|going|gone|got|gotten|greetings|h|had|hadn\'t|half|happens|hardly|has|hasn\'t|have|haven\'t|having|he|he\'d|he\'ll|hello|help|hence|her|here|hereafter|hereby|herein|here\'s|hereupon|hers|herself|he\'s|hi|him|himself|his|hither|hopefully|how|howbeit|however|hundred|i|i\'d|ie|if|ignored|i\'ll|i\'m|immediate|in|inasmuch|inc|inc.|indeed|indicate|indicated|indicates|inner|inside|insofar|instead|into|inward|is|isn\'t|it|it\'d|it\'ll|its|it\'s|itself|i\'ve|j|just|k|keep|keeps|kept|know|known|knows|l|last|lately|later|latter|latterly|least|less|lest|let|let\'s|like|liked|likely|likewise|little|look|looking|looks|low|lower|ltd|m|made|mainly|make|makes|many|may|maybe|mayn\'t|me|mean|meantime|meanwhile|merely|might|mightn\'t|mine|minus|miss|more|moreover|most|mostly|mr|mrs|much|must|mustn\'t|my|myself|n|name|namely|nd|near|nearly|necessary|need|needn\'t|needs|neither|never|neverf|neverless|nevertheless|new|next|nine|ninety|no|nobody|non|none|nonetheless|noone|no-one|nor|normally|not|nothing|notwithstanding|novel|now|nowhere|o|obviously|of|off|often|oh|ok|okay|old|on|once|one|ones|one\'s|only|onto|opposite|or|other|others|otherwise|ought|oughtn\'t|our|ours|ourselves|out|outside|over|overall|own|p|particular|particularly|past|per|perhaps|placed|please|plus|possible|presumably|probably|provided|provides|q|que|quite|qv|r|rather|rd|re|really|reasonably|recent|recently|regarding|regardless|regards|relatively|respectively|right|round|s|said|same|saw|say|saying|says|second|secondly|see|seeing|seem|seemed|seeming|seems|seen|self|selves|sensible|sent|serious|seriously|seven|several|shall|shan\'t|she|she\'d|she\'ll|she\'s|should|shouldn\'t|since|six|so|some|somebody|someday|somehow|someone|something|sometime|sometimes|somewhat|somewhere|soon|sorry|specified|specify|specifying|still|sub|such|sup|sure|t|take|taken|taking|tell|tends|th|than|thank|thanks|thanx|that|that\'ll|thats|that\'s|that\'ve|the|their|theirs|them|themselves|then|thence|there|thereafter|thereby|there\'d|therefore|therein|there\'ll|there\'re|theres|there\'s|thereupon|there\'ve|these|they|they\'d|they\'ll|they\'re|they\'ve|thing|things|think|third|thirty|this|thorough|thoroughly|those|though|three|through|throughout|thru|thus|till|to|together|too|took|toward|towards|tried|tries|truly|try|trying|t\'s|twice|two|u|un|under|underneath|undoing|unfortunately|unless|unlike|unlikely|until|unto|up|upon|upwards|us|use|used|useful|uses|using|usually|v|value|various|versus|very|via|viz|vs|w|want|wants|was|wasn\'t|way|we|we\'d|welcome|well|we\'ll|went|were|we\'re|weren\'t|we\'ve|what|whatever|what\'ll|what\'s|what\'ve|when|whence|whenever|where|whereafter|whereas|whereby|wherein|where\'s|whereupon|wherever|whether|which|whichever|while|whilst|whither|who|who\'d|whoever|whole|who\'ll|whom|whomever|who\'s|whose|why|will|willing|wish|with|within|without|wonder|won\'t|would|wouldn\'t|x|y|yes|yet|you|you\'d|you\'ll|your|you\'re|yours|yourself|yourselves|you\'ve|z|zero)\b/';
		
		// Extract out our variables
		$page = isset($vars['page']) && is_numeric($vars['page']) ? $vars['page'] : 1;
		$max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 15;
		$query = isset($vars['q']) && strlen($vars['q']) > 0 ? explode(' ', preg_replace($stop, '', $vars['q'])) : false;
		$noTags = isset($vars['noTags']) && $vars['noTags'] === true;
		$retVal = null;
		
		// If there's a query, do the search
		if (count($query) > 0) {
			$where = '(';
			$select = '';
			foreach ($query as $item) {
				$item = trim($item);
				if (strlen($item) > 0) {
					$item = db_Escape($item);
					$select .= 'IF(c.content_title LIKE "%' . $item . '%", 10, 0) + IF(c.content_body LIKE "%' . $item . '%", 1, 0) + ';
					$where .= '(c.content_body LIKE "%' . $item . '%" OR c.content_title LIKE "%' . $item . '%") OR ';
				}
			}
			
			/* Include tag search/weighting */
			$where .= 'content_id IN (SELECT content_id FROM tags WHERE ';
			$select .= '((SELECT COUNT(1) FROM tags t WHERE t.content_id=c.content_id AND (';
			foreach ($query as $item) {
				$item = trim($item);
				if (strlen($item) > 0) {
					$where .= 'tag_name LIKE "%' . $item . '%" OR ';
					$select .= 'tag_name LIKE "%' . $item . '%" OR ';
				}
			}
			
			/* Clean things up and pull the query together */
			$where = substr($where, 0, strlen($where) - 3) . ')';
			$select = substr($select, 0, strlen($select) - 3) . ')';
			$paging = 'LIMIT ' . ($page * $max - $max) . ', ' . $max;
			$query = 'SELECT *, ' . $select . ') * 5) AS weight FROM content c WHERE ' . $where . ') AND c.content_parent=0 ORDER BY weight DESC, c.content_date DESC ' . $paging;
			
			/* Get the amount of results this search will return */
			$count = 'SELECT COUNT(1) AS count FROM content c WHERE ' . $where . ')';
			$retVal->count = db_Fetch(db_Query($count))->count;
			
			$result = db_Query($query);
			$retVal->results = array();
			while ($row = db_Fetch($result)) {
				$obj = null;
				$obj->id = $row->content_id;
				$obj->title = $row->content_title;
				$obj->perma = $row->content_perma;
				$obj->type = $row->content_type;
				$obj->body = $row->content_body;
				$obj->date = $row->content_date;
				$obj->meta = json_decode($row->content_meta);
				if (!$noTags) {
					$obj->tags = self::getTags(array('id'=>$obj->id, 'noCount'=>true));
				}
				$retVal->results[] = $obj;
			}
		}
		
		return $retVal;
		
	}
	
	public static function postComment($vars, $obj) {
		$retVal = null;
		if ($vars['perma']) {
			$id = self::_getIdFromPerma($vars['perma']);
			if ($id) {
				$query = 'INSERT INTO content (content_parent, content_body, content_meta, content_date, content_type) VALUES ';
				$query .= '(' . $id . ', "' . db_Escape($obj->body) . '", "' . db_Escape(json_encode($obj->meta)) . '", ' . time() . ', "cmmnt")';
				$commentId = db_Query($query);
				$retVal = $commentId;
			} else {
				$retVal = false;
			}
		}
		return $retVal;
	}
	
	private static function _getIdFromPerma($perma) {
		
		db_Connect();
		$row = db_Fetch(db_Query('SELECT content_id FROM content WHERE content_perma="' . db_Escape($perma) . '" AND content_type!="cmmnt"'));
		return $row->content_id;
		
	}
	
	public static function syncTag($vars, $obj) {
		$id = isset($vars['id']) && is_numeric($vars['id']) ? $vars['id'] : false;
		$tag = is_object($obj) ? strtolower($obj->tag) : false;
		if ($id && $tag) {
			db_Connect();
			$tags = explode(',', $tag);

			// Tag edit
			$initial = isset($vars['initial']) ? db_Escape($vars['initial']) : false;
			if ($initial) {
				db_Query('DELETE FROM tags WHERE content_id = ' . $id . ' AND tag_name = "' . $initial . '"');
			}

			// Sync new tags
			foreach ($tags as $tag) {
				$tag = db_Escape(trim($tag));
				db_Query('DELETE FROM tags WHERE content_id = ' . $id . ' AND tag_name = "' . $tag . '"');
				db_Query('INSERT INTO tags VALUES (' . $id . ', "' . $tag . '")');
			}
		}
		DxCache::Set(CACHE_CONTENT_UPDATE, time());
	}

	private static function _syncTags($vars, $obj) {
		
		if (is_numeric($obj->id) && isset($obj->tags) && count($obj->tags) > 0) {
			
			db_Connect();
			
			// Delete old tags first
			db_Query('DELETE FROM tags WHERE content_id=' . $obj->id);
			
			// Loop through all the tags and add them
			$query = 'INSERT INTO tags VALUES ';
			foreach	($obj->tags as $tag) {
				if (is_object($tag)) {
					$tag = str_replace('-', ' ', self::_createPerma($tag->name));
				}
				$query .= '(' . $obj->id . ', "' . db_Escape($tag) . '"),';
			}
			$query = substr($query, 0, strlen($query) - 1);
			db_Query($query);
			
		}
		
	}

	/**
	 * Creates a perma ID from a string
	 * @param string $Perma String to create perma ID from
	 * @return string
	 */
	protected static function _createPerma ($Perma)
	{
		$Remove = array ("'", "\"", ".", ",", "~", "!", "?", "<", ">", "@", "#", "$", "%", "^", "&", "*", "(", ")", "+", "=", "/", "\\", "|", "{", "}", "[", "]", "-", "--");
		for ($i = 0; $i < sizeof ($Remove); $i++)
			$Perma = str_replace ($Remove[$i], "", $Perma);
		$Perma = str_replace ("  ", " ", $Perma);
		$Perma = str_replace (" ", "-", $Perma);
		return strtolower ($Perma);
	}
	
}
