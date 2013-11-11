silverstripe-externaldata
=========================

Custom DataObject/ModelAdmin implementation to work with external data sources

The aim is have an easy way to use ModelAdmin and GridField with data from external datasources.

Just create a Model which extends ExternalDataObject and implement your own get(), write() and delete() methods to work with your custom external data connection.
There are some basic examples included for a MongoDB, REST and external MySQL connection.

This module is Work In Process, and some things are likely to change!!!!

##ExternalDataObject.php
This is the base Object you need to extend you Model from.
A lot of code is copied from DataObject.php so things like SummaryFields, FormScaffolding and $form->saveInto() just works like DataObject.
Therefore you still need to add a static $db array, like you do for normal DataObjects.

###Example
  
  :::php
  
	  class MyExternalDataObject extends ExternalDataObject {
		
	  	static $db = array(
	  		'Title'	=> 'Varchar(255)'
	  	);
	  	
	  	public static function get() {
	  		$list = parent::get();
	  		
	  		// add your code to get a $result of remote records
	  		// ExternalDataAdmins GridField will use this list as well to get 1 item from the list
	  		foreach($result as $item) {
	  			$list->push(new MyExternalDataObject($item));
	  		}
	  		
	  		return $list;	
	  	}
	  	
	  	public static function get_by_id($id) {
	  		// add your code to get one remote record
	  		// This is used in the ExternalDataPage example	
	  	}
	  	
	  	public function write() {
	  		// add your code to write/update a remote record
	  	}
	  	
	  	public function delete() {
	  		// add your code to delete a remote record
	  		$this->flushCache();
	  		$this->ID = '';
	  	}
	  }
  
  
