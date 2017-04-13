<?php

require_once 'Zend/Db/Table/Abstract.php';

class AdvertsPhotos extends Zend_Db_Table_Abstract {

	protected $_name = 'Usluga_advert_photos';
	protected $_primary = 'Id';
	protected $_dependentTables = array();
	protected $_referenceMap    = array(
		'Adverts' => array(
			'columns'           => 'Advert_id',
			'refTableClass'     => 'Adverts',
			'refColumns'        => 'Id'
		)
	);

    protected $_rowClass = 'AdvertPhoto';

	private $_config;

	/**/
	public function setPhoto($advertId, $photoName, $photoDescription, $photoOrder){
		$new = $this->createRow();
		$new->Advert_id = $advertId;
		$new->File_name = $photoName;
		$new->Foto_description = $photoDescription;
		$new->Foto_order = $photoOrder;
		$new_id = $new->save();

		$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		$rootHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('root');

        $path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/{$advertId}/";
        $path_thumb = $path."thumbnail/";
        $new_name = "{$new_id}.jpg";
        if ($advertId > 10000000)
        {        	$path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/Temp_{$photoDescription}/";
        	$path_thumb = $path."thumbnail/";        }
        rename($path.$photoName, $path.$new_name);
        $new->File_name = $new_name;
        $new->save();
		$this->cropPhotoThumbnail(/*$photoName*/$new_name, $advertId, $photoDescription);
		if (is_file($path_thumb.$photoName))
			unlink($path_thumb.$photoName);
		return $new_name;
	}

	public function delPhoto($advertId, $photoName){
		$del = $this->fetchRow($this->select()
									->where('Advert_id = ?', $advertId)
									->where('File_name = ?', $photoName)
									->limit(1));
		if($del){

			$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
			$rootHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('root');

			if ($advertId > 10000000)
			{				$advertId = "Temp_{$del->Foto_description}";
			}

			$thumb_path_big = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/{$advertId}/thumbnail/big_".$photoName;
		    if (is_file($thumb_path_big))
		    	unlink($thumb_path_big);
		    $del->delete();
		}

		return true;
	}

	public function getAllPhotos(){
		$advertPhotos = $this->fetchAll($this->select());

		return $advertPhotos;
	}


	public function advertPhotosCount($advertId){
		$advertPhotos = $this->fetchAll($this->select()->where('Advert_id = ?', $advertId));

		return count($advertPhotos);
	}

	public function cropPhotoThumbnail($photoName, $advertId, $userID=''){

		$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		$rootHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('root');

        $path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/{$advertId}/".$photoName;

		if (file_exists($path))
		{			$thumb_path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/{$advertId}/thumbnail/".$photoName;		    //другие размеры тумбы
			$thumb_path_big = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/{$advertId}/thumbnail/big_".$photoName;
		}
		else
		{
			$path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/Temp_".$userID."/".$photoName;
			$thumb_path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/Temp_".$userID."/thumbnail/".$photoName;            $thumb_path_big = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/Temp_".$userID."/thumbnail/big_".$photoName;

			if (!file_exists($path))
				return false;
		}

        list($w_i, $h_i, $type) = getimagesize($path);

        if (($h_i/$w_i)<=($this->_config->company->thumbnail->maxHeight/$this->_config->company->thumbnail->maxWidth))
        {        	$scale =  $this->_config->company->thumbnail->maxHeight / $h_i;
        	$new_w =  round($w_i * $scale);
        	$new_h =  $this->_config->company->thumbnail->maxHeight;        }
        else
        {            $scale =  $this->_config->company->thumbnail->maxWidth / $w_i;
        	$new_h =  round($h_i * $scale);
        	$new_w =  $this->_config->company->thumbnail->maxWidth;        }
        $this->resizeImage($path, $thumb_path, $w_i, $h_i, $new_w, $new_h, $type);
        $this->cropImage($thumb_path, $new_w, $new_h, $this->_config->company->thumbnail->maxWidth, $this->_config->company->thumbnail->maxHeight, $type);

		//другие размеры тумбы --------- начало
		$temp_max_height = $this->_config->company->thumbnail->maxHeight_big;
		$temp_max_width = $this->_config->company->thumbnail->maxWidth_big;
		if (($h_i/$w_i)<=($temp_max_height/$temp_max_width))
        {
        	$scale =  $temp_max_height / $h_i;
        	$new_w =  round($w_i * $scale);
        	$new_h =  $temp_max_height;
        }
        else
        {
            $scale =  $temp_max_width / $w_i;
        	$new_h =  round($h_i * $scale);
        	$new_w =  $temp_max_width;
        }
        $this->resizeImage($path, $thumb_path_big, $w_i, $h_i, $new_w, $new_h, $type);
        $this->cropImage($thumb_path_big, $new_w, $new_h, $temp_max_width, $temp_max_height, $type);
        //другие размеры тумбы --------- конец

		return true;
	}

	public function resizeImage($image, $new_image, $width, $height, $newImageWidth, $newImageHeight, $imageType) {
		$imageType = image_type_to_mime_type($imageType);
		$newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);
		switch($imageType) {
			case "image/gif":
				$source=imagecreatefromgif($image);
				break;
		    case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
				$source=imagecreatefromjpeg($image);
				break;
		    case "image/png":
			case "image/x-png":
				$source=imagecreatefrompng($image);
				break;
	  	}
		imagecopyresampled($newImage,$source,0,0,0,0,$newImageWidth,$newImageHeight,$width,$height);

