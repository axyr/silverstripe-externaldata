<?php
/**
 * ExternalDataObject
 *
 * Use this class to create an object from an external datasource with CRUD options and that will work with the GridField.
 * In this way you can connect to other datasources and manage them with the build in Modeladmin.
 *
 * This is basicly a stripped down DataObject, without being tied to a Database Table,
 * and works more or less the same.
 *
 * Things that work :
 *	Create, Read, Update, Delete
 *	FormScaffolding
 *	FieldCasting
 *	Summary Fields
 *	FieldLabels
 *  Limited canView, canEdit checks
 *
 * Things that don't work:
 *	HasOne, HasMany, ManyMany relations
 *	SearchScaffolding.
 *
 * To provide a flexible way to work with External Data, you have to create your own get(), get_one(), delete()
 * functions in your subclass, since we can not know what kind of data you work with.
 *
 * Important is that you provide a method that set an ID value as an unique identifier
 * This can be any value and is not limited to an integer.
 *
 */
abstract class ExternalDataObject extends ArrayData implements ExternalDataInterface {
	
	static $db = array('ID' => 'Varchar');
	
	private $changed;
	protected $record;
	
	private static $singular_name = null;
	private static $plural_name = null;
	private static $summary_fields = null;
	
	protected static $_cache_db = array();
	protected static $_cache_get_one;
	protected static $_cache_field_labels = array();
	protected static $_cache_composite_fields = array();
	
	public function __construct($data = array()) {
		
		foreach($data as $k => $v) {
			if($v !== null) {
				$data[$k] = $v;
			}else{
				unset($data[$k]);
			}
		}
		
		if(!isset($data['ID'])) {
			$data['ID'] = '';
		}
		
		$this->record = $data;
		parent::__construct($data);
	}
	
	public static function is_composite_field($class, $name, $aggregated = true) {
		
		if(!isset(ExternalDataObject::$_cache_composite_fields[$class])) self::cache_composite_fields($class);
		
		if(isset(ExternalDataObject::$_cache_composite_fields[$class][$name])) {
			return ExternalDataObject::$_cache_composite_fields[$class][$name];
			
		} else if($aggregated && $class != 'ExternalDataObject' && ($parentClass=get_parent_class($class)) != 'ExternalDataObject') {
			return self::is_composite_field($parentClass, $name);
		}
	}
	
	private static function cache_composite_fields($class) {
		$compositeFields = array();
		
		$fields = Config::inst()->get($class, 'db', Config::UNINHERITED);
		
		if($fields) foreach($fields as $fieldName => $fieldClass) {
			if(!is_string($fieldClass)) continue;

			// Strip off any parameters
			$bPos = strpos('(', $fieldClass);
			if($bPos !== FALSE) $fieldClass = substr(0,$bPos, $fieldClass);
			
			// Test to see if it implements CompositeDBField
			if(ClassInfo::classImplements($fieldClass, 'CompositeDBField')) {
				$compositeFields[$fieldName] = $fieldClass;
			}
		}
		
		ExternalDataObject::$_cache_composite_fields[$class] = $compositeFields;
	}
	
	public function __get($property) {
		if($this->hasMethod($method = "get$property")) {
			return $this->$method();
		} elseif($this->hasField($property)) {
			return $this->getField($property);
		} elseif(isset($this->record[$property])) {
			return $this->record[$property];
		}
	}
	
	public function __set($property, $value) {
		if($this->hasMethod($method = "set$property")) {
			$this->$method($value);
		} else {
			$this->setField($property, $value);
		}
	}
	
	/**
	 * Child classes should call $list = parent::get();
	 */ 
	public static function get() {
		$list = ExternalDataList::create();
		$list->dataClass = get_called_class();
		return $list;
	}
	
	public function getID() {
		return $this->record['ID'];
	}
	
	public function getTitle() {
		if($this->hasField('Title')) return $this->getField('Title');
		if($this->hasField('Name')) return $this->getField('Name');
		
		return "#{$this->ID}";
	}
	
