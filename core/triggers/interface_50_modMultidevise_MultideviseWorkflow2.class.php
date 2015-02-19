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
 
class InterfaceMultideviseWorkflow2
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
		
		if(!defined('INC_FROM_DOLIBARR'))define('INC_FROM_DOLIBARR',true);
		dol_include_once('/tarif/config.php');
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/comm/propal/class/propal.class.php');
		dol_include_once("/societe/class/societe.class.php");
		dol_include_once("/core/lib/functions.lib.class.php");
		
		$db = &$this->db;
		
		/*
		 * Enregistrement sur PRODUCT_PRICE_MODIFY
		 */
		if($action == "PRODUCT_PRICE_MODIFY"){
			if(!empty($_REQUEST['currency'])){
				$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'currency WHERE code = "'.$_REQUEST['currency'].'" LIMIT 1');
				if($res = $db->fetch_object($resql)){
					//var_dump($object);exit;
					
					$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_price 
					SET fk_devise = '.$res->rowid.', devise_code = "'.$_REQUEST['currency'].'", devise_price=price
					WHERE fk_product = '.$object->id." AND price_level=".$object->level;
					$db->query($sql);
					
				}
			}
				
		}
		
 		/*
		 *CREATION P.U. DEVISE + TOTAL DEVISE PAR LIGNE DE COMMANDE, PROPAL OU FACTURE
		 */
		else if ($action == 'LINEORDER_INSERT' || $action == 'LINEPROPAL_INSERT' || $action == 'LINEBILL_INSERT') {
			
			/*echo '<pre>';
			print_r($object);
			echo '</pre>';exit;*/
			
			if($action == "LINEORDER_INSERT" || $action == 'LINEORDER_UPDATE'){
				$table = "commande";
				$tabledet = "commandedet";
			}
			elseif($action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_UPDATE'){
				$table = "propal";
				$tabledet = "propaldet";
			}
			elseif($action == 'LINEBILL_INSERT' || $action == 'LINEBILL_UPDATE'){
				$table = "facture";
				$tabledet = "facturedet";
			}
			
			$idligne = $object->rowid;
			$fk_product = $object->fk_product;

			$sql = "SELECT devise_code, devise_taux FROM ".MAIN_DB_PREFIX.$table." WHERE rowid=".$object->{'fk_'.$table};
			$res=$db->query($sql);
			
			if(!empty($res) && $fk_product > 0) {
				$obj = $db->fetch_object($res);
			
				$devise_code = $obj->devise_code;
				$devise_taux = $obj->devise_taux;

				$sql = "SELECT devise_price FROM ".MAIN_DB_PREFIX."product_price WHERE fk_product=".$fk_product." AND devise_code='".$devise_code."' ORDER BY rowid DESC LIMIT 1";
				$res = $db->query($sql);
				
				if(!empty($res) && $devise_taux>0 && $obj=$db->fetch_object($res)) {
					$devise_price = (float)$obj->devise_price;
					$price = $devise_price / $devise_taux;
					
					$object->subprice = $price;
					$object->devise_pu = $devise_price;
					
					$sql = "UPDATE ".MAIN_DB_PREFIX.$tabledet." SET subprice=".$price.",devise_pu=".$devise_price.",total_ht=subprice*qty,devise_mt_ligne=devise_pu*qty WHERE rowid=".$idligne;
					$db->query($sql);
				} else if (!empty($_REQUEST['fac_avoir'])) { // AVOIR
					$devise_price = $object->subprice;
					$price = $devise_price / $devise_taux;
						
					$object->subprice = $price;
					$object->devise_pu = $devise_price;

					$sql = "UPDATE ".MAIN_DB_PREFIX.$tabledet." SET subprice=".$price.",devise_pu=".$devise_price.",total_ht=subprice*qty,devise_mt_ligne=devise_pu*qty WHERE rowid=".$idligne;
					$db->query($sql);
				}
			} else if ($table == 'facture' && !empty($_REQUEST['fac_avoir'])) { // AVOIR
				// Récupération de la devise de la facture de base
				$sql = "SELECT devise_code, devise_taux FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ". $_REQUEST['fac_avoir'];
				$res = $db->query($sql);
				$obj = $db->fetch_object($res);

				if (!empty($obj)) {
					$devise_code = $obj->devise_code;
					$devise_taux = $obj->devise_taux;
				} else {
					$devise_taux = 1;
				}

				$devise_price = $object->subprice;
				$price = $devise_price / $devise_taux;
				
				$object->subprice = $price;
				$object->devise_pu = $devise_price;
				
				$sql = "UPDATE ".MAIN_DB_PREFIX.$tabledet." SET subprice=".$price.",devise_pu=".$devise_price.",total_ht=subprice*qty,devise_mt_ligne=devise_pu*qty WHERE rowid=".$idligne;
				$db->query($sql);
			}
			
				
		/*	var_dump($object);
			exit;
			*/
		}
		
		return 1;
	}
}
