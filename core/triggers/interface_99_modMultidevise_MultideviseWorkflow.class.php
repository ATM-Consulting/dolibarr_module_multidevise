<?php
/* Copyright (C) 2005-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/core/triggers/interface_90_all_Demo.class.php
 *  \ingroup    core
 *  \brief      Fichier de demo de personalisation des actions du workflow
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *				                           ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la methode constructeur doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */


/**
 *  Class of triggers for Mantis module
 */
 
class InterfaceMultideviseWorkflow
{
    var $db;
    
    /**
     *   Constructor
     *
     *   @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
    
        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "ATM";
        $this->description = "Trigger du module de devise multiple";
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'technic';
    }
    
    
    /**
     *   Return name of trigger file
     *
     *   @return     string      Name of trigger file
     */
    function getName()
    {
        return $this->name;
    }
    
    /**
     *   Return description of trigger file
     *
     *   @return     string      Description of trigger file
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   Return version of trigger file
     *
     *   @return     string      Version of trigger file
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }


	function _getMarge(&$fk_fournprice,&$buyingprice){
		global  $user, $conf;
		
		//Récupération du fk_soc associé au prix fournisseur
		$resql = $this->db->query("SELECT pfp.fk_soc FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp WHERE pfp.rowid = ".$fk_fournprice);
		$res = $this->db->fetch_object($resql);
		$fk_soc = $res->fk_soc;
		
		//Récupération du taux de la devise fournisseur
		$sql = "SELECT cr.rate
				FROM ".MAIN_DB_PREFIX."currency_rate as cr
					LEFT JOIN ".MAIN_DB_PREFIX."currency as c ON (c.rowid = cr.id_currency)
					LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (cr.rowid = s.fk_devise)
				WHERE s.rowid = ".$fk_soc."
				ORDER BY cr.dt_sync DESC
				LIMIT 1";
		
		$resql = $this->db->query($sql);
		$res = $this->db->fetch_object($resql);
		
		//Calcul du prix d'achat devisé
		$buyingprice = (defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY) ? $buyingprice / $res->rate : $buyingprice ;
		
		return $buyingprice;
	}

	
    /**
     *      Function called when a Dolibarrr business event is done.
     *      All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
     *
     *      @param	string		$action		Event action code
     *      @param  Object		$object     Object
     *      @param  User		$user       Object user
     *      @param  Translate	$langs      Object langs
     *      @param  conf		$conf       Object conf
     *      @return int         			<0 if KO, 0 if no triggered ran, >0 if OK
     */
	function run_trigger($action,&$object,$user,$langs,$conf)
	{
		global  $user, $conf;
		if(!defined('INC_FROM_DOLIBARR'))define('INC_FROM_DOLIBARR',true);
		dol_include_once('/multidevise/config.php');
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/comm/propal/class/propal.class.php');
		dol_include_once("/societe/class/societe.class.php");
		dol_include_once("/core/lib/functions.lib.class.php");
		dol_include_once('/fourn/class/fournisseur.facture.class.php');
		dol_include_once('/fourn/class/fournisseur.commande.class.php');
		dol_include_once('/fourn/class/fournisseur.product.class.php');

		$db=&$this->db;

		/*
		 * ASSOCIATION DEVISE PAR SOCIETE
		 */
		if($action == "COMPANY_CREATE" || $action =="COMPANY_MODIFY"){
			if(isset($_REQUEST['currency']) && !empty($_REQUEST['currency'])){
				$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'currency WHERE code = "'.$_REQUEST['currency'].'" LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.'societe SET fk_devise = '.$res->rowid.', devise_code = "'.$_REQUEST['currency'].'" WHERE rowid = '.$object->id);
				}
			}
				
		}
		
