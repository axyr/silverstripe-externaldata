<?php

class ExternalDataGridFieldDetailForm extends GridFieldDetailForm {
	
	public function ItemEditForm() {
		$form = parent::ItemEditForm();
		
		return $form;
	}
	
	public function handleItem($gridField, $request) {
		$controller = $gridField->getForm()->Controller();
	
		// we can't check on is_numeric, since some datasources use strings as identifiers
		if($request->param('ID') && $request->param('ID') != 'new') {
			$record = $gridField->getList()->byId($request->param("ID"));
		} else {
			$record = SS_Object::create($gridField->getModelClass());
		}

		$class = $this->getItemRequestClass();

		$handler = SS_Object::create($class, $gridField, $this, $record, $controller, $this->name);
		$handler->setTemplate($this->template);

		return $handler->handleRequest($request, DataModel::inst());
	}
}

class ExternalDataGridFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest {
	
	public function doSave($data, $form) {
		$new_record = $this->record->ID == '';// can be anything, not only integers
		$controller = Controller::curr();
		$list = $this->gridField->getList();
		
		// currently not supported for external data
		/*if($list instanceof ManyManyList) {
			// Data is escaped in ManyManyList->add()
			$extraData = (isset($data['ManyMany'])) ? $data['ManyMany'] : null;
		} else {
			$extraData = null;
		}*/
		
		if(!$this->record->canEdit()) {
			return $controller->httpError(403);
		}
		
		// we don't need a ClassName for data...
		/*
		if (isset($data['ClassName']) && $data['ClassName'] != $this->record->ClassName) {
			$newClassName = $data['ClassName'];
			// The records originally saved attribute was overwritten by $form->saveInto($record) before.
			// This is necessary for newClassInstance() to work as expected, and trigger change detection
			// on the ClassName attribute
			$this->record->setClassName($this->record->ClassName);
			// Replace $record with a new instance
			$this->record = $this->record->newClassInstance($newClassName);
		}
		*/
		try {
			$form->saveInto($this->record);
			$this->record->write();
			$list->add($this->record); //, $extraData
			//var_dump($this->record);
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			$responseNegotiator = new PjaxResponseNegotiator(array(
				'CurrentForm' => function() use(&$form) {
					return $form->forTemplate();
				},
				'default' => function() use(&$controller) {
					return $controller->redirectBack();
				}
			));
			if($controller->getRequest()->isAjax()){
				$controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
			}
			return $responseNegotiator->respond($controller->getRequest());
		}

		// TODO Save this item into the given relationship

		// TODO Allow HTML in form messages
		// $link = '<a href="' . $this->Link('edit') . '">"' 
		// 	. htmlspecialchars($this->record->Title, ENT_QUOTES) 
		// 	. '"</a>';
		$link = '"' . $this->record->Title . '"';
		$message = _t(
			'GridFieldDetailForm.Saved', 
			'Saved {name} {link}',
			array(
				'name' => $this->record->i18n_singular_name(),
				'link' => $link
			)
		);
		
		$form->sessionMessage($message, 'good');

		if($new_record) {
			return Controller::curr()->redirect($this->Link());
		} elseif($this->gridField->getList()->byId($this->record->ID)) {
			// Return new view, as we can't do a "virtual redirect" via the CMS Ajax
			// to the same URL (it assumes that its content is already current, and doesn't reload)
			return $this->edit(Controller::curr()->getRequest());
		} else {
			// Changes to the record properties might've excluded the record from
			// a filtered list, so return back to the main view if it can't be found
			$noActionURL = $controller->removeAction($data['url']);
			$controller->getRequest()->addHeader('X-Pjax', 'Content'); 
			return $controller->redirect($noActionURL, 302); 
		}
	}
	
	public function doDelete($data, $form) {
		$title = $this->record->Title;
		try {
			if (!$this->record->canDelete()) {
				throw new ValidationException(
					_t('GridFieldDetailForm.DeletePermissionsFailure',"No delete permissions"),0);
			}

			$this->record->delete();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return Controller::curr()->redirectBack();
		}

		$message = sprintf(
			_t('GridFieldDetailForm.Deleted', 'Deleted %s %s'),
			$this->record->i18n_singular_name(),
			htmlspecialchars($title, ENT_QUOTES)
		);
		
		$toplevelController = $this->getToplevelController();
		if($toplevelController && $toplevelController instanceof LeftAndMain) {
			$backForm = $toplevelController->getEditForm();
			$backForm->sessionMessage($message, 'good');
		} else {
			$form->sessionMessage($message, 'good');
		}

		//when an item is deleted, redirect to the parent controller
		$controller = Controller::curr();
		$controller->getRequest()->addHeader('X-Pjax', 'Content'); // Force a content refresh

		return $controller->redirect($this->getBacklink(), 302); //redirect back to admin section
	}
}