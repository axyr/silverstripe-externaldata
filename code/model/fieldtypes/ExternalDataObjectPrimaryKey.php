<?php

class ExternalDataObjectPrimaryKey extends PrimaryKey {
	public function scaffoldFormField($title = null, $params = null) {
		$field = new HiddenField($this->name, $title);
		return $field;
	}
}