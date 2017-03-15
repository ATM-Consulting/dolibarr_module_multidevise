<?php
class ActionsMultidevise
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
    
    function beforePDFCreation($parameters, &$object, &$action, $hookmanager) {
    	global $conf;
		
		// pour implementation dans Dolibarr 3.7
		if (in_array('pdfgeneration',explode(':',$parameters['context']))
			&& !in_array('expeditioncard',explode(':',$parameters['context']))
			&& !in_array('contractcard',explode(':',$parameters['context']))) {
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/multidevise/config.php');
			dol_include_once('/multidevise/class/multidevise.class.php');
			
			if(isset($parameters['object'])){
				if(!$conf->global->MULTIDEVISE_DONT_USE_ON_SELL && ($object->element == 'propal' || $object->element == 'facture' || $object->element == 'commande'))
					TMultidevise::preparePDF($parameters['object']);
			}
			else{
				TMultidevise::preparePDF($object);
			}
		}
		
    }
	
	function afterPDFCreation($parameters, &$object, &$action, $hookmanager) {
    	global $conf;
		// pour implementation dans Dolibarr 3.7
		if (in_array('pdfgeneration',explode(':',$parameters['context']))) {
			//echo '<pre>';
			$parameters['object']->fetch($parameters['object']->id);
			$conf->currency = $parameters['object']->origin_currency;
		}
		
    }
	
    
    function doActions($parameters, &$object, &$action, $hookmanager) 
    {
    	global $langs, $db, $conf, $user;

		if($action == 'setinvoicedate' || $action == 'setdate' || $action == 'setdatef'){
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/multidevise/config.php');
			dol_include_once('/multidevise/class/multidevise.class.php');

			if($conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE && 
				(isset($_REQUEST['invoicedate']) || isset($_REQUEST['re']) || isset($_REQUEST['order_']) || isset($_REQUEST['datef']))){

				if($_REQUEST['re']){	
					$object->date = dol_mktime(12, 0, 0, $_REQUEST ['remonth'], $_REQUEST ['reday'], $_REQUEST ['reyear']);
				}
				elseif($_REQUEST['invoicedate']){
					$object->date = dol_mktime(12, 0, 0, $_REQUEST ['invoicedatemonth'], $_REQUEST ['invoicedateday'], $_REQUEST ['invoicedateyear']);
				}
				elseif($_REQUEST['order_']){
					$object->date = dol_mktime(12, 0, 0, $_REQUEST ['order_month'], $_REQUEST ['order_day'], $_REQUEST ['order_year']);
				}
				elseif($_REQUEST['datef']){
					$object->date = dol_mktime(12, 0, 0, $_REQUEST ['datefmonth'], $_REQUEST ['datefday'], $_REQUEST ['datefyear']);
				}

				$sql = "SELECT c.code FROM ".MAIN_DB_PREFIX."currency as c 
						LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element." as f ON (f.fk_devise = c.rowid) 
						WHERE f.rowid = ".$object->id;
				$resql = $db->query($sql);
				$res = $db->fetch_object($resql);
				$currency = $res->code;

				TMultidevise::_setCurrencyRate($db, $object, $currency);
			}
			
		}

        return 0;
    }
    
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {

    	global $db, $user,$conf, $langs;
		dol_include_once("/societe/class/societe.class.php");
		dol_include_once("/core/lib/company.lib.php");
		dol_include_once("/core/lib/functions.lib.php");
		define('INC_FROM_DOLIBARR',true);
		dol_include_once("/multidevise/config.php");
		dol_include_once("/multidevise/class/multidevise.class.php");
		$langs->load('multidevise@multidevise');
		
		if (in_array('thirdpartycard',explode(':',$parameters['context']))
			|| ((in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL))
			|| in_array('ordersuppliercard',explode(':',$parameters['context']))
			|| in_array('invoicesuppliercard',explode(':',$parameters['context']))){
			
	    	/* ***********************
			 * EDIT
			 * ***********************/
	    	if($action == "edit" || $action == "create"){
	    		
				$cur = $conf->currency;
				$id = (!empty($_REQUEST['socid'])) ? $_REQUEST['socid'] : 0 ;

				//cas ou le document créer à une origine
				if((isset($_REQUEST['origin']) && isset($_REQUEST['originid']))
					|| ($object->origin && $object->origin_id )){
					
					$origin = ($_REQUEST['origin']) ? $_REQUEST['origin'] : $object->origin;
					
					if($origin == 'order_supplier') $origin = "commande_fournisseur";
					
					$origin_id = ($_REQUEST['originid']) ? $_REQUEST['originid'] : $object->origin_id;
					
					$sql = 'SELECT fk_devise, devise_code';
	    			$sql .= ' FROM '.MAIN_DB_PREFIX.$origin.' WHERE rowid = '.$origin_id;
				}
				else{// cas standard on récupère la devise associé au tiers
					$sql = 'SELECT fk_devise, devise_code';
	    			$sql .= ' FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.$id;
				}

	    		if($resql = $db->query($sql)){
					$res = $db->fetch_object($resql);
					if($res->fk_devise && !is_null($res->devise_code)){
						$cur = $res->devise_code;
					}
				}
				
				$form=new Form($db);
				print '<tr><td>'.$langs->trans('Currency').'</td><td colspan="3">';
				print $form->select_currency($cur,"currency");
				print '</td></tr>';

	    	}
			else{
				
				/* ***********************
				 * VIEW
				 * ***********************/
				
				if(__get('action')==='save_currency') {

					TMultidevise::updateCurrencyRate($db, $object,__get('currency'),__get('taux_devise',0));
				}
				
				
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$('select[name=currency]').change(function(){
							$.ajax({
								type: "POST"
								,url: "<?php echo dol_buildpath('/multidevise/script/interface.php',2); ?>"
								,dataType: "json"
								,data: {
									get : "getcurrencyrate",
									currency_code: $('select[name=currency]').val(),
									json : 1
									}
								},"json").then(function(select){
									if(select.currency_rate != ""){
										$('#taux_devise').val(select.currency_rate);
									}
								});
						});
					});
				</script>
				<?php
				
				$sql = 'SELECT fk_devise, devise_code';
	    		$sql .= ($object->table_element != "societe") ? ', devise_taux, devise_mt_total' : "";
	    		$sql .= ' FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id;

	    		$resql = $db->query($sql);
				$res = $db->fetch_object($resql);

				if(($res->fk_devise && !is_null($res->devise_code)) || !empty($conf->global->MULTIDEVISE_ALLOW_UPDATE_FK_DEVISE_ON_OLD_DOC)){
					
					print '
					<form name="saveCurrecy" action="#" />
					<input name="action" value="save_currency" type="hidden" />
					<input name="facid" type="hidden" value="'.__get('facid').'" />
					<input name="id" type="hidden" value="'.__get('id').'" />
					<input name="socid" type="hidden" value="'.__get('socid').'" />
					<tr><td>'.$langs->trans('Currency');
					
					if($action!='edit_currency') {
						print '<a style="float:right;" href="?id='.__get('id').'&facid='.__get('facid').'&socid='.__get('socid').'&action=edit_currency">'.img_picto('', 'edit').'</a>';
					}
					print '</td><td colspan="3">';
					
					if($action=='edit_currency') {
						$form=new Form($db);
						echo $form->select_currency($res->devise_code,"currency");
						
					}
					else {
						print currency_name($res->devise_code,1);
						print ' ('.$res->devise_code.')</td></tr>';
					}


					if($object->table_element != "societe"){
						print '<tr><td>'.$langs->trans('CurrencyRate').'</td>';
						if($action=='edit_currency') {
							print '<td colspan="3"><input type="text" name="taux_devise" id="taux_devise" value="'.__val($res->devise_taux,1).'" size="10" />
							
							<input type="submit" value="'.$langs->trans('Modify').'" />
							</td>';	
						}
						else {
							print '<td colspan="3"><span title="'.$res->devise_taux.'">'.price(__val($res->devise_taux,1),0,'',1,$conf->global->MAIN_MAX_DECIMALS_UNIT,$conf->global->MAIN_MAX_DECIMALS_UNIT).'</span><input type="hidden" id="taux_devise" value="'.__val($res->devise_taux,1).'" /></td>';	
						}
						print '</tr>';
						//pre($object);exit;
						print '<tr><td>'.$langs->trans('CurrencyTotal').'</td><td colspan="3">'.price($res->devise_mt_total,0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT).'</td></tr>';
						
						print '<tr><td>'.$langs->trans('CurrencyVAT').'</td><td colspan="3">'.price($object->total_tva*$res->devise_taux,0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT).'</td></tr>';
						
						print '<tr><td>'.$langs->trans('CurrencyTotalVAT').'</td><td colspan="3">'.price($res->devise_mt_total + ($object->total_tva*$res->devise_taux),0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT).'</td></tr>';
					}
					elseif($action=='edit_currency'){
						print '<input type="submit" value="'.$langs->trans('Modify').'" />';
					}

					print '</form>';					
				}
				else{
					
					print '<tr><td>'.$langs->trans('Currency').'</td><td colspan="3">';
					print currency_name($conf->currency,1);
					print ' ('.$conf->currency.')</td></tr>';

					if($object->table_element != "societe"){
						print '<tr><td>'.$langs->trans('CurrencyRate').'</td><td colspan="3">1,0<input type="hidden" id="taux_devise" value="1" /></td></tr>';
						print '<tr><td>'.$langs->trans('CurrencyTotal').'</td><td colspan="3"></td></tr>';
					}
					
				}
				/* *********************************************************************************
				 * Ajout d'attribut aux lignes et colonne pour accessibilité plus facile avec jquery
				 * *********************************************************************************/
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						console.log('formObjectOptions::columnsAndLines');
						
						$('#tablelines tr').each(function(iLigne) {
								if(!$(this).attr('numeroLigne')) {
										$(this).attr('numeroLigne', iLigne);	
								}

								var iColonne=0;

								$(this).find('>td').each(function() {

									if(!$(this).attr('numeroColonne')) {
										$(this).attr('numeroColonne', iColonne);	
									}

									if(!$(this).attr('colspan')) {
										iColonne++;	
									}
									else {
										iColonne+=parseInt($(this).attr('colspan'));
									}

								});

								if($('tr[numeroLigne='+iLigne+'] td[numeroColonne=2]').length) {
									$('tr[numeroLigne='+iLigne+'] td[numeroColonne=2]').after('<td align="right" numeroColonne="2b"></td>');	
								}
								else {
									$('tr[numeroLigne='+iLigne+'] td[numeroColonne=0]').after('<td align="right" numeroColonne="2c"></td>');
								}

								if($('tr[numeroLigne='+iLigne+'] td[numeroColonne=5]').length) {
									$('tr[numeroLigne='+iLigne+'] td[numeroColonne=5]').after('<td align="right" numeroColonne="5b"></td>');	
								}
								else {
									$('tr[numeroLigne='+iLigne+'] td[numeroColonne=0]').after('<td align="right" numeroColonne="5c"></td>');
								}

						});

						// Ajout des libellé de colonne
		         		$('#tablelines .liste_titre > td[numeroColonne=2b]').html('P.U. Devise');
		         		$('#tablelines .liste_titre > td[numeroColonne=5b]').first().html('Total Devise');


		         		// Ajout des prix devisé sur les lignes
	         			<?php
	         			
	         			if(!empty($object->lines)) {
	         				
		         			foreach($object->lines as $line){
								
								if($line->rowid)
									$line->id = $line->rowid;
								
								$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$object->table_element_line." WHERE rowid = ".$line->id);
								$res = $db->fetch_object($resql);
								
								if($line->product_type!=9) {
									
			         				echo "$('#row-".$line->id." td[numeroColonne=2b]').html('".price($res->devise_pu,0,'',1,$conf->global->MAIN_MAX_DECIMALS_UNIT,$conf->global->MAIN_MAX_DECIMALS_UNIT)."');";
									echo "$('#row-".$line->id." td[numeroColonne=5b]').html('".price($res->devise_mt_ligne,0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT)."');";
								}
								
								if($line->error != '') echo "alert('".$line->error."');";
		         			}
							
	         			}
	         			
						?>
					});
			    </script>	
		    	<?php
			}
		}

		elseif(in_array('viewpaiementcard',explode(':',$parameters['context'])) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL)){
			
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$(".liste_titre").find('>td').each(function(i,element){
						switch (i){
							case 0:
								$(element).after('<td align="right"><?php echo $langs->trans('mulicurrency_currency'); ?></td>');
								break;
	
							case 2:
								$(element).after('<td align="right"><?php echo $langs->trans('mulicurrency_payment_amount_currency'); ?></td>');
								break;
							
							case 3:
								$(element).after('<td align="right"><?php echo $langs->trans('mulicurrency_already_paid_currency'); ?></td>');
								break;
								
							case 4:
								$(element).after('<td align="right"><?php echo $langs->trans('mulicurrency_rest_to_pay_currency'); ?></td>');
								break;
						}
					});
				});
			</script>
			<?php
		}
		
		elseif(in_array('pricesuppliercard',explode(':',$parameters['context']))){
			//pre($object); exit;
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('tr .liste_titre:first').before('<td class="liste_titre" align="left">Devise Fournisseur</td>');
				});
			</script>
			<?php
		}
		
		return 0;
	}
	
	/* *********************************************************
	 * Hook uniquement disponible pour les FACTURES fournisseur
	 * *********************************************************/ 
	function formCreateProductSupplierOptions ($parameters, &$object, &$action, $hookmanager){
		
		global $db,$user,$conf;
		if (in_array('invoicesuppliercard',explode(':',$parameters['context']))){
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
			
			if($action != "create"){
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#np_desc').parent().parent().find(' > td[numeroColonne=0]').attr('colspan',$('#np_desc').parent().parent().find(' > td[numeroColonne=0]').attr('colspan')-1);
						$('#np_desc').parent().parent().find(' > td[numeroColonne=2c]').after('<td></td>');
		         		//$('#np_desc').parent().parent().find(' > td[numeroColonne=2c]').html('<input type="text" value="" name="np_pu_devise" size="6">');
						$('#dp_desc').parent().parent().find(' > td[numeroColonne=2b]').html('<input type="text" value="" name="dp_pu_devise" size="6">');
						
						var taux = $('#taux_devise').val();
						$('#idprodfournprice').change( function(){
							$.ajax({
								type: "POST"
								,url: "<?php echo dol_buildpath('/multidevise/script/interface.php',1); ?>"
								,dataType: "json"
								,data: {
									fk_product: $('#idprodfournprice').val(),
									get : "getproductfournprice",
									<?php echo (defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY) ? "taux : taux," : '' ;?>
									json : 1
								}
							},"json").then(function(select){
								/*if(select.price != ""){
									$("input[name=np_pu_devise]").val(select.price * taux.replace(",","."));
									$("input[name=np_pu_devise]").attr('value',select.price * taux.replace(",","."));
								}*/
							});
						});
						
						$('input[name=amount]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") * taux);
							$('input[name=dp_pu_devise]').val(mt);
						});
						
						$('input[name=dp_pu_devise]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") / taux);
							$('input[name=amount]').val(mt);
						});
						
			     	});
			    </script>	
		    	<?php
	    	}
	    }
		return 0;
	}
	
	/* *********************************************************
	 * Hook uniquement disponible pour les COMMANDES fournisseur
	 * *********************************************************/
	function formCreateProductOptions($parameters, &$object, &$action, $hookmanager){
		
		global $db,$user,$conf;
		if (in_array('ordersuppliercard',explode(':',$parameters['context']))){
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
			
			if($action != "create"){
				?>
				<script type="text/javascript">
					$(document).ready(function(){
		         		//$('#np_desc').parent().parent().find(' > td[numeroColonne=2c]').html('<input type="text" value="" name="np_pu_devise" size="6">');
						$('#dp_desc').parent().parent().find(' > td[numeroColonne=2b]').html('<input type="text" value="" name="dp_pu_devise" size="6">');
						
						var taux = $('#taux_devise').val();
						
						$('#idprodfournprice').change( function(){
							$.ajax({
								type: "POST"
								,url: "<?php echo DOL_URL_ROOT; ?>/custom/multidevise/script/interface.php"
								,dataType: "json"
								,data: {
									fk_product: $('#idprodfournprice').val(),
									get : "getproductfournprice",
									<?php echo (defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY) ? "taux : taux," : '' ;?>
									json : 1
								}
								},"json").then(function(select){
									if(select.price != ""){
										<?php
										if(defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY)
											print 'price = Math.round((select.price * taux.replace(",",".") * 100)) / 100;';
										else
											print 'price = select.price * taux.replace(",",".");';
										?>
										
										price = price / $('#qty_predef').val();
										
										/*$("input[name=np_pu_devise]").val(price);
										$("input[name=np_pu_devise]").attr('value',price);*/
									}
								});
						});
						
						$('input[name=pu]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") * taux);
							$('input[name=dp_pu_devise]').val(mt);
						});
						
						$('input[name=dp_pu_devise]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") / taux);
							$('input[name=pu]').val(mt);
						});
			     	});
			    </script>	
		    	<?php
	    	}
	    }
		return 0;
	}

	function formAddObjectLine($parameters, &$object, &$action, $hookmanager){
		
		/*echo "<pre>";
		print_r($parameters);
		echo "</pre>";exit;*/
		
		global $db,$user,$conf;
		if (((in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL))
			|| in_array('ordersuppliercard',explode(':',$parameters['context']))
			|| in_array('invoicesuppliercard',explode(':',$parameters['context']))){
			
			if($action != "create"){
				?>
				<script type="text/javascript">
					$(document).ready(function(){
		         		//$('#np_desc').parent().parent().find(' > td[numeroColonne=2c]').html('<input type="text" value="" name="np_pu_devise" size="6">');
						$('#dp_desc').parent().parent().find(' > td[numeroColonne=2b]').html('<input type="text" value="" name="dp_pu_devise" size="6">');
						
						var taux = $('#taux_devise').val();
						$('#idprod').change( function(){
							$.ajax({
								type: "POST"
								,url: "<?php echo DOL_URL_ROOT; ?>/custom/multidevise/script/interface.php"
								,dataType: "json"
								,data: {
									fk_product: $('#idprod').val(),
									get : "getproductprice",
									json : 1
								}
								},"json").then(function(select){
									/*if(select.price != ""){
										$("input[name=np_pu_devise]").val(select.price * taux.replace(",","."));
										$("input[name=np_pu_devise]").attr('value',select.price * taux.replace(",","."));
									}*/
								});
						});
						
						$('input[name=price_ht]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") * taux);
							$('input[name=dp_pu_devise]').val(mt);
						});
						
						$('input[name=dp_pu_devise]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") / taux);
							$('input[name=price_ht]').val(mt);
						});
						
						$('input#prod_entry_mode_predef, select#idprod, select#select_type').change(function() {
							if($('input#prod_entry_mode_predef').is(':checked')) {
								
								$('input[name=dp_pu_devise]').hide();
								
							}
							else{
								$('input[name=dp_pu_devise]').show();
							}
							
						});
			     	});
			    </script>	
		    	<?php
	    	}
	    }
	    
		return 0;
	}
	
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
		
    	global $db, $user,$conf;
		include_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
		include_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
		
		/*echo '<pre>';
		print_r($_REQUEST);
		echo '</pre>'; exit;*/
		
		if (((in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL))
			|| in_array('ordersuppliercard',explode(':',$parameters['context']))
			|| in_array('invoicesuppliercard',explode(':',$parameters['context']))){
			
			if($action == "editline" || $action == "edit_line"){
				?>
				<script type="text/javascript">
					$(document).ready(function(){
	         			var taux = $('#taux_devise').val();

	         			$('input[name=price_ht], input[name=pu], input[name=puht]').keyup(function(){
							var mt = parseFloat($(this).val().replace(",",".").replace(" ","") * taux);
							$('input[name=dp_pu_devise]').val(mt);
						});
						$('input[name=dp_pu_devise]').ready(function(){
							$('input[name=dp_pu_devise]').keyup(function(){
								var mt = parseFloat($(this).val().replace(",",".").replace(" ","") / taux);
								$('input[name=price_ht], input[name=pu], input[name=puht]').val(mt);
							});
	         			});
	         			$('#price_ht').change(function(){
	         				var pu_devise = parseFloat($('#price_ht').val().replace(",", ".")) * taux;
	         				$('input[name=dp_pu_devise]').val(pu_devise);
	         			});

	         			$('input[name=action]').prev().prev().append('<input type="hidden" value="0" name="pu_devise" size="3">');

						<?php
						foreach($object->lines as $k=>$line){
							
							$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$object->table_element_line." WHERE rowid = ".$line->id);
							$res = $db->fetch_object($resql);
							
							if($line->product_type != 9 && $line->id == (($_REQUEST['lineid']) ? $_REQUEST['lineid'] : $_REQUEST['rowid'])) {
									
								if(in_array('invoicesuppliercard',explode(':',$parameters['context'])) || in_array('ordersuppliercard',explode(':',$parameters['context']))){
									echo "$('input[value=".$line->id."]').parent().parent().find(' > td[numeroColonne=2b]').html('<input type=\"text\" value=\"".price2num($res->devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT)."\" name=\"dp_pu_devise\" size=\"6\">');";
								}
								else{
									echo "$('#line_".$line->id."').parent().parent().find(' > td[numerocolonne=5]').attr('colspan','4'); ";
									echo "$('#line_".$line->id."').parent().parent().find(' > td[numeroColonne=2b]').html('<input type=\"text\" value=\"".price2num($res->devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT)."\" name=\"dp_pu_devise\" size=\"6\">');";
								}

							}
							
							if($line->error != '') echo "alert('".$line->error."');";
	         			}
				        ?>
				        
					});
				</script>
				<?php
			}
			
			$this->resprints='';
		}
        return 0;
    }

	function paymentsupplierinvoices($parameters, &$object, &$action, $hookmanager){
		$this->printObjectLine($parameters, $object, $action, $hookmanager);
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		
		global $db, $user, $conf, $langs;
	
		/*echo '<pre>';
		print_r($object);
		echo '</pre>'; exit;*/
		
		include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
		include_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");
		
		define('INC_FROM_DOLIBARR',true);
		dol_include_once("/multidevise/config.php");
		dol_include_once("/multidevise/class/multidevise.class.php");
		/*
		 * Création de règlements
		 * 
		 */
		
		if((in_array('paiementcard',explode(':',$parameters['context'])) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL)) || in_array('paymentsupplier',explode(':',$parameters['context']))){
			$langs->load('multidevise@multidevise');
			
			if(in_array('paiementcard',explode(':',$parameters['context']))){
				$context = 'paiementcard';
			}
			else{
				$context = 'paymentsupplier';
			}
			
			if($conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE){
				//Récupération du taux en date de la facture
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$("#reyear").attr('onchange','_changedate();');
					});
					
					function _changedate(){
						$.ajax({
							type: "POST"
							,url: "<?php echo DOL_URL_ROOT; ?>/custom/multidevise/script/interface.php"
							,dataType: "json"
							,data: {
								reday: $('#reday').val(),
								remonth : $('#remonth').val(),
								reyear :  $('#reyear').val(),
								socid : $('input[name=socid]').val(),
								context : "<?php echo $context; ?>",
								get : "getpaymentrate",
								json : 1
							}
						},"json").then(function(TFactureRate){
							for(var idFac in TFactureRate){
								$("input[name=amount_"+idFac+"]").closest('tr').children(':eq(3)').html(TFactureRate[idFac]);
								$("input[name=amount_"+idFac+"]").closest('tr').children(':eq(3)').append('<input type="hidden" name="taux_devise" value="'+TFactureRate[idFac]+'">');
							}
						});
					}
				</script>
				<?php
			}
			
			
			if(in_array('paiementcard',explode(':',$parameters['context']))){
				
				if(!defined('MULTIDEVISE_ALREADY_INSERT_PAIEMENT_TITLE')) { // à cause du manque d'un hook dans le 3.6
					define('MULTIDEVISE_ALREADY_INSERT_PAIEMENT_TITLE',1);
				
				
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$('.liste_titre').children().eq(1).after('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_currency'); ?></td>');
						$('.liste_titre').children().eq(2).after('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_current_rate'); ?></td>');
						$('.liste_titre').children().eq(4).after('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_amount_ttc_currency'); ?></td>');
						$('.liste_titre').children().eq(6).after('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_currency_received'); ?></td>');
						$('.liste_titre').children().eq(7).after('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_rest_to_cash_currency'); ?></td>');
						$('.liste_titre > td:last-child').before('<td align="right" ><?php echo $langs->transnoentitiesnoconv('mulicurrency_paid_amount_currency'); ?></td>');
						
						$('tr[class=impair], tr[class=pair]').each(function(){
							$(this).children().eq(1).after('<td align="right" class="devise"></td>');
							$(this).children().eq(2).after('<td align="right" class="taux_devise"></td>');
							$(this).children().eq(4).after('<td align="right" class="ttc_devise"></td>');
							$(this).children().eq(6).after('<td align="right" class="recu_devise"></td>');
							$(this).children().eq(7).after('<td align="right" class="reste_devise"></td>');
							$(this).children().eq(10).after('<td align="right" class="montant_devise"><input type="text" value="" name="devise['+$(this).children().eq(10).children().last().attr('name')+']" size="8"></td>');
						});
						
						$('tr[class=liste_total]').children().eq(0).after('<td align="right" class="total_devise"></td>');
						$('tr[class=liste_total]').children().eq(1).after('<td align="right" class="total_taux_devise"></td>');
						$('tr[class=liste_total]').children().eq(3).after('<td align="right" class="total_ttc_devise"></td>');
						$('tr[class=liste_total]').children().eq(5).after('<td align="right" class="total_recu_devise"></td>');
						$('tr[class=liste_total]').children().eq(6).after('<td align="right" class="total_reste_devise">0</td>');
						$('tr[class=liste_total]').children().eq(9).after('<td align="right" class="total_montant_devise"></td>');
					});
			    </script>
		    	<?php
		    	
		    	}
			
				
				$facture = new Facture($db);
				$facture->fetch($object->facid);
			
				//Récupération des règlements déjà effectué
				$resql = $db->query('SELECT SUM(pf.devise_mt_paiement) as total_paiement
									 FROM '.MAIN_DB_PREFIX.'paiement_facture as pf
									 WHERE pf.fk_facture = '.$facture->id);
				$res = $db->fetch_object($resql);
				$total_recu_devise = ($res->total_paiement) ? $res->total_paiement : $total_recu_devise = "0.00";

				$resql = $db->query('SELECT f.total as total, c.code as code, c.name as name, cr.rate as taux, f.devise_mt_total as total_devise
										   FROM '.MAIN_DB_PREFIX.'facture as f
										    LEFT JOIN '.MAIN_DB_PREFIX.'currency as c ON (c.rowid = f.fk_devise)
										    LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate as cr ON (cr.id_currency = c.rowid)
										   WHERE f.rowid = '.$facture->id.'
										   AND cr.id_entity IN(0, '.(! empty($conf->multicompany->enabled) && ! empty($conf->multicompany->transverse_mode) ? '1,':''). $conf->entity.')
										   ORDER BY cr.dt_sync DESC LIMIT 1');

				$res = $db->fetch_object($resql);

				if($action == "add_paiement"){
					$champ = "remain";
				}
				else{
					$champ = "amount";
				}
			}
			else{
				
				if(!defined('MULTIDEVISE_ALREADY_INSERT_PAIEMENT_TITLE')) { // à cause du manque d'un hook dans le 3.6
					define('MULTIDEVISE_ALREADY_INSERT_PAIEMENT_TITLE',1);
				
					?>
					<script type="text/javascript">
						$(document).ready(function(){
							$('.liste_titre').children().eq(1).after('<td align="right" >Devise</td>');
							$('.liste_titre').children().eq(2).after('<td align="right" >Taux Devise actuel</td>');
							$('.liste_titre').children().eq(5).after('<td align="right" >Montant TTC Devise</td>');
							$('.liste_titre').children().eq(6).after('<td align="right" >Reçu devise</td>');
							$('.liste_titre').children().eq(8).after('<td align="right" >Reste à encaisser devise</td>');
							$('.liste_titre > td:last-child').after('<td align="right" >Montant règlement devise</td>');
							
							$('tr[class=impair], tr[class=pair]').each(function(){
								$(this).children().eq(1).after('<td align="right" class="devise"></td>');
								$(this).children().eq(2).after('<td align="right" class="taux_devise"></td>');
								$(this).children().eq(5).after('<td align="right" class="ttc_devise"></td>');
								$(this).children().eq(6).after('<td align="right" class="recu_devise"></td>');
								$(this).children().eq(8).after('<td align="right" class="reste_devise"></td>');
								$(this).children().eq(11).after('<td align="right" class="montant_devise"><input type="text" value="" name="devise['+$(this).children().eq(11).children().last().attr('name')+']" size="8"></td>');
							});
							
							$('tr[class=liste_total]').children().eq(0).after('<td align="right" class="total_devise"></td>');
							$('tr[class=liste_total]').children().eq(1).after('<td align="right" class="total_taux_devise"></td>');
							$('tr[class=liste_total]').children().eq(2).after('<td align="right" class="total_ttc_devise"></td>');
							$('tr[class=liste_total]').children().eq(3).after('<td align="right" class="total_recu_devise"></td>');
							$('tr[class=liste_total]').children().eq(6).after('<td align="right" class="total_reste_devise">0</td>');
							$('tr[class=liste_total]').children().eq(8).after('<td align="right" class="total_montant_devise"></td>');
						});
				    </script>
			    	<?php
				}
				
				
				$facture = new FactureFournisseur($db);
				$facture->fetch($object->facid);
				
				//Récupération des règlements déjà effectué
				$resql = $db->query('SELECT SUM(pf.devise_mt_paiement) as total_paiement
									 FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn as pf
									 WHERE pf.fk_facturefourn = '.$facture->id);
				$res = $db->fetch_object($resql);
				$total_recu_devise = ($res->total_paiement) ? $res->total_paiement : $total_recu_devise = "0.00";

				$resql = $db->query('SELECT f.total_ttc as total, c.code as code, c.name as name, cr.rate as taux, f.devise_mt_total as total_devise
										   FROM '.MAIN_DB_PREFIX.'facture_fourn as f
										    LEFT JOIN '.MAIN_DB_PREFIX.'currency as c ON (c.rowid = f.fk_devise)
										    LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate as cr ON (cr.id_currency = c.rowid)
										   WHERE f.rowid = '.$facture->id.'
										   AND cr.id_entity = '.$conf->entity.'
										   ORDER BY cr.dt_sync DESC LIMIT 1');

				$res = $db->fetch_object($resql);

				$champ = "amount";
				$champ2 = "remain";
			}
			
			//Récupération du taux en date de règlement si conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE
			$devise_taux = $res->taux;
			if($conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE){
				$devise_taux = TMultidevise::_setCurrencyRate($db, $facture, $res->code,1);
			}
			
			if($res->code){
				?>
				<script type="text/javascript">
					function number_format (montant,type){
						$.ajax({
							async : false
							,type: "POST"
							,url: "<?php echo dol_buildpath('/multidevise/script/interface.php',1); ?>"
							,dataType: "json"
							,data: {
								montant : montant,
								type : type,
								get : "numberformat",
								json : 1
							}
						}).done(function(val){
							if(val != null) resmontant = val['montant'];
							else resmontant = 0;
						});

						return (type == 'price2num') ? parseFloat(resmontant) : resmontant;
					}

					$(document).ready(function(){

						<?php
						if(!empty($_REQUEST['devise'])){
							foreach($_REQUEST['devise'] as $id_input => $mt_devise){
								$id_input = str_replace("remain", "amount", $id_input);
								echo "$('input[name=\"devise[".$id_input."]\"]').attr('value','".price2num($mt_devise,'MT')."').attr('disabled','disabled');";
								echo "$('input[name=\"devise[".$id_input."]\"]').parent().append('<input type=\"hidden\" value=\"".price2num($mt_devise,'MT')."\" name=\"devise[".$id_input."]\" />');";
							}
						}
						
						?>
						
						ligne = $('input[name=<?php echo $champ."_".$facture->id; ?>]').parent().parent();
						$(ligne).find('> td[class=devise]').append('<?php echo $res->name.' ('.$res->code.')'; ?>');
						$(ligne).find('> td[class=taux_devise]').append('<?php echo price($devise_taux); ?>');
						$(ligne).find('> td[class=taux_devise]').append('<input type="hidden" value="<?php echo $devise_taux; ?>" name="taux_devise" />');
						$(ligne).find('> td[class=recu_devise]').append('<?php echo price($total_recu_devise,'MT'); ?>');
						$(ligne).find('> td[class=ttc_devise]').append('<?php echo price(round($res->total_devise,$conf->global->MAIN_MAX_DECIMALS_TOT),'MT'); ?>');
						$(ligne).find('> td[class=reste_devise]').append('<?php echo price(round(($res->total_devise) - $total_recu_devise,$conf->global->MAIN_MAX_DECIMALS_TOT),'MT'); ?>');

						if($('td[class=total_reste_devise]').length > 0){

							$('td[class=total_recu_devise]').html(number_format($('td[class=total_recu_devise]').val() + <?php echo price2num($total_recu_devise,'MT'); ?>,'price'));

							total_reste_devise = number_format($('td[class=total_reste_devise]').html(),'price2num');

							$('td[class=total_reste_devise]').html(number_format(total_reste_devise + <?php echo price2num(($facture->total_ttc * $devise_taux) - $total_recu_devise,'MT'); ?>,'price'));
						}
						
						//Modification du montant règlement devise
						$("#payment_form").find("input[name*=\"devise[<?php echo $champ."_".$facture->id; ?>\"]").blur(function() {
							$('.button').hide();
							
							total = 0;
							
							$("#payment_form").find("input[name*=\"devise[<?php echo $champ."_".$facture->id; ?>\"]").each(function(){
								if( $(this).val() != "") total = total + number_format($(this).val(),'price2num');
							});
							
							if($('td[class=total_reste_devise]').length > 0){ 
								$('td[class=total_montant_devise]').empty();
								$('td[class=total_montant_devise]').html(number_format(total,'price'));
							}

							mt_devise = number_format($(this).val(),'price2num');
							mt_devise = mt_devise / parseFloat($(ligne).find('> td[class=taux_devise]').find('> input[name=taux_devise]').val());

							$(this).parent().prev().find('> input[type=text]').val(number_format(mt_devise,'price'));
							
							$('.button').show();
						});
						
						
						//Modification du montant règlement
						$("#payment_form").find("input[name*=\"<?php echo $champ."_".$facture->id; ?>\"]").blur(function() {
							
							$('.button').hide();
							
							mt_rglt = number_format($(this).val(),'price2num');
							mt_rglt = mt_rglt * $(ligne).find('> td[class=taux_devise]').find('> input[name=taux_devise]').val();
							
							$(this).parent().next().find('> input[type=text]').val(number_format(mt_rglt,'price'));
							
							$('.button').show();
						});
					});
				</script>
				<?php
			}
		}

		/*
		 * Fiche règlement
		 * 
		 */	
		elseif(in_array('viewpaiementcard',explode(':',$parameters['context'])) && empty($conf->global->MULTIDEVISE_DONT_USE_ON_SELL)){

			//Cas facture fournisseur
			if($object->ref_supplier){
				$resql = $db->query('SELECT pf.devise_taux, pf.devise_mt_paiement, pf.devise_code, f.devise_mt_total, pf.devise_taux
									 FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn as pf
									 	LEFT JOIN '.MAIN_DB_PREFIX.'facture_fourn as f On (f.rowid = pf.fk_facturefourn)
									 WHERE pf.fk_paiementfourn = '.$_REQUEST['id'].' AND f.rowid = '.$object->rowid);

				$facture = new FactureFournisseur($db);
				$facture->fetch($object->rowid);
				
				$object->facid = $object->rowid;
			}
			else{ //cas facture client
				$resql = $db->query('SELECT pf.devise_taux, pf.devise_mt_paiement, pf.devise_code, f.devise_mt_total, pf.devise_taux
									 FROM '.MAIN_DB_PREFIX.'paiement_facture as pf
									 	LEFT JOIN '.MAIN_DB_PREFIX.'facture as f On (f.rowid = pf.fk_facture)
									 WHERE pf.fk_paiement = '.$_REQUEST['id'].' AND f.rowid = '.$object->facid);
				$facture = new Facture($db);
				$facture->fetch($object->facid);
			}
			$res = $db->fetch_object($resql);
			?>
			<script type="text/javascript">
			
				$('#row-<?php echo $object->facid; ?>').find('>td').each(function(i,element){
					switch (i){
						case 0:
							$(element).after('<td align="right"><?php echo $res->devise_code;?></td>');
							break;

						case 2:
							$(element).after('<td align="right"><?php echo price(round($res->devise_mt_total + ($facture->total_tva * $res->devise_taux),$conf->global->MAIN_MAX_DECIMALS_TOT),'MT');?></td>');
							break;
						
						case 3:
							$(element).after('<td align="right"><?php echo $res->devise_mt_paiement;?></td>');
							break;

						case 4:
							$(element).after('<td align="right"><?php echo round($res->devise_mt_total + ($facture->total_tva * $res->devise_taux) - $res->devise_mt_paiement,$conf->global->MAIN_MAX_DECIMALS_TOT);?></td>');
							break;
					}
				});
			</script>
			<?php
		}

		/*
		 * Affichage des ligne commande et facture fournisseur
		 * 
		 */
		elseif(in_array('ordersuppliercard',explode(':',$parameters['context']))
			|| in_array('invoicesuppliercard',explode(':',$parameters['context']))){
					
				
			
			if(DOL_VERSION>3.5) {
				$idLine = $parameters['line']->id;	
				$tableElement = $object->table_element_line;
			}
			else{
				$idLine = $object->id;
				$tableElement = $object->table_element;
			}
			
			$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tableElement." 
			WHERE rowid = ".$idLine);
			$res = $db->fetch_object($resql);
			
			?>
			<script type="text/javascript">
			<?php
			
			if($object->product_type!=9) {
				echo "$('#row-".$object->id." td[numeroColonne=2b]').html('".price($res->devise_pu,0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT)."');";
				echo "$('#row-".$object->id." td[numeroColonne=5b]').html('".price($res->devise_mt_ligne,0,'',1,$conf->global->MAIN_MAX_DECIMALS_TOT,$conf->global->MAIN_MAX_DECIMALS_TOT)."');";
			}
			
			?>
			</script>
			<?php
			if($line->error != '') echo "alert('".$line->error."');";

		}
		elseif(in_array('pricesuppliercard',explode(':',$parameters['context']))){
			
			$id_pdf = __val($parameters['id_pfp'], $object->product_fourn_price_id);
			
			$resql = $db->query("SELECT s.devise_code 
								 FROM ".MAIN_DB_PREFIX."societe as s
									LEFT JOIN ".MAIN_DB_PREFIX."product_fournisseur_price as pfp ON (pfp.fk_soc = s.rowid)								 
								 WHERE pfp.rowid = ".(int)$id_pdf);
								 
			$res = $db->fetch_object($resql);
			
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('#row-<?php echo $object->product_fourn_price_id; ?>').find('>td:first').after('<td align="left"><?php echo $res->devise_code; ?></td>');
				});
			</script>
			<?php
		}

		return 0;
	}
}
