<?php
	ini_set('display_errors','On');
	error_reporting(E_ALL);

	require("../config.php");
	require(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
                

	$get = isset($_REQUEST['get'])?$_REQUEST['get']:'';
	
	_get($get);

function _get($case) {
	
	$ATMdb = new TPDOdb;
	
	switch ($case) {
		case 'getproductprice':
			__out(_getproductprice($ATMdb,$_POST['fk_product']));			
			break;
		case 'getproductfournprice':
			__out(_getproductfournprice($ATMdb,$_POST['fk_product']));			
			break;
			
		default:
			
			break;
	}
	
}


// Retourne le prix d'un produit
function _getproductprice(&$ATMdb,&$id) {

	$Tres = array();
	
	$sql = "SELECT price
			FROM ".MAIN_DB_PREFIX."product_price
			WHERE fk_product = ".$id."
			ORDER BY date_price DESC
			LIMIT 1";
	
	$ATMdb->Execute($sql);
	
	while($ATMdb->Get_line()){
		$Tres["price"] = $ATMdb->Get_field('price');
	}
	
	return $Tres;
}

// Retourne le prix fournisseur d'un produit
function _getproductfournprice(&$ATMdb,&$id) {

	$Tres = array();
	
	$sql = "SELECT price
			FROM ".MAIN_DB_PREFIX."product_fournisseur_price
			WHERE rowid = ".$id;
	
	$ATMdb->Execute($sql);
	
	while($ATMdb->Get_line()){
		$Tres["price"] = $ATMdb->Get_field('price');
	}
	
	return $Tres;
}

