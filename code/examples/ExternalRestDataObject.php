<?php

/**
 * ExternalRestDataObject
 *
 * This is an example implementation with a external Silverstipe REST connection
 * It's a 'fake' connection wich calls Director::absoluteBaseURL() . 'api/v1'
 * to get a RestDataObject (set)
 *
 * All connections are in json
 *
 * @requires restfulserver
 * @see RestDataObject
 */
class ExternalRestDataObject extends ExternalDataObject {
	
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
	
	public static function service() {
		$service = new RestfulService(Director::absoluteBaseURL() . 'api/v1', 0);
		$service->httpHeader('Accept: application/json');
		$service->httpHeader('Content-Type: application/json');
		return $service;	
	}
	
	/**
	 * Child classes should call $list = parent::get();
	 */
	public static function get() {
		$list = parent::get();
		$result = self::service()->request('/RestDataObject');
		if($result->getStatusCode() == 200) {
			$body = Convert::json2obj($result->getBody());
			if(isset($body->items)) {
				foreach($body->items  as $item) {
					$list->push(new ExternalRestDataObject((array)$item));
				}
				
			}			
		}
		return $list;
	}
	
	public static function get_by_id($id) {
		$result = self::service()->request('/RestDataObject/' . $id);
		if($result->getStatusCode() == 200) {
			return new ExternalRestDataObject(Convert::json2array($result->getBody()));
		}
	}
	
	public function write() {
		$writableData = array_intersect_key($this->record, $this->db());
		$id = $this->getID();
		
		if($id) {
			$result = self::service()->request('/RestDataObject/' . $id, 'PUT', Convert::array2json($writableData));
			if($result->getStatusCode() == 200) {
				
			}
		} else {
			$result = self::service()->request('/RestDataObject/', 'POST', Convert::array2json($writableData));
			if($result->getStatusCode() == 201) {
				$data = Convert::json2array($result->getBody());
				$this->record['ID'] = $data["ID"];
				$this->getID();
			}
		}
		
		return $this->ID;	
	}
	
	public function delete() {
		$result = self::service()->request('/RestDataObject/' . $this->getID(), 'DELETE');
		if($result->getStatusCode() == 204) {
			$this->flushCache();
			$this->ID = '';
		}
	}
	
	public static function delete_by_id($id) {
		if($obj = self::get_by_id($id)) {
			$obj->delete();
		}
	}
}