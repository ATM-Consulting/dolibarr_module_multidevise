<?php
class TMultidevise{
	
	static function doActionsMultidevise(&$parameters, &$object, &$action, &$hookmanager) {
		global $langs, $db, $conf, $user;

//ini_set('display_errors',1);
//error_reporting(E_ALL);
//print "la";		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('expeditioncard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))
			|| in_array('ordersuppliercard',explode(':',$parameters['context'])) || in_array('invoicesuppliercard',explode(':',$parameters['context']))){

        	if ($action == 'builddoc')
			{
				
				// 1 - Dans le haut du document
				$devise_change = false;
				//Modification des prix si la devise est différente
				if(!in_array('expeditioncard',explode(':',$parameters['context']))){
					
					$resl = $db->query('SELECT devise_code FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id);
					$res = $db->fetch_object($resl);
					$last_devise = 0;
					
					if($res){
						
						if($conf->currency != $res->devise_code){
							$last_devise = $conf->currency;
							$conf->currency  = $res->devise_code;
							$devise_change = true;
						}
					}
				}
				
				// 2 - Dans les lignes
				foreach($object->lines as $line){
					
					//Modification des montant si la devise a changé
					if($devise_change){
						
						$resl = $db->query('SELECT devise_pu, devise_mt_ligne FROM '.MAIN_DB_PREFIX.$object->table_element_line.' WHERE rowid = '.(($line->rowid) ? $line->rowid : $line->id) );
						$res = $db->fetch_object($resl);

						if($res){
							$line->tva_tx = 0;
							$line->subprice = round($res->devise_pu,2);
							$line->price = round($res->devise_pu,2);
							$line->pu_ht = round($res->devise_pu,2);
							$line->total_ht = round($res->devise_mt_ligne,2);
							$line->total_ttc = round($res->devise_mt_ligne,2);
							$line->total_tva = 0;
						}
					}
				}
				
				// 3 - Dans le bas du document
				//Modification des TOTAUX si la devise a changé
				if($devise_change){
					
					$resl = $db->query('SELECT devise_mt_total FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id);
					$res = $db->fetch_object($resl);

					if($res){
						
						$object->total_ht = round($res->devise_mt_total,2);
						$object->total_ttc = round($res->devise_mt_total,2);
						$object->total_tva = 0;
					}
				}
				
				//Si le module est actif sans module spécifique client alors on reproduit la génération standard dolibarr sinon on retourne l'objet modifié
				if(!$conf->global->USE_SPECIFIC_CLIENT){
					//exit($object->element);	
					// ***********************************************
					// On reproduis le traitement standard de dolibarr
					// ***********************************************
					
					if (GETPOST('model'))
					{
						$object->setDocModel($user, GETPOST('model'));
					}
					
					// Define output language
					$outputlangs = $langs;
					if (! empty($conf->global->MAIN_MULTILANGS))
					{
						$outputlangs = new Translate("",$conf);
						$newlang=(GETPOST('lang_id') ? GETPOST('lang_id') : $object->client->default_lang);
						$outputlangs->setDefaultLang($newlang);
					}
					
					switch ($object->element) {
						case 'propal':
							$result= propale_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							break;
						case 'facture':
							$result= facture_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							break;
						case 'commande':
							$result= commande_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							break;
						case 'shipping':
							$result= expedition_pdf_create($db, $object, $object->modelpdf, $outputlangs);
							break;
						case 'delivery':
							$result= delivery_order_pdf_create($db, $object, $object->modelpdf, $outputlangs);
							break;
						case 'order_supplier':
							$result= supplier_order_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							break;
						case 'invoice_supplier':
							$result= supplier_invoice_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							//echo $result; exit;
							break;

						default:
							
							break;
					}
					
					if ($result <= 0)
					{
						dol_print_error($db,$result);
						exit;
					}
					elseif(!in_array('ordercard',explode(':',$parameters['context'])))
					{
						header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.(empty($conf->global->MAIN_JUMP_TAG)?'':'#builddoc'));
						exit;
					}
				}
				
				//Devise retrouve ça valeur d'origine
				if($last_devise != $conf->currency && $last_devise != 0)
					$conf->currency = $last_devise;
			}
		}
	}
	
