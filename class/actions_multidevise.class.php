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
			
	    	//EDIT
	    	if($action == "edit" || $action == "create"){
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
			elseif(!in_array('thirdpartycard',explode(':',$parameters['context']))){
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
						print '<tr><td>Montant Devise</td><td colspan="3">'.price2num($res->devise_mt_total,'MT').'</td></tr>';
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
		         				$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$table."det WHERE rowid = ".$line->rowid);
								$res = $db->fetch_object($resql);
		         				echo "$('#row-".$line->rowid."').children().eq(2).after('<td class=\"nowrap\" align=\"right\">".$res->devise_pu."</td>');";
								echo "$('#row-".$line->rowid."').children().eq(6).after('<td class=\"nowrap\" align=\"right\">".price2num($res->devise_mt_ligne,'MT')."</td>');";
								if($line->error != '') echo "alert('".$line->error."');";
		         			}
						?>
					});
			    </script>	
		    	<?php
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
					$(document).ready(function(){
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
						$('input[name=price_ht]').keyup(function(){
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
			     	});
			    </script>	
		    	<?php
	    	}
	    }
	    
		if(in_array('paiementcard',explode(':',$parameters['context']))){
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('.liste_titre').children().eq(1).after('<td align="right" >Devise</td>');
					$('.liste_titre').children().eq(2).after('<td align="right" >Taux Devise actuel</td>');
					$('.liste_titre').children().eq(5).after('<td align="right" >Reçu devise</td>');
					$('.liste_titre').children().eq(7).after('<td align="right" >Reste à encaisser devise</td>');
					$('.liste_titre > td:last-child').before('<td align="right" >Montant règlement devise</td>');
					$('tr[class=impair], tr[class=pair]').each(function(){
						$(this).children().eq(1).after('<td align="right" class="devise"></td>');
						$(this).children().eq(2).after('<td align="right" class="taux_devise"></td>');
						$(this).children().eq(5).after('<td align="right" class="recu_devise"></td>');
						$(this).children().eq(7).after('<td align="right" class="reste_devise"></td>');
						$(this).children().eq(9).after('<td align="right" class="montant_devise"><input type="text" value="" name="devise['+$(this).children().eq(9).children().attr('name')+']" size="8"></td>');
					});
					$('tr[class=liste_total]').children().eq(0).after('<td align="right" class="total_devise"></td>');
					$('tr[class=liste_total]').children().eq(1).after('<td align="right" class="total_taux_devise"></td>');
					$('tr[class=liste_total]').children().eq(4).after('<td align="right" class="total_recu_devise"></td>');
					$('tr[class=liste_total]').children().eq(6).after('<td align="right" class="total_reste_devise">0</td>');
					$('tr[class=liste_total]').children().eq(8).after('<td align="right" class="total_montant_devise"></td>');
				});
		    </script>
	    	<?php
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
	         			$('.tabBar td').each(function(){
							if($(this).html() == "Taux Devise"){
								taux = $(this).next().html();
							}
						});
	         			$('#price_ht').keyup(function(){
	         				$('input[name=dp_pu_devise]').val($('#price_ht').val() * taux);
	         				$('input[name=pu_devise]').val($('#price_ht').val() * taux);
	         			});
	         			$('input[name=action]').prev().prev().append('<input type="hidden" value="0" name="pu_devise" size="3">');
						<?php
						foreach($object->lines as $line){
	         				$resql = $db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet." WHERE rowid = ".$line->rowid);
							$res = $db->fetch_object($resql);
							
							if($line->rowid == $_REQUEST['lineid']){
								echo "$('#product_desc').parent().next().next().after('<td align=\"right\"><input type=\"text\" value=\"".$res->devise_pu."\" name=\"dp_pu_devise\" size=\"6\"></td>');";
								echo "$('input[name=pu_devise]').val(".$res->devise_pu.");";
								echo "$('#product_desc').parent().next().next().next().next().next().after('<td align=\"right\"></td>');";
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

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		
		/*echo '<pre>';
		print_r($object);
		echo '</pre>';*/
		
		global $db, $user, $conf;
		include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
				
		if(in_array('paiementcard',explode(':',$parameters['context']))){
			$facture = new Facture($db);
			$facture->fetch($object->facid);
			
			//Récupération des règlements déjà effectué
			$resql = $db->query('SELECT SUM(p.devise_mt_paiement) as total_paiement
								 FROM '.MAIN_DB_PREFIX.'paiement as p
								 	LEFT JOIN '.MAIN_DB_PREFIX.'paiement_facture as pf ON (pf.fk_paiement = p.rowid)
								 WHERE pf.fk_facture = '.$facture->id);
			$res = $db->fetch_object($resql);
			$total_recu_devise = ($res->total_paiement) ? $res->total_paiement : $total_recu_devise = "0,00";
			
			$resql = $db->query('SELECT f.total as total, c.code as code, c.name as name, cr.rate as taux, f.devise_mt_total as total_devise
									   FROM '.MAIN_DB_PREFIX.'facture as f
									    LEFT JOIN '.MAIN_DB_PREFIX.'currency as c ON (c.rowid = f.fk_devise)
									    LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate as cr ON (cr.id_currency = c.rowid)
									   WHERE f.rowid = '.$facture->id.'
									   ORDER BY cr.dt_sync DESC LIMIT 1');
			
			$res = $db->fetch_object($resql);
			if($res->code){
				?>
				<script type="text/javascript">
					function number_format (number, decimals, dec_point, thousands_sep) {
					  number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
					  var n = !isFinite(+number) ? 0 : +number,
					    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
					    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
					    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
					    s = '',
					    toFixedFix = function (n, prec) {
					      var k = Math.pow(10, prec);
					      return '' + Math.round(n * k) / k;
					    };
					  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
					  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
					  if (s[0].length > 3) {
					    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
					  }
					  if ((s[1] || '').length < prec) {
					    s[1] = s[1] || '';
					    s[1] += new Array(prec - s[1].length + 1).join('0');
					  }
					  return s.join(dec);
					}

					$(document).ready(function(){
						ligne = $('input[name=remain_<?php echo $facture->id; ?>]').parent().parent();
						$(ligne).find('> td[class=devise]').append('<?php echo $res->name.' ('.$res->code.')'; ?>');
						$(ligne).find('> td[class=taux_devise]').append('<?php echo $res->taux; ?>');
						$(ligne).find('> td[class=recu_devise]').append('<?php echo $total_recu_devise; ?>');
						$(ligne).find('> td[class=reste_devise]').append('<?php echo price2num($res->total_devise - $total_recu_devise,'MT'); ?>');
						
						if($('td[class=total_reste_devise]').length > 0){
							$('td[class=total_recu_devise]').html($('td[class=total_recu_devise]').val() + <?php echo $total_recu_devise; ?>);
							total_reste_devise = $('td[class=total_reste_devise]').html();
							$('td[class=total_reste_devise]').html(parseFloat(total_reste_devise.replace(',','.')) + <?php echo price2num($res->total_devise - $total_recu_devise,'MT'); ?>);
						}
						
						$("#payment_form").find("input[name*=\"devise[remain_\"]").keyup(function() {
							total = 0;
							$("#payment_form").find("input[name*=\"devise[remain_\"]").each(function(){
								if( $(this).val() != "") total += parseFloat($(this).val().replace(',','.'));
							});
							if($('td[class=total_reste_devise]').length > 0) $('td[class=total_montant_devise]').html(total);
							mt_devise = parseFloat($(this).val().replace(',','.'));
							$(this).parent().prev().find('> input[type=text]').val(number_format(mt_devise / <?php echo $res->taux; ?>,2,',','')).keyup();
						});
					});
				</script>
				<?php
			}

			if($action == 'add_paiement'){
				?>
				<script type="text/javascript">

					$(document).ready(function(){
						ligne = $('input[name=remain_<?php echo $facture->id; ?>]').parent().parent();
						$(ligne).find('> td[class=devise]').append('<?php echo $res->name.' ('.$res->code.')'; ?>');
						$(ligne).find('> td[class=taux_devise]').append('<?php echo $res->taux; ?>');
						$(ligne).find('> td[class=recu_devise]').append('<?php echo $total_recu_devise; ?>');
						$(ligne).find('> td[class=reste_devise]').append('<?php echo price2num($res->total_devise - $total_recu_devise,'MT'); ?>');
						
						if($('td[class=total_reste_devise]').length > 0){
							$('td[class=total_recu_devise]').html($('td[class=total_recu_devise]').val() + <?php echo $total_recu_devise; ?>);
							total_reste_devise = $('td[class=total_reste_devise]').html();
							$('td[class=total_reste_devise]').html(parseFloat(total_reste_devise.replace(',','.')) + <?php echo price2num($res->total_devise - $total_recu_devise,'MT'); ?>);
						}
						
						$("#payment_form").find("input[name*=\"devise[remain_\"]").keyup(function() {
							total = 0;
							$("#payment_form").find("input[name*=\"devise[remain_\"]").each(function(){
								if( $(this).val() != "") total += parseFloat($(this).val().replace(',','.'));
							});
							if($('td[class=total_reste_devise]').length > 0) $('td[class=total_montant_devise]').html(total);
							mt_devise = parseFloat($(this).val().replace(',','.'));
							$(this).parent().prev().find('> input[type=text]').val(number_format(mt_devise / <?php echo $res->taux; ?>,2,',','')).keyup();
						});
					});
				</script>
				<?php
			}
		}
		return 0;
	}
}