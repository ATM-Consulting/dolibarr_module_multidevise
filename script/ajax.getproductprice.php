<?php
require("../config.php");
require(DOL_DOCUMENT_ROOT."/product/class/product.class.php");

$id = $_POST['fk_product'];

$ATMdb = new Tdb;
$Tres = array();

$sql = "SELECT price_ttc
		FROM ".MAIN_DB_PREFIX."product_price
		WHERE fk_product = ".$id."
		ORDER BY date_price DESC
		LIMIT 1";

$ATMdb->Execute($sql);

while($ATMdb->Get_line()){
	$Tres["price"] = $ATMdb->Get_field('price_ttc');
}

echo json_encode($Tres);