<?php

class SerializeXML {

	
	public function serialize($object, $root = 'root') {
		
		return '<?xml version="1.0" encoding="utf-8"?>' . $this->_serializeItem($object, $root);
		
	}
	
	private function _serializeItem($item, $root, $attributes = '') {
		
		$retVal = '<' . $root . $attributes . '>';
		
		if (null === $item) {
			$retVal .= 'null';
		} elseif (is_object($item) || is_array($item)) {
			
			foreach ($item as $key=>$val) {
				$elName = is_numeric($key) ? $root . '_item' : $key;
				$elAttr = is_numeric($key) ? ' index="' . $key . '"' : '';
				$retVal .= $this->_serializeItem($val, $elName, $elAttr);
			}
			
		} elseif (is_bool($item)) {
			$retVal .= false == $item ? 'false' : 'true';
		} elseif (is_string($item)) {
			$retVal .= '<![CDATA[' . mb_convert_encoding($item, 'UTF-8') . ']]>';
		} else {
			$retVal .= $item;
		}
		
		$retVal .= '</' . $root . '>';
		return $retVal;
	
	}
	
}
