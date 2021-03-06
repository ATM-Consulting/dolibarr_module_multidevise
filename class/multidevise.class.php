<?php
class TMultidevise{

	static function doActionsMultidevise(&$parameters, &$object, &$action, &$hookmanager) {
		global $langs, $db, $conf, $user;


		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('expeditioncard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))
			|| in_array('ordersuppliercard',explode(':',$parameters['context'])) || in_array('invoicesuppliercard',explode(':',$parameters['context']))){

			 null; // AA  après avoir supprimé les commentaire... beh vlà quoi
		}
	}
	static function getActionByTable($table) {

		switch($table) {
			case 'commande':
				return 'LINEORDER_UPDATE';
				break;

			case 'propal':
				return 'LINEPROPAL_UPDATE';
				break;

			case 'facture':
				return 'LINEBILL_UPDATE';
				break;

			case 'commande_fournisseur':
				return 'LINEORDER_SUPPLIER_UPDATE';
				break;

			case 'facture_fourn':
				return 'LINEBILL_SUPPLIER_UPDATE';
				break;

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
				case 'PAYMENT_CUSTOMER_CREATE':
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
		global  $user, $conf;

		if ($action === 'LINEORDER_SUPPLIER_DELETE' || $action === 'LINEBILL_SUPPLIER_DELETE') {

			//Obligé puisque dans le cas d'une suppresion le trigger est appelé avant et non après
			if (get_class($object) == 'SupplierInvoiceLine')
			{
				$facturefourn = new FactureFournisseur($db);
				$facturefourn->fetch(GETPOST('id'));
				$facturefourn->deleteline($object->id, TRUE);

				$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne
				    FROM '.MAIN_DB_PREFIX.$facturefourn->table_element_line.'
				    WHERE '.$facturefourn->fk_element.' = '.$id;
			}
			else
			{
				$object->deleteline($lineid, TRUE); // TODO est si on echappe simplement la ligne dans ce qui suit

				$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne
				    FROM '.MAIN_DB_PREFIX.$object->table_element_line.'
				    WHERE '.$object->fk_element.' = '.$id;
			}
			$db->commit();

			$resql = $db->query($sql);
			if ($resql && ($res = $db->fetch_object($resql)))
			{
				// WARNING : sur la suppression d'une ligne de facture fourn, l'objet donné est SupplierInvoiceLine (l'objet enfant) sauf qu'on souhaite modifier le montant du document (donc la facture fourn)
				$objToUse = (get_class($object) == 'SupplierInvoiceLine') ? $facturefourn : $object;

				$db->query('UPDATE '.MAIN_DB_PREFIX.$objToUse->table_element.'
				SET devise_mt_total = '.(($res->total_ligne != 0 ) ? $res->total_ligne : 0 /* Si y a 0, on met 0 sinon c'est pas sûr hein */)."
				WHERE rowid = ".(($objToUse->{'fk_'.$objToUse->table_element}) ? $objToUse->{'fk_'.$object->table_element} : $id )); // TODO c'est la même chose qu'en dessous non ?
			}

		}
		else {
			list($parent_object) = TMultidevise::getTableByAction($action);

			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne
				    FROM '.MAIN_DB_PREFIX.$object->table_element.'
				    WHERE fk_'.$parent_object.' = '.$object->{"fk_".$parent_object};


			if($action === 'LINEBILL_DELETE' && (! empty($object->id) || ! empty($object->rowid))) { // Trigger LINEBILL_DELETE appelé avant suppression, et pas de $notrigger dans FactureLigne::delete() : on filtre la ligne à supprimer
				$idLigne = ! empty($object->id) ? $object->id : $object->rowid;
				$sql .= " AND rowid != ".$idLigne;
			}

			$resql = $db->query($sql);

			if ($resql) {
				$res = $db->fetch_object($resql);

				$db->query('UPDATE '.MAIN_DB_PREFIX.$parent_object.'
						SET devise_mt_total = '.(($res->total_ligne != 0 ) ? $res->total_ligne : "0")."
						WHERE rowid = ".(($object->{'fk_'.$parent_object}) ? $object->{'fk_'.$parent_object} : $id ));
			}
		}
	}

	static function getTableByOrigin(&$object, $origin = '') {
		global  $user, $conf,$db;

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
		elseif($origin == "shipping"){
			$table_origin = "commande";
			$tabledet_origin = "commandedet";
			$object->fetchObjectLinked();

			//Dans le cas ou on créé automatiquement la facture depuis l'expédition, origin et origin_id sont en $_REQUEST et non directement dans l'objet
			if(empty($object->origin) && empty($object->origin_id)){
				$shipping = new Expedition($db);
				$shipping->fetch(GETPOST('originid'));
				$shipping->fetch_origin();
			}

			//pre($object,true);

			//Dans le cas de création depuis facture, c'est l'objet ligne qui est passé et non l'objet facture donc on prend le lien dans l'objet shipping
			$originid = (get_class($object) == 'Facture') ? $object->linkedObjects['shipping'][0]->origin_id : $shipping->origin_id;
			//echo $originid.'<br>';
		}

		return array($table_origin, $tabledet_origin, $originid);

	}
	static function getElementCurrency($element,$object,$useDefaultAttrId=false,$field='') {
		global $db, $user, $conf;

		if(empty($field)) $field = 'rowid';
		if ($useDefaultAttrId) $resql = $db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".$object->{$field});
		else $resql = $db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".$object->{'fk_'.$element});
		$res = $db->fetch_object($resql);
		$devise_taux = __val($res->devise_taux,1);
		if ($devise_taux == 0) $devise_taux = 1;

		return $devise_taux;
	}
	static function getThirdCurrency($socid) {

		global $db, $user, $conf;

		$sql = 'SELECT fk_devise
				FROM '.MAIN_DB_PREFIX.'societe
				WHERE rowid = '.$socid;

		$resql = $db->query($sql);
		$res = $db->fetch_object($resql);

		if($res->fk_devise > 0) {
			$sql = 'SELECT code
					FROM '.MAIN_DB_PREFIX.'currency
					WHERE rowid = '.$res->fk_devise;

			$resql = $db->query($sql);
			$res = $db->fetch_object($sql);
			$code_currency = $res->code;
		}

		return $code_currency;

	}

	static function getDocumentCurrency(&$object) {

		global  $user, $conf, $db;

		list($table_origin, $tabledet_origin, $originid) = TMultidevise::getTableByOrigin($object);

		if(empty($originid))return false;

		$sql = 'SELECT fk_devise
				FROM '.MAIN_DB_PREFIX.$table_origin.'
				WHERE rowid = '.$originid;

		$resql = $db->query($sql);
		$res = $db->fetch_object($resql);

		$code_currency=false;
		if($res->fk_devise > 0) {
			$sql = 'SELECT code
					FROM '.MAIN_DB_PREFIX.'currency
					WHERE rowid = '.$res->fk_devise;

			$resql = $db->query($sql);
			$res = $db->fetch_object($sql);
			$code_currency = $res->code;
		}

		return $code_currency;

	}

	static function createDoc(&$db, &$object,$currency,$origin) {
		global  $user, $conf;

		if($currency){
			//var_dump($currency);
			TMultidevise::_setCurrencyRate($db,$object,$currency);
		//	exit;
		}

		//Création a partir d'un objet d'origine (propale ou commande)
		if(!empty($origin) && !empty($object->origin_id)){

			list($table_origin, $tabledet_origin, $originid) = TMultidevise::getTableByOrigin($object, $origin);

			if (!empty($_REQUEST['valuedeposit']) && $_REQUEST['typedeposit'] == 'amount') {
				$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '. $_REQUEST['valuedeposit'] .' WHERE rowid = '.$object->id);
			} else {
				$originid = ($originid) ? $originid : $object->origin_id;
				$resql = $db->query("SELECT devise_mt_total FROM ".MAIN_DB_PREFIX.$table_origin." WHERE rowid = ".$originid);
				$res = $db->fetch_object($resql);

				$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '.$res->devise_mt_total.' WHERE rowid = '.$object->id);
			}
		}

	}

	static function _setCurrencyRate(&$db,&$object,$currency,$get=0){
		global  $user, $conf;
		//pre($object,true);
		$devise_taux_origin=false;
		$multidevise_use_rate=false;

		if($conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE){
			$sql = 'SELECT c.rowid AS rowid, c.code AS code, cr.rate AS rate
					 FROM '.MAIN_DB_PREFIX.'currency AS c LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate AS cr ON (cr.id_currency = c.rowid)
					 WHERE c.code = "'.$currency.'"
					 	AND cr.id_entity  =  '.$conf->entity.'
					  	AND cr.date_cre LIKE "'.date('Y-m-d',($object->date) ? $object->date : time()).'%"
					 ORDER BY cr.dt_sync DESC LIMIT 1';

			$resql = $db->query($sql);

			if($db->num_rows($resql) > 0){
				$multidevise_use_rate = true;
			}
		}
		elseif ($conf->global->MULTIDEVISE_USE_ORIGIN_TX)
		{
			if ($object->origin_id > 0)
			{
				if ($object->origin == 'commande') $table_cmd = 'commande';
				elseif ($object->origin == 'order_supplier') $table_cmd = 'commande_fournisseur';
				elseif ($object->origin == 'propal') $table_cmd = 'propal';
				else $table_cmd = '';

				if (!empty($table_cmd))
				{
					$sql = 'SELECT devise_taux FROM '.MAIN_DB_PREFIX.$table_cmd.' WHERE rowid = '.$object->origin_id;
					$resql = $db->query($sql);
					if ($resql && ($row = $db->fetch_object($resql)))
					{
						$devise_taux_origin = $row->devise_taux;
					}
				}

			}
		}

		if(!$multidevise_use_rate){

			$sql = 'SELECT c.rowid AS rowid, c.code AS code, '.($devise_taux_origin === false ? 'cr.rate' : $devise_taux_origin).' AS rate
					 FROM '.MAIN_DB_PREFIX.'currency AS c LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate AS cr ON (cr.id_currency = c.rowid)
					 WHERE c.code = "'.$currency.'"
					 AND cr.id_entity = '.$conf->entity.' ORDER BY cr.dt_sync DESC LIMIT 1';


			//echo $sql."<br>";exit;
			$resql = $db->query($sql);
		}

		if($res = $db->fetch_object($resql)){

			$rowid = $res->rowid;
			$code = $res->code;
			$rate = $res->rate;

			if($get){ // TODO créer fonction GET
				return $res->rate;
			}
		}

		$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET fk_devise = '.$rowid.', devise_code = "'.$code.'", devise_taux = '.$rate.' WHERE rowid = '.$object->id);
	}

	static function insertLine(&$db, &$object,&$user, $action, $origin, $originid, $dp_pu_devise,$idProd,$quantity,$quantity_predef,$remise_percent,$idprodfournprice,$fournprice,$buyingprice) {

		global  $user, $conf;

		// TODO replace by updateLine

		list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);

		if($action == 'LINEBILL_INSERT'){
			$object->update($user,true);
		}
		elseif($action != 'LINEORDER_SUPPLIER_CREATE'){
			if(empty($object->entity)) $object->entity = $conf->entity;
			$object->fetch($object->id);
			$object->update($user,true);
		}
		else{
			$db->commit();
		}

		//echo $origin." ".$originid.'<br>';
//var_dump($object);
		//Création a partir d'un objet d'origine (propale,commande client ou commande fournisseur)

		if($origin && $originid){

//	var_dump($origin, $originid);
			if ($origin == 'commande' && !empty($originid)) $originidcommande = $originid;
			if ($origin == "propal" && !empty($originid)) $originidpropal = $originid; // cas propal c'est l'idpropal qui est là;

			list($table_origin, $tabledet_origin, $originid) = TMultidevise::getTableByOrigin($object, $origin);

			//echo $table_origin." ".$tabledet_origin." ".$originid;exit;

			if($origin == "propal" && empty($originid)){
				$propal = new Propal($db);
				$propal->fetch($originidpropal);

				foreach($propal->lines as $line){

					if($line->rang == $object->rang) {
						$originid = $line->rowid;
					}
				}
			}

			if ($origin == 'commande' && empty($originid)) {
				$commande = new Commande($db);
				$commande->fetch($originidcommande);

				foreach ($commande->lines as $line) {
					if ($line->rang == $object->rang) {
						$originid = $line->rowid;
					}
				}
			}


			if (!empty($_REQUEST['valuedeposit']) && $_REQUEST['typedeposit'] == 'amount') {
				if ($origin == 'commande') {
					$resql = $db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = " . $object->fk_facture);
					$res = $db->fetch_object($resql);

					$devise_taux = __val($res->devise_taux, 1);
					if ($devise_taux == 0) $devise_taux = 1;

					//On part du principe que le montant acompte est dans la devise du client et non celle de Dolibarr
					$object->pu_ht = $object->subprice = $object->subprice / $devise_taux;
					$object->total_ht = round($object->pu_ht * $object->qty,$conf->global->MAIN_MAX_DECIMALS_TOT);
					$object->total_ttc = $object->total_ht * (1 + ( $object->tva_tx / 100));
					$id_line = $object->rowid;
					//echo $object->pu_ht;exit;

					TMultidevise::updateLine($db, $object, $user, $action, $id_line, $remise_percent);

					//$db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
				}
			} else if ($_REQUEST['typedeposit'] == 'variable') {
				if ($origin == 'commande') {
					$valuedeposit = 100;
					if(!empty($_REQUEST['valuedeposit'])) $valuedeposit = $_REQUEST['valuedeposit'];

					$resql = $db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = " . $object->fk_facture);
					$res = $db->fetch_object($resql);

					$devise_taux = __val($res->devise_taux, 1);
					if ($devise_taux == 0) $devise_taux = 1;

					//On part du principe que le montant acompte est dans la devise du client et non celle de Dolibarr
					$object->pu_ht = $object->subprice = ($object->subprice / $devise_taux);
					$object->total_ht = round($object->pu_ht * $object->qty,$conf->global->MAIN_MAX_DECIMALS_TOT);
					$object->total_ttc = $object->total_ht * (1 + ( $object->tva_tx / 100));
					$id_line = $object->rowid;
					//echo $object->pu_ht;exit;

					//pre($object,true);exit;

					TMultidevise::updateLine($db, $object, $user, $action, $id_line, $remise_percent);

					//MAJ du total devise de la commande/facture/propale
					$sql = 'SELECT SUM(f.devise_mt_ligne) as total_devise
					FROM '.MAIN_DB_PREFIX.$element_line.' as f LEFT JOIN '.MAIN_DB_PREFIX.$element.' as m ON (f.'.$fk_element.' = m.rowid)';

					if($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEBILL_INSERT'){
						$sql .= 'WHERE m.rowid = '.$object->{'fk_'.$element};
					}
					else{
						$sql .= 'WHERE m.rowid = '.$object->id;
					}

					$resql = $db->query($sql);
					$res = $db->fetch_object($resql);

					$db->query('UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id) );

					//$db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
				}

			} else if($origin == 'shipping'){
				$db->commit();
				$db->commit();
				$db->commit(); // J'ai été obligé mais je sais pas pourquoi // TODO AA beh savoir pourquoi et me virer cette merde

				// Récupération du prix devise de la ligne de commande correspondant à la ligne d'expédition, pour remultiplier par la quantité
				// car il est possible que l'expédition soit partielle, et pour prise en compte de la remise

				if($object->origin == 'commande') {
                                        $sql = "SELECT cdet.devise_pu, cdet.devise_mt_ligne
                                                FROM ".MAIN_DB_PREFIX."commandedet cdet
                                                WHERE cdet.rowid = ".$object->origin_id;

				}
				else {

					$sql = "SELECT cdet.devise_pu, cdet.devise_mt_ligne
						FROM ".MAIN_DB_PREFIX.$tabledet_origin." cdet
						LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet edet ON (edet.fk_origin_line = cdet.rowid)
						WHERE edet.rowid = ".$object->origin_id;
						//pre($_REQUEST,true);
						dol_syslog('[PHF] = '.$sql);
				}

				$resql = $db->query($sql);
				$res = $db->fetch_object($resql);

				$devise_pu = $res->devise_pu;
				$devise_mt_ligne = $devise_pu * $object->qty * (100 - $object->remise_percent) / 100;

                $sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT).', devise_mt_ligne = '.round($devise_mt_ligne,$conf->global->MAIN_MAX_DECIMALS_TOT).' WHERE rowid = '.$object->rowid;
				$db->query($sql);

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

				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id);
				$db->query( $sql );

			}
			else{

				//Pas de liaison ligne origine => ligne destination pour la création de facture fourn depuis commande fourn donc on improvise
				if($object->origin == 'order_supplier'){

					$db->commit();
					$db->commit();
					$db->commit(); // J'ai été obligé mais je sais pas pourquoi

					if(!empty($object->origin_line_id)){
						$originid = $object->origin_line_id;
					}
					else{
						$commande_fourn_origine = new CommandeFournisseur($db);
						$commande_fourn_origine->fetch($object->origin_id);

						$object->fetch_lines();

						$keys = array_keys($object->lines);
						$last_key = $keys[count($keys)-1];

						$originid = $commande_fourn_origine->lines[$last_key]->id;
					}
				}

				$resql = $db->query("SELECT devise_pu, devise_mt_ligne
									FROM ".MAIN_DB_PREFIX.$tabledet_origin."
									WHERE rowid = ".$originid);
				$res = $db->fetch_object($resql);

				$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.'
							SET devise_pu = '.$res->devise_pu.', devise_mt_ligne = '.$res->devise_mt_ligne.'
							WHERE rowid = '.(($object->rowid) ? $object->rowid : $object->id )); //TODO check id si rowid vide

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
		else{
			/* ***************************
			 *	Création standard
			 * ***************************/

			//Ligne de produit/service existant
			if($idProd>0 && empty($dp_pu_devise)){

				$sql = "SELECT devise_code, devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".(($object->{"fk_".$element})? $object->{"fk_".$element} : $object->id) ;

		                $resql = $db->query($sql);
                		$res = $db->fetch_object($resql);
				$devise_taux = __val($res->devise_taux,1);
				if ($devise_taux == 0) $devise_taux = 1;

				//obligatoire sur partie achat car c'est l'objet parent et non l'object ligne qui est transmis au trigger
				if($action == 'LINEORDER_SUPPLIER_CREATE'){
					$ligne = new CommandeFournisseurLigne($db);
					$ligne->fetch($object->rowid);
					$object_last = $object;
					$object = $ligne;
				}
				elseif($action == 'LINEBILL_SUPPLIER_CREATE'){
					$dol_version = (float) DOL_VERSION;

					$ligne = new ProductFournisseur($db);
					$ligne->fetch_product_fournisseur_price($idprodfournprice);
					$object->subprice = $ligne->fourn_price;

					if ($dol_version <= 3.6)
					{
						$object->qty = $_REQUEST['qty'];
					}
					else
					{
						$lastline =  $object->lines[count($object->lines)-1];
						$object->qty = $lastline->qty;
					}

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
				//echo $devise_pu.'<br>';
				// OMG OMFG, pas de round maintenant !!!!
				//$devise_pu = round($devise_pu,2);

				//pre($object,true).'<br>';
				//Mais un round ici - sur le montant total
				$devise_mt_ligne = round($devise_pu * (($object->qty) ? $object->qty : $quantity_predef),$conf->global->MAIN_MAX_DECIMALS_TOT);

//print $devise_mt_ligne;exit;
				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.'
						SET devise_pu = '.$devise_pu.'
						, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $remise_percent) / 100))).'
						WHERE rowid = '.$object->rowid;
				//echo $sql;exit;
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
						$object->pa_ht = price(TMultidevise::_getMarge($db,$fournprice, $buyingprice));
						$object->fk_fournprice = 0; //mise a zero obligatoire sinon affiche le prix fournisseur non modifé
					}
				}

				if(get_class($object)=='CommandeFournisseur') {
					//echo "2";exit;
					$object->updateline($object->rowid, $ligne->desc, $subprice, $ligne->qty, $ligne->remise_percent, $ligne->tva_tx,0,0,'HT',0, 0, true);
				}
				elseif(defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY && $action == 'LINEBILL_SUPPLIER_CREATE'){
					//echo "3";exit;
					$object->updateline($object->rowid, $ligne->description, $object->subprice, $ligne->tva_tx,0,0,$_REQUEST['qty'],$ligne->product_id,'HT',0,0,0,true);
				}
				else {
					//echo $action;exit;
					//var_dump($object); exit;
					$qty = price2num(GETPOST('qty_predef'));
					if($qty==0)$qty = $_REQUEST['qty'];

					if($action == 'LINEBILL_SUPPLIER_CREATE') {
						$object->updateline($object->rowid, $ligne->description, $object->subprice, $ligne->tva_tx,0,0,$qty,$ligne->product_id,'HT',0,0,0,true);
					}
					else {
						$object->update($user, 1);
					}
				}

			}
			//Ligne libre
			elseif($idProd==0 && $dp_pu_devise){

				$devise_pu = round(price2num($dp_pu_devise) ,$conf->global->MAIN_MAX_DECIMALS_UNIT);

				$devise_mt_ligne = $devise_pu * $quantity;

				$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);

			}
			elseif($idProd==0 && !$dp_pu_devise && $_REQUEST['action'] == 'setabsolutediscount'){
				// autre ligne, ex : acompte
				$devise_taux = TMultidevise::getElementCurrency($element,$object);

				$devise_pu = round($object->subprice * $devise_taux ,$conf->global->MAIN_MAX_DECIMALS_UNIT);

				$devise_mt_ligne = $devise_pu * $object->qty;

				$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);



			}
			else{

				//inspiration de la ligne 567 - FIX tk2539
				if($action == 'LINEORDER_SUPPLIER_CREATE')
				{
					$ligne = new CommandeFournisseurLigne($db);
					$ligne->fetch($object->rowid);
					$object_last = $object;
					$object = $ligne;

					$devise_taux = TMultidevise::getElementCurrency($element,$object_last,1,'id');

					$pu = $dp_pu_devise ? $dp_pu_devise : $object->subprice;

					$devise_pu = round($pu / $devise_taux ,$conf->global->MAIN_MAX_DECIMALS_UNIT);
					$devise_mt_ligne = $dp_pu_devise * $object->qty;
					//var_dump($devise_mt_ligne);exit;
					$object_last->updateline($ligne->rowid, $ligne->desc, $devise_pu, $object->qty);
					$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$dp_pu_devise.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);

					$object = $object_last;
				}
				else
				{

					if((float) DOL_VERSION < 3.8) {
						$devise_taux = TMultidevise::getElementCurrency($element,$object,1,'id');
						$devise_pu = round($object->subprice * $devise_taux ,$conf->global->MAIN_MAX_DECIMALS_UNIT);
						$devise_mt_ligne = $devise_pu * $object->qty;
						$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
					}elseif($action == 'LINEBILL_SUPPLIER_CREATE'){
						$ligne = new SupplierInvoiceLine($db);

						$ligne->fetch($object->rowid);
						$object_last = $object;
						$object = $ligne;

						$devise_taux = TMultidevise::getElementCurrency($element,$object_last,1,'id');

						$pu = $dp_pu_devise ? $dp_pu_devise : $object->subprice;

						$devise_pu = round($pu / $devise_taux ,$conf->global->MAIN_MAX_DECIMALS_UNIT);
						$devise_mt_ligne = $devise_pu * $object->qty;
						//var_dump($devise_mt_ligne);exit;
						//$object_last->updateline($ligne->rowid, $ligne->description, $devise_pu, $ligne->tva_tx, 0, 0, $ligne->qty, $ligne->fk_product);
						$db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
//pre($object,1);
//var_dump($dp_pu_devise,$pu,$ligne->tva_tx,$devise_pu);
//exit('la');
						$object = $object_last;
					}
				}

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

	static function _getMarge(&$db,&$fk_fournprice,&$buyingprice){
		global  $user, $conf;
		//echo $buyingprice;exit;
		//Récupération du fk_soc associé au prix fournisseur
		$resql = $db->query("SELECT pfp.fk_soc FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp WHERE pfp.rowid = ".$fk_fournprice);
		$res = $db->fetch_object($resql);
		$fk_soc = $res->fk_soc;

//		exit($fk_soc);
		//Récupération du taux de la devise fournisseur
		$sql = "SELECT cr.rate
				FROM ".MAIN_DB_PREFIX."currency_rate as cr
					LEFT JOIN ".MAIN_DB_PREFIX."currency as c ON (c.rowid = cr.id_currency)
					LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (c.rowid = s.fk_devise)
				WHERE s.rowid = " . $fk_soc . "
				ORDER BY cr.dt_sync DESC
				LIMIT 1";
		//echo $sql;exit;
		$resql = $db->query($sql);
		$res = $db->fetch_object($resql);

		//Calcul du prix d'achat devisé
		$buyingprice = (defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY) ? price2num($buyingprice) / $res->rate : $buyingprice ;

		//		echo $buyingprice;exit;
		return $buyingprice;
	}

	static function updateLine(&$db, &$object,&$user, $action,$id_line,$remise_percent, $devise_taux=0, $fk_parent=0,$rateApplication='PU_DEVISE') {
		global $conf,$user;

		list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);

		if($action === 'LINEBILL_UPDATE'){

			$object->update($user,true);
		}
		elseif($action != 'LINEORDER_SUPPLIER_UPDATE' && $action!='LINEORDER_SUPPLIER_CREATE' && $action!='ORDER_SUPPLIER_CREATE' && $action!='BILL_SUPPLIER_CREATE' && $action!='LINEBILL_SUPPLIER_UPDATE'){

			$object->update($user,true);

		}

		if(empty($fk_parent)){
			if($action === 'LINEORDER_UPDATE' || $action === 'LINEPROPAL_UPDATE' || $action === 'LINEBILL_UPDATE'){
				$fk_parent = __val($object->oldline->{"fk_".$element}, __val($object->{"fk_".$element}, $object->id) );

			}
			elseif($action === 'LINEBILL_SUPPLIER_UPDATE' && DOL_VERSION >= 3.8){
				$fk_parent = $_REQUEST['id'];
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
			if ($devise_taux == 0) $devise_taux = 1;
		}

		if($object->origin == "shipping"){
				$db->commit();
				$db->commit();
				$db->commit();

				if($rateApplication=='PU_DOLIBARR') {
					// a priori cas impossible
				}
				else {

					$db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet
								SET devise_pu = '.round($object->subprice * $devise_taux,$conf->global->MAIN_MAX_DECIMALS_UNIT).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,$conf->global->MAIN_MAX_DECIMALS_TOT).'
								WHERE rowid = '.$object->rowid);

				}

		}
		elseif($action === 'LINEORDER_UPDATE' || $action ==='LINEPROPAL_UPDATE' || $action === 'LINEBILL_UPDATE'
		|| $action==='PROPAL_CREATE' || $action==='BILL_CREATE' || $action==='ORDER_CREATE' || $action === 'BILL_SUPPLIER_CREATE'){

		    $pu_devise = !empty($object->device_pu) ? $object->device_pu : $object->subprice * $devise_taux;

			$tva_devise = !empty($object->total_tva_device) ? $$object->total_tva_device : $object->total_tva * $devise_taux;

			$pu_devise = round($pu_devise,$conf->global->MAIN_MAX_DECIMALS_UNIT);

			$devise_mt_ligne = $pu_devise * $object->qty;
			if($rateApplication=='PU_DOLIBARR') {

				$object->subprice = $pu_devise / $devise_taux;
				$object->total = $object->subprice * $object->qty * (1+($object->remise_percent/100));

/*var_dump($pu_devise,$action,$rateApplication,$object);
				exit();*/
			}
			else {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.'
							SET devise_pu = '.$pu_devise.'
							, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $remise_percent) / 100))).'
							WHERE rowid = '.$object->rowid;

				$db->query($sql);
			}


			list($object->total_ht, $object->total_tva, $object->total_ttc)=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, 0, 0, 0, 'HT', $object->info_bits, $object->fk_product_type);
			if($rateApplication=='PU_DOLIBARR') {
				if($action === 'LINEBILL_UPDATE'){
					$object->update($user,true);
				}
				else{
					$object->update($user,true);
				}
			}
			//$db->query($sql); ???
		}
		else{

			if($action == 'LINEBILL_SUPPLIER_UPDATE' || $action=='LINEBILL_SUPPLIER_CREATE' || $action=='LINEBILL_SUPPLIER_CREATE'){
				$sql = "SELECT pu_ht as subprice, qty, remise_percent as remise FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$id_line;
			}
			else{
				$sql = "SELECT subprice, qty, remise_percent as remise  FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$id_line;
			}

			$resql = $db->query($sql);
            $res = $db->fetch_object($resql);

			$pu_devise = !empty($object->device_pu) ? $object->device_pu : $res->subprice * $devise_taux;
			$tva_devise = !empty($object->total_tva_device) ? $$object->total_tva_device : $object->total_tva * $devise_taux;

			$pu_devise = round($pu_devise,$conf->global->MAIN_MAX_DECIMALS_UNIT);
			$devise_mt_ligne = $pu_devise * $res->qty;

/*var_dump($object);
var_dump($pu_devise,$devise_mt_ligne,$tva_devise,$rateApplication);
exit('la2');

*/			if($rateApplication=='PU_DOLIBARR') {

					$subprice = $pu_devise / $devise_taux;
					$object->subprice = $subprice;
					$object->pu_ht = $subprice;
					$object->total_ht = $object->subprice * $object->qty * (1+($object->remise_percent / 100));

					if($action==='LINEORDER_SUPPLIER_UPDATE') {
						$parent = new CommandeFournisseur($db);
						$parent->fetch($fk_parent);
						$parent->updateline(
					        $id_line,
					        $object->desc,
					        $object->subprice,
					        $object->qty,
					        $object->remise_percent,
					        $object->tva_tx,
					        $object->localtax1,
					        $object->localtax1,
					        'HT',
					        0,
					        $object->product_type,
					        true
					    );

					}
					else if($action==='LINEBILL_SUPPLIER_UPDATE') {

						$parent = new FactureFournisseur($db);
						$parent->fetch($fk_parent);
//exit('la');
						$parent->updateline(
							$id_line,
					        $object->desc,
					        $object->pu_ht,
					        $object->tva_tx,
					        $object->localtax1_tx,
						    $object->localtax2_tx,
						    $object->qty,
					        $object->fk_product,
					        'HT',
					        0,
					        $object->product_type,
					        $object->remise_percent,
					        true
					    );
						//var_dump($subprice, $parent);exit;
					}
			}
			else{

				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.'
							SET devise_pu = '.$pu_devise.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($res->remise / 100))).'
							WHERE rowid = '.$id_line;
				$db->query($sql);

			}

		}

		if($rateApplication=='PU_DOLIBARR') {
			// déjà fait par update line
		}
		else {
			//MAJ du total devise de la commande/facture/propale
			$resql = $db->query('SELECT SUM(f.devise_mt_ligne) as total_devise
									   FROM '.MAIN_DB_PREFIX.$element_line.' as f LEFT JOIN '.MAIN_DB_PREFIX.$element.' as m ON (f.'.$fk_element.' = m.rowid)
									   WHERE m.rowid = '.$fk_parent);

			$res = $db->fetch_object($resql);
			$db->query('UPDATE '.MAIN_DB_PREFIX.$element.'
						SET devise_mt_total = '.$res->total_devise."
						WHERE rowid = ".$fk_parent);


		}
	}

	/*
	 * Mise à jour de la devise du client
	 */
	static function updateCompany(&$db,&$object, $currency) {
		global  $user, $conf;
			if($currency){
				$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'currency WHERE code = "'.$currency.'" LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.'societe SET fk_devise = '.$res->rowid.', devise_code = "'.$currency.'" WHERE rowid = '.$object->id);
				}
			}

	}

	static function updateCurrencyRate(&$db, &$object, $currency, $currencyRate) {
		global $user,$conf;
			/*pre($_REQUEST,true);
			pre($object,true);
			exit('1');*/
			if($currency){
				$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'currency WHERE code = "'.$currency.'" LIMIT 1');
				if($res = $db->fetch_object($resql)){

					if ($object->table_element != "societe") {
						$sql="SELECT devise_taux FROM ".MAIN_DB_PREFIX.$object->table_element." WHERE rowid = ".$object->id;
						$res2= $db->query($sql);
						$obj2 = $db->fetch_object($res2);
						$old_currencyRate=$obj2->devise_taux;
					}

					$sql = " UPDATE ".MAIN_DB_PREFIX.$object->table_element."
		    		SET fk_devise = ".$res->rowid.", devise_code='".$currency."'";

					if ($object->table_element != "societe") {
						$sql .= ',devise_taux='.$currencyRate;
					}

		    		$sql.=" WHERE rowid = ".$object->id;
					$db->query($sql);

					if(!empty($object->lines)) {

						foreach($object->lines as &$line) {

							$id_line = __val($line->id, $line->rowid);

							$remise_percent = __val($line->remise_percent, $line->rowid);

							$line->device_pu = __val($line->subprice,$line->pu_ht) * $old_currencyRate;

							$action = TMultidevise::getActionByTable($object->table_element);
							//var_dump($line, $action, $id_line, $remise_percent);
							TMultidevise::updateLine($db, $line, $user, $action, $id_line, $remise_percent,$currencyRate,$object->id,$conf->global->MULTIDEVISE_MODIFY_RATE_APPLICATION);

						}

					}

				}


				if(method_exists($object, 'update_price') && $object->table_element != "societe") {
					$object->update_price();
				}

				?>
				<script language="javascript">
					if(<?php echo $object->table_element; ?> != "societe")
						document.location.href="?id=<?php echo $object->id; ?>";
					else
						document.location.href="?socid=<?php echo $object->id; ?>";
				</script>
				<?php

			}

	}

	static function preparePDF(&$object) {
			global $conf,$user, $db;

			$paid = 0;

			$req = $db->query('SELECT devise_code, devise_taux FROM ' . MAIN_DB_PREFIX .$object->table_element. ' WHERE rowid = ' . $object->id);
			if ($req) {
				$result = $db->fetch_object($req);

				if(empty($object->origin_currency))$object->origin_currency = $conf->currency;
				$conf->currency  = $result->devise_code;

				$devise_rate = $result->devise_taux;


				if($object->table_element=='facture')
				{
					/* paiements */
					$req = $db->query('SELECT devise_mt_paiement FROM ' . MAIN_DB_PREFIX . 'paiement_facture WHERE fk_facture = ' . $object->id);

					while ($result = $db->fetch_object($req)) {
						$paid += $result->devise_mt_paiement;
					}

				}
				elseif($object->table_element=='facture_fourn')
				{
					/* paiements */
					$req = $db->query('SELECT devise_mt_paiement FROM ' . MAIN_DB_PREFIX . 'paiementfourn_facturefourn WHERE fk_facturefourn = ' . $object->id);

					while ($result = $db->fetch_object($req)) {
						$paid += $result->devise_mt_paiement;
					}

				}

				$total_tva = 0;

				// 2 - Dans les lignes
				foreach($object->lines as &$line){
					 if ($line->special_code < 9) {
						//Modification des montant si la devise a changé
						$lineid = (($line->rowid) ? $line->rowid : $line->id);

						$resl = $db->query('SELECT devise_pu, devise_mt_ligne FROM '.MAIN_DB_PREFIX.$object->table_element_line.' WHERE rowid = '.$lineid );
						$res = $db->fetch_object($resl);

						if($res){

							if(empty($line->total_tva_devise)) {
								$line->total_tva_devise = $line->total_tva * $devise_rate;

							}

					//		$line->tva_tx = 0;
							$line->subprice = round($res->devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT);
							$line->price = round($res->devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT);
							$line->pu_ht = round($res->devise_pu,$conf->global->MAIN_MAX_DECIMALS_UNIT);
							$line->total_ht = round($res->devise_mt_ligne,$conf->global->MAIN_MAX_DECIMALS_TOT);
							$line->total_ttc = round($res->devise_mt_ligne + $line->total_tva_devise,$conf->global->MAIN_MAX_DECIMALS_TOT);
							$line->total_tva = $line->total_ttc - $line->total_ht;

							$total_tva+= $line->total_tva;
						}
					 }
				}
			} else {
				dol_syslog(__METHOD__.' ERROR:'.$db->lasterror);
			}


				// 3 - Dans le bas du document
			//Modification des TOTAUX si la devise a changé

			//pre($object,true);exit;

			$resl = $db->query('SELECT devise_mt_total FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id);
			if ($resl) {
				$res = $db->fetch_object($resl);

				if($res){
					$object->total_ht = round($res->devise_mt_total,$conf->global->MAIN_MAX_DECIMALS_TOT);
					$object->total_tva = round($total_tva,$conf->global->MAIN_MAX_DECIMALS_TOT);
					$object->total_ttc = round($object->total_ht + $object->total_tva,$conf->global->MAIN_MAX_DECIMALS_TOT);

					if($object->total_localtax1) $object->total_ttc += $object->total_localtax1;
					if($object->total_localtax2) $object->total_ttc += $object->total_localtax2;

				}
			} else {
				dol_syslog(__METHOD__.' ERROR:'.$db->lasterror);
			}

			//$object = $object_old;

			return array(
				$paid
			);

	}


	static function addpaiement(&$db,&$TRequest,&$object,$action){
		global $user,$conf;

		list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);

		$TDevise=array();
		foreach($TRequest as $key=>$value) {

			$mask = 'amount_';
			if(strpos($key, $mask)===0) {

				$id_facture = (int)substr($key, strlen($mask));
				$TDevise[$id_facture] = $TRequest['devise'][$mask.$id_facture]; // On récupère la liste des factures et le montant du paiement

			}
		}

		//pre($TDevise,true);exit;
		if(!empty($TDevise)){
			$db->commit();
			$db->commit();

			$note = "";
			$somme = 0.00;

			foreach($TDevise  as $id_fac => $mt_devise){
				$somme += price2num($mt_devise);

				if($action == "PAYMENT_CUSTOMER_CREATE"){
					$facture = new Facture($db);
					$facture->fetch($id_fac);
					$element = "facture";
				}
				else{
					$facture = new FactureFournisseur($db);
					$facture->fetch($id_fac);
					$element = "facture_fourn";
				}

				$sql = 'SELECT devise_mt_total, devise_code, devise_taux FROM '.MAIN_DB_PREFIX.$element.' WHERE rowid = '.$facture->id;
				$resql = $db->query($sql);
				$res = $db->fetch_object($resql);

				$devise_taux = $res->devise_taux;
				if($conf->global->MULTIDEVISE_USE_RATE_ON_INVOICE_DATE){
					$devise_taux = TMultidevise::_setCurrencyRate($db, $facture, $res->devise_code,1);
				}

				$account = new Account($db);
				$account->fetch($TRequest['accountid']);

				//Règlement total
				if(price2num($res->devise_mt_total+($facture->total_tva*$devise_taux),'MT') == price2num($mt_devise,'MT')){

					$facture->set_paid($user);

					if($account->currency_code != $res->devise_code) {
						// TODO Ecriture comptable à enregistrer dans un compte. En dessous la note n'a pas de sens : ($_REQUEST['amount_'.$facture->id] - $facture->total_ttc) ne correspond jamais à un gain ou à une perte suite à une conversion

						//Ajout de la note si des écarts sont lié aux conversions de devises
						if(round(strtr($TRequest['amount_'.$facture->id],array(','=>'.')),$conf->global->MAIN_MAX_DECIMALS_TOT) < strtr(round($facture->total_ttc,$conf->global->MAIN_MAX_DECIMALS_TOT),array(','=>'.'))){
							$note .= "facture : ".$facture->ref." => PERTE après conversion : ".($facture->total_ttc - price2num($TRequest['amount_'.$facture->id]))." ".$conf->currency."\n";
						}
						elseif(round(strtr($TRequest['amount_'.$facture->id],array(','=>'.')),$conf->global->MAIN_MAX_DECIMALS_TOT) > strtr(round($facture->total_ttc,$conf->global->MAIN_MAX_DECIMALS_TOT),array(','=>'.'))){
							$note .= "facture : ".$facture->ref." => GAIN après conversion : ".(price2num($TRequest['amount_'.$facture->id]) - $facture->total_ttc)." ".$conf->currency."\n";
						}
					}
				}

				if($action == "PAYMENT_CUSTOMER_CREATE"){
					//MAJ du montant paiement_facture
					$db->query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET devise_mt_paiement = "'.price2num($mt_devise).'" , devise_taux = "'.$devise_taux.'", devise_code = "'.$res->devise_code.'"
								WHERE fk_paiement = '.$object->id.' AND fk_facture = '.$facture->id);

					$db->query('UPDATE '.MAIN_DB_PREFIX."paiement SET note = CONCAT(note,' ', '".$note."') WHERE rowid = ".$object->id);
				}
				else{
					//MAJ du montant paiement_facturefourn
					$db->query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn_facturefourn SET devise_mt_paiement = "'.price2num($mt_devise).'" , devise_taux = "'.$devise_taux.'", devise_code = "'.$res->devise_code.'"
								WHERE fk_paiementfourn = '.$object->id.' AND fk_facturefourn = '.$facture->id);

					$db->query('UPDATE '.MAIN_DB_PREFIX."paiementfourn SET note = '".$note."' WHERE rowid = ".$object->id);
				}
			}
		}
	}

	static function updateAmountBankLine(&$db, &$payment_object, $mt_devise, $type='client')
	{
		if ($type == 'client')
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'bank b INNER JOIN '.MAIN_DB_PREFIX.'paiement p ON (p.fk_bank = b.rowid) SET b.amount = "'.$mt_devise.'" WHERE p.rowid = '.$payment_object->id;
			return $db->query($sql);
		}

		return false;
	}

	static function getInfoMultidevise(&$db, $id, $type='client')
	{
		if ($type == 'client')
		{
			$sql = 'SELECT devise_mt_total, devise_code, devise_taux FROM '.MAIN_DB_PREFIX.'facture WHERE rowid = '.$id;
			$resql = $db->query($sql);

			if ($resql && ($res = $db->fetch_object($resql))) return $res;
			else return false;
		}

		return false;
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
