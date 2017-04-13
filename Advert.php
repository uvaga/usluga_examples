<?php

class Advert extends Zend_Db_Table_Row_Abstract {

	private   $_dateFormat = 'j M Y';
	private   $_dateFormatTime = 'Y-m-d H:i';
	private   $_dateFormatForEdit = 'd.m.Y';
	private   $_dateFormatForDB = 'Y-m-d H:i:s';
	private   $_dateFormatForCompare = 'Y-m-d';

	public function getReadableDate(){
		$date = new Zend_Date($this->Advert_date,Zend_Date::ISO_8601);

		return $date->toString($this->_dateFormat);
	}

	public function getEditDateCompare(){
		$date = new Zend_Date($this->Advert_edit_date, Zend_Date::ISO_8601);

		return $date->toString($this->_dateFormatForCompare);
	}

	public function getReadableEditDate(){
		$date = new Zend_Date($this->Advert_edit_date,Zend_Date::ISO_8601);

		return $date->toString($this->_dateFormat);
	}

	public function getReadableEditDateTime(){
		$date = new Zend_Date($this->Advert_edit_date,Zend_Date::ISO_8601);

		return $date->toString($this->_dateFormatTime);
	}

	public function getDateForEdit(){
		$date = new Zend_Date($this->Advert_date,Zend_Date::ISO_8601);
		$this->Advert_date = $date->toString($this->_dateFormatForEdit);

		return $this->Advert_date;
	}

	public function getEditDateForEdit(){
		$date = new Zend_Date($this->Advert_edit_date,Zend_Date::ISO_8601);
		$this->Advert_edit_date = $date->toString($this->_dateFormatForEdit);

		return $this->Advert_edit_date;
	}

	/**/
	public function getReadableActiveDate(){
		$activeDate = strtotime("$this->Advert_edit_date + 60 month");
		$date = new Zend_Date($activeDate, Zend_Date::ISO_8601);

		return $date->toString($this->_dateFormat);
	}

	public function getActiveDate(){
		$activeDate = strtotime("$this->Advert_edit_date + 60 month");

		return date("Y-m-d",$activeDate);
	}

	public function getTimeFromUp(){
		if ($this->Advert_is_main_in_group == 1)
			$updateUnixTime = strtotime($this->Advert_date_last_update_group);
		else
			$updateUnixTime = strtotime($this->Advert_edit_date);

		$timeLeft = time()-$updateUnixTime;
		if ($timeLeft < 60*60)
            $result = floor($timeLeft/60)." мин.";
        elseif ($timeLeft > 60*60 && $timeLeft < 24*60*60)
            $result = floor($timeLeft/3600)." час.";
		elseif ($timeLeft > 24*60*60 && $timeLeft < 7*24*60*60)
            $result = floor($timeLeft/(24*3600))." дн.";
		else
			$result = false;
		return $result;
	}
	/**/

	public function save(){
		if($this->Advert_date != NULL){
			$date = new Zend_Date($this->Advert_date,Zend_Date::ISO_8601);
			$this->Advert_date = $date->toString($this->_dateFormatForDB);
		}
		if($this->Advert_edit_date != NULL){
			$date = new Zend_Date($this->Advert_edit_date,Zend_Date::ISO_8601);
			$this->Advert_edit_date = $date->toString($this->_dateFormatForDB);
		}

		parent::save();

		return true;
	}

	public function createAlias(){
		$translateHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('translit');
		$i=1;

		$alias = $translateHelper->direct($this->Advert_title).($i==1?'':'-'.$i);

		$this->Advert_alias = $alias;
		return $this->Advert_alias;
	}

	public function editAlias($newAlias){
		$translateHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('translit');
		$i=1;

		$alias = $translateHelper->direct($newAlias).($i==1?'':'-'.$i);

		$this->Advert_alias = $alias;
		return $this->Advert_alias;
	}

	public function getRubric() {
		$category = $this->findParentCategories();

		return $category->getRubric();
	}

	public function getCategory() {
		return $this->findParentCategories();
	}

	public function getAdvertFilters() {
		return $this->findAdvertFilters();
	}

	public function getFilter($id) {
		$filters = new CategoryFilters();
		$filter = $filters->getCategoryFilter($id);
		return $filter;
	}

	public function getCompany() {
		return $this->findParentCompanies();
	}

	public function getMeasure() {
		return $this->findParentMeasures();
	}

	public function getCurrencyRate() {
		return $this->findParentCurrencyRate();
	}

	public function getAdvertLogo($prefix = ''){
		$logo = false;
		$config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');

		$photo = $this->getPhotos(1);
		if ($photo != null)
			$logo = $photo[0]->getThumbPhotoLink($prefix);
        else
        	$logo = false;

		if ($logo == false)
			$logo = "/".$config->advert->blank->images->nophoto_img."/nophoto{$prefix}.png";

		return $logo;
	}

	public function getGroupAdverts(){	    $select = $this->select()->where("Category_id = ?", $this->Category_id)->where("Company_id = ?", $this->Company_id)->order("Advert_edit_date");
		$items = $this->getTable()->fetchAll($select);

		return $items;
	}

