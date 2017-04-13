<?php

require_once 'Zend/Db/Table/Abstract.php';

class Adverts extends Zend_Db_Table_Abstract {

	protected $_name = 'Usluga_adverts';
	protected $_primary = 'Id';
	protected $_dependentTables = array('AdvertsComments', 'AdvertFilters', 'AdvertsPhotos', 'AdvertStatistic', 'PayAdvertOrders');
	protected $_referenceMap    = array(
		'Companies' => array(
			'columns'           => 'Company_id',
			'refTableClass'     => 'Companies',
			'refColumns'        => 'Id'
		),
		'Categories'  => array(
			'columns'			=> 'Category_id',
			'refTableClass'		=> 'Categories',
			'refColumns'		=> 'Id',
		),
		'Measures' => array(
			'columns'           => 'Measure_id',
			'refTableClass'     => 'Measures',
			'refColumns'        => 'Id'
		),
		'CurrencyRate' => array(
			'columns'           => 'Advert_currency',
			'refTableClass'     => 'CurrencyRate',
			'refColumns'        => 'Id'
		)
	);

	protected $_rowClass = 'Advert';

	public static $ADVERT_ID_ASC = 1;
	public static $ADVERT_ID_DESC = 2;
	public static $ADVERT_CREATED_ASC = 3;
	public static $ADVERT_CREATED_DESC = 4;
    public static $ADVERT_STATUS_ASC = 5;
    public static $ADVERT_STATUS_DESC = 6;
	public static $ADVERT_HEAD_ASC = 7;
    public static $ADVERT_HEAD_DESC = 8;
	public static $ADVERT_PRICE_ASC = 9;
    public static $ADVERT_PRICE_DESC = 10;

	public static $STATUS_ALL = 4;
	public static $STATUS_HIDE = -1;
	public static $STATUS_PAS = 0;
	public static $STATUS_PREACT = 1;
	public static $STATUS_ACT = 2;
	public static $STATUS_ARCH = 3;
	public static $STATUS_ERROR = 6;
	public static $STATUS_PREACT_ACT = 7;

	public static $STATUS_ALL_READABLE = 'Все';
	public static $STATUS_HIDE_READABLE = 'Скрытые';
	public static $STATUS_PAS_READABLE = 'Неактивное';
	public static $STATUS_PREACT_READABLE = 'Активное, требует модерации';
	public static $STATUS_ACT_READABLE = 'Активное';
	public static $STATUS_ARCH_READABLE = 'Отправлено в архив';
	public static $STATUS_ERROR_READABLE = 'Ошибочный статус';

	public static $FIND_ALL = 0;
	public static $FIND_ID = 1;
	public static $FIND_TITLE = 2;
	public static $FIND_COMPANY = 3;
	public static $FIND_ACT_COMPANY = 4;

	public static $FILTER_ALL = 0;
	public static $FILTER_TD = 1;
	public static $FILTER_YTD = 2;
	public static $FILTER_LW = 3;

	public function getAdverts($where, $order, $find, $thatfind, $limitStart = 0, $limitEnd = ''){
		$items = $this->select()
						->from($this)
						->order($order);

		if($where == Adverts::$STATUS_ARCH){
			$items = $items->where('Advert_is_archive = 1');
		}elseif($where == Adverts::$STATUS_PREACT_ACT){
			$items = $items->where('Advert_is_archive = 0');
			$items = $items->where('Advert_active_status > 0');
		}elseif($where <> Adverts::$STATUS_ALL){
			$items = $items->where('Advert_active_status = ?', $where);
		}

		if($find == Adverts::$FIND_ID){
			$items = $items->where('ID = ?', $thatfind);
		}else if($find == Adverts::$FIND_TITLE){
			$items = $items->where('Advert_title LIKE ?', '%'.$thatfind.'%');
		}else if($find == Adverts::$FIND_ACT_COMPANY){
			$items = $items->join('Usluga_companies',
									'Usluga_companies.Id = Usluga_adverts.Company_id',array())
							->where('Usluga_companies.Active_status > 0')
							->setIntegrityCheck(false);
		}
		else if($find == Adverts::$FIND_COMPANY){
			$items = $items->join('Usluga_companies',
									'Usluga_companies.Id = Usluga_adverts.Company_id',array())
							->where('Company_name LIKE ?', '%'.$thatfind.'%')
							->setIntegrityCheck(false);
		}

		if ($limitEnd != '')
			$items = $items->limit($limitEnd,$limitStart );
        //$sql=$items->__toString();

		$items = $this->fetchAll($items);

		return $items;
	}

