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
		if($action == "ORDER_CREATE" || $action == "PROPAL_CREATE" || $action =="BILL_CREATE" ){
			
			if($action == "ORDER_CREATE")
				$table = "commande";
			elseif($action == "PROPAL_CREATE")
				$table = "propal";
			elseif($action =="BILL_CREATE")
				$table = "facture";
			
			if(isset($_REQUEST['currency']) && !empty($_REQUEST['currency'])){
				$resql = $db->query('SELECT c.rowid AS rowid, c.code AS code, cr.rate AS rate
									 FROM '.MAIN_DB_PREFIX.'currency AS c LEFT JOIN '.MAIN_DB_PREFIX.'currency_rate AS cr ON (cr.id_currency = c.rowid)
									 WHERE c.code = "'.$_REQUEST['currency'].'" AND cr.id_entity = '.$conf->entity.' ORDER BY cr.dt_sync DESC LIMIT 1');
				if($res = $db->fetch_object($resql)){
					$db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET fk_devise = '.$res->rowid.', devise_code = "'.$res->code.'", devise_taux = '.$res->rate.' WHERE rowid = '.$object->id);
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
				
				$resql = $this->db->query("SELECT devise_mt_total FROM ".MAIN_DB_PREFIX.$table_origin." WHERE rowid = ".$originid);
				$res = $this->db->fetch_object($resql);
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET devise_mt_total = '.$res->devise_mt_total.' WHERE rowid = '.$object->id);
			}
		}
		
		/*
		 *CREATION P.U. DEVISE + TOTAL DEVISE PAR LIGNE DE COMMANDE, PROPAL OU FACTURE
		 */
		if ($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEBILL_INSERT') {
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
			
			if($action == "LINEORDER_INSERT" || $action == 'LINEORDER_UPDATE'){
				$table = "commande";
				$tabledet = "commandedet";
				$object->update(true);
			}
			elseif($action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_UPDATE'){
				$table = "propal";
				$tabledet = "propaldet";
				$object->update(true);
			}
			elseif($action == 'LINEBILL_INSERT' || $action == 'LINEBILL_UPDATE'){
				$table = "facture";
				$tabledet = "facturedet";
				$object->update($user,true);
			}
			
			//Création a partir d'un objet d'origine (propale ou commande)
			if((!empty($object->origin) && !empty($object->origin_id)) || (!empty($_POST['origin']) && !empty($_POST['originid']))){
				
				/*echo '<pre>';
				print_r($object);
				echo '</pre>';*/
				
				if($_POST['origin'] == "propal"){
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
				
				$resql = $this->db->query("SELECT devise_pu, devise_mt_ligne FROM ".MAIN_DB_PREFIX.$tabledet_origin." WHERE rowid = ".$originid);
				$res = $this->db->fetch_object($resql);
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$tabledet.' SET devise_pu = '.$res->devise_pu.', devise_mt_ligne = '.$res->devise_mt_ligne.' WHERE rowid = '.$object->rowid);
				
				
			}
			else{//Création standard
				/*echo '<pre>';
				print_r($object);
				echo '</pre>';exit;*/
				 
				$idProd = 0;
				if(!empty($_POST['idprod'])) $idProd = $_POST['idprod'];
				if(!empty($_POST['productid'])) $idProd = $_POST['productid'];
				
				//Ligne de produit/service existant
				if(!empty($idProd) && $idProd != 0 && isset($_REQUEST['pu_devise_product']) && !empty($_REQUEST['pu_devise_product'])){
					$devise_mt_ligne = $_REQUEST['pu_devise_product'] * $_REQUEST['qty'];
					$sql = 'UPDATE '.MAIN_DB_PREFIX.$tabledet.' SET devise_pu = '.$_REQUEST['pu_devise_product'].', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid;
					$this->db->query($sql);
					
					$sql = "SELECT devise_taux FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".$object->{"fk_".$table};
					
					$resql = $this->db->query($sql);
					$res = $this->db->fetch_object($resql);
					
					$subprice_ttc = $_REQUEST['pu_devise_product'] / $res->devise_taux;
					$subprice = $subprice_ttc / (1 + ($object->tva_tx / 100));
					
					if($subprice != $object->subprice){
						$class = ucfirst($table);
						$parent_object = new $class($this->db);
						$parent_object->fetch($object->fk_{$table});
					
						$parent_object->updateline($object->rowid, $subprice, $object->qty, $object->remise_percent, $object->tva_tx);
					}
				}
				//Ligne libre
				elseif(isset($_REQUEST['pu_devise_libre']) && !empty($_REQUEST['pu_devise_libre'])){
					$devise_mt_ligne = $_REQUEST['pu_devise_libre'] * $_REQUEST['qty'];
					$this->db->query('UPDATE '.MAIN_DB_PREFIX.$tabledet.' SET devise_pu = '.$_REQUEST['pu_devise_libre'].', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
				}
				
				//MAJ du total devise de la commande/facture/propale
				$resql = $this->db->query('SELECT SUM(f.devise_mt_ligne) as total_devise 
										   FROM '.MAIN_DB_PREFIX.$tabledet.' as f LEFT JOIN '.MAIN_DB_PREFIX.$table.' as m ON (f.fk_'.$table.' = m.rowid)
										   WHERE m.rowid = '.$object->{'fk_'.$table});
				$res = $this->db->fetch_object($resql);
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".$object->{'fk_'.$table});
			}
		}
	
		/*
		 * MODIFICATION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if($action == 'LINEORDER_UPDATE' || $action == 'LINEPROPAL_UPDATE' || $action == 'LINEBILL_UPDATE'){
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
		
			if($action == "LINEORDER_INSERT" || $action == 'LINEORDER_UPDATE'){
				$table = "commande";
				$tabledet = "commandedet";
				$object->update(true);
			}
			elseif($action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_UPDATE'){
				$table = "propal";
				$tabledet = "propaldet";
				$object->update(true);
			}
			elseif($action == 'LINEBILL_INSERT' || $action == 'LINEBILL_UPDATE'){
				$table = "facture";
				$tabledet = "facturedet";
				$object->update($user,true);
			}
			
			$devise_mt_ligne = $_REQUEST['pu_devise'] * $_REQUEST['qty'];
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$tabledet.' SET devise_pu = '.$_REQUEST['pu_devise'].', devise_mt_ligne = '.($devise_mt_ligne - ($devise_mt_ligne * ($object->remise_percent / 100))).' WHERE rowid = '.$object->rowid);
			
			//MAJ du total devise de la commande/facture/propale
			$resql = $this->db->query('SELECT SUM(f.devise_mt_ligne) as total_devise 
									   FROM '.MAIN_DB_PREFIX.$tabledet.' as f LEFT JOIN '.MAIN_DB_PREFIX.$table.' as m ON (f.fk_'.$table.' = m.rowid)
									   WHERE m.rowid = '.$object->oldline->{'fk_'.$table});
			$res = $this->db->fetch_object($resql);
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET devise_mt_total = '.$res->total_devise." WHERE rowid = ".$object->oldline->{'fk_'.$table});
		}
	
		/*
		 * SUPPRESSION LIGNE DE COMMANDE, PROPAL OU FACTURE = MAJ DU MONTANT TOTAL DEVISE
		 */
		if ($action == 'LINEORDER_DELETE' || $action == 'LINEPROPAL_DELETE' || $action == 'LINEBILL_DELETE') {
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
			
			if($action == "LINEORDER_DELETE"){
				$table = "commande";
				$tabledet = "commandedet";
			}
			elseif($action == 'LINEPROPAL_DELETE'){
				$table = "propal";
				$tabledet = "propaldet";
			}
			elseif($action == 'LINEBILL_DELETE'){
				$table = "facture";
				$tabledet = "facturedet";
			}
			
			$resql = $this->db->query('SELECT SUM(devise_mt_ligne) as total_ligne 
									   FROM '.MAIN_DB_PREFIX.$tabledet.'
									   WHERE fk_'.$table.' = '.$object->{"fk_".$table});
									   
			$res = $this->db->fetch_object($resql);
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET devise_mt_total = '.$res->total_ligne." WHERE rowid = ".$object->{'fk_'.$table});
		}
		
		
		/*
		 * AJOUT D'UN PAIEMENT 
		 */
		if($action == "PAYMENT_CUSTOMER_CREATE" ){
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';
			
			echo '<pre>';
			print_r($_REQUEST);
			echo '</pre>';*/
			
			if(!empty($_REQUEST['devise'])){
				$this->db->commit();
				$this->db->commit();
				
				$note = "";
				$somme = 0.00;
				foreach($_REQUEST['devise'] as  $id_fac => $mt_devise){
					$id_fac = explode('_', $id_fac);
					$id_fac = $id_fac[1];
					$somme += str_replace(',','.',$mt_devise);
					
					$facture = new Facture($db);
					$facture->fetch($id_fac);
					
					$resql = $db->query('SELECT devise_mt_total FROM '.MAIN_DB_PREFIX.'facture WHERE rowid = '.$facture->id);
					$res = $db->fetch_object($resql);
					
					//Règlement total
					if($res->devise_mt_total == $mt_devise){
						$facture->set_paid($user);
						
						//Ajout de la note si des écarts sont lié aux conversions de devises
						if($_REQUEST['amount_'.$facture->id] < $facture->total_ttc)
							$note .= "facture : ".$facture->facnumber." => PERTE après conversion : ".($facture->total_ttc - $_REQUEST['amount_'.$facture->id]);
						elseif($_REQUEST['amount_'.$facture->id] > $facture->total_ttc)
							$note .= "facture : ".$facture->facnumber." => GAIN après conversion : ".($_REQUEST['amount_'.$facture->id] - $facture->total_ttc);
					}
					
					//MAJ du montant paiement_facture
					$db->query('UPDATE '.MAIN_DB_PREFIX.'paiement_facture SET devise_mt_paiement = "'.str_replace(',','.',$mt_devise).'"
						WHERE fk_paiement = '.$object->id.' AND fk_facture = '.$facture->id);
				}
				//MAJ du montant paiement
				$db->query('UPDATE '.MAIN_DB_PREFIX.'paiement SET devise_mt_paiement = "'.$somme.'", devise_taux = "'.$_REQUEST['taux_devise'].'"
							WHERE rowid = '.$object->id);
			}
		}
		
		return 1;
	}
}
