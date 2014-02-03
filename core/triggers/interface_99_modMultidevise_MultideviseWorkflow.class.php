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
	function run_trigger($action,$object,$user,$langs,$conf)
	{
		global $db, $user, $conf;
		if(!defined('INC_FROM_DOLIBARR'))define('INC_FROM_DOLIBARR',true);
		dol_include_once('/tarif/config.php');
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/comm/propal/class/propal.class.php');
		dol_include_once("/societe/class/societe.class.php");
		dol_include_once("/core/lib/functions.lib.class.php");
		
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
				
				/*echo '<pre>';
				print_r($object);
				echo '</pre>';
				
				echo '<pre>';
				print_r($_REQUEST);
				echo '</pre>';
				
				exit;*/
				
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
				
				if($object->origin == "shipping"){
					
					$this->db->commit();
					$this->db->commit();
					$this->db->commit();

					$resql = $this->db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$object->fk_facture);
					$res = $this->db->fetch_object($resql);

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $res->devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $res->devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
					
				}
				else{
					
					$resql = $this->db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet_origin." WHERE rowid = ".$originid);
					$res = $this->db->fetch_object($resql);
					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_pu = '.$res->devise_pu.', devise_mt_ligne = '.$res->devise_mt_ligne.' WHERE rowid = '.$object->rowid);
					
					/*$this->db->commit();
					$this->db->commit();
					$this->db->commit();
					
					$devise_mt_ligne = $res->devise_mt_ligne;
					
					$sql = 'SELECT devise_mt_total 
							FROM '.MAIN_DB_PREFIX.$element.'
							WHERE rowid = '.$object->{"fk_".$element};
					
					$resql = $this->db->query($sql);
					$res = $this->db->fetch_object($resql);
					
					//echo 'UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.($res->devise_mt_total + $devise_mt_ligne)." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id) ; exit;
					
					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element.' SET devise_mt_total = '.($res->devise_mt_total + $devise_mt_ligne)." WHERE rowid = ".(($object->{'fk_'.$element})? $object->{'fk_'.$element} : $object->id) );*/

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
				
				//echo $idProd; exit;
				//Ligne de produit/service existant
				if(!empty($idProd) && $idProd != 0 && isset($_REQUEST['np_pu_devise']) && !empty($_REQUEST['np_pu_devise'])){
					
					/*echo '<pre>';
					print_r($object);
					echo '</pre>'; exit;*/
					
					//Si module tarif activé, on recalcule le prix devisé une fois que le tarif par conditionnement a été calculé
					if($conf->tarif->enabled){
						$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$element." WHERE rowid = ".$object->{"fk_".$element};

						$resql = $this->db->query($sql);
						$res = $this->db->fetch_object($resql);
						
						$devise_pu = $object->subprice * $res->devise_taux;
					}
					else{//Sinon on prends le prix devisé passé dans le formulaire
						$devise_pu = $_REQUEST['np_pu_devise'];
					}

					$devise_mt_ligne = $devise_pu * $_REQUEST['qty'];
					$sql = 'UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$devise_pu.', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid;
					$this->db->query($sql);
				}
				//Ligne libre
				elseif(isset($_REQUEST['dp_pu_devise']) && !empty($_REQUEST['dp_pu_devise'])){
					
					$devise_mt_ligne = $_REQUEST['dp_pu_devise'] * $_REQUEST['qty'];

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$_REQUEST['dp_pu_devise'].', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
					
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
			print_r($_REQUEST);
			echo '</pre>';exit;*/
			
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
			
			if($object->origin == "shipping"){
					$this->db->commit();
					$this->db->commit();
					$this->db->commit();

					$resql = $this->db->query("SELECT devise_taux FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$object->fk_facture);
					$res = $this->db->fetch_object($resql);

					$this->db->query('UPDATE '.MAIN_DB_PREFIX.'facturedet SET devise_pu = '.round($object->subprice * $res->devise_taux,2).', devise_mt_ligne = '.round(($object->subprice * $res->devise_taux) * $object->qty,2).' WHERE rowid = '.$object->rowid);
					
			}
			else{
				
				$devise_mt_ligne = $_REQUEST['dp_pu_devise'] * $_REQUEST['qty'];
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$element_line.' SET devise_pu = '.$_REQUEST['dp_pu_devise'].', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
				
			}
			
			//MAJ du total devise de la commande/facture/propal
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
			
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$parent_object.' SET devise_mt_total = '.$res->total_ligne." WHERE rowid = ".(($object->{'fk_'.$parent_object}) ? $object->{'fk_'.$parent_object} : $_REQUEST['id'] ));
			
		}
		
		/*
		 * SUPPRESSION LIGNE DE COMMANDE OU FACTURE FOURNISSEUR = MAJ DU MONTANT TOTAL DEVISE
		 */
		if ($action == 'LINEORDER_SUPPLIER_DELETE' || $action == 'LINEBILL_SUPPLIER_DELETE') {
			
			$sql = 'SELECT SUM(devise_mt_ligne) as total_ligne 
				    FROM '.MAIN_DB_PREFIX.$object->table_element_line.' 
				    WHERE '.$object->fk_element.' = '.$_REQUEST['id'];

			$resql = $this->db->query($sql);
			$res = $this->db->fetch_object($resql);

			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET devise_mt_total = '.$res->total_ligne." WHERE rowid = ".(($object->{'fk_'.$object->table_element}) ? $object->{'fk_'.$object->table_element} : $_REQUEST['id'] ));
			
		}
		
		
		/*
		 * AJOUT D'UN PAIEMENT 
		 */
		if($action == "PAYMENT_CUSTOMER_CREATE" || $action == "PAYMENT_SUPPLIER_CREATE "){
			
			$TDevise=array();
			foreach($_REQUEST as $key=>$value) {
				
				$mask = 'amount_';
				if(strpos($key, $mask)===0) {
					
					$id_facture = (int)substr($key, strlen($mask));
					$TDevise[$id_facture] = $value; // On récupère la liste des factures et le montant du paiement
					
				}
				
			}
			
			if(!empty($TDevise)){
				$this->db->commit();
				$this->db->commit();
				
				$note = "";
				$somme = 0.00;
				foreach($TDevise  as $id_fac => $mt_devise){
					$somme += str_replace(',','.',$mt_devise); //TODO à quoi ça sert?
					
					$facture = new Facture($db);
					$facture->fetch($id_fac);
					
					$sql = 'SELECT devise_mt_total, devise_code FROM '.MAIN_DB_PREFIX.'facture WHERE rowid = '.$facture->id;					
					$resql = $db->query($sql);
					$res = $db->fetch_object($resql);
					
					$account = new Account($db);
					$account->fetch($_REQUEST['accountid']);
					
					//Règlement total
					if($res->devise_mt_total == $mt_devise){ // TODO pourquoi ne passer ce test que si le montant d'un paiement est égal au total de la facture ?
						$facture->set_paid($user);
						
						if($account->currency_code == $res->devise_code) {
							return null;
						} else {
							// TODO Ecriture comptable à enregistrer dans un compte. En dessous la note n'a pas de sens : ($_REQUEST['amount_'.$facture->id] - $facture->total_ttc) ne correspond jamais à un gain ou à une perte suite à une conversion
							
							//Ajout de la note si des écarts sont lié aux conversions de devises
							if($_REQUEST['amount_'.$facture->id] < $facture->total_ttc)
								$note .= "facture : ".$facture->facnumber." => PERTE après conversion : ".($facture->total_ttc - $_REQUEST['amount_'.$facture->id]);
							elseif($_REQUEST['amount_'.$facture->id] > $facture->total_ttc)
								$note .= "facture : ".$facture->facnumber." => GAIN après conversion : ".($_REQUEST['amount_'.$facture->id] - $facture->total_ttc);
						}
					}

					//MAJ du montant paiement_facture
					$db->query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET devise_mt_paiement = "'.str_replace(',','.',$mt_devise).'" , devise_taux = "'.$_REQUEST['taux_devise'].'", devise_code = "'.$res->devise_code.'"
								WHERE fk_paiement = '.$object->id.' AND fk_facture = '.$facture->id);
				}
			}
		}
		
		return 1;
	}
}
