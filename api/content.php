<?php

/**
 * DxApi
 * @author Matt Hackmann <matt@dxprog.com>
 * @package DxApi
 * @license GPLv3
 */

namespace Api {
	
	use stdClass;
	use Lib;
	
	define('HITS_CACHE_THRESHOLD', 50);
	define('HITS_CACHE_TIMEOUT', 60);

	class Content extends Lib\Dal {
	 	
		/**
		 * Database mappers
		 */
		protected $_dbTable = 'content';
		protected $_dbPrimaryKey = 'content_id';
		protected $_dbMap = [
			'id' => 'content_id',
			'title' => 'content_title',
			'perma' => 'content_perma',
			'type' => 'content_type',
			'date' => 'content_date',
			'body' => 'content_body',
			'parent' => 'content_parent',
			'meta' => 'content_meta'
		];

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
		
		public function __construct($obj = null, $getTags = false) {
		
			if (null != $obj) {
				if ($obj instanceof Content) {
				
				} else {
					$this->_createObjectFromRow($obj, $getTags);
				}
			}
		
		}
		
		public static function getContent($vars) {

			// Get the properties passed
			$retVal = new stdClass;
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
			
			$params = array();
			
			// Build the query
			$join = '';
			$where = '';
			
			if (is_numeric($id)) {
				$where .= 'c.content_id=:id AND ';
				$max = 1;
				$params[':id'] = $id;
			}
			
			if (strlen($perma) > 0) {
				$where .= 'c.content_perma=:perma AND ';
				$params[':perma'] = $perma;
			}
			
			if ($select != 'c.*') {
				$items = explode(',', $select);
				// The ID always gets returned
				$select = [ 'c.content_id' ];
				
				// Build the returns based upon the comma delimeted string passed in
				foreach ($items as $item) {
					switch ($item) {
						case 'title':
							$select[] = 'c.content_title';
							break;
						case 'parent':
							$select[] = 'c.content_parent';
							break;
						case 'body':
							$select[] = 'c.content_body';
							break;
						case 'date':
							$select[] = 'c.content_date';
							break;
						case 'meta':
							$select[] = 'c.content_meta';
							break;
						case 'perma':
							$select[] = 'c.content_perma';
							break;
						case 'type':
							$select[] = 'c.content_type';
							break;
					}
				}
				$select = implode(',', $select);
			} else {
				$select = 'c.content_id,c.content_title,c.content_type,c.content_body,c.content_date,c.content_perma,c.content_parent';
				//if (DxApi::checkSignature($vars, false)) {
					$select .= ',c.content_meta';
				//}
			}
			
			if (is_numeric($parent)) {
				$where .= 'c.content_parent=:parent AND ';
				$params[':parent'] = $parent;
			}
			
			if (is_numeric($mindate) || ($mindate = strtotime($mindate)) !== false) {
				$where .= 'c.content_date >= :minDate AND ';
				$params[':minDate'] = $mindate;
			}
			
			if (is_numeric($maxdate) || ($maxdate = strtotime($maxdate)) !== false) {
				$params[':maxDate'] = $maxdate > time() ? time() : $maxdate;
			} else {
				$params[':maxDate'] = time();
			}
			$where .= 'c.content_date <= :maxDate AND ';
			
			if (null != $type) {
				$types = explode(',', $type);
				$t = '(';
				$count = 0;
				foreach ($types as $item) {
					$paramName = ':type' . $count;
					$t .= 'c.content_type=' . $paramName . ' OR ';
					$params[$paramName] = $item;
					$count++;
				}
				$where .= substr($t, 0, strlen($t) - 4) . ') AND ';
			}
			if (strlen($tag) > 0) {
				$join .= 'INNER JOIN tags t ON t.content_id=c.content_id ';
				$where .= 't.tag_name=:tag AND ';
				$params[':tag'] = $tag;
			}
			if (null != $meta) {
				preg_match('/\{(.*?)\}/', $meta, $m);
				$params[':meta'] = '%' . $m[1] . '%';
				$where .= 'c.content_meta LIKE :meta AND ';
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
			
			$noCount = isset($vars['noCount']) && $vars['noCount'] === true;
			$count = $noCount ? '' : ', (SELECT COUNT(1) FROM content WHERE content_parent=c.content_id) AS children_count';
			
			// Get the item count
			if (!$noCount) {
				$retVal->count = Lib\Db::Fetch(Lib\Db::Query('SELECT COUNT(1) AS total FROM content c ' . $join . 'WHERE ' . $where . '1', $params))->total;
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
				$result = Lib\Db::Query($query, $params);
				$retVal->content = array();
				while ($row = Lib\Db::Fetch($result)) {
					if (isset($row->content_type) && class_exists('Api\\' . $row->content_type)) {
						$className = 'Api\\' . $row->content_type;
						$retVal->content[] = new $className($row);
					} else {
						$retVal->content[] = new Content($row, !$noTags);
					}
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
			$noCount = isset($vars['noCount']) && $vars['noCount'] === true ? $count = null : $count = 'count(*) AS tag_count, ';
			
			$params = array();
			
			// Build the query
			$query = 'SELECT ' . $count . 't.tag_name FROM content c INNER JOIN tags t ON t.content_id=c.content_id WHERE ';
			if (is_numeric($id)) {
				$query .= 'c.content_id=:id AND ';
				$params[':id'] = $id;
			}
			if (strlen($perma) > 0) {
				$query .= 'c.content_perma=:perma AND ';
				$params[':perma'] = $perma;
			}
			if (is_numeric($parent)) {
				$query .= 'c.content_parent=:parent AND ';
				$params[':parent'] = $parent;
			}
			if (is_numeric($mindate) || ($mindate = strtotime($mindate)) !== false) {
				$query .= 'c.content_date >= :minDate AND ';
				$params[':minDate'] = $mindate;
			}
			if (is_numeric($maxdate) || ($maxdate = strtotime($maxdate)) !== false) {
				$query .= 'c.content_date <= :maxDate AND ';
				$params[':maxDate'] = $maxdate;
			} else {
				$query .= 'c.content_date <= :time AND ';
				$params[':time'] = time();
			}
			if (strlen($type) > 0) {
				$query .= 'c.content_type=:type AND ';
				$params[':type'] = $type;
			}
			$query .= '1 GROUP BY t.tag_name ' . ($noCount ? 'ORDER BY tag_count DESC ' : '');
			if (is_numeric($max)) {
				$query .= 'LIMIT ' . $max;
			} else {
				$query .= 'LIMIT 25';
			}
			
			// Round up all the returned tags into an array for output
			$result = Lib\Db::Query($query, $params);
			while ($row = Lib\Db::Fetch($result)) {
				$t = new stdClass;
				$t->name = $row->tag_name;
				$t->count = isset($row->tag_count) ? $row->tag_count : 0;
				$retVal[] = $t;
			}

			return $retVal;
			
		}

		/**
		 * Returns a list of months/years where there are blog posts
		 **/
		public static function getArchives($vars) {
			
			$retVal = array();
			
			// Get a date for every month/year there was a post
			$result = Lib\Db::Query('SELECT COUNT(1) AS total, MONTH(FROM_UNIXTIME(content_date)) AS month, YEAR(FROM_UNIXTIME(content_date)) AS year FROM content WHERE content_date <= ' . time() . ' GROUP BY year, month ORDER BY year DESC, month DESC');
			while ($row = Lib\Db::Fetch($result)) {
				$t = new stdClass;
				$t->timestamp = mktime(0, 0, 0, $row->month, 1, $row->year) + 3600;
				$t->text = date("F Y", $t->timestamp);
				$t->count = $row->total;
				$retVal[] = $t;
			}
			
			return $retVal;
			
		}
		
		/**
		 * Logs a view of content into the database
		 */
		public static function logContentView($vars) {
			
			// Fancy caching happens here
			$cacheKey = 'ContentHits';
			$forceWrite = isset($vars['forceWrite']) ? $vars['forceWrite'] : false;
			$hits = \Lib\Cache::Get($cacheKey);
			if (!is_array($hits)) {
				$hits = array();
			}
			
			// Sniff for bots
			$botString = "/(Slurp!|Googlebot|AdsBot|msnbot|bingbot|crawler|Spinn3r|spider|robot|yandex|slurp|dotbot)/i";
			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			if (!$userAgent || preg_match($botString, $userAgent) > 0) {
				return false;
			}
			
			// Make sure the values are good and log the view
			if (is_numeric($vars['id'])) {
				
				// Set up an object with all the info and stuff it into the cached array
				$obj = new stdClass;
				$obj->ip = $_SERVER['REMOTE_ADDR'];
				$obj->id = intVal($vars['id']);
				$obj->time = time();
				$hits[] = $obj;
				$lastStore = \Lib\Cache::Get('ContentHits_Date');

				// If we've hit the threshold (count or timeout), dump all of the hits into the database
				if (count($hits) >= HITS_CACHE_THRESHOLD || $lastStore + HITS_CACHE_TIMEOUT < time() || $forceWrite) {
					
					$query = 'INSERT INTO hits VALUES ';
					foreach ($hits as $hit) {
						$query .= '(' . $hit->id . ', "' . $hit->ip . '", ' . $hit->time . '),';
					}
					$query = substr($query, 0, strlen($query) - 1);
					Lib\Db::Query($query);

					// Null out the array so we can start over
					$hits = null;
					\Lib\Cache::Set('ContentHits_Date', time());
					
				}
				
				// Update the cache
				\Lib\Cache::Set($cacheKey, $hits);
				
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
			$params = array();
			$where = '';
			
			// Check for a content type filter
			$contentType = null;
			if (isset($vars['contentType'])) {
				$where = ' AND content_type=:type ';
				$params[':type'] = $vars['contentType'];
				$contentType = $vars['contentType'];
			}
			
			// If no valid timestamp was passed, we'll default to one week
			$vars['mindate'] = isset($vars['mindate']) ? $vars['mindate'] : time() - 604800;
			if (!is_numeric($vars['mindate'])) {
				$vars['mindate'] = time() - 604800;
			}
			$params[':minDate'] = $vars['mindate'];
			
			// If no valid max value was passed, default to 5
			if (!isset($vars['max']) || !is_numeric($vars['max'])) {
				$vars['max'] = 5;
			}
			
			// Get the most viewed posts within the time frame
			$params[':commentWeight'] = self::_getAvgHitsPerComment($contentType, $vars['mindate']);
			$result = Lib\Db::Query('SELECT COUNT(DISTINCT h.hit_ip) + (SELECT COUNT(1) * :commentWeight FROM content WHERE content_parent=c.content_id AND content_date >= :minDate) AS total, c.content_id, c.content_title, c.content_perma, c.content_meta FROM hits h INNER JOIN content c ON c.content_id=h.content_id WHERE h.hit_date >= :minDate' . $where . ' GROUP BY h.content_id ORDER BY total DESC, c.content_date DESC LIMIT ' . $vars['max'], $params);
			
			while ($row = Lib\Db::Fetch($result)) {
				$t = new Content($row);
				$t->count = $row->total;
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
			
			
			// Get the five pieces of content with the most similar tags. Sort by most relevant and most recent
			if (is_numeric($vars['id'])) {
				
				$result = Lib\Db::Query('SELECT COUNT(*) AS total, c.content_id, c.content_title, c.content_perma FROM tags t INNER JOIN content c ON c.content_id=t.content_id WHERE t.tag_name IN (SELECT tag_name FROM tags WHERE content_id=' . $vars['id'] . ') AND c.content_parent=0 AND t.content_id != ' . $vars['id'] . ' GROUP BY content_id ORDER BY total DESC, c.content_date DESC LIMIT 5');
				while ($row = Lib\Db::Fetch($result)) {
					$obj = new stdClass;
					$obj->title = $row->content_title;
					$obj->perma = $row->content_perma;
					$retVal[] = $obj;
				}
				
			}
			
			return $retVal;
			
		}
		
		/**
		 * Returns a list of tags by their popularity
		 */
		public static function getTagsByPopularity($vars) {
			$retVal = array();
			$max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 25;
			
			$result = Lib\Db::Query('SELECT tag_name AS name, COUNT(h.content_id) AS count FROM hits h INNER JOIN tags t ON t.content_id = h.content_id GROUP BY t.tag_name ORDER BY count DESC LIMIT ' . $max);
			while ($row = Lib\Db::Fetch($result)) {
				$retVal[] = $row;
			}
			
			return $retVal;
		}
		
		/**
		 * Inserts or updates a piece of content in the database
		 */
		public static function syncContent($vars, $obj) {
		
			$id = isset($obj->id) && is_numeric($obj->id) ? intVal($obj->id) : null;
			
			$params = array();
			$params[':title'] = isset($obj->title) ? $obj->title : null;
			$params[':parent'] = isset($obj->parent) ? $obj->parent : 0;
			$params[':body'] = isset($obj->body) ? $obj->body : null;
			$params[':type'] = isset($obj->type) ? $obj->type : null;
			$params[':meta'] = isset($obj->meta) ? json_encode($obj->meta) : '{}';
			$params[':date'] = isset($obj->date) ? $obj->date : time();
			
			if ($id !== null && $id > 0) {
				// If there is an ID set, do an UPDATE
				$params[':id'] = $id;
				$query = 'UPDATE content SET content_title=:title, content_parent=:parent, content_body=:body, content_date=:date, content_meta=:meta, content_type=:type WHERE content_id=:id';
				Lib\Db::Query($query, $params);
			} else {
				// Otherwise, do an INSERT
				$params[':perma'] = isset($obj->perma) ? $obj->perma : isset($obj->title) ? self::_createPerma($obj->title) : null;
				$query = 'INSERT INTO content (content_title, content_perma, content_body, content_date, content_type, content_parent, content_meta) VALUES ';
				$query .= '(:title, :perma, :body, :date, :type, :parent, :meta)';
				$id = $obj->id = Lib\Db::Query($query, $params);
			}
			
			// Sync the tags
			if ($id && isset($obj->tags)) {
				self::_syncTags($obj);
			}
		
			$retVal = null;
			if ($id) {
				$retVal = self::getContent(array('id'=>$id));
			}

			return $retVal;
		
		}
		
		/**
		 * Performs a content search on the database
		 */
		public static function search($vars) {
			
			// Stop words
			$stop = '/\b(a|able|about|above|abroad|according|accordingly|across|actually|adj|after|afterwards|again|against|ago|ahead|ain\'t|all|allow|allows|almost|alone|along|alongside|already|also|although|always|am|amid|amidst|among|amongst|an|and|another|any|anybody|anyhow|anyone|anything|anyway|anyways|anywhere|apart|appear|appreciate|appropriate|are|aren\'t|around|as|a\'s|aside|ask|asking|associated|at|available|away|awfully|b|back|backward|backwards|be|became|because|become|becomes|becoming|been|before|beforehand|begin|behind|being|believe|below|beside|besides|best|better|between|beyond|both|brief|but|by|c|came|can|cannot|cant|can\'t|caption|cause|causes|certain|certainly|changes|clearly|c\'mon|co|co.|com|come|comes|concerning|consequently|consider|considering|contain|containing|contains|corresponding|could|couldn\'t|course|c\'s|currently|d|dare|daren\'t|definitely|described|despite|did|didn\'t|different|directly|do|does|doesn\'t|doing|done|don\'t|down|downwards|during|e|each|edu|eg|eight|eighty|either|else|elsewhere|end|ending|enough|entirely|especially|et|etc|even|ever|evermore|every|everybody|everyone|everything|everywhere|ex|exactly|example|except|f|fairly|far|farther|few|fewer|fifth|first|five|followed|following|follows|for|forever|former|formerly|forth|forward|found|four|from|further|furthermore|g|get|gets|getting|given|gives|go|goes|going|gone|got|gotten|greetings|h|had|hadn\'t|half|happens|hardly|has|hasn\'t|have|haven\'t|having|he|he\'d|he\'ll|hello|help|hence|her|here|hereafter|hereby|herein|here\'s|hereupon|hers|herself|he\'s|hi|him|himself|his|hither|hopefully|how|howbeit|however|hundred|i|i\'d|ie|if|ignored|i\'ll|i\'m|immediate|in|inasmuch|inc|inc.|indeed|indicate|indicated|indicates|inner|inside|insofar|instead|into|inward|is|isn\'t|it|it\'d|it\'ll|its|it\'s|itself|i\'ve|j|just|k|keep|keeps|kept|know|known|knows|l|last|lately|later|latter|latterly|least|less|lest|let|let\'s|like|liked|likely|likewise|little|look|looking|looks|low|lower|ltd|m|made|mainly|make|makes|many|may|maybe|mayn\'t|me|mean|meantime|meanwhile|merely|might|mightn\'t|mine|minus|miss|more|moreover|most|mostly|mr|mrs|much|must|mustn\'t|my|myself|n|name|namely|nd|near|nearly|necessary|need|needn\'t|needs|neither|never|neverf|neverless|nevertheless|new|next|nine|ninety|no|nobody|non|none|nonetheless|noone|no-one|nor|normally|not|nothing|notwithstanding|novel|now|nowhere|o|obviously|of|off|often|oh|ok|okay|old|on|once|one|ones|one\'s|only|onto|opposite|or|other|others|otherwise|ought|oughtn\'t|our|ours|ourselves|out|outside|over|overall|own|p|particular|particularly|past|per|perhaps|placed|please|plus|possible|presumably|probably|provided|provides|q|que|quite|qv|r|rather|rd|re|really|reasonably|recent|recently|regarding|regardless|regards|relatively|respectively|right|round|s|said|same|saw|say|saying|says|second|secondly|see|seeing|seem|seemed|seeming|seems|seen|self|selves|sensible|sent|serious|seriously|seven|several|shall|shan\'t|she|she\'d|she\'ll|she\'s|should|shouldn\'t|since|six|so|some|somebody|someday|somehow|someone|something|sometime|sometimes|somewhat|somewhere|soon|sorry|specified|specify|specifying|still|sub|such|sup|sure|t|take|taken|taking|tell|tends|th|than|thank|thanks|thanx|that|that\'ll|thats|that\'s|that\'ve|the|their|theirs|them|themselves|then|thence|there|thereafter|thereby|there\'d|therefore|therein|there\'ll|there\'re|theres|there\'s|thereupon|there\'ve|these|they|they\'d|they\'ll|they\'re|they\'ve|thing|things|think|third|thirty|this|thorough|thoroughly|those|though|three|through|throughout|thru|thus|till|to|together|too|took|toward|towards|tried|tries|truly|try|trying|t\'s|twice|two|u|un|under|underneath|undoing|unfortunately|unless|unlike|unlikely|until|unto|up|upon|upwards|us|use|used|useful|uses|using|usually|v|value|various|versus|very|via|viz|vs|w|want|wants|was|wasn\'t|way|we|we\'d|welcome|well|we\'ll|went|were|we\'re|weren\'t|we\'ve|what|whatever|what\'ll|what\'s|what\'ve|when|whence|whenever|where|whereafter|whereas|whereby|wherein|where\'s|whereupon|wherever|whether|which|whichever|while|whilst|whither|who|who\'d|whoever|whole|who\'ll|whom|whomever|who\'s|whose|why|will|willing|wish|with|within|without|wonder|won\'t|would|wouldn\'t|x|y|yes|yet|you|you\'d|you\'ll|your|you\'re|yours|yourself|yourselves|you\'ve|z|zero)\b/';
			
			// Extract out our variables
			$page = isset($vars['page']) && is_numeric($vars['page']) ? $vars['page'] : 1;
			$max = isset($vars['max']) && is_numeric($vars['max']) ? $vars['max'] : 15;
			$query = isset($vars['q']) && strlen($vars['q']) > 0 ? explode(' ', preg_replace($stop, '', $vars['q'])) : false;
			$noTags = isset($vars['noTags']) && $vars['noTags'] === true;
			$contentType = isset($vars['contentType']) ? $vars['contentType'] : '';
			$retVal = null;
			$params = array();
			
			// If there's a query, do the search
			if ($query && count($query) > 0) {
				$where = '(';
				$select = '';
				$pCount = 0;
				foreach ($query as $item) {
					$item = trim($item);
					if (strlen($item) > 0) {
						$pCount++;
						$pName = ':param' . $pCount;
						$params[$pName] = '%' . $item . '%';
						$select .= 'IF(c.content_title LIKE ' . $pName . ', 10, 0) + IF(c.content_body LIKE ' . $pName . ', 1, 0) + ';
						$where .= '(c.content_body LIKE ' . $pName . ' OR c.content_title LIKE ' . $pName . ') OR ';
					}
				}
				
				/* Include tag search/weighting */
				$where .= 'content_id IN (SELECT content_id FROM tags WHERE ';
				$select .= '((SELECT COUNT(1) FROM tags t WHERE t.content_id=c.content_id AND (';
				foreach ($params as $key=>$val) {
					$where .= 'tag_name LIKE ' . $key . ' OR ';
					$select .= 'tag_name LIKE ' . $key . ' OR ';
				}
				
				// Content type
				if ($contentType) {
					$params[':type'] = $contentType;
					$contentType = 'AND c.content_type = :type ';
				}
				
				/* Clean things up and pull the query together */
				$where = substr($where, 0, strlen($where) - 3) . ')';
				$select = substr($select, 0, strlen($select) - 3) . ')';
				$paging = 'LIMIT ' . ($page * $max - $max) . ', ' . $max;
				$query = 'SELECT *, ' . $select . ') * 5) AS weight FROM content c WHERE ' . $where . ') ' . $contentType . 'AND c.content_parent=0 ORDER BY weight DESC, c.content_date DESC ' . $paging;
				
				/* Get the amount of results this search will return */
				$count = 'SELECT COUNT(1) AS count FROM content c WHERE ' . $where . ')';
				$retVal->count = Lib\Db::Fetch(Lib\Db::Query($count, $params))->count;
				
				$result = Lib\Db::Query($query, $params);
				$retVal->results = array();
				while ($row = Lib\Db::Fetch($result)) {
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
		
		/**
		 * Deletes a content item from the database. Requires authentication
		 */
		public static function deleteContent($vars) {
			
			$id = isset($vars['id']) && is_numeric($vars['id']) ? $vars['id'] : false;
			if ($id && DxApi::checkSignature($vars)) {
				$params = array( ':id' => $id);
				Lib\Db::Query('DELETE FROM content WHERE content_id = :id', $params);
			}
			
		}
		
		/**
		 * Adds a rating to a piece of content
		 */
		public static function addRating($vars) {
		
		}
		
		/**
		 * Gets the content ID of an object based upon the incoming perma
		 */
		public static function getIdFromPerma($perma) {
			
			$params = array(':perma'=>$perma);
			$row = Lib\Db::Fetch(Lib\Db::Query('SELECT content_id FROM content WHERE content_perma=:perma AND content_type != "cmmnt"', $params));
			return $row->content_id;
			
		}
		
        /**
         * Trending items, but using an updated algorithm
         */
        public static function getBest($vars) {
        
            $retVal = null;
            $since = Lib\Url::GetInt('since', time() - 604800, $vars);
            $max = Lib\Url::GetInt('max', 15, $vars);
            
            // Get the average number of plays for the time period. Anything on the hot list has to have that many plays at least
            $query = 'SELECT COUNT(1) / COUNT(DISTINCT content_id) AS average_plays FROM hits WHERE hit_date >= ' . $since;

            $row = Lib\Db::Fetch(Lib\Db::Query($query));
            $average = round($row->average_plays);
            
            $query = 'SELECT c.content_id, c.content_parent, c.content_title, c.content_meta, '
                   . 'COUNT(1) AS plays, FROM_UNIXTIME(MIN(h.hit_date)) AS first_play, FROM_UNIXTIME(MAX(h.hit_date)) AS last_play '
                   . 'FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE '
                   . 'h.hit_date >= ' . $since;
            
            $query .= ' GROUP BY h.content_id HAVING plays > ' . $average;
            
            $phat = 1;
            $z = 1.0;
            $z2 = $z * $z;
            
            $wrap = 'SELECT q.*, SQRT(' . $phat . ' + ' . $z2 . ' / (2 * q.plays) - ' . $z . ' * ((' . $phat . ' * (1 - ' . $phat . ') + ' . $z2 . ' / (4 * q.plays)) / q.plays)) / ( 1 + ' . $z2 . ' / q.plays) AS score';
            $wrap .= ' FROM (' . $query . ') AS q ORDER BY score DESC LIMIT ' . $max;
            $result = Lib\Db::Query($wrap);
            if ($result && $result->count > 0) {
                while ($row = Lib\Db::Fetch($result)) {
                    $obj = new stdClass;
                    $obj->id = (int) $row->content_id;
                    $obj->title = $row->content_title;
                    $obj->meta = json_decode($row->content_meta);
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
		 * Updates the tags associated to a piece of content
		 */
		private static function _syncTags($obj) {
			
			if (is_numeric($obj->id) && count($obj->tags) > 0) {
				
				// Delete old tags first
				Lib\Db::Query('DELETE FROM tags WHERE content_id=' . $obj->id);
				
				// Loop through all the tags and add them
				$query = 'INSERT INTO tags VALUES ';
				$params = array();
				$tagCount = 0;
				foreach	($obj->tags as $tag) {
					if (is_object($tag)) {
						$tag = $tag->name;
					}
					$tagCount++;
					$pName = 'tag' . $tagCount;
					$params[$pName] = $tag;
					$query .= '(' . $obj->id . ', :' . $pName . '),';
				}
				$query = substr($query, 0, strlen($query) - 1);
				Lib\Db::Query($query, $params);
				
			}
			
		}
		
		/**
		 * Returns the average amount of hits a content item has per comment
		 */
		private static function _getAvgHitsPerComment($contentType = null, $minDate = null) {
			$params = array( ':minDate' => $minDate );
			if (null == $contentType) {
				$avgHits = (double)Lib\Db::Fetch(Lib\Db::Query('SELECT AVG(hits.count) AS avg_hits FROM (SELECT COUNT(1) AS count FROM hits WHERE hit_date >= :minDate GROUP BY content_id) AS hits', $params))->avg_hits;
				$avgComments = (double)Lib\Db::Fetch(Lib\Db::Query('SELECT AVG(comments.count) AS avg_comments FROM (SELECT (SELECT COUNT(1) FROM content WHERE content_type="cmmnt" AND content_parent=c.content_id AND content_date >= :minDate) AS count FROM content c) AS comments', $params))->avg_comments;
			} else {
				$params[':type'] = $contentType;
				$avgHits = (double)Lib\Db::Fetch(Lib\Db::Query('SELECT AVG(hits.count) AS avg_hits FROM (SELECT COUNT(1) AS count FROM hits h INNER JOIN content c ON c.content_id = h.content_id WHERE c.content_type = :type AND h.hit_date >= :minDate GROUP BY h.content_id) AS hits', $params))->avg_hits;
				$avgComments = (double)Lib\Db::Fetch(Lib\Db::Query('SELECT AVG(comments.count) AS avg_comments FROM (SELECT (SELECT COUNT(1) FROM content x INNER JOIN content p ON p.content_id = x.content_parent WHERE x.content_type="cmmnt" AND x.content_parent=c.content_id AND p.content_type = :type AND x.content_date >= :minDate) AS count FROM content c) AS comments', $params))->avg_comments;				
			}
			
			$avgComments = $avgComments != 0 ? $avgComments : 1;

			return floor($avgHits / $avgComments);
		}
		
		/**
		 * Creates a perma ID from a string
		 * @param string $Perma String to create perma ID from
		 * @return string
		 */
		private static function _createPerma ($Perma)
		{
			$Remove = array ('"', '\'', '.', ':', ',', '~', '!', '?', '<', '>', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '=', '/', '\\', '|', '{', '}', '[', ']', '-', '--');
			for ($i = 0; $i < sizeof ($Remove); $i++)
				$Perma = str_replace ($Remove[$i], "", $Perma);
			$Perma = str_replace ("  ", " ", $Perma);
			$Perma = str_replace (" ", "-", $Perma);
			return strtolower ($Perma);
		}	
		
		/**
		 * Copies parameters to the current object from a database row
		 */
		protected function _createObjectFromRow($obj, $getTags = false) {
			$this->copyFromDbRow($obj);
			$this->meta = json_decode($this->meta);
			$this->parent = (int) $this->parent;
			$this->date = (int) $this->date;
			if ($getTags) {
				$this->tags = self::getTags([ 'id' => $this->id, 'noCount' => true ]);
			}
		}
		
	}
}
