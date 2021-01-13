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
require_once __DIR__ . '/../class/discountrule.class.php';
require_once __DIR__ . '/../class/discountSearch.class.php';
require_once __DIR__ . '/../lib/discountrules.lib.php';

global $langs, $db, $hookmanager, $user;

$hookmanager->initHooks('discountruleinterface');

// Load traductions files requiredby by page
$langs->loadLangs(array("discountrules@discountrules", "other", 'main'));

$action = GETPOST('action');


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

	$fk_product = GETPOST('fk_product', 'int');
	$fk_project = GETPOST('fk_project', 'int');
	$fk_company = GETPOST('fk_company', 'int');
	$fk_country = GETPOST('fk_country', 'int');
	$qty = GETPOST('qty', 'int');
	$fk_c_typent = GETPOST('fk_c_typent', 'int');

	$search = new DiscountSearch($db);
	$jsonResponse = $search->search($qty, $fk_product, $fk_company, $fk_project, array(), array(), $fk_c_typent, $fk_country);

	// Mise en page de du résultat
	$jsonResponse->tpMsg = getDiscountRulesInterfaceMessageTpl($langs, $jsonResponse, $action);

	// Note that $action and $object may be modified by hook
	// Utilisation initiale : interception pour remplissage customisé de $jsonResponse->tpMsg

	$parameters = array(
		'search' => $search,
		'action' => $action,
		'productId' => $fk_product,
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

