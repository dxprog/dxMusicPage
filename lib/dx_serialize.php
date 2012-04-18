<?php

/**
 * XML Serializer
 * @copyright Matt Hackmann (C) 2010
 **/
 
class SerializeXML {

	private $_iterator;
	private $_i = 0;
	private $_dataSet;

	public function serialize($data, $root) {
		$retVal = '<?xml version="1.0"?><root />';
		if (null !== $data) {
			$retVal = $this->_serializeXml($data);
			$retVal = '<?xml version="1.0" encoding="ISO-8859-1"?><'.$root.'>'.$retVal.'</'.$root.'>';
		}
		return $retVal;
	}
	
	/**
	 * Serializes an object to an XML string
	 **/
	private function _serializeXml($data) {
		
		$obj = null;
		
		// Type catch
		if (is_object($data) || is_array($data)) {
			
			// Serialize as XML
			$this->_dataSet = serialize($data);
			$this->_iterator = 0;
			$retVal = $this->_serializeXmlItem('root');

		}

		return $retVal;
		
	}
	
	/**
	 * Serializes a group of items
	 **/
	private function _serializeXmlGroup($count, $parent = null, $parentName = null) {
	
		$retVal = '';
		for ($i = 0; $i < $count; $i++) {
			$retVal .= $this->_serializeXmlItem($parent, true, $parentName);
		}
		
		return $retVal;
		
	}
	
	/**
	 * Serializes a single item
	 **/
	private function _serializeXmlItem($parent, $increment = true, $parentName = null) {

		$p = $this->_getCurrentItem();
		$retVal = '';
		
		if (count($p) == 0) {
			for ($i = 0; $i < 3; $i++) {
				$p[$i] = null;
			}
		}
		
		if (isset($p[2])) {
			$val = str_replace('"', '', $p[2]);
		}
		
		switch ($p[0]) {
			case "O":
				$this->_iterator++;
				$retVal = $this->_serializeXmlGroup(intVal($p[3]), 'object', $parentName);
				$this->_iterator--;
				break;
			case 's':
				
				// If the parent is an object, get the next two items (key->val)
				switch ($parent) {
					case 'object':
						$this->_iterator++;
						$n = $this->_serializeXmlItem('string', false, $val);
						$retVal = "<$val>$n</$val>\n";
						break;
					case 'array':
						$this->_iterator++;
						$n = $this->_serializeXmlItem('string', false, $val);
						if ($parentName !== null) {
							$tagName = $parentName . '_item';
						} else {
							$tagName = 'item';
						}
						$retVal = "<$tagName index=\"$val\">$n</$tagName>\n";
						break;
					case 'string':
						$retVal = '<![CDATA[' . $p[2] . ']]>';
						break;
					default:
						$retVal = '<string><![CDATA[' . $p[2] . ']]></string>';
						break;
				}
				break;
			case 'd':
				if ($parent == "string") {
					$retVal = $p[1];
				} else {
					$retVal = '<double>'.$p[1].'</double>';
				}
				break;
			case 'a':
				$this->_iterator++;
				$retVal = $this->_serializeXmlGroup(intVal($p[1]), "array", $parentName);
				$this->_iterator--;
				break;
			case 'b':
				if ($p[1] != 0) {
					$retVal = "true";
				} else {
					$retVal = "false";
				}
				break;
			case 'i':
				if ($parent == 'array') {
					$this->_iterator++;
					$n = $this->_serializeXmlItem('string', false, $parentName);
					if ($parentName !== null) {
						$tagName = $parentName . '_item';
					} else {
						$tagName = 'item';
					}
					$retVal = "<$tagName index=\"".$p[1]."\">$n</$tagName>\n";

					break;
				} else {
					$retVal = $p[1];
				}
				break;
			case 'N':
				$retVal = 'NULL';
				break;
		}
		
		if ($increment === true) {
			$this->_iterator++;
		}
		
		return $retVal;
	
	}
	
	/**
	 * Gets the current item splitting the array depending on the object type
	 **/
	private function _getCurrentItem() {
		
		$retVal = null;
		
		// Figure out the type so we know how many times to explode this
		switch ($this->_dataSet{0}) {
			case '}':
				$this->_dataSet = substr($this->_dataSet, 1);
				$retVal = $this->_getCurrentItem();
			case 'O':
				$count = 4;
				break;
			case 'b':
			case 'd':
			case 'a':
			case 'i':
				$count = 2;
				break;
			case 'N':
				$count = 1;
				break;
			default:
				$count = 3;
				break;
		}
		
		if ($retVal === null) {
			// Break out the portions we need, drop the rest
			$chunks = explode(":", $this->_dataSet, $count);

			// On arrays and objects, strip the data out of the curly braces. Everything else, explode off the semi-colon
			$data = array_pop($chunks);
			if (!isset($chunks[0])) {
				$chunks[0] = null;
			}
			
			switch ($chunks[0]) {
				case "O":
				case "a":
					$t = explode(":", $data, 2);
					$data = substr($t[1], 1);
					break;
				case "s":
					$t[0] = substr($data, 1, intVal($chunks[1]));
					$data = substr($data, intVal($chunks[1]) + 3);
					break;
				default:
					$t = explode(";", $data, 2);
					$data = $t[1];
					break;
			}
			$chunks[] = $t[0];
			$this->_dataSet = $data;
			
			$retVal = $chunks;
		}
		
		return $retVal;

	}

}