	static function getTableByAction($action) {
		
		switch ($action) {
				case 'LINEORDER_UPDATE':
				case 'LINEORDER_INSERT':
				case 'LINEORDER_DELETE':
				case 'ORDER_CREATE':
					$element = "commande";
					$element_line = "commandedet";
					$fk_element = "fk_commande";
					break;
				case 'LINEPROPAL_UPDATE':
				case 'LINEPROPAL_INSERT':
				case 'LINEPROPAL_DELETE':
				case 'PROPAL_CREATE':
					$element = "propal";
					$element_line = "propaldet";
					$fk_element = "fk_propal";
					break;
				case 'LINEBILL_INSERT':
				case 'LINEBILL_DELETE':
				case 'LINEBILL_UPDATE':
				case 'BILL_CREATE':
					$element = "facture";
					$element_line = "facturedet";
					$fk_element = "fk_facture";
					break;
				case 'LINEORDER_SUPPLIER_UPDATE':
				case 'LINEORDER_SUPPLIER_CREATE':
				case 'ORDER_SUPPLIER_CREATE':
					$element = "commande_fournisseur";
					$element_line = "commande_fournisseurdet";
					$fk_element = "fk_commande";
					break;
				case 'LINEBILL_SUPPLIER_UPDATE':
				case 'LINEBILL_SUPPLIER_CREATE':
				case 'BILL_SUPPLIER_CREATE':
					$element = "facture_fourn";
					$element_line = "facture_fourn_det";
					$fk_element = "fk_facture_fourn";
					break;
		}
		
		return array($element, $element_line, $fk_element);
	}
	
	static function deleteLine(&$db, &$object, $action, $id, $lineid) {
		global $conf;
		
		if ($action == 'LINEORDER_SUPPLIER_DELETE' || $action == 'LINEBILL_SUPPLIER_DELETE') {
			
			//Obligé puisque dans le cas d'une suppresion le trigger est appelé avant et non après
			$object->deleteline($lineid, TRUE); // TODO est si on echappe simplement la ligne dans ce qui suit
			$db->commit();
			
			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne 
				    FROM '.MAIN_DB_PREFIX.$object->table_element_line.' 
				    WHERE '.$object->fk_element.' = '.$id;

			$resql = $db->query($sql);
			$res = $db->fetch_object($resql);
			
			$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' 
			SET devise_mt_total = '.(($res->total_ligne > 0 ) ? $res->total_ligne : 0 /* Si y a 0, on met 0 sinon c'est pas sûr hein */)." 
			WHERE rowid = ".(($object->{'fk_'.$object->table_element}) ? $object->{'fk_'.$object->table_element} : $id )); // TODO c'est la même chose qu'en dessous non ?
			
		}
		else {
			list($parent_object) = TMultidevise::getTableByAction($action);
		
			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne 
				    FROM '.MAIN_DB_PREFIX.$object->table_element.' 
				    WHERE fk_'.$parent_object.' = '.$object->{"fk_".$parent_object};
			
			$resql = $db->query($sql);
			$res = $db->fetch_object($resql);
			
			$db->query('UPDATE '.MAIN_DB_PREFIX.$parent_object.' 
					SET devise_mt_total = '.(($res->total_ligne > 0 ) ? $res->total_ligne : "0")." 
					WHERE rowid = ".(($object->{'fk_'.$parent_object}) ? $object->{'fk_'.$parent_object} : $id ));
			
		}
			
			
		
	}
	
	static function getTableByOrigin(&$object, $origin = '') {
		
		if(empty($origin)) $origin = $object->origin;
		
		if($origin == "propal"){
			$table_origin = "propal";
			$tabledet_origin = "propaldet";
			$originid = $object->origin_id;
    	}
		elseif($origin == "commande"){
			$table_origin = "commande";
			$tabledet_origin = "commandedet";
			$originid = $object->origin_id;
		}
		elseif($origin == "order_supplier"){
			$table_origin = "commande_fournisseur";
			$tabledet_origin = "commande_fournisseurdet";
			$originid = $object->origin_id;
		}
		
		return array($table_origin, $tabledet_origin, $originid);
		
	}

