<?php

class AdvertPhoto extends Zend_Db_Table_Row_Abstract {

	private $_config;

	public function getPhotoLink(){
		if(!$this->_config){
			$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		}

		$link = $this->_config->upload->companies->adverts->photos.'/'.$this->Advert_id.'/'.$this->File_name;

		if(is_file($link)){
			return '/'.$link;
		}

		return false;
	}

	public function getThumbPhotoLink($prefix = ''){
		if(!$this->_config){
			$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		}

		$link = $this->_config->upload->companies->adverts->photos.'/'.$this->Advert_id.'/thumbnail/'.$prefix.$this->File_name;

		if(is_file($link)){
			return '/'.$link;
		}

		return false;
	}

	public function getTextPart(){
		$result = '';

		if(mb_strlen($this->Foto_description,'UTF-8') > 15){
			$result = mb_substr($this->Foto_description, 0, 12, 'UTF-8');
			$result = $result.'...';
		}else{
			$result = $this->Foto_description;
		}

		return $result;
	}
}