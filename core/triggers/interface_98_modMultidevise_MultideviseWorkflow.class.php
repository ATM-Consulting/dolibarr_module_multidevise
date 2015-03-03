<?php /* Copyright (C) 2005-2011 Laurent Destailleur 
<eldy@users.sourceforge.net>
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
		dol_include_once('/multidevise/class/multidevise.class.php');
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
			$currency=__get('currency','');
			TMultidevise::updateCompany($db, $object, $currency);
		}
	
		/*
		 * ASSOCIATION DEVISE, TAUX PAR COMMANDE, PROPAL OU FACTURE
		 */
		if($action === "ORDER_CREATE" || $action  ===  "PROPAL_CREATE" || $action  ===  "BILL_CREATE" 
		|| $action === "ORDER_SUPPLIER_CREATE" || $action  === "BILL_SUPPLIER_CREATE"){

			//TODO mettre en fonction de là....
			$origin=__get('origin', $object->origin);
			
			// Pour le cas où l'on vient de replanish : s'il n'y a pas d'origine, on récupère la devise du tiers
			if(empty($origin)) $used_currency = TMultidevise::getThirdCurrency($object->socid);
			else{
				$used_currency = TMultidevise::getDocumentCurrency($object);
				if(empty($used_currency)){
					$used_currency = $conf->currency;
				}
			}
			
			//Il est possible que les tiers n'aient pas de devise assigné car lors de l'import initiale on ne renseigne pas les champs de multidevise
			$currency = __get('currency',($used_currency) ? $used_currency : $conf->currency );
			
			//TODO ... à de là !!
			
			$actioncard = __get('action','');

			if($actioncard=='confirm_clone' && ($action==='ORDER_SUPPLIER_CREATE' || $action==='BILL_SUPPLIER_CREATE' || $action==='PROPAL_CREATE' || $action==='ORDER_CREATE' || $action==='BILL_CREATE') ) {
				
				$objectid = __get('facid', __get('id'));

				$sql = 'SELECT o.fk_devise, o.devise_code, o.devise_taux
						 FROM '.MAIN_DB_PREFIX.$object->table_element.' AS o
						 WHERE o.rowid = '.$objectid;
				
				$resql = $db->query($sql);

				if($res = $db->fetch_object($resql)){
					$object->fetch($object->id);

					$fk_parent = $object->id;				
					$devise_taux = $res->devise_taux;

					$sql="UPDATE ".MAIN_DB_PREFIX.$object->table_element." 
					SET fk_devise=".$res->fk_devise.",devise_code='".$res->devise_code."',devise_taux=".$devise_taux."
					WHERE rowid=".$fk_parent;
					$db->query($sql);

					foreach($object->lines as &$line) {
							
						$id_line = ($action==='BILL_SUPPLIER_CREATE') ? $line->rowid : $line->id ;

						TMultidevise::updateLine($db, $line,$user, $action, $id_line ,$line->remise_percent,$devise_taux,$fk_parent);	

					}
					
				
				}
				
				
				
			} else {
				
				// Quand workflow activé et qu'une commande se crée en auto après la signature d'une propal
				// les PU Devise et Total Devise n'étaient pas récupérés, d'où cette répétition de code : (Ticket 1731)
			
				if(get_class($object) === "Commande") {
				
					$object->fetch_lines();
					
					foreach($object->lines as &$line) {
							
						$id_line = ($action==='BILL_SUPPLIER_CREATE') ? $line->rowid : $line->id ;
	
						TMultidevise::updateLine($db, $line,$user, $action, $id_line ,$line->remise_percent,$devise_taux,$fk_parent);	
	
					}					
				}				
			}
			
			//Clonage => On récupère la devise et le taux de l'objet cloné
			//TODO A quoi ça sert?
			if(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'confirm_clone'){
				
				$objectid = ($_REQUEST['id']) ? $_REQUEST['id'] : $_REQUEST['facid'] ;


			}
			
			else{
				
				TMultidevise::createDoc($db, $object,$currency,$origin);
				
			}
		}
		
		/*
		 *  CREATION P.U. DEVISE + TOTAL DEVISE PAR LIGNE DE COMMANDE, PROPAL, FACTURE, COMMANDE FOURNISSEUR OU FACTURE FOURNISSEUR
		 */
		if ($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT'	|| $action == 'LINEBILL_INSERT' 
		|| $action == 'LINEORDER_SUPPLIER_CREATE' || $action == 'LINEBILL_SUPPLIER_CREATE') {
			
			$origin=__get('origin', $object->origin);
			$originid=__get('originid', $object->origin_id);
			$dp_pu_devise = __get('dp_pu_devise');
			
			$idProd=__get('idprodfournprice', __get('productid', __get('idprod', __get('id', 0)) )  ); 
			if(empty($idProd) && isset($_REQUEST['valid']) && !empty($object->lines)){
				$idProd = $object->lines[count($object->lines)-1]->fk_product;
				
				if($action==='LINEORDER_SUPPLIER_CREATE') {
					list($element, $element_line, $fk_element) = TMultidevise::getTableByAction($action);
					$sql = "SELECT devise_code, devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".(($object->{"fk_".$element})? $object->{"fk_".$element} : $object->id) ;
					
	                $resql = $db->query($sql);
	                $res = $db->fetch_object($resql);
					$devise_taux = __val($res->devise_taux,1);
					
					
					if(empty($devise_taux)) {
						if(empty($origin) && empty($currency)) $currency = TMultidevise::getThirdCurrency($object->socid);
						TMultidevise::createDoc($db, $object,$currency,$origin);
					} 
					
				}
			
			}
			
			$quantity = __get('qty',0);	 
			$quantity_predef=__get('qty_predef',0);	
			$remise_percent =__get('remise_percent',0);	 
			$idprodfournprice = __get('idprodfournprice',0);	 
			$fournprice=__get('fournprice_predef','');
			$buyingprice=__get('buying_price_predef','');	 
				
			$actioncard = __get('action','');
				
			if($actioncard=='confirm_clone') {
				
				null; //TMultidevise::updateLine($db, $object,$user, $action,$object->rowid,$object->remise_percent);
				
			}
			else {
				//Spécifique nomadic : récupération des services pour la facturation depuis une expédition   ticket 1774
				if ($conf->clinomadic->enabled) {
					if ($object->product_type == 1 && empty($object->origin)) {
						$object->origin = 'shipping';
					}
				}

				TMultidevise::insertLine($db, $object,$user, $action, $origin, $originid, $dp_pu_devise,$idProd,$quantity,$quantity_predef,$remise_percent,$idprodfournprice,$fournprice,$buyingprice);
				
			}				
		}
	
		/*
		 * MODIFICATION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE' 
		|| $action == 'LINEORDER_SUPPLIER_UPDATE' || $action == 'LINEBILL_SUPPLIER_UPDATE'){
			

			switch ($action) {
				case "LINEORDER_SUPPLIER_UPDATE":
					$id_line = __get('elrowid',0);
					break;
				case 'LINEBILL_SUPPLIER_UPDATE':
					$id_line = __get('lineid',0);
					break;
				default:
					$id_line = __get('id',0);
					break;
			}

			$remise_percent =__get('remise_percent',0);
			
			TMultidevise::updateLine($db, $object,$user, $action,$id_line,$remise_percent);

		}
	
		/*
		 * SUPPRESSION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if ($action == 'LINEORDER_DELETE' || $action == 'LINEPROPAL_DELETE' || $action == 'LINEBILL_DELETE'
		|| $action == 'LINEORDER_SUPPLIER_DELETE' || $action == 'LINEBILL_SUPPLIER_DELETE') {

			TMultidevise::deleteLine($db, $object,$action,__get('id'),__get('lineid') );

		}
		
		/*
		 * AJOUT D'UN PAIEMENT 
		 */
		if($action == "PAYMENT_CUSTOMER_CREATE" || $action == "PAYMENT_SUPPLIER_CREATE"){
			//pre($_REQUEST);
			
			TMultidevise::addpaiement($db,$_REQUEST,$object,$action);

		}
	
		if($action == "BEFORE_PROPAL_BUILDDOC" || $action == "BEFORE_ORDER_BUILDDOC"  || $action == "BEFORE_BILL_BUILDDOC" || $action == "BEFORE_ORDER_SUPPLIER_BUILDDOC" || $action == "BEFORE_BILL_SUPPLIER_BUILDDOC"){
			
			
			TMultidevise::preparePDF($object,$object->societe);
			
		}	
		
		if($action == "PROPAL_BUILDDOC" || $action == "ORDER_BUILDDOC"  || $action == "BILL_BUILDDOC" || $action == "ORDER_SUPPLIER_BUILDDOC" || $action == "BILL_SUPPLIER_BUILDDOC") {
			
			$object->fetch($object->id);

		}
		
		//Sur l'ajout du paiement dans le compte bancaire on multiplie toujours le montant dolibarr par le taux de la devise du compte bancaire
		if($action == "PAYMENT_ADD_TO_BANK"){
			
			$db->commit();
			
			//Récupération du taux de la devise du compte bancaire
			$sql = "SELECT cr.rate
					FROM ".MAIN_DB_PREFIX."currency_rate as cr
						LEFT JOIN ".MAIN_DB_PREFIX."currency as c ON (c.rowid = cr.id_currency)
						LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON (ba.currency_code = c.code)
					WHERE ba.rowid = ".$_REQUEST['accountid']."
					ORDER BY cr.dt_sync DESC LIMIT 1";

			$resql = $db->query($sql);
			$res = $db->fetch_object($resql);

			$rate = $res->rate;
			
			//Mise à jour de l'objet avec le total * taux
			$total = $object->total * $rate;

			$db->query('UPDATE '.MAIN_DB_PREFIX.'bank SET amount = "'.$total.'"
						WHERE rowid = (SELECT rowid 
									   FROM '.MAIN_DB_PREFIX.'bank 
									   WHERE amount = '.$object->total.'
									   		AND fk_account = '.$_REQUEST['accountid'].' 
									   ORDER BY rowid DESC LIMIT 1)');
		}
		
		if($action == "DISCOUNT_CREATE") {
			
			global $conf;
			/*
			// On récupère la devise du client
			$sql = "SELECT devise_code";
			$sql.= " FROM ".MAIN_DB_PREFIX."societe";
			$sql.= " WHERE rowid = ".$object->fk_soc;
			$resql = $db->query($sql);
			if($resql->num_rows > 0) {
				$res = $db->fetch_object($resql);
				$devise_code = $res->devise_code;
			}
			
			// On récupère le taux de conversion pour cette devise
			$sql = "SELECT rate";
			$sql.= " FROM ".MAIN_DB_PREFIX."currency_rate cr";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."currency c on c.rowid = cr.id_currency";
			$sql.= " WHERE code = '".$devise_code."'";
			$sql.= " AND cr.id_entity = ".$conf->entity;
			$sql.= " ORDER BY cr.dt_sync DESC";
			$sql.= " LIMIT 1";
			$resql = $db->query($sql);
			if($resql->num_rows > 0) {
				$res = $db->fetch_object($resql);
				$rate = $res->rate;
			}
			*/
			
			dol_include_once("/compta/facture/class/facture.class.php");
			
			$fact = new Facture($this->db);
			$fact->fetch($_REQUEST['facid']);
			
			// On récupère la devise de la facture
			$sql = "SELECT devise_code";
			$sql.= " FROM ".MAIN_DB_PREFIX."facture";
			$sql.= " WHERE rowid = ".$_REQUEST['facid'];
			$resql = $this->db->query($sql);
			$res = $this->db->fetch_object($resql);
			$monnaie_facture = $res->devise_code;
			
			// On récupère la monnaie du dolibarr
			$monnaie_dolibarr = $conf->global->MAIN_MONNAIE;
			
			// Si la monnaie est différente de celle du dolibarr
			if($monnaie_dolibarr !== $monnaie_facture) {
			
				$montant_total_acompte = 0;
				foreach($fact->lines as $line) {
					$sql = "SELECT devise_mt_ligne";
					$sql.= " FROM ".MAIN_DB_PREFIX."facturedet";
					$sql.= " WHERE rowid = ".$line->rowid;
					$resql = $this->db->query($sql);
					if($resql->num_rows > 0) {
						$res = $this->db->fetch_object($resql);
						$devise_mt_ligne = $res->devise_mt_ligne;
					}
					$montant_total_acompte += $devise_mt_ligne;
				}
				
				/*$sql = " UPDATE ".MAIN_DB_PREFIX."societe_remise_except";
				$sql.= " SET amount_ht = ".$montant_total_acompte;
				$sql.= ", amount_ttc = ".$montant_total_acompte;
				$sql.= " WHERE rowid = ".$object->id;
				$resql = $this->db->query($sql);
				
				$this->db->commit();*/
				
			}
			
		}
		
		return 1;
	}
}
