<?php

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = dirname(__FILE__) . '/';

// Include and load Dolibarr environment variables
$res = 0;
if (!$res && file_exists($path . "master.inc.php")) $res = @include($path . "master.inc.php");
if (!$res && file_exists($path . "../master.inc.php")) $res = @include($path . "../master.inc.php");
if (!$res && file_exists($path . "../../master.inc.php")) $res = @include($path . "../../master.inc.php");
if (!$res && file_exists($path . "../../../master.inc.php")) $res = @include($path . "../../../master.inc.php");
if (!$res) die("Include of master fails");
dol_include_once('discountrules/class/discountrule.class.php');
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

require_once __DIR__ . '/../lib/discountrules.lib.php';

global $langs, $db, $hookmanager, $user;

$hookmanager->initHooks('discountruleinterface');

// Load traductions files requiredby by page
$langs->loadLangs(array("discountrules@discountrules", "other", 'main'));

$action = GETPOST('action');

$activateDebugLog = GETPOST('activatedebug','int');

// Security check
if (empty($conf->discountrules->enabled)) accessforbidden('Module not enabled');

//TODO: why user is not loaded ?
//if ($action === 'product-discount'
//	&& ($user->socid > 0 || empty($user->rights->discountrules->read))
//)
//{
//	$jsonResponse = new stdClass();
//	$jsonResponse->result = false;
//	$jsonResponse->log = array("Not enough rights", $user->rights->discountrules->read );
//
//	// output
//	print json_encode($jsonResponse, JSON_PRETTY_PRINT);
//	$db->close();    // Close $db database opened handler
//	exit;
//}