	public function getCMSFields() {
		$tabbedFields = $this->scaffoldFormFields(array(
			// Don't allow has_many/many_many relationship editing before the record is first saved
			'includeRelations' => 0, //($this->ID > 0)
			'tabbed' => false,
			'ajaxSafe' => true
		));
		
		$this->extend('updateCMSFields', $tabbedFields);
		
		return $tabbedFields;
	}
	
	public function getFrontEndFields($params = null) {
		$untabbedFields = $this->scaffoldFormFields($params);
		$this->extend('updateFrontEndFields', $untabbedFields);
	
		return $untabbedFields;
	}
	
	public function scaffoldFormFields($_params = null) {
		$params = array_merge(
			array(
				'tabbed' => false,
				'includeRelations' => false,
				'restrictFields' => false,
				'fieldClasses' => false,
				'ajaxSafe' => false
			),
			(array)$_params
		);
		
		$fs = new ExternalDataFormScaffolder($this);
		$fs->tabbed = $params['tabbed'];
		$fs->includeRelations = $params['includeRelations'];
		$fs->restrictFields = $params['restrictFields'];
		$fs->fieldClasses = $params['fieldClasses'];
		$fs->ajaxSafe = $params['ajaxSafe'];
		
		return $fs->getFieldList();
	}
	
	public function db($fieldName = null) {
		$classes = class_parents($this) + array($this->class => $this->class);
		$good = false;
		$items = array();
		
		foreach($classes as $class) {
			// Wait until after we reach ExternalDataObject
			if(!$good) {
				if($class == 'ExternalDataObject') {
					$good = true;
				}
				continue;
			}

			if(isset(self::$_cache_db[$class])) {
				$dbItems = self::$_cache_db[$class];
			} else {
				$dbItems = (array) Config::inst()->get($class, 'db', Config::INHERITED);
				self::$_cache_db[$class] = $dbItems;
			}

			if($fieldName) {
				if(isset($dbItems[$fieldName])) {
					return $dbItems[$fieldName];
				}
			} else {
				// Validate the data
				foreach($dbItems as $k => $v) {
					if(!is_string($k) || is_numeric($k) || !is_string($v)) {
						user_error("$class::\$db has a bad entry: " 
						. var_export($k,true). " => " . var_export($v,true) . ".  Each map key should be a"
						. " property name, and the map value should be the property type.", E_USER_ERROR);
					}
				}

				$items = isset($items) ? array_merge((array) $items, $dbItems) : $dbItems;
			}
		}

		return $items;
	}
	
	public function dbObject($fieldName) {
		// If we have a CompositeDBField object in $this->record, then return that
		if(isset($this->record[$fieldName]) && is_object($this->record[$fieldName])) {
			return $this->record[$fieldName];
			
		// Special case for ID field
		} else if($fieldName == 'ID') { // sure?
			return new ExternalDataObjectPrimaryKey('ID', $this->ID); // ?Varchar
		// General casting information for items in $db
		} else if($helper = $this->db($fieldName)) {
			$obj = Object::create_from_string($helper, $fieldName);
			$obj->setValue($this->$fieldName, $this->record, false);
			return $obj;
		}
	}
	
	public function fieldLabels($includerelations = false) {
		
		$cacheKey = $this->class . '_' . $includerelations;
		
		if(!isset(self::$_cache_field_labels[$cacheKey])) {
			$customLabels = $this->stat('field_labels');
			$autoLabels = array();
			
			// get all translated static properties as defined in i18nCollectStatics()
			$ancestry = class_parents($this->class) + array($this->class => $this->class);
			
			$ancestry = array_reverse($ancestry);
			if($ancestry) foreach($ancestry as $ancestorClass) {
				if($ancestorClass == 'ViewableData') break;
				$types = array(
					'db'        => (array)Config::inst()->get($ancestorClass, 'db', Config::UNINHERITED)
				);
				if($includerelations){
					$types['has_one'] = (array)singleton($ancestorClass)->uninherited('has_one', true);
					$types['has_many'] = (array)singleton($ancestorClass)->uninherited('has_many', true);
					$types['many_many'] = (array)singleton($ancestorClass)->uninherited('many_many', true);
				}
				foreach($types as $type => $attrs) {
					foreach($attrs as $name => $spec) {
						// var_dump("{$ancestorClass}.{$type}_{$name}");
						$autoLabels[$name] = _t("{$ancestorClass}.{$type}_{$name}",FormField::name_to_label($name));
					}
				}
			}

			$labels = array_merge((array)$autoLabels, (array)$customLabels);
			$this->extend('updateFieldLabels', $labels);	
			self::$_cache_field_labels[$cacheKey] = $labels;
		}
		
		return self::$_cache_field_labels[$cacheKey];
	}
	