		/*
		 * ASSOCIATION DEVISE, TAUX PAR COMMANDE, PROPAL OU FACTURE
		 */
		if($action == "ORDER_CREATE" || $action == "PROPAL_CREATE" || $action =="BILL_CREATE" || $action =="ORDER_SUPPLIER_CREATE" || $action =="BILL_SUPPLIER_CREATE"){
			
			if(isset($_REQUEST['currency']) && !empty($_REQUEST['currency'])){
				$resql = $db->query('SELECT c.rowid AS rowid, c.code AS code, cr.rate AS rate
									 FROM '.MAIN_DB_PREFIX.'currency AS c LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate AS cr ON (cr.id_currency = c.rowid)
									 WHERE c.code = "'.$_REQUEST['currency'].'" AND cr.id_entity = '.$conf->entity.' ORDER BY cr.dt_sync DESC LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET fk_devise = '.$res->rowid.', devise_code = "'.$res->code.'", devise_taux = '.$res->rate.' WHERE rowid = '.$object->id);
				}
			}
			
			//Création a partir d'un objet d'origine (propale ou commande)
			if((!empty($object->origin) && !empty($object->origin_id)) || (!empty($_POST['origin']) && !empty($_POST['originid']))){
				
				if($_POST['origin'] == "propal"){
					$table_origin = "propal";
					$tabledet_origin = "propaldet";
					$originid = $object->origin_id;
	        	}
				elseif($object->origin == "commande"){
					$table_origin = "commande";
					$tabledet_origin = "commandedet";
					$originid = $object->origin_id;
				}
				elseif($object->origin == "order_supplier"){
					$table_origin = "commande_fournisseur";
					$tabledet_origin = "commande_fournisseurdet";
					$originid = $object->origin_id;
				}
				
				$resql = $this->db->query("SELECT devise_mt_total FROM ".MAIN_DB_PREFIX.$table_origin." WHERE rowid = ".$originid);
				$res = $this->db->fetch_object($resql);
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '.$res->devise_mt_total.' WHERE rowid = '.$object->id);
			}
		}
		
		/*
		 *  CREATION P.U. DEVISE + TOTAL DEVISE PAR LIGNE DE COMMANDE, PROPAL, FACTURE, COMMANDE FOURNISSEUR OU FACTURE FOURNISSEUR
		 */
		if ($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEBILL_INSERT' || $action == 'LINEORDER_SUPPLIER_CREATE' || $action == 'LINEBILL_SUPPLIER_CREATE') {
			
			/*echo '<pre>';
			print_r($_REQUEST);
			echo '</pre>';exit;*/
			
			switch ($action) {
				case 'LINEORDER_INSERT':
					$element = "commande";
					$element_line = "commandedet";
					$fk_element = "fk_commande";
					break;
				case 'LINEPROPAL_INSERT':
					$element = "propal";
					$element_line = "propaldet";
					$fk_element = "fk_propal";
					break;
				case 'LINEBILL_INSERT':
					$element = "facture";
					$element_line = "facturedet";
					$fk_element = "fk_facture";
					break;
				case 'LINEORDER_SUPPLIER_CREATE':
					$element = "commande_fournisseur";
					$element_line = "commande_fournisseurdet";
					$fk_element = "fk_commande";
					break;
				case 'LINEBILL_SUPPLIER_CREATE':
					$element = "facture_fourn";
					$element_line = "facture_fourn_det";
					$fk_element = "fk_facture_fourn";
					break;
			}
			
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
			if((!empty($object->origin) && !empty($object->origin_id)) || (!empty($_POST['origin']) && !empty($_POST['originid']))){

				if($_REQUEST['origin'] == "propal"){
					$table_origin = "propal";
					$tabledet_origin = "propaldet";
					$propal = new Propal($this->db);
					$propal->fetch($_POST['originid']);

					foreach($propal->lines as $line){
						if($line->rang == $object->rang)
							$originid = $line->rowid;
					}
	        	}
				elseif($object->origin == "commande"){
					$table_origin = "commande";
					$tabledet_origin = "commandedet";
					$originid = $object->origin_id;
				}
				elseif($object->origin == "order_supplier"){
					$table_origin = "commande_fournisseur";
					$tabledet_origin = "commande_fournisseurdet";
				}
				
				if($object->origin == "shipping"){
					$this->db->commit();
					$this->db->commit();
					$this->db->commit(); // J'ai été obligé mais je sais pas pourquoi 

					$resql = $this->db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$object->fk_facture);
					$res = $this->db->fetch_object($resql);
					$devise_taux = __val($res->devise_taux,1);

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
					
				}
				else{
					
					//Pas de liaison ligne origine => ligne destination pour la création de facture fourn depuis commande fourn donc on improvise
					if($object->origin == "order_supplier"){
						
						$this->db->commit();
						$this->db->commit();
						$this->db->commit(); // J'ai été obligé mais je sais pas pourquoi 
						
						$commande_fourn_origine = new CommandeFournisseur($this->db);
						$commande_fourn_origine->fetch($object->origin_id);
						
						$object->fetch_lines();
						
						$keys = array_keys($object->lines);
						$last_key = $keys[count($keys)-1];
						
						$originid = $commande_fourn_origine->lines[$last_key]->id;
					}
					
					$resql = $this->db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet_origin." WHERE rowid = ".$originid);
					$res = $this->db->fetch_object($resql);

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$res->devise_pu.', devise_mt_ligne = '.$res->devise_mt_ligne.' WHERE rowid = '.$object->rowid);

				}
				
				
			}
			else{
				/* ***************************
				 *	Création standard
				 * ***************************/ 

				$idProd = 0;
				if(!empty($_POST['id'])) $idProd = $_POST['id'];
				if(!empty($_POST['idprod'])) $idProd = $_POST['idprod'];
				if(!empty($_POST['productid'])) $idProd = $_POST['productid'];
				if(!empty($_POST['idprodfournprice'])) $idProd = $_POST['idprodfournprice'];
				
				//Ligne de produit/service existant
				if($idProd>0 && empty($_REQUEST['dp_pu_devise'])){
					
					$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".(($object->{"fk_".$element})? $object->{"fk_".$element} : $object->id) ;
					
                    $resql = $this->db->query($sql);
                    $res = $this->db->fetch_object($resql);
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
						$ligne->fetch_product_fournisseur_price($_REQUEST['idprodfournprice']);
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
					$devise_mt_ligne = $devise_pu * (($object->qty) ? $object->qty : $_REQUEST['qty_predef']);
//print $devise_mt_ligne;exit;
					$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' 
							SET devise_pu = '.$devise_pu.'
							, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $_REQUEST['remise_percent']) / 100))).' 
							WHERE rowid = '.$object->rowid;