	static function createDoc(&$db, &$object,$currency,$origin) {
	global $conf;
			if($currency){
				$resql = $db->query('SELECT c.rowid AS rowid, c.code AS code, cr.rate AS rate
									 FROM '.MAIN_DB_PREFIX.'currency AS c LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate AS cr ON (cr.id_currency = c.rowid)
									 WHERE c.code = "'.$currency.'" AND cr.id_entity = '.$conf->entity.' ORDER BY cr.dt_sync DESC LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET fk_devise = '.$res->rowid.', devise_code = "'.$res->code.'", devise_taux = '.$res->rate.' WHERE rowid = '.$object->id);
				}
				
			}
			
			//Création a partir d'un objet d'origine (propale ou commande)
			if(!empty($origin) && !empty($object->origin_id)){
				
				list($table_origin, $tabledet_origin, $originid) = TMultidevise::getTableByOrigin($object, $origin);
				
				$resql = $db->query("SELECT devise_mt_total FROM ".MAIN_DB_PREFIX.$table_origin." WHERE rowid = ".$originid);
				$res = $db->fetch_object($resql);
				$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '.$res->devise_mt_total.' WHERE rowid = '.$object->id);
			
			}
		
	}

	static function insertLine(&$db, &$object,&$user, $action, $origin, $originid, $dp_pu_devise,$idProd,$quantity,$quantity_predef,$remise_percent,$idprodfournprice,$fournprice,$buyingprice) {
		
		global $conf;
		
		list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);
			
		if($action == 'LINEBILL_INSERT'){
			$object->update($user,true);
		}
		elseif($action != 'LINEORDER_SUPPLIER_CREATE'){
			$object->update(true);
		}
		else{
			$db->commit();
		}
		//Création a partir d'un objet d'origine (propale,commande client ou commande fournisseur)
		if($origin && $originid){

			if($origin == "propal" && !empty($originid))$originidpropal = $originid; // cas propal c'est l'idpropal qui est là;

			list($table_origin, $tabledet_origin, $originid) = TMultidevise::getTableByOrigin($object, $origin);
			if($origin == "propal" && empty($originid)){
				$propal = new Propal($db);
				$propal->fetch($originidpropal);
				
				foreach($propal->lines as $line){
					
					if($line->rang == $object->rang) {
						$originid = $line->rowid;
					}
				}	
			}
			
			
			if($object->origin == 'shipping'){
				$db->commit();
				$db->commit();
				$db->commit(); // J'ai été obligé mais je sais pas pourquoi // TODO AA beh savoir pourquoi et me virer cette merde

				$resql = $db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$object->fk_facture);
				$res = $db->fetch_object($resql);
				$devise_taux = __val($res->devise_taux,1);

				$db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
				
			}
			else{
				
				//Pas de liaison ligne origine => ligne destination pour la création de facture fourn depuis commande fourn donc on improvise
				if($object->origin == 'order_supplier'){
					
					$db->commit();
					$db->commit();
					$db->commit(); // J'ai été obligé mais je sais pas pourquoi 
					
					$commande_fourn_origine = new CommandeFournisseur($db);
					$commande_fourn_origine->fetch($object->origin_id);
					
					$object->fetch_lines();
					
					$keys = array_keys($object->lines);
					$last_key = $keys[count($keys)-1];
					
					$originid = $commande_fourn_origine->lines[$last_key]->id;
				}
				
				$resql = $db->query("SELECT devise_pu, devise_mt_ligne 
									FROM ".MAIN_DB_PREFIX.$tabledet_origin." 
									WHERE rowid = ".$originid);
				$res = $db->fetch_object($resql);

				$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' 
							SET devise_pu = '.$res->devise_pu.', devise_mt_ligne = '.$res->devise_mt_ligne.' 
							WHERE rowid = '.$object->rowid); //TODO check id si rowid vide

			}
			
			
		}
		else{
			/* ***************************
			 *	Création standard
			 * ***************************/ 
			
			//Ligne de produit/service existant
			if($idProd>0 && empty($dp_pu_devise)){
					
				$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".(($object->{"fk_".$element})? $object->{"fk_".$element} : $object->id) ;
				
                $resql = $db->query($sql);
                $res = $db->fetch_object($resql);
				$devise_taux = __val($res->devise_taux,1);
				
				//obligatoire sur partie achat car c'est l'objet parent et non l'object ligne qui est transmis au trigger
				if($action == 'LINEORDER_SUPPLIER_CREATE'){
					$ligne = new CommandeFournisseurLigne($db);
					$ligne->fetch($object->rowid);
					$object_last = $object;
					$object = $ligne;
				}
				elseif($action == 'LINEBILL_SUPPLIER_CREATE'){
					$ligne = new ProductFournisseur($db);
					$ligne->fetch_product_fournisseur_price($idprodfournprice);
					$object->subprice = $ligne->fourn_price;
					//$object = $ligne;
				}
				
				//Cas ou le prix de référence est dans la devise fournisseur et non dans la devise du dolibarr
				if(defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY && ($action == 'LINEORDER_SUPPLIER_CREATE' || $action == 'LINEBILL_SUPPLIER_CREATE')){
					$devise_pu = $object->subprice;
					$object->subprice = $devise_pu / $devise_taux;
					$subprice = $object->subprice;
				}
				else{
					$subprice = $object->subprice;
					$devise_pu = !empty($object->devise_pu) ? $object->devise_pu : $object->subprice * $devise_taux;
				}
				
				$devise_pu = round($devise_pu,2);
				$devise_mt_ligne = $devise_pu * (($object->qty) ? $object->qty : $quantity_predef);
//print $devise_mt_ligne;exit;
				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' 
						SET devise_pu = '.$devise_pu.'
						, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $remise_percent) / 100))).' 
						WHERE rowid = '.$object->rowid;