	public function hasField($field) {
		return (
			array_key_exists($field, $this->record)
			|| $this->db($field)
			|| (substr($field,-2) == 'ID') && $this->has_one(substr($field,0, -2))
			|| $this->hasMethod("get{$field}")
		);
	}
	
	public function setField($fieldName, $val) {
		// Situation 1: Passing an DBField
		if($val instanceof DBField) {
			$val->Name = $fieldName;

			// If we've just lazy-loaded the column, then we need to populate the $original array by
			// called getField(). Too much overhead? Could this be done by a quicker method? Maybe only
			// on a call to getChanged()?
			$this->getField($fieldName);

			$this->record[$fieldName] = $val;
		// Situation 2: Passing a literal or non-DBField object
		} else {
			// If this is a proper database field, we shouldn't be getting non-DBField objects
			if(is_object($val) && $this->db($fieldName)) {
				user_error('ExternalDataObject::setField: passed an object that is not a DBField', E_USER_WARNING);
			}
		
			$defaults = $this->stat('defaults');
			// if a field is not existing or has strictly changed
			if(!isset($this->record[$fieldName]) || $this->record[$fieldName] !== $val) {
				// TODO Add check for php-level defaults which are not set in the db
				// TODO Add check for hidden input-fields (readonly) which are not set in the db
				// At the very least, the type has changed
				$this->changed[$fieldName] = 1;
				
				if((!isset($this->record[$fieldName]) && $val) || (isset($this->record[$fieldName])
						&& $this->record[$fieldName] != $val)) {

					// Value has changed as well, not just the type
					$this->changed[$fieldName] = 2;
				}

				// If we've just lazy-loaded the column, then we need to populate the $original array by
				// called getField(). Too much overhead? Could this be done by a quicker method? Maybe only
				// on a call to getChanged()?
				$this->getField($fieldName);

				// Value is always saved back when strict check succeeds.
				$this->record[$fieldName] = $val;
			}
		}
		return $this;
	}
	
	public function setCastedField($fieldName, $val) {
		if(!$fieldName) {
			user_error("ExternalDataObject::setCastedField: Called without a fieldName", E_USER_ERROR);
		}
		$castingHelper = $this->castingHelper($fieldName);
		if($castingHelper) {
			$fieldObj = Object::create_from_string($castingHelper, $fieldName);
			$fieldObj->setValue($val);
			$fieldObj->saveInto($this);
		} else {
			$this->$fieldName = $val;
		}
		return $this;
	}
	
	public function getField($field) {
		// If we already have an object in $this->record, then we should just return that
		if(isset($this->record[$field]) && is_object($this->record[$field]))  return $this->record[$field];

		// Do we have a field that needs to be lazy loaded?
		if(isset($this->record[$field.'_Lazy'])) {
			$tableClass = $this->record[$field.'_Lazy'];
			$this->loadLazyFields($tableClass);
		}
		
		// Otherwise, we need to determine if this is a complex field
		if(self::is_composite_field($this->class, $field)) {
			$helper = $this->castingHelper($field);
			$fieldObj = Object::create_from_string($helper, $field);

			$compositeFields = $fieldObj->compositeDatabaseFields();
			foreach ($compositeFields as $compositeName => $compositeType) {
				if(isset($this->record[$field.$compositeName.'_Lazy'])) {
					$tableClass = $this->record[$field.$compositeName.'_Lazy'];
					$this->loadLazyFields($tableClass);
				}
			}

			// write value only if either the field value exists,
			// or a valid record has been loaded from the database
			$value = (isset($this->record[$field])) ? $this->record[$field] : null;
			if($value || $this->exists()) $fieldObj->setValue($value, $this->record, false);
			
			$this->record[$field] = $fieldObj;

			return $this->record[$field];
		}

		return isset($this->record[$field]) ? $this->record[$field] : null;
	}
	
