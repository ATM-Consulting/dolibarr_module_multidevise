<?php
	
	require('../config.php');
	dol_include_once("/core/lib/functions.lib.php");
	/*
	 * Ajoute le choix de currency 
	 */
	$form=new Form($db);
	ob_start();
	$form->select_currency($cur,"currency");
	
	$select_currency =ob_get_clean();

?>


$(document).ready(function() {
	
	if(document.referrer.indexOf('/htdocs/product/price.php?')!=-1) {
		/*
		 * Fiche price
		 */
		
		
		$("div.fiche [name^=multiprices_base_type_]").each(function() {
			
			if($(this).attr('name')=="multiprices_base_type_1") {
				null; // monnaie courante du logiciel obligatoire
			}
			else {
				$(this).after("<?=addslashes($select_currency ) ?>");	
			}
					
			
		});
		
		
	}
	
	
	
});
	
