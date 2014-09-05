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
		case 'numberformat':
			__out(_numberformat($_REQUEST['montant'], $_REQUEST['type']));
			break;
		case 'getcurrencyrate':
			__out(_getcurrencyrate($ATMdb,$_POST['currency_code']));
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
	$ATMdb->Get_line();
	
	if(isset($_REQUEST['taux'])){
		$price = $ATMdb->Get_field('price') / $_REQUEST['taux'];
	}
	else{
		$price = $ATMdb->Get_field('price');
	}
	
	$Tres["price"] = $price;
	
	return $Tres;
}

function _numberformat($price, $type='price2num'){
	
	switch ($type) {
		case 'price2num':
			return array('montant'=>strtr(price2num($price,2),array(','=>''))); //conversion d'un prix en nombre
			break;

		case 'price':
			return array('montant'=>strtr(price($price,'MT','',1,2,2),array("&nbsp;"=>" ")));//conversion d'un nombre en prix
			break;

		default:
			
			break;
	}
}

function _getcurrencyrate(&$ATMdb,$currency_code){
	global $conf;
	
	$sql = 'SELECT cr.rate
			FROM '.MAIN_DB_PREFIX.'currency_rate as cr
				LEFT JOIN '.MAIN_DB_PREFIX.'currency as c ON (c.rowid = cr.id_currency)
			WHERE c.code = "'.$currency_code.'" AND cr.id_entity = '.$conf->entity.'
				ORDER BY cr.dt_sync DESC LIMIT 1';
	
	$ATMdb->Execute($sql);
	$ATMdb->Get_line();
	
	$Tres["currency_rate"] = round($ATMdb->Get_field('rate'),2);
	
	return $Tres;
}
