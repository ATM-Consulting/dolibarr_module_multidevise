<?php
	
	require('../config.php');
	dol_include_once("/core/lib/functions.lib.php");
	/*
	 * Ajoute le choix de currency 
	 */
	if($conf->global->PRODUCT_MULTIPRICE){
	
		$form=new Form($db);
		ob_start();
		$form->select_currency($cur,"currency");
		$select_currency =ob_get_clean();
		
		$res =$db->query("SELECT DISTINCT devise_code, price_level FROM ".MAIN_DB_PREFIX."product_price ORDER BY date_price DESC");
		$Tab=array();
		while($obj = $db->fetch_object($res)) {
			if($obj->price_level>1) {
				$Tab[$obj->price_level] = $obj->devise_code;	
			}
					
		}
		
		$referrer = $_SERVER['HTTP_REFERER'];

	?>
	
	
	$(document).ready(function() {
		var ref="<?php echo $referrer?>";
		if(ref.indexOf('/htdocs/product/price.php?action=edit_price')!=-1) {
			/*
			 * Fiche price
			 */
			
			var i=1;
			
			$("div.fiche [name^=multiprices_base_type_]").each(function() {
				
				if(i==1) {
					null; // monnaie courante du logiciel obligatoire
				}
				else {
					$select = $("<?php echo addslashes($select_currency ) ?>");
					$select.attr('id', 'currency_'+i);
					
					$(this).after($select);	
				}
						
				i++;
			});
			
			<?php
			
			foreach($Tab as $price_level=>$cur) {
				?>
				$('#currency_<?php echo $price_level ?>').val('<?php echo $cur ?>');
				<?php
			}
			
			
			?>
			
		}
		else if(ref.indexOf('/htdocs/product/price.php?id=')!=-1) {
			var TCurrency = new Array;
			<?php
			
			foreach($Tab as $price_level=>$cur) {
				?>
				TCurrency[<?php echo $price_level ?>] = '<?php echo $cur ?>';
				<?php
			}
			?>
			
			$('div.fiche table tr>td+td').each(function() {
				
				price_level = $(this).html();
				
				if(TCurrency[price_level]!=null) {
					$(this).next().next().next().append(' '+TCurrency[price_level]);
				}
				
			});
			
		}
		else {
			/*alert(document.referrer);*/
		}
		
		
		
	});
	
	<?php
	}
?>
	