		switch($imageType) {
			case "image/gif":
		  		imagegif($newImage,$new_image);
				break;
	      	case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
		  		imagejpeg($newImage,$new_image,90);
				break;
			case "image/png":
			case "image/x-png":
				imagepng($newImage,$new_image);
				break;
	    }

		chmod($new_image, 0777);
		return $new_image;
	}

	public function cropImage($image, $width, $height, $newImageWidth, $newImageHeight, $imageType) {

		$imageType = image_type_to_mime_type($imageType);
		$newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);
		switch($imageType) {
			case "image/gif":
				$source=imagecreatefromgif($image);
				break;
		    case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
				$source=imagecreatefromjpeg($image);
				break;
		    case "image/png":
			case "image/x-png":
				$source=imagecreatefrompng($image);
				break;
	  	}
	  	if ($width > $newImageWidth)
		{
            $x_o = round(($width-$newImageWidth)/2);
            $y_o = 0;
		}
		else
		{
            $x_o = 0;
            $y_o = round(($height-$newImageHeight)/2);
		}

		imagecopy($newImage, $source, 0, 0, $x_o, $y_o, $newImageWidth, $newImageHeight);

		switch($imageType) {
			case "image/gif":
		  		imagegif($newImage,$image);
				break;
	      	case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
		  		imagejpeg($newImage,$image,90);
				break;
			case "image/png":
			case "image/x-png":
				imagepng($newImage,$image);
				break;
	    }

		chmod($image, 0777);
		return $image;
	}

	public function reRegistrationPhoto($advertId, $roleId){

		if(!$this->_config){
			$this->_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		}

		$tempLink = $this->_config->upload->companies->adverts->photos.'/Temp_'.$roleId.'/';
		$newLink = $this->_config->upload->companies->adverts->photos.'/'.$advertId.'/';

		if(is_dir($tempLink)){
			if(rename($tempLink, $newLink)){
				$edits = $this->fetchAll($this->select()
											->where('Advert_id = ?', 999999999)
											->where('Foto_description = ?', $roleId));
				foreach($edits as $edit){
					$edit->Advert_id = $advertId;
					$edit->save();
				}
			}
		}
	}
	/**/

	public function catchPhoto($advert_id,$photoText,$photoExistText){

        $rootHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('root');
		$this->_config = Zend_Registry::get('config');
		$thumbnailHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('thumbnail');

		foreach($_FILES as $key => $photo){

			$key_parts = explode('_',$key);
			$photo_name = $key_parts[0];

			if(isset($key_parts[1])){
				$key_parts_2 = explode('/',$key_parts[1]);
				$photo_id = $key_parts_2[0];
				if(isset($key_parts_2[1])){
					$photo_del = $key_parts_2[1];
				}else{
					$photo_del = 0;
				}
			}

			if(($photo_name == 'photo' || $photo_name == 'photoExist') &&
			   ((empty($photo['error']) && $photo['size'] < 1048576) ||
			    ($photo_name == 'photoExist' && $photo['error'] == 4))){

				if($photo_del <> 1){

					$fileExt = $this->_getPhotoExt($photoc);

					if(!$this->checkPhoto($fileExt)){
						if(empty($photo["name"]) &&
						   $photo["size"] == 0 &&
						   (!empty($photoExistText[$photo_id]) && $photoExistText[$photo_id] <> 'Комментарий к фото')
						   ){
								$new_photo = $this->find($photo_id)->current();
		            			$new_photo->photo_text = $photoExistText[$photo_id];
		            			$new_photo->save();
						}
							continue;
					}

					if($photo_name == 'photo'){
						$new_photo = $this->createRow();
						$new_photo->advert_id = $advert_id;
            			$new_photo->extension = $fileExt;
            			if($photoText[$photo_id] <> 'Комментарий к фото'){
            				$new_photo->photo_text = $photoText[$photo_id];
            			}else{
            				$new_photo->photo_text = '';
            			}
            			$new_photo->save();
					}elseif($photo_name == 'photoExist'){
						$new_photo = $this->find($photo_id)->current();
						$new_photo->advert_id = $advert_id;
            			$new_photo->extension = $fileExt;
            			if($photoExistText[$photo_id] <> 'Комментарий к фото'){
            				$new_photo->photo_text = $photoExistText[$photo_id];
            			}else{
            				$new_photo->photo_text = '';
            			}
            			$new_photo->save();
					}

					$path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/".$new_photo->id.".".$fileExt;
					$thumbnailPath = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->thumbnail."/".$new_photo->id.".gif";

					if(move_uploaded_file($photo['tmp_name'],$path)){
                		if(is_file($thumbnailPath)){
                			unlink($thumbnailPath);
                		}
                		$thumbnailHelper->direct($path,$thumbnailPath, $this->_config->company->thumbnail->maxWidth, $this->_config->company->thumbnail->maxHeight, 'gif');
                	}
				}else{
					$new_photo = $this->find($photo_id)->current();

					if($new_photo){

						$path = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->photos."/".$new_photo->id.".".$new_photo->extension;
						$thumbnailPath = $rootHelper->direct().$this->_config->public_folder."/".$this->_config->upload->companies->adverts->thumbnail."/".$new_photo->id.".gif";

						if(is_file($path)){
                			unlink($path);
                		}
						if(is_file($thumbnailPath)){
                			unlink($thumbnailPath);
                		}
            			$new_photo->delete();
					}
                }
			}
		}
	}

	private function _getPhotoExt($file){
		$fileArr = explode('.',$file);
		return $fileArr[count($fileArr)-1];
	}

	private function checkPhoto($ext){
		$extAccess = array('jpeg','jpg','gif','png');
		if(in_array($ext,$extAccess)){
			return true;
		}
		return false;
	}

}