	public function fieldLabel($name) {
		$labels = $this->fieldLabels();
		return (isset($labels[$name])) ? $labels[$name] : FormField::name_to_label($name);
	}
	
	public function singular_name() {
		if(!$name = $this->stat('singular_name')) {
			$name = ucwords(trim(strtolower(preg_replace('/_?([A-Z])/', ' $1', $this->class))));
		}
		
		return $name;
	}
	
	public function i18n_singular_name() {
		return _t($this->class.'.SINGULARNAME', $this->singular_name());
	}
	
	public function plural_name() {
		if($name = $this->stat('plural_name')) {
			return $name;
		} else {
			$name = $this->singular_name();
			if(substr($name,-1) == 'e') $name = substr($name,0,-1);
			else if(substr($name,-1) == 'y') $name = substr($name,0,-1) . 'ie';

			return ucfirst($name . 's');
		}
	}

	public function i18n_plural_name() {
		$name = $this->plural_name();
		return _t($this->class.'.PLURALNAME', $name);
	}
	
	//todo, but set so custom ModelAdmin wont choke...
	public function getDefaultSearchContext() {
		
	}
	
	public function canCreate($member = null) {
		return true;
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if($extended !== null) {
			return $extended;
		}
		return Permission::check('ADMIN', 'any', $member);
	}
	
	public function canView($member = null) {
		return true;
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if($extended !== null) {
			return $extended;
		}	
	}
	
	public function canEdit($member = null) {
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if($extended !== null) {
			return $extended;
		}
		return Permission::check('ADMIN', 'any', $member);
	}
	
	public function canDelete($member = null) {
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if($extended !== null) {
			return $extended;
		}
		return Permission::check('ADMIN', 'any', $member);
	}
	
	public function extendedCan($methodName, $member) {
		$results = $this->extend($methodName, $member);
		if($results && is_array($results)) {
			// Remove NULLs
			$results = array_filter($results, function($v) {return !is_null($v);});
			// If there are any non-NULL responses, then return the lowest one of them.
			// If any explicitly deny the permission, then we don't get access 
			if($results) return min($results);
		}
		return null;
	}
	
	public function summaryFields(){
		$fields = $this->stat('summary_fields');

		// if fields were passed in numeric array,
		// convert to an associative array
		if($fields && array_key_exists(0, $fields)) {
			$fields = array_combine(array_values($fields), array_values($fields));
		}

		if (!$fields) {
			$fields = array();
			// try to scaffold a couple of usual suspects
			if ($this->db('Name')) $fields['Name'] = 'Name';
			if ($this->db('Title')) $fields['Title'] = 'Title';
			
		}
		$this->extend("updateSummaryFields", $fields);
		
		// Final fail-over, just list ID field
		if(!$fields) $fields['ID'] = 'ID';

		// Localize fields (if possible)
		foreach($this->fieldLabels(false) as $name => $label) {
			if(isset($fields[$name])) $fields[$name] = $label;
		}
		
		return $fields;
	}
	
	public function flushCache($persistent = true) {
		if($persistent) Aggregate::flushCache($this->class);
		
		if($this->class == 'ExternalDataObject') {
			ExternalDataObject::$_cache_get_one = array();
			return $this;
		}

		$classes = class_parents($this->class) + array($this->class => $this->class);
	
		foreach($classes as $class) {
			if(isset(ExternalDataObject::$_cache_get_one[$class])) unset(ExternalDataObject::$_cache_get_one[$class]);
		}
		
		$this->extend('flushCache');
		
		return $this;
	}
	
}