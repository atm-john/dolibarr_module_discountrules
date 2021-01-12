<?php

require_once __DIR__ . '/discountrule.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/../lib/discountrules.lib.php';

/**
 * A class to search in discounts
 *
 * Class DiscountRuleSearch
 */
class DiscountRulesSearch
{

	/**
	 * @var string[] $TDebugLog
	 */
	public $TDebugLog = array();

	/** Search input
	 * @var integer[] $TCompanyCat
	 */
	public $TCompanyCat = array();

	/** Search input
	 * @var integer[] $TProductCat
	 */
	public $TProductCat = array();


	/** Searched input
	 * @var int $fk_country
	 */
	public $fk_country = 0;

	/** Searched input
	 * @var int $fk_c_typent
	 */
	public $fk_c_typent = 0;

	/** Searched input
	 * @var double $qty
	 */
	public $qty = 0;

	/** Searched input
	 * @var int $fk_product
	 */
	public $fk_product = 0;

	/** Searched input
	 * @var int $fk_company
	 */
	public $fk_company = 0;

	/** Searched input
	 * @var int $fk_project
	 */
	public $fk_project = 0;



	/**
	 * @var DoliDb		Database handler (result of a new DoliDB)
	 */
	protected $db;

	/**
	 * @var Product $product
	 */
	protected $product;

	/**
	 * @var Societe $societe
	 */
	protected $societe;

	/**
	 * @var DiscountRulesSearchResult $result
	 */
	public $result;

	/**
	 * @var DiscountRule $discount
	 */
	public $discount;

	/**
	 * @var object $documentDiscount
	 */
	public $documentDiscount;

	/**
	 * @var double $defaultCustomerReduction
	 */
	protected $defaultCustomerReduction = 0;


	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;

