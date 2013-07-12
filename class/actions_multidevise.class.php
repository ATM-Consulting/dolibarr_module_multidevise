<?php
class ActionsMultidevise
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
    	/*ini_set('dysplay_errors','On');
			error_reporting(E_ALL); */
    	/*global $db;
		include_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
		include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
		include_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");
		
    	if (in_array('propalcard',explode(':',$parameters['context'])) || in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context'])))
        {
        	
			/*
			 * AJOUT DU CHAMPS POIDS
			 */
        	/*if(in_array('propalcard',explode(':',$parameters['context']))){
        		$instance = new Propal($db);
				$instance->fetch($_GET['id']);
				$table = "propaldet";
        	}
			elseif(in_array('ordercard',explode(':',$parameters['context']))){
				$instance = new Commande($db);
				$instance->fetch($_GET['id']);
				$table = "commandedet";
			}
        	elseif(in_array('invoicecard',explode(':',$parameters['context']))){
        		$instance = new Facture($db);
				$instance->fetch($_GET['id']);
				$table = "facturedet";
        	}
			
			if($action == "editline"){
				
				?>
				<script type="text/javascript">
					$(document).ready(function(){
						<?php
						foreach($instance->lines as $line){
	         				$resql = $db->query("SELECT tarif_poids, poids FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".$line->rowid);
							$res = $db->fetch_object($resql);
							switch($res->poids){
								case -6:
									$unite = "mg";
									break;
								case -3:
									$unite = "g";
									break;
								case 0:
									$unite = "kg";
									break;
							}
							
							if($line->rowid == $_REQUEST['lineid']){
								?>
								$('input[name=qty]').parent().after('<td align="right"><input id="poidsAff" type="text" value="<?php if(!is_null($res->tarif_poids)) echo $res->tarif_poids; ?>" name="poidsAff" size="6"><select class="flat" name="weight_unitsAff" id="weight_unitsAff"><option value="-6" <?php if($unite == "mg") echo ' selected="selected" '; ?>>mg</option><option value="-3" <?php if($unite == "g") echo ' selected="selected" '; ?>>g</option><option value="0" <?php if($unite == "kg") echo ' selected="selected" '; ?>>kg</option></select></td>');
								$('#tablelines').children().first().children().first().children().last().prev().prev().prev().prev().prev().after('<td align=\"right\" width=\"100\">Poids</td>');
								$('input[name=token]').prev().append('<input id="poids" type="hidden" value="0" name="poids" size="3">');
					         	$('input[name=token]').prev().append('<input id="weight_units" type="hidden" value="0" name="weight_units" size="3">');
					         	$('#savelinebutton').click(function() {
					         		$('#poids').val( $('#poidsAff').val() );
					         		$('#weight_units').val( $('#weight_unitsAff option:selected').val() );
					         		return true;
					         	});
								<?php
							}
							else{
								echo "$('#row-".$line->rowid."').children().last().prev().prev().prev().prev().prev().after('<td align=\"right\">".((!is_null($res->tarif_poids))?number_format($res->tarif_poids,2)." ".$unite:"")."</td>');";
							}
				        }
						?>
					});
				</script>
				<?php
			}
			
			$this->resprints='';
		}
        return 0;*/
    }


	function formBuilddocOptions ($parameters, &$object, &$action, $hookmanager) {
		
		/*global $db;
		include_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
		include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
		include_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");
		include_once(DOL_DOCUMENT_ROOT."/core/lib/functions.lib.php");
		
		if (in_array('propalcard',explode(':',$parameters['context'])) || in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	if(in_array('propalcard',explode(':',$parameters['context']))){
        		$instance = new Propal($db);
	        	$instance->fetch($_GET['id']);
				$table = "propaldet";
        	}
			elseif(in_array('ordercard',explode(':',$parameters['context']))){
				$instance = new Commande($db);
	        	$instance->fetch($_GET['id']);
				$table = "commandedet";
			}
        	elseif(in_array('invoicecard',explode(':',$parameters['context']))){
        		$instance = new Facture($db);
	        	$instance->fetch((isset($_GET['facid']))?$_GET['facid']:$_GET['id']);
				$table = "facturedet";
        	}
			//echo count($instance->lines);
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';*/
			
			/*if($object->line->error)
				dol_htmloutput_mesg($object->line->error,'', 'error');
        	?>
         	<script type="text/javascript">
         		<?php
         			//echo (count($instance->lines) >0)? "$('#tablelines').children().first().children().first().children().last().prev().prev().prev().prev().prev().after('<td align=\"right\" width=\"50\">Poids</td>');" : '' ;
         			foreach($instance->lines as $line){
         				$resql = $db->query("SELECT tarif_poids, poids FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".$line->rowid);
						$res = $db->fetch_object($resql);
						switch($res->poids){
							case -6:
								$unite = "mg";
								break;
							case -3:
								$unite = "g";
								break;
							case 0:
								$unite = "kg";
								break;
						}
         				echo "$('#row-".$line->rowid."').children().eq(3).after('<td align=\"right\">".((!is_null($res->tarif_poids))? number_format($res->tarif_poids,2)." ".$unite : "")."</td>');";
						if($line->error != '') echo "alert('".$line->error."');";
         			}
         		?>
	         	$('#tablelines .liste_titre > td').each(function(){
	         		if($(this).html() == "Qté")
	         			$(this).after('<td align="right" width="140">Poids</td>');
	         	});
	         	$('#np_desc').parent().next().after('<td align="right"><input class="poidsAff" type="text" value="0" name="poidsAff" size="6"><select class="flat weight_unitsAff" name="weight_unitsAff"><option value="-6">mg</option><option value="-3">g</option><option selected="selected" value="0">kg</option></select></td>');
	         	$('#dp_desc').parent().next().next().next().after('<td align="right"><input class="poidsAff" type="text" value="0" name="poidsAff" size="6"><select class="flat weight_unitsAff" name="weight_unitsAff"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>');
	         	$('#addpredefinedproduct').append('<input class="poids" type="hidden" value="0" name="poids" size="3">');
	         	$('#addpredefinedproduct').append('<input class="weight_units" type="hidden" value="0" name="weight_units" size="3">');
	         	$('#addproduct').append('<input class="poids" type="hidden" value="0" name="poids" size="3">');
	         	$('#addproduct').append('<input class="weight_units" type="hidden" value="0" name="weight_units" size="3">');
	         	$('input[name=addline]').click(function() {
	         		$('.poids').val( $(this).parent().prev().prev().find('> .poidsAff').val() );
	         		$('.weight_units').val( $(this).parent().prev().prev().find('> .weight_unitsAff option:selected').val() );
	         		return true;
	         	});
	         	
	         	//Sélection automatique de l'unité de mesure associé au produit sélectionné
	         	$('#idprod').change( function(){
					$.ajax({
						type: "POST"
						,url: "<?=DOL_URL_ROOT; ?>/custom/tarif/script/ajax.unite_poids.php"
						,dataType: "json"
						,data: {fk_product: $('#idprod').val()}
						},"json").then(function(select){
							if(select.unite != ""){
								$('.weight_unitsAff:last option:selected').removeAttr('selected');
								$('.weight_unitsAff:last option[value='+select.unite+']').attr('selected','selected');
							}
							else{
								$('.weight_unitsAff:last option:selected').removeAttr('selected');
								$('.weight_unitsAff:last option[value=0]').attr('selected','selected');
							}
						});
				});
         	</script>
         	<?php
        }

		return 0;*/
	}
}