<?php

/**
 * ExternalMySQLDataObject
 *
 * This is an example implementation with a external MySQL database
 * It uses a switch in query_remote(), where it connects with a alternative DB conn, 
 * execute a raw query and return to the default DB::conn()
 *
 * Make sure you set a $remote_database_config details
 *
 * @see RestDataObject
 */
class ExternalMySQLDataObject extends ExternalDataObject {
	
	public static $insert_id = 0;
	
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
	
	private static $singular_name = 'External MySQL DataObject';
	
	/**
	 * remote mysql database connection
	 **/
	static $remote_database_config = array(
		"type" => 'MySQLDatabase',
		"server" => 'localhost',
		"username" => '',
		"password" => '',
		"database" => '',
		"path" => ''
	);
	
	/**
	 * Return to the default $databaseConfig
	 * Add this after each remote table operations are executed
	 **/
	public static function return_to_default_config() {
		global $databaseConfig;
		$dbClass = $databaseConfig['type'];
		$conn = new $dbClass($databaseConfig);
		DB::setConn($conn);	
	}
	
	/**
	 * Connect to a secondary MySQL database
	 **/
	public static function connect_remote() {
		$config = self::$remote_database_config;
		$dbClass = $config['type'];
		$conn = new $dbClass($config);
		DB::setConn($conn);	
	}
	
	/**
	 * Connect to a secondary MySQL database, execute the query and set the database to the default connection
	 **/
	public static function query_remote($query) {
		self::connect_remote();
		$res = DB::Query($query);
		self::$insert_id = DB::getConn()->getGeneratedID('RestDataObject');
		self::return_to_default_config();
		return $res;
	}
	
	public static function get() {
		$list = parent::get();
		if($res = self::query_remote("SELECT * FROM RestDataObject")) {
			foreach($res as $item) {
				$list->push(new ExternalMySQLDataObject($item));
			}
		}
		return $list;
	}
	
	public static function get_by_id($id) {
		if($res = self::query_remote("SELECT * FROM RestDataObject WHERE ID =" . (int)$id)->record()) {
			return new ExternalMySQLDataObject($res);
		}
	}
	
	public static function delete_by_id($id) {
		$res = self::query_remote("DELETE FROM RestDataObject WHERE ID =" . (int)$id);
	}
	
	public function write() {
		// remove values that are not in self::$db
		$writableData = Convert::raw2sql(array_intersect_key($this->record, $this->db()));
		if($this->ID) {
			$updates = array();
			foreach($writableData as $k => $v) {
				$updates[] = "{$k} = '{$v}'";
			}
			$query = "UPDATE RestDataObject SET " . implode(",",$updates) . " WHERE ID=" . $this->ID;
			self::query_remote($query);
		}else{
			$query = "INSERT INTO RestDataObject (" . implode(',',array_keys($writableData)) . ") VALUES ('" . implode("','",$writableData) . "')";
			self::query_remote($query);
			$this->record['ID'] = self::$insert_id;
			$this->getID();
		}
		return $this->ID;
	}
	
	function delete() {
		self::query_remote("DELETE FROM RestDataObject WHERE ID =" . (int)$this->getID());
		$this->flushCache();
		$this->ID = '';
	}
	
}