<?php

/**
 * MongoDataObject
 *
 * This is an example implementation with a MongoDB as the external datasource
 * You need the PHP MongoDB extension loaded to run this example
 *
 * http://www.php.net/manual/en/book.mongo.php
 */
class MongoDataObject extends ExternalDataObject {
	
	static $db = array(
		'Title'	=> 'Varchar(255)',
		'Name'	=> 'Varchar(255)',
		'Email'	=> 'Varchar(255)'
	);
	
	static $summary_fields = array(
		'Title'	=> 'Title',
		'Name'	=> 'Name',
		'Email'	=> 'Email'
	);
	
	/**
	 * Dummy collection
	 * WIll be created if it does not exists
	 */
	static function collection() {
		$m = new MongoClient();
		$db = $m->externaldata;
		return $db->dataset;
	}
	
	/**
	 * MongoDB identifiers can be objects
	 * Make sure we have a $this->ID to work witch
	 */ 
	public function getID() {
		$id = isset($this->record['ID']) ? $this->record['ID'] : '';
		$id = isset($this->record['_id']) ? $this->record['_id'] : $id;
		
		if(is_object($id)) {
			$key = '$id';
			return $id->$key;
		}
		$this->ID = $id;
		return $id;
	}
	
	/**
	 * Child classes should call $list = parent::get();
	 */
	public static function get() {
		$list = parent::get();
		$cursor = self::collection()->find();
		if($cursor) {			
			foreach ($cursor as $key => $document) {
				//var_dump($document);
				if(is_object($document["_id"])) {
					$class = get_called_class();
					$list->push(new $class($document));
				} else {
					$collection->remove(array('_id' => $key));
				}
			}
		}
		return $list;
	}
	
	public static function get_by_id($id) {
		$document = self::collection()->findOne(array('_id' => new MongoId($id)));
		return new MongoDataObject($document);
	}
	
	public function write() {
		// remove values that are not in self::$db && selff:$fixed_fields
		$writableData = array_intersect_key($this->record, $this->db());
		$collection = self::collection();
		if($this->ID) {
			$res = $collection->update(array('_id' => new MongoId($this->ID)), $writableData);
		}else{
			$res = $collection->insert($writableData);
			$this->record['ID'] = $writableData["_id"];
			$this->getID();
		}
		return $this->ID;
	}
	
	function delete() {
		$res = self::collection()->remove(array('_id' => new MongoId($this->getID())));
		$this->flushCache();
		$this->ID = '';
	}
	
	public static function delete_by_id($id) {
		self::collection()->remove(array('_id' => new MongoId($id)));
	}
}