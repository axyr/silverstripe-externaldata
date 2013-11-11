silverstripe-externaldata
=========================

Custom DataObject/ModelAdmin implementation to work with external data sources

The aim is have an easy way to use ModelAdmin and GridField with data from external datasources.

Just create a Model which extends ExternalDataObject and implement your own get(), write() and delete() methods to work with your custom external data connection.
There are some basic examples included for a MongoDB, REST and external MySQL connection.

This module is Work In Process, and some things are likely to change!!!!

##ExternalDataObject
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
  

##ExternalDataAdmin
Extends ModelAdmin, but a few custom GridField Components are replaced to make sure the GridField can work with external data.
The SearchScaffolder is stripped out, since this is not that easy to implement when you don't know the external datasource and structure.


##Examples
I added some basic examples in the examples folder, which you can also test in frontend with ExternalDataPage.

Note that MongoDBExternalDataObject requires the PHP MongoDB extension :
http://www.php.net/manual/en/book.mongo.php

The ExternalRestDataObject requires the RestFullserver module :
https://github.com/silverstripe/silverstripe-restfulserver
This examples just connects local to Director::absoluteBaseURL() . 'api/v1' and quries the RestDataObject table

The ExternalMySQLDataObject example uses also the RestDataObject table.
You need to set the static $remote_database_config variable with the correct database credentials.