if ($action === 'product-discount') {

	$productId = GETPOST('fk_product', 'int');
	$fk_project = GETPOST('fk_project', 'int');

	$fk_company = GETPOST('fk_company', 'int');
	$fk_country = GETPOST('fk_country', 'int');
	$qty = GETPOST('qty', 'int');
	$fk_c_typent = GETPOST('fk_c_typent', 'int');


	$jsonResponse = new stdClass();
	$jsonResponse->result = false;
	$jsonResponse->log = array();
	$jsonResponse->defaultCustomerReduction = 0;

	/**
	 * SEARCH DISCOUNT BY FILTERS
	 */

	// GET SOCIETE CAT
	$TCompanyCat = array();
	if (!empty($fk_company)) {
		$c = new Categorie($db);
		$TCompanyCat = $c->containing($fk_company, Categorie::TYPE_CUSTOMER, 'id');

		$societe = new Societe($db);
		if ($societe->fetch($fk_company) > 0) {

			if (empty($fk_country)) {
				$fk_country = $societe->country_id;
			}

			if (empty($fk_c_typent)) {
				$fk_c_typent = $societe->typent_id;
			}

			$jsonResponse->defaultCustomerReduction = $societe->remise_percent;
		}
	}

	//$activateDebugLog = 1;
	_debugLog($TCompanyCat); // pass get var activatedebug or set $activatedebug to show log

	if (empty($qty)) $qty = 1;


	// GET product infos and categories
	$product = false;
	if (!empty($productId)) {
		dol_include_once('product/class/product.class.php');
		$product = new Product($db);

		if ($product->fetch($productId) > 0) {
			// Get current categories
			$c = new Categorie($db);
			$TProductCat = $c->containing($product->id, Categorie::TYPE_PRODUCT, 'id');
		}else {
			$product = false;
			$productId = 0;
		}
	}

	$discount = false;

	if (empty($TProductCat)) {
		$TProductCat = array(0); // force searching in all cat
	} else {
		$TProductCat[] = 0; // search in all cat too
	}

	_debugLog($TProductCat); // pass get var activatedebug or set $activatedebug to show log
	_debugLog($TCompanyCat); // pass get var activatedebug or set $activatedebug to show log

	$TAllProductCat = DiscountRule::getAllConnectedCats($TProductCat);
	$TAllCompanyCat = DiscountRule::getAllConnectedCats($TCompanyCat);

	_debugLog($TAllProductCat); // pass get var activatedebug or set $activatedebug to show log
	_debugLog($TAllCompanyCat); // pass get var activatedebug or set $activatedebug to show log

	$discountRes = new DiscountRule($db);
	$res = $discountRes->fetchByCrit($qty, $productId, $TAllProductCat, $TCompanyCat, $fk_company,  time(), $fk_country, $fk_c_typent, $fk_project);
	_debugLog($discountRes->error);
	if ($res > 0) {
		$discount = $discountRes;
	}
	else{
		$jsonResponse->log[] = $discountRes->error;
	}

	/**
	 * SEARCH ALREADY APPLIED DISCOUNT IN DOCUMENTS (need setup option activated)
	 */

	if($product) {
		$documentDiscount = false;
		$from_quantity = empty($conf->global->DISCOUNTRULES_SEARCH_QTY_EQUIV) ? 0 : $qty;

		if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_ORDERS)) {
			$commande = DiscountRule::searchDiscountInDocuments('commande', $product->id, $fk_company, $from_quantity);
			$documentDiscount = $commande;
		}
		if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_PROPALS)) {
			$propal = DiscountRule::searchDiscountInDocuments('propal', $product->id, $fk_company, $from_quantity);
			if (!empty($propal)
				&& (empty($documentDiscount) || DiscountRule::calcNetPrice($documentDiscount->subprice, $documentDiscount->remise_percent) > DiscountRule::calcNetPrice($propal->subprice, $propal->remise_percent) ))
			{
				$documentDiscount = $propal;
			}
		}
		if (!empty($conf->global->DISCOUNTRULES_SEARCH_IN_INVOICES)) {
			$facture = DiscountRule::searchDiscountInDocuments('facture', $product->id, $fk_company, $from_quantity);
			if (!empty($facture)
				&& (empty($documentDiscount)|| DiscountRule::calcNetPrice($documentDiscount->subprice, $documentDiscount->remise_percent) > DiscountRule::calcNetPrice($facture->subprice, $facture->remise_percent) ) )
			{
				$documentDiscount = $facture;
			}
		}

		if (!empty($documentDiscount) && $documentDiscount->remise_percent > $discount->reduction) {

			$useDocumentReduction = true;
			if (!empty($discount)) {
				$discountNetPrice = $discount->getNetPrice($product->id, $fk_company);
				if(!empty($discountNetPrice) && DiscountRule::calcNetPrice($documentDiscount->subprice, $documentDiscount->remise_percent) > $discountNetPrice) {
					$useDocumentReduction = false;
				}
			}

			if($useDocumentReduction) {

				$discount = false;

				$jsonResponse->result = true;
				$jsonResponse->element = $documentDiscount->element;
				$jsonResponse->id = $documentDiscount->rowid;
				$jsonResponse->label = $documentDiscount->ref;
				$jsonResponse->qty = $documentDiscount->qty;
				$jsonResponse->subprice = doubleval($documentDiscount->subprice);
				$jsonResponse->product_reduction_amount = 0;
				$jsonResponse->reduction = $documentDiscount->remise_percent;
				$jsonResponse->entity = $documentDiscount->entity;
				$jsonResponse->fk_status = $documentDiscount->fk_status;
				$jsonResponse->date_object = $documentDiscount->date_object;
				$jsonResponse->date_object_human = dol_print_date($documentDiscount->date_object, '%d %b %Y');
			}
		}
	}


	/**
	 * PREPARE JSON RETURN
	 */

	if (!empty($discount)) {

		$jsonResponse->result = true;
		$jsonResponse->element = 'discountrule';
		$jsonResponse->id = $discount->id;
		$jsonResponse->label = $discount->label;
		$jsonResponse->subprice = $discount->getDiscountSellPrice($productId, $fk_company) - $discount->product_reduction_amount;
		$jsonResponse->product_price = $discount->product_price;
		$jsonResponse->standard_product_price = $discount::getProductSellPrice($productId, $fk_company);
		$jsonResponse->product_reduction_amount = $discount->product_reduction_amount;
		$jsonResponse->reduction = $discount->reduction;
		$jsonResponse->entity = $discount->entity;
		$jsonResponse->from_quantity = $discount->from_quantity;
		$jsonResponse->fk_c_typent = $discount->fk_c_typent;
		$jsonResponse->fk_project = $discount->fk_project;
		$jsonResponse->priority_rank = $discount->priority_rank;

		$jsonResponse->typentlabel  = getTypeEntLabel($discount->fk_c_typent);
		if(!$jsonResponse->typentlabel ){ $jsonResponse->typentlabel = ''; }

		$jsonResponse->fk_status = $discount->fk_status;
		$jsonResponse->fk_product = $discount->fk_product;
		$jsonResponse->date_creation = $discount->date_creation;
		$jsonResponse->match_on = $discount->lastFetchByCritResult;
		if (!empty($discount->lastFetchByCritResult)) {
			// Here there are matching parameters for product categories or company categories
			// ADD humain readable informations from search result
			$jsonResponse->match_on->product_info = '';
			if($product && !empty($discount->fk_product) && $product->id == $discount->fk_product ){
				$jsonResponse->match_on->product_info = $product->ref . ' - '.$product->label;
			}

			$jsonResponse->match_on->category_product = $langs->transnoentities('AllProductCategories');
			if (!empty($discount->lastFetchByCritResult->fk_category_product)) {
				$c = new Categorie($db);
				$c->fetch($discount->lastFetchByCritResult->fk_category_product);
				$jsonResponse->match_on->category_product = $c->label;
			}

			$jsonResponse->match_on->category_company = $langs->transnoentities('AllCustomersCategories');
			if (!empty($discount->lastFetchByCritResult->fk_category_company)) {
				$c = new Categorie($db);
				$c->fetch($discount->lastFetchByCritResult->fk_category_company);
				$jsonResponse->match_on->category_company = $c->label;
			}

			$jsonResponse->match_on->company = $langs->transnoentities('AllCustomers');
			if (!empty($discount->lastFetchByCritResult->fk_company)) {
				$s = new Societe($db);
				$s->fetch($discount->lastFetchByCritResult->fk_company);

				$jsonResponse->match_on->company = $s->name ? $s->name : $s->nom;
				$jsonResponse->match_on->company .= !empty($s->name_alias) ? ' (' . $s->name_alias . ')' : '';
			}

			if (!empty($discount->lastFetchByCritResult->fk_project)) {
				$p = new Project($db);
				$p->fetch($discount->lastFetchByCritResult->fk_project);
				$jsonResponse->match_on->project = $p->ref . ' : '.$p->title;
			}
		}
	}


	// Mise en page de du résultat
	$jsonResponse->tpMsg = getDiscountRulesInterfaceMessageTpl($langs, $jsonResponse, $action);

	// Note that $action and $object may be modified by hook
	// Utilisation initiale : interception pour remplissage customisé de $jsonResponse->tpMsg

	$parameters = array(
		'action' => $action,
		'activateDebugLog' => $activateDebugLog,
		'productId' => $productId,
		'fk_project' => $fk_project,
		'fk_company' => $fk_company,
		'fk_country' => $fk_country,
		'qty' => $qty,
		'fk_c_typent' => $fk_c_typent
	);

	$reshook = $hookmanager->executeHooks('ToolTipformAddInfo', $parameters, $jsonResponse, $action);


	// output
	print json_encode($jsonResponse, JSON_PRETTY_PRINT);
}


$db->close();    // Close $db database opened handler


function _debugLog($log = null){
	global $activateDebugLog;

	if($activateDebugLog){
		var_dump($log);
	}
}


