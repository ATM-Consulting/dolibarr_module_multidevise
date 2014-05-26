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


	function _getMarge(&$fk_fournprice,&$buyingprice){
		global  $user, $conf;
		//echo $buyingprice;exit;
		//Récupération du fk_soc associé au prix fournisseur
		$resql = $this->db->query("SELECT pfp.fk_soc FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp WHERE pfp.rowid = ".$fk_fournprice);
		$res = $this->db->fetch_object($resql);
		$fk_soc = $res->fk_soc;
//		exit($fk_soc);
		//Récupération du taux de la devise fournisseur
		$sql = "SELECT cr.rate
				FROM ".MAIN_DB_PREFIX."currency_rate as cr
					LEFT JOIN ".MAIN_DB_PREFIX."currency as c ON (c.rowid = cr.id_currency)
					LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (cr.rowid = s.fk_devise)
				WHERE s.rowid = ".$fk_soc."
				ORDER BY cr.dt_sync DESC
				LIMIT 1";
		//echo $sql;exit;
		$resql = $this->db->query($sql);
		$res = $this->db->fetch_object($resql);
		
		//Calcul du prix d'achat devisé
		$buyingprice = (defined('BUY_PRICE_IN_CURRENCY') && BUY_PRICE_IN_CURRENCY) ? price2num($buyingprice) / $res->rate : $buyingprice ;
//		echo $buyingprice;exit;
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
		if($action == "ORDER_CREATE" || $action == "PROPAL_CREATE" || $action =="BILL_CREATE" 
		|| $action =="ORDER_SUPPLIER_CREATE" || $action =="BILL_SUPPLIER_CREATE"){
			
			$currency=__get('currency','');

			$origin=__get('origin', $object->origin);
			
			//Clonage => On récupère la devise et le taux de l'objet cloné
			if(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'confirm_clone'){
				
				$objectid = ($_REQUEST['id']) ? $_REQUEST['id'] : $_REQUEST['facid'] ;

				$resql = $db->query('SELECT o.fk_devise, o.devise_code, o.devise_taux
									 FROM '.MAIN_DB_PREFIX.$object->table_element.' AS o
									 WHERE o.rowid = '.$objectid);

				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET fk_devise = '.$res->fk_devise.', devise_code = "'.$res->devise_code.'", devise_taux = '.$res->devise_taux.' WHERE rowid = '.$object->id);
				}
				
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
			$quantity = __get('qty',0);	 
			$quantity_predef=__get('qty_predef',0);	
			$remise_percent =__get('remise_percent',0);	 
			$idprodfournprice = __get('idprodfournprice',0);	 
			$fournprice=__get('fournprice_predef','');
			$buyingprice=__get('buying_price_predef','');	 

			TMultidevise::insertLine($db, $object,$user, $action, $origin, $originid, $dp_pu_devise,$idProd,$quantity,$quantity_predef,$remise_percent,$idprodfournprice,$fournprice,$buyingprice);
		}
	
		/*
		 * MODIFICATION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE' 
		|| $action == 'LINEORDER_SUPPLIER_UPDATE' || $action == 'LINEBILL_SUPPLIER_UPDATE'){
				
			$id_line =__get('id',0);	 
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
			//TODO à mettre dnas la classe en fonction
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
		
		if($action == "PROPAL_BUILDDOC" || $action == "ORDER_BUILDDOC"  || $action == "BILL_BUILDDOC" || $action == "ORDER_SUPPLIER_BUILDDOC" || $action == "BILL_SUPPLIER_BUILDDOC") {
			
			$object->fetch($object->id);
			
		}
		
		
		return 1;
	}
}