	public function getAdvert($id){
		return $this->fetchRow($this->select()->where('Id = ?', $id));
	}

	public function setAdvertShowCount($id){
		$advert = $this->fetchRow($this->select()->where('Id = ?', $id));
		if($advert){
			$advert->Advert_shows ++;
			$advert->save();

			return true;
		}else{
			return false;
		}
	}

	public function incShows($ids){
		return $this->getAdapter()->query("UPDATE `Usluga_adverts` set Advert_shows = Advert_shows + 1 where Id in (".implode(",",$ids).")");
	}

	public function getAdvertByAlias($alias){
		return $this->fetchRow($this->select()->where('Advert_alias = ?', $alias));
	}

	public function getAdvertByIdByAlias($id, $alias){
		return $this->fetchRow($this->select()->where('Id = ?', $id)->where('Advert_alias = ?', $alias));
	}

	public function checkAdvertAlias($alias, $id){
		return $this->fetchAll($this->select()->where('Advert_alias = ?', $alias)->where('Id != ?', $id));
	}

	public function getAdvertsByCompany($companyId, $order = "Advert_date DESC"){
		return $this->fetchAll($this->select()
									->where('Company_id = ?', $companyId)
									->order($order)
								);
	}

	public function getPreAdverts($companyId, $advertDate, $order = 'Advert_edit_date DESC'){
		$items = $this->select()->distinct()
						->from($this)
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array())
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = Usluga_adverts.Advert_currency',
								array('(Usluga_adverts.Advert_start_price * Usluga_currency_rate.Rate) as AdvertStartPrice',
									  '(Usluga_adverts.Advert_end_price * Usluga_currency_rate.Rate) as AdvertEndPrice',
									  'Usluga_currency_rate.Currency'))
						->where('Usluga_companies.Active_status > ?',0)
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > ?',0)
						->where('Usluga_companies.Id = ?', $companyId)
						->where('Usluga_adverts.Advert_edit_date < ?', $advertDate)
						->order($order)
						->setIntegrityCheck(false);

		$items = $this->fetchAll($items);

		return $items;
	}

	public function getCompanyAdverts($companyId){
		$items = $this->select()->distinct()
						->from($this)
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array())
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = Usluga_adverts.Advert_currency',
								array('(Usluga_adverts.Advert_start_price * Usluga_currency_rate.Rate) as AdvertStartPrice',
									  '(Usluga_adverts.Advert_end_price * Usluga_currency_rate.Rate) as AdvertEndPrice',
									  'Usluga_currency_rate.Currency'))

						->where('Usluga_companies.Active_status > ?',0)
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > ?',0)
						->where('Usluga_companies.Id = ?', $companyId)
						->order(array('Usluga_categories.Rubric_id ASC', 'Usluga_adverts.Category_id ASC'))
						->setIntegrityCheck(false);

		$items = $this->fetchAll($items);

		return $items;
	}

	public function getCompanyAdvertsFilters($companyId){
		$items = $this->select()->distinct()
						->from($this, array())
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array('Usluga_categories.Id'))
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = Usluga_adverts.Advert_currency',
								array())
						->join('Usluga_advert_filters',
								'Usluga_advert_filters.Advert_id = Usluga_adverts.Id',
								array('Usluga_advert_filters.Filter_id'))
						->join('Usluga_category_filters',
								'Usluga_category_filters.Id = Usluga_advert_filters.Filter_id',
								array('Usluga_category_filters.Filter_name'))
						->where('Usluga_companies.Active_status > ?',0)
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > ?',0)
						->where('Usluga_companies.Id = ?', $companyId)
						->order(array('Usluga_categories.Id ASC', 'Usluga_category_filters.Filter_name ASC'))
						->setIntegrityCheck(false);

		$items = $this->fetchAll($items);

		$filters = array();

		if(count($items)){
			foreach($items as $item){
				$filters[$item->Id][] = $item;
			}
		}else{
			$filters = false;
		}

		return $filters;
	}


	public function getPublicAdverts($limit = null, $order = 'Usluga_adverts.Id ASC', $categories = null) {

		$select = $this->select()
						->distinct()
						->from($this)
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->where('Usluga_companies.Active_status > 0')
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > 0')
						->order($order)
						->limit($limit)
						->setIntegrityCheck(false);

		if(!is_null($categories)){
			$select->where('Usluga_adverts.Category_id IN '.$categories);
		}

		$adverts = $this->fetchAll($select);

		return $adverts;
	}

	public function getRecommendedAdverts($limit = null, $order = 'Advert_edit_date DESC'){

		$items = $this->select()
						->distinct()
						->from($this)
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array())
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = Usluga_adverts.Advert_currency',
								array('(Usluga_adverts.Advert_start_price * Usluga_currency_rate.Rate) as AdvertStartPrice',
									  '(Usluga_adverts.Advert_end_price * Usluga_currency_rate.Rate) as AdvertEndPrice',
									  'Usluga_currency_rate.Currency'))
						->where('Usluga_companies.Active_status > ?',0)
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > ?',0)
						->where('Usluga_adverts.Advert_special > ?',0)
						->order($order)
						->setIntegrityCheck(false);

		if($limit <> null){
			$items = $items->limit($limit);
		}

		$items = $this->fetchAll($items);

		return $items;
	}

	public function editAdvert($id, $category, $filters,  $companyId, $measure, $title, $text, $admin_text, $meta_text,
			$price_start, $price_end, $currency, $status, $hide_reason, $hide_comments, $admin = false, $recommended = null, $alias = null){


		if($id){
			$edit = $this->fetchRow($this->select()->where('Id = ?', $id));
			$edit->Category_id = $category;

			$edit->Measure_id = $measure;
			if($admin){
				$edit->Advert_title = htmlentities($title, ENT_NOQUOTES, 'UTF-8');
				if($alias){
					$edit->editAlias($alias);
				}
			}
			$edit->Advert_description = htmlentities($text, ENT_QUOTES, 'UTF-8');
			if($admin_text <> null)
				$edit->Advert_admin_description = htmlentities($admin_text, ENT_QUOTES, 'UTF-8');
			if($meta_text <> null)
				$edit->Advert_meta_description = htmlentities($meta_text, ENT_QUOTES, 'UTF-8');
			$edit->Advert_start_price = $price_start;
			$edit->Advert_end_price = $price_end;
			$edit->Advert_currency = $currency;
			if(!$admin && date('Y-m-d') > $edit->getEditDateCompare()){
				$edit->Advert_edit_date = date('Y-m-d H:i:s');
				//апаем дату группового обновления у всех объяв в группе
				$this->getAdapter()->query("UPDATE `Usluga_adverts` set Advert_date_last_update_group = '".date("Y-m-d H:i:s")."' where Category_id = {$edit->Category_id} and Company_id = {$edit->Company_id}");
			}
			if(is_numeric($status)){
				$edit->Advert_active_status = $status;
			}else{
				$edit->Advert_active_status = 0;
			}
			$edit->Advert_hide_reason = htmlentities($hide_reason, ENT_QUOTES, 'UTF-8');
			if(is_numeric($hide_comments))
				$edit->Advert_hide_comments = $hide_comments;
			if($recommended){
				$edit->Advert_special = $recommended;
			}else{
				$edit->Advert_special = 0;
			}

			if($edit->save()){
				$return[0] = 1;
				$return[1] = $edit;
				/**/
				$advertFilters = new AdvertFilters();
				$advertFilters->editFilter($edit->Id, $filters);
				/**/
			}else{
				$return[0] = 0;
			}
		}else{
			$new = $this->createRow();
			$new->Category_id = $category;
			$new->Company_id = $companyId;
			$new->Measure_id = $measure;
			$new->Advert_title = htmlentities($title, ENT_NOQUOTES, 'UTF-8');
			$new->createAlias();
			$new->Advert_description = htmlentities($text, ENT_QUOTES, 'UTF-8');
			$new->Advert_admin_description = htmlentities($admin_text, ENT_QUOTES, 'UTF-8');
			if($meta_text <> null)
				$new->Advert_meta_description = htmlentities($meta_text, ENT_QUOTES, 'UTF-8');
			$new->Advert_start_price = $price_start;
			$new->Advert_end_price = $price_end;
			$new->Advert_currency = $currency;
			$new->Advert_date = date('Y-m-d H:i:s');
			$new->Advert_edit_date = date('Y-m-d H:i:s');
			$new->Advert_date_last_update_group = date('Y-m-d H:i:s');

			$new->Advert_views = 0;
			$new->Advert_hide_reason = htmlentities($hide_reason, ENT_QUOTES, 'UTF-8');
			if(is_numeric($hide_comments))
				$new->Advert_hide_comments = $hide_comments;

			if($new->save()){
				$return[0] = 1;
				$return[1] = $new;
				/**/
				$advertFilters = new AdvertFilters();
				$advertFilters->editFilter($new->Id, $filters);
				/**/
			}else{
				$return[0] = 0;
			}
		}

		return $return;
	}

	public function upAdvert($id){

		$return = 0;

		if($id){
			$edit = $this->fetchRow($this->select()->where('Id = ?', $id));

			if($edit){

					$edit->Advert_edit_date = date('Y-m-d H:i:s');

					if($edit->save()){						//апаем дату группового обновления у всех объяв в группе						$this->getAdapter()->query("UPDATE `Usluga_adverts` set Advert_date_last_update_group = '".date("Y-m-d H:i:s")."' where Category_id = {$edit->Category_id} and Company_id = {$edit->Company_id}");
						$return = 1;
					}else{
						$return = 0;
					}

			}else{
				$return = 0;
			}
		}

		return $return;
	}

	public function setNewMainAdvDeAct($advObj, $exceptId = 0){	    $select = $this->select()->where('Category_id = ?', $advObj->Category_id)->where('Company_id = ?', $advObj->Company_id)->where('Advert_is_archive = 0')->where('Advert_active_status >= 0');
	    if ($exceptId > 0)
	    	$select = $select->where('Id != ?', $exceptId);
	    $select = $select->order("Advert_active_status desc")->order("Advert_edit_date desc")->limit(1);
	    $group_advs = $this->fetchRow($select);
		if (isset($group_advs->Id) && (int)$group_advs->Id > 0)
			$this->setMainInGroup($group_advs->Id, $advObj);
		return true;
	}

	public function setNewMainAdvAct($advObj, $exceptId = 0){
	    $select = $this->select()->where('Category_id = ?', $advObj->Category_id)->where('Company_id = ?', $advObj->Company_id)->where('Advert_is_archive = 0')->where('Advert_active_status > 0');
	    if ($exceptId > 0)
	    	$select = $select->where('Id != ?', $exceptId);
	    $select = $select->order("Advert_edit_date desc")->limit(1);
	    $group_advs = $this->fetchRow($select);
		if (!isset($group_advs->Id))
			$this->setMainInGroup($advObj->Id, $advObj);
		return true;
	}

	public function setInArchive($id){
		$item = $this->fetchRow($this->select()->where('Id = ?', $id));
		if($item){
			$item->Advert_is_archive = 1;
			$item->Advert_active_status = 0;
			if ($item->Advert_is_main_in_group == 1)// если отправляем главное в группе объявление в архив,
			{   //то нужно найти ему замену как главное среди активных из группы				$this->setNewMainAdvDeAct($item, $id);
			}
			if($item->save()){
				$return = true;
			}else{
				$return = false;
			}
		}else{
			$return = false;
		}

		return $return;
	}

	public function setFromArchive($id){
		$item = $this->fetchRow($this->select()->where('Id = ?', $id));
		$item->Advert_is_archive = 0;
		if ($item->Advert_is_main_in_group == 0)// если извлекаем объявление из архива и оно не было главным,
		{   //и это единственное активное объявление в группе, то помечаем его как главное в группе
			$this->setNewMainAdvAct($item);
		}
		if($item->save()){
			$return = true;
		}else{
			$return = false;
		}

		return $return;
	}

	public function getAdvertsForRubric($rId, $cId, $cfId, $order = 'Advert_edit_date DESC', $cityId,
										$filterPhoto = 0, $filterPrice = 0, $filterLicence = 0, $filterDate = 0,
										$onlyPayedAdverts = 0, $getAdditionalAdverts = 0){

		$_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');

		if ($getAdditionalAdverts > 0) //если пришел запрос на выборку доп. объяв, то убираем подсчет объяв
		{
			$temp_adv = $this->getAdvert($getAdditionalAdverts);
			$count_array = array();
		}else
		{
			$count_array = array();
			//$count_array = array('Company_adverts_count' => 'COUNT(*)');
		}


		$items = $this->select()
						->distinct()
						->from(array('a' => 'Usluga_adverts'))
						->join('Usluga_companies',
								'Usluga_companies.Id = a.Company_id',
								//array())
								$count_array)

						->join('Usluga_categories',
								'Usluga_categories.Id = a.Category_id',
								array())
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = a.Advert_currency',
								array('(a.Advert_start_price * Usluga_currency_rate.Rate) as AdvertStartPrice',
									  '(a.Advert_end_price * Usluga_currency_rate.Rate) as AdvertEndPrice',
									  'Usluga_currency_rate.Currency'))
						->where('Usluga_companies.Active_status > 0')
						->where('a.Advert_is_archive = 0')
						->where('a.Advert_active_status > 0')
						->where('Usluga_categories.Rubric_id = ?', $rId)
						//->order($order)
						->setIntegrityCheck(false);

		if ($getAdditionalAdverts > 0)//если выбираем доп. объявы, то исключаем уже показанное одно объявление и отбираем остальные объявы этой же компании
		{
			$items = $items->where("a.Id != {$temp_adv->Id}")
						   ->where("a.Company_id = {$temp_adv->Company_id}");
		}

		if ($onlyPayedAdverts == 0) //если выбираем простые объявления не оплаченные объявления
		{
			$items = $items->where("(Pay_advert_type IN (0, {$_config->payadvert->types->day_elongation}, {$_config->payadvert->types->show_counts}))");

		}
		elseif ($onlyPayedAdverts == $_config->payadvert->types->category) //если выбираем объявления выделенные в разделе
		{
			$items = $items->where("Pay_advert_type = {$_config->payadvert->types->category}")
							->order('a.Pay_advert_order_pos ASC');
	    }
		elseif ($onlyPayedAdverts == $_config->payadvert->types->filter) //если выбираем объявления выделенные в фильтре
			$items = $items->where("Pay_advert_type = {$_config->payadvert->types->filter}");
		elseif ($onlyPayedAdverts == $_config->payadvert->types->show_counts) //если выбираем объявление по оплате за показы
		{
			$seenIds = $this->getSeenAdvertsIds();
			$items = $items->where("Pay_advert_type = {$_config->payadvert->types->show_counts}")
			->where("Pay_advert_order_pos > 0")
			->where("a.Id not IN ({$seenIds})")
			->limit(1);
        }

		if($cityId > 1){
			$items = $items->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
							->where('Usluga_companies.Respublican_services = 1 or Usluga_company_addresses.City_id = ?', $cityId);
		}

		if($cId && $cfId){
			$items = $items->join('Usluga_advert_filters', 'Usluga_advert_filters.Advert_id = a.Id', array())
							->where('Usluga_categories.Id = ?', $cId)
							->where('Usluga_advert_filters.Filter_id = ?', $cfId);
		}elseif($cId && !$cfId){
			$items = $items->where('Usluga_categories.Id = ?', $cId);
		}

        if($cId && !$cfId && $getAdditionalAdverts == 0 && $onlyPayedAdverts != $_config->payadvert->types->show_counts) // сортировка по главным объявам в группах
			$items = $items->order("Advert_is_main_in_group DESC")->order("Advert_date_last_update_group DESC");
        else
        	$items = $items->order($order);

		/**/
		if($filterPhoto == 1){
			$items = $items->join('Usluga_advert_photos', 'Usluga_advert_photos.Advert_id = a.Id', array());
		}

		if($filterPrice == 1){
			$items = $items->where('a.Advert_start_price > 0 or a.Advert_end_price > 0');
		}

		if($filterLicence == 1){
			$items = $items->where('Usluga_companies.License_extension != ?', '');
		}

		if($filterDate == Adverts::$FILTER_TD){
			$items = $items->where('Unix_timestamp(a.Advert_edit_date) > ?', strtotime(date('d.m.Y')));
		}elseif($filterDate == Adverts::$FILTER_YTD){
			$items = $items->where('Unix_timestamp(a.Advert_edit_date) > ?',
																					strtotime(date('d.m.Y').' - 1 day'));
		}elseif($filterDate == Adverts::$FILTER_LW){
			$items = $items->where('Unix_timestamp(a.Advert_edit_date) > ?',
																					strtotime(date('d.m.Y').' - 1 week'));
		}

        if ($getAdditionalAdverts == 0) //если пришел запрос на выборку всех объяв, то добавляем подсчет объяв
		{
			$count_array = array('n.*');
			if ($onlyPayedAdverts == 0)
				$count_array['Company_adverts_count'] = 'COUNT(*)';
			else
				$count_array['Company_adverts_count'] = "Id";
			$secondQuery = $this->select()
 			->from(array('s' => 'Usluga_adverts'), $count_array)
 			->join(array('n' => $items),
        	'n.Id = s.Id',
        	array())
			->setIntegrityCheck(false);

			// если не идет отбор доп. объяв и выбираются простые не оплаченные, то группируем по компании и добавляем сортировку по дате обновления
			if ($onlyPayedAdverts == 0)
			{
				if($cId && !$cfId)
					$secondQuery = $secondQuery->order("Advert_is_main_in_group DESC")->order("Advert_date_last_update_group DESC")->group('n.Company_id');
				else
					$secondQuery = $secondQuery->order($order)->group('n.Company_id');

            }
			$items = $secondQuery;
		}


		/**/

		//если выбираем оплаченные объявления или доп. объявления, то отдаем сразу список объявлений, а не select
        if ($onlyPayedAdverts > 0 || $getAdditionalAdverts >0)
			$items = $this->fetchAll($items);

		return $items;
	}

	public function getNoDubleIdList($IdList)
	{		$list = array();
		$order = "FIELD(Usluga_adverts.Id, {$IdList} )";
		$select = $this->select()->distinct()
						->from($this->_name)
						->where("Usluga_adverts.Id in ({$IdList})")
						->order($order)
						->group('Company_id')
						->setIntegrityCheck(false);

		$stmt = $this->getAdapter()->query($select);
		$items = $stmt->fetchAll(Zend_Db::FETCH_OBJ);
		foreach ($items as $v)
			 $list[] = $v->Id;

		return 	$list;	}

	public function getAdvertsByIdList($IdList, $with_photo = 0, $with_price = 0, $with_licence = 0, $city_id = 0){

		$_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');

		$order = "FIELD(Usluga_adverts.Id, {$IdList} )";
		$join_company = 'Usluga_companies.Id = Usluga_adverts.Company_id';
		$join_city = 'Usluga_company_addresses.Company_id = Usluga_companies.Id';

		if ($with_licence>0)
			$join_company.=" and Usluga_companies.License_extension!=''";
		if ($city_id>1)
			$join_city.=" and Usluga_company_addresses.City_id={$city_id}";

		$select = $this->select()->distinct()
						->from($this->_name)
						->join('Usluga_companies',
								$join_company,
								array())
						->join('Usluga_company_addresses',
								$join_city,
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array())
						->join('Usluga_currency_rate',
								'Usluga_currency_rate.Id = Usluga_adverts.Advert_currency',
								array('(Usluga_adverts.Advert_start_price * Usluga_currency_rate.Rate) as AdvertStartPrice',
									  '(Usluga_adverts.Advert_end_price * Usluga_currency_rate.Rate) as AdvertEndPrice',
									  'Usluga_currency_rate.Currency'))
						->order($order)
						->setIntegrityCheck(false);

		if ($with_photo > 0)
			$select->join('Usluga_advert_photos', 'Usluga_advert_photos.Advert_id = Usluga_adverts.Id', array());

		$select->where("Usluga_adverts.Id in ({$IdList})")
				->where('Usluga_companies.Active_status > ?',0)
				->where('Usluga_adverts.Advert_is_archive = 0')
				->where('Usluga_adverts.Advert_active_status > ?',0);

		if ($with_price>0)
			$select->where("Usluga_adverts.Advert_start_price > ?",0);


		/**/
        $stmt = $this->getAdapter()->query($select);
		$items = $stmt->fetchAll(Zend_Db::FETCH_OBJ);

        $result = array("select" => $select,"items" => $items);

		return $result;
	}

	public function getAdvertsForWarnings(){
		$items = $this->select()//->distinct()
						->from($this)
						->join('Usluga_companies',
								'Usluga_companies.Id = Usluga_adverts.Company_id',
								array())
						->join('Usluga_company_addresses',
								'Usluga_company_addresses.Company_id = Usluga_companies.Id',
								array())
						->join('Usluga_categories',
								'Usluga_categories.Id = Usluga_adverts.Category_id',
								array())
						->where('Usluga_companies.Active_status > ?',0)
						->where('Usluga_adverts.Advert_is_archive = 0')
						->where('Usluga_adverts.Advert_active_status > ?',0);

		$items = $this->fetchAll($items);

		return $items;
	}

	public function getAdvertsForTraffic($category_id = 0){
		$select = $this->select()
						->from($this)
						->where("Category_id = {$category_id}")
						->where("Advert_is_archive = 0")
						->where("Advert_active_status > 1")
						->order("Id");

		$items = $this->fetchAll($select);

		$ids = array();
		foreach ($items as $k=>$v)
		{
			$ids[] = $v["Id"];
		}
        $IDs = implode(",", $ids);
		$select = $this->getAdvertsByIdList($IDs);
		$items = $this->fetchAll($select["select"]);

		return $items;
	}

	public function getSeenAdvertsIds()
	{		$_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		if (isset($_COOKIE["clsh"]))
		{            $results = array(0);
            $ids = json_decode($_COOKIE["clsh"],true);
            foreach ($ids as $k=>$v)
            {            	if ($v >= 3)
            		$results[] = $k;            }
            return implode(",",$results);
		}
		else
		{
		 	setcookie("clsh",json_encode(array()),(time()+86400),'/',".{$_config->siteUrl}");
		 	$_COOKIE["clsh"] = json_encode(array());
		 	return 0;
		}	}

	public function setSeenAdvertsIds($advObj)
	{
		$_config = Zend_Controller_Action_HelperBroker::getStaticHelper('Config');
		$ids = json_decode($_COOKIE["clsh"],true);

		if (array_key_exists($advObj->Id,$ids))
			$ids[$advObj->Id]++;
		else
			$ids[$advObj->Id] = 1;

		setcookie("clsh",json_encode($ids),(time()+86400),'/',".{$_config->siteUrl}");
		$newAdvObj = $this->getAdvert($advObj->Id);
		$newAdvObj->decShowCount();

		return true;
	}

	public function setMainInGroup($newMainAdvId, $currentAdvObj)
	{		$this->getAdapter()->query("UPDATE `Usluga_adverts` set Advert_is_main_in_group = 0 where Category_id = {$currentAdvObj->Category_id} and Company_id = {$currentAdvObj->Company_id}");
        $this->getAdapter()->query("UPDATE `Usluga_adverts` set Advert_is_main_in_group = 1 where Id = {$newMainAdvId}");
		return true;	}

}