	public function getPhotos($limit = 0){
		$photos = null;
		$select = $this->select()->order(array('Foto_order ASC', 'Id Asc'));
		if ($limit > 0)
			$select = $select->limit($limit,0);

		$photosSelect = $this->findDependentRowset('AdvertsPhotos',null,$select);
		if(count($photosSelect) > 0){
			$photos = $photosSelect;
		}

		return $photos;
	}

	public function getAdvertComments(){
		$items = $this->findDependentRowset('AdvertsComments',null,$this->select()
																		->where('Advert_id = ?', $this->Id)
																		->where('Parent_comment_id = ?', 0)
																		->order('Comment_date ASC'));

		return $items;
	}

    public function getLastPayAdvertOrders(){
		$item = false;
		$items = $this->findDependentRowset('PayAdvertOrders',
											null,
											$this->select()
													->where('Order_status = 2')
													->order('Order_payed_date DESC')
													->limit(1));
		if(count($items)){
			foreach($items as $oneItem){
				$item = $oneItem;
				break;
			}
		}

		return $item;
	}

	public function getAdvertURL(){
		$config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		$url = 'https://'.$config->siteUrl.'/'.$this->Advert_alias.'-'.$this->Id.'.html';

		return $url;
	}

	public function getAdvertPhones(){
		$photos = null;
		$photosSelect = $this->findDependentRowset('AdvertsPhotos',null,$this->select()
																			 ->order(array('Foto_order ASC', 'Id Asc')));
		if(count($photosSelect) > 0){
			$photos = $photosSelect;
		}

		return $photos;
	}

	public function setAdvertViews(){
		if(!isset($_COOKIE["Usluga_advert_$this->Id"])){
			$this->Advert_views++;
			$this->save();
			/**/
			$advertStatistics = new AdvertStatistic();
			$advertStatistic = $advertStatistics->setAdvertStatisticViews($this->Id);
			$config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
			setcookie("Usluga_advert_$this->Id", $this->Id, time()+3600*24, "/", ".$config->siteUrl");
		}

		return $this->Advert_views;
	}

	public function setViewCount(){
		$this->Advert_views ++;
		$this->save();

		return true;
	}



	public function getStatus(){
		if($this->Advert_active_status == Adverts::$STATUS_ACT){
			$return = Adverts::$STATUS_ACT;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PREACT){
			$return = Adverts::$STATUS_PREACT;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PAS && $this->Advert_is_archive == 0){
			$return = Adverts::$STATUS_PAS;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PAS && $this->Advert_is_archive == 1){
			$return = Adverts::$STATUS_ARCH;
		}elseif($this->Advert_active_status == Adverts::$STATUS_HIDE){
			$return = Adverts::$STATUS_HIDE;
		}else{
			$return = Adverts::$STATUS_ERROR;
		}

		return $return;
	}

	public function getReadableStatus(){
		if($this->Advert_active_status == Adverts::$STATUS_ACT){
			$return = Adverts::$STATUS_ACT_READABLE;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PREACT){
			$return = Adverts::$STATUS_PREACT_READABLE;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PAS && $this->Advert_is_archive == 0){
			$return = Adverts::$STATUS_PAS_READABLE;
		}elseif($this->Advert_active_status == Adverts::$STATUS_PAS && $this->Advert_is_archive == 1){
			$return = Adverts::$STATUS_ARCH_READABLE;
		}elseif($this->Advert_active_status == Adverts::$STATUS_HIDE){
			$return = Adverts::$STATUS_HIDE_READABLE;
		}else{
			$return = Adverts::$STATUS_ERROR_READABLE;
		}

		return $return;
	}

	public function decShowCount(){		$this->Pay_advert_order_pos = $this->Pay_advert_order_pos - 1;
        if ($this->Pay_advert_order_pos <= 0)
			$this->Pay_advert_type = 0;

		$this->save();

		return $this->Pay_advert_order_pos;	}


	public function getAdvertHideReasons($id = 0){		$this->getTable()->getAdapter()->setFetchMode(Zend_Db::FETCH_ASSOC);
		if ($id == 0)
		{
			$items = $this->getTable()->getAdapter()->fetchAll("select * from Usluga_hide_reasons_advert");
			return $items;
		}
		else
		{			$items = $this->getTable()->getAdapter()->fetchAll("select * from Usluga_hide_reasons_advert where id = {$id}");
			return $items[0];		}
	}

	public function getStartPriceTxt() {

		$price = number_format($this->Advert_start_price, 2, ".", "");
		return $price;
	}

	public function getEndPriceTxt() {

		$price = number_format($this->Advert_end_price, 2, ".", "");
		return $price;
	}

	public function getStartPriceReadable() {

		return sprintf("%d<span>.%02d</span>", $this->Advert_start_price, ($this->Advert_start_price-floor($this->Advert_start_price))*100);
	}

	public function getEndPriceReadable() {

		return sprintf("%d<span>.%02d</span>", $this->Advert_end_price, ($this->Advert_end_price-floor($this->Advert_end_price))*100);
	}

}