//exit($sql);
					$db->query($sql);
					
					$tabprice=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, 0, 0, 0, 'HT', $object->info_bits, $object->fk_product_type);
					$object->total_ht  = $tabprice[0];
					$object->total_tva = $tabprice[1];
					$object->total_ttc = $tabprice[2];
					
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
						$fournprice=(GETPOST('fournprice_predef')?GETPOST('fournprice_predef'):'');
						$buyingprice=(GETPOST('buying_price_predef')?GETPOST('buying_price_predef'):'');
						
						$object->pa_ht = price($this->_getMarge($fournprice, $buyingprice));
						$object->fk_fournprice = 0; //mise a zero obligatoire sinon affiche le prix fournisseur non modifé
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
				elseif(isset($_REQUEST['dp_pu_devise']) && !empty($_REQUEST['dp_pu_devise'])){
					
					$devise_pu = round($_REQUEST['dp_pu_devise'],2);
					
					$devise_mt_ligne = $devise_pu * $_REQUEST['qty'];
					
					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
					
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
				
				$resql = $this->db->query($sql);
				$res = $this->db->fetch_object($resql);

				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id) );
			}
		}
	
		/*
		 * MODIFICATION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE' || $action == 'LINEORDER_SUPPLIER_UPDATE' || $action == 'LINEBILL_SUPPLIER_UPDATE'){
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';*/
		
			switch ($action) {
				case 'LINEORDER_UPDATE':
					$element = "commande";
					$element_line = "commandedet";
					$fk_element = "fk_commande";
					break;
				case 'LINEPROPAL_UPDATE':
					$element = "propal";
					$element_line = "propaldet";
					$fk_element = "fk_propal";
					break;
				case 'LINEBILL_UPDATE':
					$element = "facture";
					$element_line = "facturedet";
					$fk_element = "fk_facture";
					break;
				case 'LINEORDER_SUPPLIER_UPDATE':
					$element = "commande_fournisseur";
					$element_line = "commande_fournisseurdet";
					$fk_element = "fk_commande";
					break;
				case 'LINEBILL_SUPPLIER_UPDATE':
					$element = "facture_fourn";
					$element_line = "facture_fourn_det";
					$fk_element = "fk_facture_fourn";
					break;
			}
			
			if($action == 'LINEBILL_UPDATE'){
				$object->update($user,true);
			}
			elseif($action != 'LINEORDER_SUPPLIER_UPDATE'){
				$object->update(true);
			}
			
			if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE')
				$fk_parent = isset($object->oldline->{"fk_".$element}) ? $object->oldline->{"fk_".$element} : $object->{"fk_".$element};
			else
				$fk_parent = $object->id;
			
			$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".$fk_parent;

            $resql = $this->db->query($sql);
            $res = $this->db->fetch_object($resql);
            $devise_taux = __val($res->devise_taux,1);

			if($object->origin == "shipping"){
					$this->db->commit();
					$this->db->commit();
					$this->db->commit();

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
					
			}
			elseif($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE'){
				$pu_devise = $object->subprice * $devise_taux;
			    $pu_devise = !empty($object->device_pu) ? $object->device_pu : $object->subprice * $devise_taux;

				$pu_devise = round($pu_devise,2);

				$devise_mt_ligne = $pu_devise * $object->qty;

				$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' 
							SET devise_pu = '.$devise_pu.'
							, devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ((($object->remise_percent) ? $object->remise_percent : $_REQUEST['remise_percent']) / 100))).' 
							WHERE rowid = '.$object->rowid;