//exit($sql);
				$db->query($sql);
				
				list($object->total_ht, $object->total_tva, $object->total_ttc) = calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, 0, 0, 0, 'HT', $object->info_bits, $object->fk_product_type);
				
				if($action == 'LINEORDER_SUPPLIER_CREATE'){
					$ligne = new CommandeFournisseurLigne($db);
					$ligne->fetch($object->rowid);
					$object = $ligne;
				}
				
				//obligatoire sur partie achat car c'est l'objet parent et non l'object ligne qui est transmis au trigger
				//on reprends l'object parent car l'objet ligne ne possède pas de méthode update
				if($action == 'LINEORDER_SUPPLIER_CREATE'){
					$object = $object_last;
				}
				
				// Marge
				if ($conf->margin->enabled && $user->rights->margins->creer && defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY){			
					
				//exit($fournprice);	
					if($fournprice) {
//exit("1");
						$object->pa_ht = price($this->_getMarge($fournprice, $buyingprice));
						$object->fk_fournprice = 0; //mise a zero obligatoire sinon affiche le prix fournisseur non modifé
					}
				}
				
				if(get_class($object)=='CommandeFournisseur') {
					$object->updateline($object->rowid, $ligne->desc, $subprice, $ligne->qty, $ligne->remise_percent, $ligne->tva_tx,0,0,'HT',0, 0, true);
				}
				elseif(defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY && $action == 'LINEBILL_SUPPLIER_CREATE'){
					$object->updateline($object->rowid, $ligne->description, $object->subprice, $ligne->tva_tx,0,0,$_REQUEST['qty'],$ligne->product_id,'HT',0,0,0,true);
				}
				else {
					$object->update(1);
				}
				
			}
			//Ligne libre
			elseif($idProd==0 && $dp_pu_devise){
				
				$devise_pu = round(price2num($dp_pu_devise) ,2);
				
				$devise_mt_ligne = $devise_pu * $quantity;
				
				$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
				
			}
			
			$sql = 'SELECT SUM(f.devise_mt_ligne) as total_devise 
					FROM '.MAIN_DB_PREFIX.$element_line.' as f LEFT JOIN '.MAIN_DB_PREFIX.$element.' as m ON (f.'.$fk_element.' = m.rowid)';
			
			//MAJ du total devise de la commande/facture/propale
			if($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEBILL_INSERT'){
				$sql .= 'WHERE m.rowid = '.$object->{'fk_'.$element};
			}
			else{
				$sql .= 'WHERE m.rowid = '.$object->id;
			}
			
			$resql = $db->query($sql);
			$res = $db->fetch_object($resql);

			$db->query('UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id) );
		}
		
		
	}
	static function updateLine(&$db, &$object,&$user, $action,$id_line,$remise_percent, $devise_taux=0, $fk_parent=0) {
		global $conf;
		
			list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);
			if($action == 'LINEBILL_UPDATE'){
				$object->update($user,true);
			}
			elseif($action != 'LINEORDER_SUPPLIER_UPDATE' && $action!='LINEORDER_SUPPLIER_CREATE' && $action!='ORDER_SUPPLIER_CREATE'){
				$object->update(true);
			}
			
			if(empty($fk_parent)){
				if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE'){
					$fk_parent = isset($object->oldline->{"fk_".$element}) ? $object->oldline->{"fk_".$element} : $object->{"fk_".$element};
				}
				else{
					$fk_parent = $object->id;
				}
				
			}	
					
			if(empty($devise_taux)) {
				
				$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".$fk_parent;
	
	            $resql = $db->query($sql);
	            $res = $db->fetch_object($resql);
	            $devise_taux = __val($res->devise_taux,1);
				
			}
			

			if($object->origin == "shipping"){
					$db->commit();
					$db->commit();
					$db->commit();

					$db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet 
									SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' 
									WHERE rowid = '.$object->rowid);
					
			}
			elseif($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE'){
				$pu_devise = $object->subprice * $devise_taux;
			    $pu_devise = !empty($object->device_pu) ? $object->device_pu : $object->subprice * $devise_taux;

				$pu_devise = round($pu_devise,2);

				$devise_mt_ligne = $pu_devise * $object->qty;

				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' 
							SET devise_pu = '.$pu_devise.'
							, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $remise_percent) / 100))).' 
							WHERE rowid = '.$object->rowid;

				$db->query($sql);
								
				list($object->total_ht, $object->total_tva, $object->total_ttc)=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, 0, 0, 0, 'HT', $object->info_bits, $object->fk_product_type);
				  
				$db->query($sql);
			}
			else{
				if($action == 'LINEORDER_SUPPLIER_UPDATE' || $action=='LINEORDER_SUPPLIER_CREATE' || $action=='ORDER_SUPPLIER_CREATE'){
					$sql = "SELECT subprice, qty, remise FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$id_line;
				}
				else{
					$sql = "SELECT pu_ht as subprice, qty, remise_percent as remise FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$id_line;
				}
		
				$resql = $db->query($sql);
	            $res = $db->fetch_object($resql);

				$pu_devise = $res->subprice * $devise_taux;
				
				$pu_devise = round($pu_devise,2);
				
				$devise_mt_ligne = $pu_devise * $res->qty;
				
				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' 
							SET devise_pu = '.$pu_devise.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($res->remise / 100))).' 
							WHERE rowid = '.$id_line;
				$db->query($sql);
				
				
			}

			//MAJ du total devise de la commande/facture/propale
			$resql = $db->query('SELECT SUM(f.devise_mt_ligne) as total_devise 
									   FROM '.MAIN_DB_PREFIX.$element_line.' as f LEFT JOIN '.MAIN_DB_PREFIX.$element.' as m ON (f.'.$fk_element.' = m.rowid)
									   WHERE m.rowid = '.$fk_parent);
			
			$res = $db->fetch_object($resql);
			$db->query('UPDATE '.MAIN_DB_PREFIX.$element.' 
						SET devise_mt_total = '.$res->total_devise." 
						WHERE rowid = ".$fk_parent);
		
	}
	
	/*
	 * Mise à jour de la devise du client
	 */
	static function updateCompany(&$db,&$object, $currency) {
		
			if($currency){
				$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'currency WHERE code = "'.$currency.'" LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.'societe SET fk_devise = '.$res->rowid.', devise_code = "'.$currency.'" WHERE rowid = '.$object->id);
				}
			}
		
	}
}


class TMultideviseClient extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'societe');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseProductPrice extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'product_price');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_code','type=chaine;');
		parent::add_champs('devise_price','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}


class TMultidevisePropal extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'propal');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePropaldet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'propaldet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseFacture extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facture');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}
class TMultideviseFacturedet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facturedet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommande extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commande');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommandedet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commandedet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommandeFournisseur extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commande_fournisseur');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommandeFournisseurdet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commande_fournisseurdet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseFactureFournisseur extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facture_fourn');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseFactureFournisseurdet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facture_fourn_det');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePaiementFacture extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'paiement_facture');
		parent::add_champs('devise_taux,devise_mt_paiement,devise_mt_paiement','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePaiementFactureFournisseur extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'paiementfourn_facturefourn');
		parent::add_champs('devise_taux,devise_mt_paiement,devise_mt_paiement','type=float;');
		parent::add_champs('devise_code','type=chaine;');
		
		parent::_init_vars();
		parent::start();
	}
}