		$this->result = new DiscountRulesSearchResult();
	}

	/**
	 * Launch search
	 * @return DiscountRulesSearchResult
	 */
	private function launchSearch()
	{
		global $langs;

		$this->result = new DiscountRulesSearchResult();
		$this->result->defaultCustomerReduction = $this->defaultCustomerReduction;

		$this->launchSearchRule();
		$this->launchSearchDocumentsDiscount();

		// Search product net price
		$productNetPrice = $this->discount->getNetPrice($this->fk_product, $this->fk_company);

		$useDocumentReduction = false;
		if (!empty($this->documentDiscount)) {

			$useDocumentReduction = true;
			if (!empty($this->discount)) {
				if(!empty($productNetPrice) && DiscountRule::calcNetPrice($this->documentDiscount->subprice, $this->documentDiscount->remise_percent) > $productNetPrice) {
					$useDocumentReduction = false;
				}
			}

			if($useDocumentReduction) {
				$this->discount = false;

				$this->result->result = true;
				$this->result->element = $this->documentDiscount->element;
				$this->result->id = $this->documentDiscount->rowid;
				$this->result->label = $this->documentDiscount->ref;
				$this->result->qty = $this->documentDiscount->qty;
				$this->result->subprice = doubleval($this->documentDiscount->subprice);
				$this->result->product_reduction_amount = 0;
				$this->result->reduction = $this->documentDiscount->remise_percent;
				$this->result->entity = $this->documentDiscount->entity;
				$this->result->fk_status = $this->documentDiscount->fk_status;
				$this->result->date_object = $this->documentDiscount->date_object;
				$this->result->date_object_human = dol_print_date($this->documentDiscount->date_object, '%d %b %Y');
			}
		}

		// TODO : Manage customer default reduction
		if(!empty($this->defaultCustomerReduction) && !empty($productNetPrice)){
			if($useDocumentReduction){

			}
			elseif ($this->discount) {
				if(!empty($productNetPrice) && DiscountRule::calcNetPrice($this->documentDiscount->subprice, $this->documentDiscount->remise_percent) > $productNetPrice) {
					$useDocumentReduction = false;
				}
			}
			else{
				// use customer reduction
			}
		}


		/**
		 * PREPARE RESULT
		 */

		if (!empty($this->discount)) {

			$this->result->result = true;
			$this->result->element = 'discountrule';
			$this->result->id = $this->discount->id;
			$this->result->label = $this->discount->label;

			$this->result->subprice = $this->discount->getDiscountSellPrice($this->fk_product, $this->fk_company) - $this->discount->product_reduction_amount;
			$this->result->product_price = $this->discount->product_price;
			$this->result->standard_product_price = DiscountRule::getProductSellPrice($this->fk_product, $this->fk_company);
			$this->result->product_reduction_amount = $this->discount->product_reduction_amount;
			$this->result->reduction = $this->discount->reduction;
			$this->result->entity = $this->discount->entity;
			$this->result->from_quantity = $this->discount->from_quantity;
			$this->result->fk_c_typent = $this->discount->fk_c_typent;
			$this->result->fk_project = $this->discount->fk_project;
			$this->result->priority_rank = $this->discount->priority_rank;

			$this->result->typentlabel  = getTypeEntLabel($this->discount->fk_c_typent);
			if(!$this->result->typentlabel ){ $this->result->typentlabel = ''; }

			$this->result->fk_status = $this->discount->fk_status;
			$this->result->fk_product = $this->discount->fk_product;
			$this->result->date_creation = $this->discount->date_creation;
			$this->result->match_on = $this->discount->lastFetchByCritResult;
			if (!empty($this->discount->lastFetchByCritResult)) {
				// Here there are matching parameters for product categories or company categories
				// ADD humain readable informations from search result
				$this->result->match_on->product_info = '';
				if($this->product && !empty($this->discount->fk_product) && $this->product->id == $this->discount->fk_product ){
					$this->result->match_on->product_info = $this->product->ref . ' - '.$this->product->label;
				}

				$this->result->match_on->category_product = $langs->transnoentities('AllProductCategories');
				if (!empty($this->discount->lastFetchByCritResult->fk_category_product)) {
					$c = new Categorie($this->db);
					$c->fetch($this->discount->lastFetchByCritResult->fk_category_product);
					$this->result->match_on->category_product = $c->label;
				}

				$this->result->match_on->category_company = $langs->transnoentities('AllCustomersCategories');
				if (!empty($this->discount->lastFetchByCritResult->fk_category_company)) {
					$c = new Categorie($this->db);
					$c->fetch($this->discount->lastFetchByCritResult->fk_category_company);
					$this->result->match_on->category_company = $c->label;
				}

				$this->result->match_on->company = $langs->transnoentities('AllCustomers');
				if (!empty($this->discount->lastFetchByCritResult->fk_company)) {
					$s = new Societe($this->db);
					$s->fetch($this->discount->lastFetchByCritResult->fk_company);

					$this->result->match_on->company = $s->name ? $s->name : $s->nom;
					$this->result->match_on->company .= !empty($s->name_alias) ? ' (' . $s->name_alias . ')' : '';
				}

				if (!empty($this->discount->lastFetchByCritResult->fk_project)) {
					$p = new Project($this->db);
					$p->fetch($this->discount->lastFetchByCritResult->fk_project);
					$this->result->match_on->project = $p->ref . ' : '.$p->title;
				}
			}
		}


		return $this->result;
	}

	/**
	 * Launch search rule
	 * @return DiscountRule|false
	 */
	private function launchSearchRule()
	{
		if (empty($this->qty)) $this->qty = 1;

		if (empty($this->TProductCat)) {
			$this->TProductCat = array(0); // force searching in all cat
		} else {
			$this->TProductCat[] = 0; // search in all cat too
		}

		$this->debugLog($this->TProductCat); // pass get var activatedebug or set activatedebug to show log
		$this->debugLog($this->TCompanyCat); // pass get var activatedebug or set activatedebug to show log

		$TAllProductCat = DiscountRule::getAllConnectedCats($this->TProductCat);
		$TCompanyCat = DiscountRule::getAllConnectedCats($this->TCompanyCat);

		$this->debugLog($TAllProductCat); // pass get var activatedebug or set activatedebug to show log
		$this->debugLog($TCompanyCat); // pass get var activatedebug or set activatedebug to show log

		$discountRes = new DiscountRule($this->db);
		$res = $discountRes->fetchByCrit($this->qty, $this->fk_product, $TAllProductCat, $TCompanyCat, $this->fk_company,  time(), $this->fk_country, $this->fk_c_typent, $this->fk_project);
		$this->debugLog($discountRes->error);
		if ($res > 0) {
			$this->discount = $discountRes;
		}
		else{
			$this->discount = false;
			$this->result->log[] = $discountRes->error;
		}

		return $this->discount;
	}


	/**
	 * SEARCH ALREADY APPLIED DISCOUNT IN DOCUMENTS (need setup option activated)
	 * @return object
	 */
	private function launchSearchDocumentsDiscount()
	{
		if (empty($this->qty)) $this->qty = 1;

		$this->documentDiscount = false;

		if($this->fk_product) {

			$from_quantity = empty($conf->global->DISCOUNTRULES_SEARCH_QTY_EQUIV) ? 0 : $this->qty;

			if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_ORDERS)) {
				$commande = DiscountRule::searchDiscountInDocuments('commande', $this->fk_product, $this->fk_company, $from_quantity);
				$this->documentDiscount = $commande;
			}
			if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_PROPALS)) {
				$propal = DiscountRule::searchDiscountInDocuments('propal', $this->fk_product, $this->fk_company, $from_quantity);
				if (!empty($propal)
					&& (empty($this->documentDiscount) || DiscountRule::calcNetPrice($this->documentDiscount->subprice, $this->documentDiscount->remise_percent) > DiscountRule::calcNetPrice($propal->subprice, $propal->remise_percent) ))
				{
					$this->documentDiscount = $propal;
				}
			}
			if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_INVOICES)) {
				$facture = DiscountRule::searchDiscountInDocuments('facture', $this->fk_product, $this->fk_company, $from_quantity);
				if (!empty($facture)
					&& (empty($this->documentDiscount)|| DiscountRule::calcNetPrice($this->documentDiscount->subprice, $this->documentDiscount->remise_percent) > DiscountRule::calcNetPrice($facture->subprice, $facture->remise_percent) ) )
				{
					$this->documentDiscount = $facture;
				}
			}
		}

		return $this->documentDiscount;
	}





	/**
	 * Add company info to search query
	 * @param int $fk_company
	 * @return boolean
	 */
	public function feedBySoc($fk_company){

		$this->fk_company = 0;

		if (!empty($fk_company)) {
			$this->societe = new Societe($this->db);
			if ($this->societe->fetch($fk_company) > 0) {

				$c = new Categorie($this->db);
				$this->TCompanyCat = $c->containing($fk_company, Categorie::TYPE_CUSTOMER, 'id');

				if (empty($this->fk_country)) {
					$this->fk_country = $this->societe->country_id;
				}

				if (empty($this->fk_c_typent)) {
					$this->fk_c_typent = $this->societe->typent_id;
				}

				$this->defaultCustomerReduction = $this->societe->remise_percent;
				$this->fk_company = $this->societe->id;
				return true;
			}
			else{
				$this->societe = false;
				return false;
			}
		}

		return false;
	}

	/**
	 * Add product info to search query
	 * @param int $fk_product
	 * @return boolean
	 */
	public function feedByProduct($fk_product){
		// GET product infos and categories
		$this->product = false;
		$this->fk_product = 0;

		if (!empty($fk_product)) {
			$this->product = new Product($this->db);
			if ($this->product->fetch($fk_product) > 0) {
				$this->fk_product = $this->product->id;

				// Get current categories
				$c = new Categorie($this->db);
				$this->TProductCat = $c->containing($this->product->id, Categorie::TYPE_PRODUCT, 'id');
				return true;
			}else {
				$this->product = false;
				return false;
			}
		}

		return false;
	}


	/**
	 * @param string $log
	 */
	public function debugLog($log = null){
		if(!empty($log)) $this->TDebugLog[] = $log;
	}

}


/**
 * A class to manage results
 * only for IDE auto complete
 *
 * USED to return result compatible ajax json usage
 *
 * Class DiscountRuleSearch
 */
class DiscountRulesSearchResult
{
	public $result = false;
	public $log = array();


	public $defaultCustomerReduction = 0;
	public $discount = false;

	public $element;
	public $id;
	public $label;
	public $qty;
	public $subprice;
	public $product_price;
	public $standard_product_price;
	public $product_reduction_amount = 0;
	public $reduction;
	public $entity;
	public $fk_status;
	public $date_object;
	public $date_object_human;
	public $from_quantity;
	public $fk_c_typent;
	public $fk_project;
	public $priority_rank ;

	public $typentlabel;
	public $fk_product;
	public $date_creation;
	public $match_on;
}