//exit($sql);
				$this->db->query($sql);
				
				$tabprice=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, 0, 0, 0, 'HT', $object->info_bits, $object->fk_product_type);
				$object->total_ht  = $tabprice[0];
				$object->total_tva = $tabprice[1];
				$object->total_ttc = $tabprice[2];
				
				$this->db->query($sql);
			}
			else{
				if($action == 'LINEORDER_SUPPLIER_UPDATE')
					$sql = "SELECT subprice, qty, remise FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$object->rowid;
				else
					$sql = "SELECT pu_ht as subprice, qty, remise_percent as remise FROM ".MAIN_DB_PREFIX.$element_line." WHERE rowid = ".$object->rowid;

				$resql = $this->db->query($sql);
	            $res = $this->db->fetch_object($resql);

				$pu_devise = $res->subprice * $devise_taux;
				
				$pu_devise = round($pu_devise,2);
				
				$devise_mt_ligne = $pu_devise * $res->qty;
				
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$pu_devise.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($res->remise / 100))).' WHERE rowid = '.$object->rowid);
				
			}

			//MAJ du total devise de la commande/facture/propale
			$resql = $this->db->query('SELECT SUM(f.devise_mt_ligne) as total_devise 
									   FROM '.MAIN_DB_PREFIX.$element_line.' as f LEFT JOIN '.MAIN_DB_PREFIX.$element.' as m ON (f.'.$fk_element.' = m.rowid)
									   WHERE m.rowid = '.$_REQUEST['id']);
			
			$res = $this->db->fetch_object($resql);
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".$_REQUEST['id']);
		}
	
		/*
		 * SUPPRESSION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if ($action == 'LINEORDER_DELETE' || $action == 'LINEPROPAL_DELETE' || $action == 'LINEBILL_DELETE') {
			
			switch ($action) {
				case 'LINEORDER_DELETE':
					$parent_object = "commande";
					break;
					
				case 'LINEPROPAL_DELETE':
					$parent_object = "propal";
					break;
					
				case 'LINEBILL_DELETE':
					$parent_object = "facture";	
					break;
			}
			
			
			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne 
				    FROM '.MAIN_DB_PREFIX.$object->table_element.' 
				    WHERE fk_'.$parent_object.' = '.$object->{"fk_".$parent_object};
			
			$resql = $this->db->query($sql);
			$res = $this->db->fetch_object($resql);
			
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$parent_object.' SET devise_mt_total = '.(($res->total_ligne > 0 ) ? $res->total_ligne : "0")." WHERE rowid = ".(($object->{'fk_'.$parent_object}) ? $object->{'fk_'.$parent_object} : $_REQUEST['id'] ));
			
		}
		
		/*
		 * SUPPRESSION LIGNE DE COMMANDE OU FACTURE FOURNISSEUR = MAJ DU MONTANT TOTAL DEVISE
		 */
		if ($action == 'LINEORDER_SUPPLIER_DELETE' || $action == 'LINEBILL_SUPPLIER_DELETE') {
			
			//Obligé puisque dans le cas d'une suppresion le trigger est appelé avant et non après
			$object->deleteline($_REQUEST['lineid'], TRUE);
			$db->commit();
			
			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne 
				    FROM '.MAIN_DB_PREFIX.$object->table_element_line.' 
				    WHERE '.$object->fk_element.' = '.$_REQUEST['id'];

			$resql = $this->db->query($sql);
			$res = $this->db->fetch_object($resql);
			
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '.(($res->total_ligne > 0 ) ? $res->total_ligne : "0")." WHERE rowid = ".(($object->{'fk_'.$object->table_element}) ? $object->{'fk_'.$object->table_element} : $_REQUEST['id'] ));
			
		}
		
		
		/*
		 * AJOUT D'UN PAIEMENT 
		 */
		if($action == "PAYMENT_CUSTOMER_CREATE" || $action == "PAYMENT_SUPPLIER_CREATE"){
			
			//pre($_REQUEST);
			
			$TDevise=array();
			foreach($_REQUEST as $key=>$value) {
				
				$mask = 'amount_';
				if(strpos($key, $mask)===0) {
					
					$id_facture = (int)substr($key, strlen($mask));
					$TDevise[$id_facture] = $_REQUEST['devise'][$mask.$id_facture]; // On récupère la liste des factures et le montant du paiement
					
				}
			}
			
			//pre($TDevise); exit;
			
			if(!empty($TDevise)){
				$this->db->commit();
				$this->db->commit();

				$note = "";
				$somme = 0.00;
				
				foreach($TDevise  as $id_fac => $mt_devise){
					$somme += str_replace(',','.',$mt_devise);
					
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

					$sql = 'SELECT devise_mt_total, devise_code FROM '.MAIN_DB_PREFIX.$element.' WHERE rowid = '.$facture->id;					
					$resql = $db->query($sql);
					$res = $db->fetch_object($resql);

					$account = new Account($db);
					$account->fetch($_REQUEST['accountid']);
					
					/*echo "\$account->currency_code : ".$account->currency_code."<br />";
					echo "\$facture->devise_code : ".$res->devise_code;*/
					//pre($facture);

					//Règlement total
					if(strtr(round($res->devise_mt_total,2),array(','=>'.')) == strtr(round($mt_devise,2),array(','=>'.'))){

						$facture->set_paid($user);

						if($account->currency_code == $res->devise_code) {
							return null;
						} else {
							// TODO Ecriture comptable à enregistrer dans un compte. En dessous la note n'a pas de sens : ($_REQUEST['amount_'.$facture->id] - $facture->total_ttc) ne correspond jamais à un gain ou à une perte suite à une conversion

							//Ajout de la note si des écarts sont lié aux conversions de devises
							if(round(strtr($_REQUEST['amount_'.$facture->id],array(','=>'.')),2) < strtr(round($facture->total_ttc,2),array(','=>'.'))){
								$note .= "facture : ".$facture->ref." => PERTE après conversion : ".($facture->total_ttc - price2num($_REQUEST['amount_'.$facture->id]))." ".$conf->currency."\n";
							}
							elseif(round(strtr($_REQUEST['amount_'.$facture->id],array(','=>'.')),2) > strtr(round($facture->total_ttc,2),array(','=>'.'))){
								$note .= "facture : ".$facture->ref." => GAIN après conversion : ".(price2num($_REQUEST['amount_'.$facture->id]) - $facture->total_ttc)." ".$conf->currency."\n";
							}
						}
					}
					
					if($action == "PAYMENT_CUSTOMER_CREATE"){
						//MAJ du montant paiement_facture
						$db->query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET devise_mt_paiement = "'.str_replace(',','.',$mt_devise).'" , devise_taux = "'.$_REQUEST['taux_devise'].'", devise_code = "'.$res->devise_code.'"
									WHERE fk_paiement = '.$object->id.' AND fk_facture = '.$facture->id);

						$db->query('UPDATE '.MAIN_DB_PREFIX."paiement SET note = '".$note."' WHERE rowid = ".$object->id);
					}
					else{
						//MAJ du montant paiement_facture
						$db->query('UPDATE '.MAIN_DB_PREFIX.'paiementfourn_facturefourn SET devise_mt_paiement = "'.str_replace(',','.',$mt_devise).'" , devise_taux = "'.$_REQUEST['taux_devise'].'", devise_code = "'.$res->devise_code.'"
									WHERE fk_paiementfourn = '.$object->id.' AND fk_facturefourn = '.$facture->id);

						$db->query('UPDATE '.MAIN_DB_PREFIX."paiementfourn SET note = '".$note."' WHERE rowid = ".$object->id);
					}
				}
			}
		}
		
		if($action == "BEFORE_PROPAL_BUILDDOC" || $action == "BEFORE_ORDER_BUILDDOC"  || $action == "BEFORE_BILL_BUILDDOC" || $action == "BEFORE_ORDER_SUPPLIER_BUILDDOC" || $action == "BEFORE_BILL_SUPPLIER_BUILDDOC"){
				
			
			$devise_change = false;
			//Modification des prix si la devise est différente
				
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
		}	
		
		return 1;
	}
}
