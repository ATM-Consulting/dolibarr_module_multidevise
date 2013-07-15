<?php
class ActionsMultidevise
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
    
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {
		/*echo '<pre>';
		print_r($object);
		echo '</pre>';*/
    	global $db, $user,$conf;
		include_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
		include_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
		
		if (in_array('thirdpartycard',explode(':',$parameters['context']))
			|| in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))){
			
			if(in_array('thirdpartycard',explode(':',$parameters['context'])))
				$table = "societe";
			if(in_array('propalcard',explode(':',$parameters['context'])))
				$table = "propal";
			if(in_array('ordercard',explode(':',$parameters['context'])))
				$table = "commande";
			if(in_array('invoicecard',explode(':',$parameters['context'])))
				$table = "facture";
			
	    	//VIEW
	    	if($action == "view" || $action == "" || $action == "addline" || $action == "editline"){
	    		$sql = 'SELECT fk_devise, devise_code';
	    		$sql .= ($table != "societe") ? ', devise_taux, devise_mt_total' : "";
	    		$sql .= ' FROM '.MAIN_DB_PREFIX.$table.' WHERE rowid = '.$object->id;
				
	    		$resql = $db->query($sql);
				$res = $db->fetch_object($resql);
				if($res->fk_devise && !is_null($res->devise_code)){
					print '<tr><td>Devise</td><td colspan="3">';
					print currency_name($res->devise_code,1);
					print ' ('.$res->devise_code.')</td></tr>';
					if($table != "societe"){
						print '<tr><td>Taux Devise</td><td colspan="3">'.$res->devise_taux.'</td></tr>';
						print '<tr><td>Montant Devise</td><td colspan="3">'.$res->devise_mt_total.'</td></tr>';
					}
				}
				else{
					print '<tr><td>Devise</td><td colspan="3">';
					print currency_name($conf->currency,1);
					print ' ('.$conf->currency.')</td></tr>';
					if($table != "societe"){
						print '<tr><td>Taux Devise</td><td colspan="3"></td></tr>';
						print '<tr><td>Montant Devise</td><td colspan="3"></td></tr>';
					}
				}
	    	}
	    	//EDIT
	    	elseif($action == "edit" || $action == "create"){
	    		$sql = 'SELECT fk_devise, devise_code';
	    		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.$_REQUEST['socid'];
				
	    		$resql = $db->query($sql);
				$res = $db->fetch_object($resql);
				if($res->fk_devise && !is_null($res->devise_code)){
					$form=new Form($db);
					print '<tr><td>Devise</td><td>';
					print $form->select_currency($res->devise_code,"currency");
					print '</td></tr>';
				}
				else{
					$form=new Form($db);
					print '<tr><td>Devise</td><td colspan="3">';
					print $form->select_currency($conf->currency,"currency");
					print '</td></tr>';
				}
	    	}
		}
		return 0;
	}  
    
	
	
	
	function formAddObjectLine($parameters, &$object, &$action, $hookmanager){
		
		global $db,$user,$conf;
		if (in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))){
			
			if(in_array('propalcard',explode(':',$parameters['context']))){
				$table = "propal";
				$tabledet = "propaldet";
			}
			if(in_array('ordercard',explode(':',$parameters['context']))){
				$table = "commande";
				$tabledet = "commandedet";
			}
			if(in_array('invoicecard',explode(':',$parameters['context']))){
				$table = "facture";
				$tabledet = "facturedet";
			}
			
			if($action != "create"){
				?>
				<script type="text/javascript">
					<?php
					foreach($object->lines as $line){
	         				$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet." WHERE rowid = ".$line->rowid);
							$res = $db->fetch_object($resql);
	         				echo "$('#row-".$line->rowid."').children().eq(2).after('<td class=\"nowrap\" align=\"right\">".$res->devise_pu."</td>');";
							echo "$('#row-".$line->rowid."').children().eq(6).after('<td class=\"nowrap\" align=\"right\">".$res->devise_mt_ligne."</td>');";
							if($line->error != '') echo "alert('".$line->error."');";
	         			}
					?>
					$('#tablelines .liste_titre > td').each(function(){
		         		if($(this).html() == "Qté")
		         			$(this).before('<td align="right" width="140">P.U. Devise</td>');
		         		if($(this).html() == "Total HT")
		         			$(this).after('<td align="right" width="140">Total Devise</td>');
	         		});
	         		$('#np_desc').parent().after('<td align="right"><input type="text" value="" name="np_pu_devise" size="6"></td>');
					$('#dp_desc').parent().next().next().after('<td align="right"><input type="text" value="" name="dp_pu_devise" size="6"></td>');
					$('input[name=addline]').parent().attr('colspan','5');
					$('.tabBar td').each(function(){
						if($(this).html() == "Taux Devise"){
							taux = $(this).next().html();
						}
					});
					$('#idprod').change( function(){
						$.ajax({
							type: "POST"
							,url: "<?=DOL_URL_ROOT; ?>/custom/multidevise/script/ajax.getproductprice.php"
							,dataType: "json"
							,data: {fk_product: $('#idprod').val()}
							},"json").then(function(select){
								if(select.price != ""){
									$("input[name=np_pu_devise]").val(select.price * taux);
									$("input[name=np_pu_devise]").attr('value',select.price * taux);
									$('input[name=pu_devise_product]').val(select.price * taux);
								}
							});
					});
					$('input[name=price_ht]').blur(function(){
						$(this).parent().next().children().val($(this).val() * taux);
						$(this).parent().next().children().attr('value',$(this).val() * taux);
						$('input[name=pu_devise_libre]').val($(this).val() * taux);
					})
					$('#addpredefinedproduct').append('<input type="hidden" value="0" name="pu_devise_product" size="3">');
		         	$('#addproduct').append('<input type="hidden" value="0" name="pu_devise_libre" size="3">');
		         	$('imput[name=dp_pu_devise]').change(function() {
		         		$('input[name=pu_devise_libre]').val($('imput[name=dp_pu_devise]').val() );	
		         	});
		         	$('imput[name=np_pu_devise]').change(function() {
		         		$('input[name=pu_devise_product]').val($('imput[name=np_pu_devise]').val() );
		         	});
			    </script>	
		    	<?php
	    	}
	    }
		return 0;
	}
	
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
    	/*ini_set('dysplay_errors','On');
			error_reporting(E_ALL); */
    	global $db, $user,$conf;
		include_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
		include_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
		
		if (in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('ordercard',explode(':',$parameters['context']))
			|| in_array('invoicecard',explode(':',$parameters['context']))){
			
			if(in_array('propalcard',explode(':',$parameters['context']))){
				$table = "propal";
				$tabledet = "propaldet";
			}
			if(in_array('ordercard',explode(':',$parameters['context']))){
				$table = "commande";
				$tabledet = "commandedet";
			}
			if(in_array('invoicecard',explode(':',$parameters['context']))){
				$table = "facture";
				$tabledet = "facturedet";
			}
			
			if($action == "editline"){
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#tablelines .liste_titre > td').each(function(){
			         		if($(this).html() == "Qté")
			         			$(this).before('<td align="right" width="140">P.U. Devise</td>');
			         		if($(this).html() == "Total HT")
			         			$(this).after('<td align="right" width="140">Total Devise</td>');
	         			});
						<?php
						foreach($object->lines as $line){
	         				$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet." WHERE rowid = ".$line->rowid);
							$res = $db->fetch_object($resql);
							
							if($line->rowid == $_REQUEST['lineid']){
								?>
								$('#product_desc').parent().next().next().after('<td align="right"><input type="text" value="<?php echo $res->devisqe_pu; ?>" name="dp_pu_devise" size="6"></td>');
								$('#product_desc').parent().next().next().next().next().next().after('<td align="right"></td>');
								<?php
							}
							else{
								echo "$('#row-".$line->rowid."').children().eq(2).after('<td class=\"nowrap\" align=\"right\">".$res->devise_pu."</td>');";
								echo "$('#row-".$line->rowid."').children().eq(6).after('<td class=\"nowrap\" align=\"right\">".$res->devise_mt_ligne."</td>');";
								if($line->error != '') echo "alert('".$line->error."');";
							}
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
}