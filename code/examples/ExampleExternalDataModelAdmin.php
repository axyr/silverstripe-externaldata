<?php

class ExampleExternalDataModelAdmin extends ExternalDataAdmin {
	
	static $url_segment 	= 'externaldataadmin';
	static $menu_title 		= 'External Data';
	
	static $managed_models	= array(
		'MongoDataObject',
		'ExternalRestDataObject',
		'ExternalMySQLDataObject'
	);
	
}
