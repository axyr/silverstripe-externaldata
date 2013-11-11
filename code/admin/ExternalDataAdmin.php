<?php
/**
 * ExternalDataAdmin
 *
 * @category   ExternalData
 * @package    ExternalData
 * @author     Martijn van Nieuwenhoven <info@axyrmedia.nl>
 */

class ExternalDataAdmin extends ModelAdmin {
	static $url_segment 	= 'externaldataadmin';
	static $menu_title 		= 'External Data';
	static $page_length 	= 10;
	static $default_model 	= '';	
	
	static $managed_models	= array(
		'MongoDataObject',
		'ExternalRestDataObject',
		'ExternalMySQLDataObject'
	);	
	
	public function getEditForm($id = null, $fields = null) {
		$list = $this->getList();
		
		//var_dump($list);
		
		//$exportButton = new GridFieldExportButton('before');
		//$exportButton->setExportColumns($this->getExportFields());
		//var_dump($this->sanitiseClassName($this->modelClass));
		$listField = GridField::create(
			$this->sanitiseClassName($this->modelClass),
			false,
			$list,
			$fieldConfig = GridFieldConfig_RecordEditor::create($this->stat('page_length'))
				//->addComponent($exportButton)
				->removeComponentsByType('GridFieldFilterHeader')
				->removeComponentsByType('GridFieldDetailForm')
				->removeComponentsByType('GridFieldDeleteAction')
				
				->addComponents(new ExternalDataGridFieldDetailForm())
				->addComponents(new ExternalDataGridFieldDeleteAction())
				//->addComponents(new GridFieldPrintButton('before'))
		);

		// Validation
		if(singleton($this->modelClass)->hasMethod('getCMSValidator')) {
			$detailValidator = singleton($this->modelClass)->getCMSValidator();
			$listField->getConfig()->getComponentByType('GridFieldDetailForm')->setValidator($detailValidator);
		}

		$form = CMSForm::create( 
			$this,
			'EditForm',
			new FieldList($listField),
			new FieldList()
		)->setHTMLID('Form_EditForm');
		$form->setResponseNegotiator($this->getResponseNegotiator());
		$form->addExtraClass('cms-edit-form cms-panel-padded center');
		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
		$editFormAction = Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm');
		$form->setFormAction($editFormAction);
		$form->setAttribute('data-pjax-fragment', 'CurrentForm');

		$this->extend('updateEditForm', $form);
		return $form;
	}
	
	public function getList() {
		$class = $this->modelClass;
		$list =  $class::get();
		$list->dataModel = $class;
		/*
		$context = $this->getSearchContext();
		$params = $this->request->requestVar('q');
		$list = $context->getResults($params);

		$this->extend('updateList', $list);
		*/
		return $list;
	}
	
	public function getRecord($id) {
		$className = $this->modelClass;
		if($className && $id instanceof $className) {
			return $id;
		} else if($id == 'root') {
			return singleton($className);
		} else if($id) {
			return $className::get_by_id($id);
		} else {
			return false;
		}
	}
	
	public function getSearchContext() {	
	}
	public function SearchForm() {
	}
	public function ImportForm() {
	}
}