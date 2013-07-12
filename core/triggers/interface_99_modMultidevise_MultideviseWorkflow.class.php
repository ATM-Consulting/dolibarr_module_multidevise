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
		/*
		if(!defined('INC_FROM_DOLIBARR'))define('INC_FROM_DOLIBARR',true);
		dol_include_once('/tarif/config.php');
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/fourn/class/fournisseur.commande.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/comm/propal/class/propal.class.php');
		
		if ($action == 'LINEORDER_INSERT' || $action == 'LINEORDER_UPDATE' ||
			$action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_UPDATE' ||
			$action == 'LINEBILL_INSERT' || $action == 'LINEBILL_UPDATE') {
			
			$prix = 0;
			$tva_tx = 0;
			$remise = !empty($_POST['remise_percent']) ? $_POST['remise_percent'] : 0;
			$poids = 0;
			$weight_units = 0;
			
			
			//Création a partir d'un objet d'origine (propale ou commande)
			if((!empty($object->origin) && !empty($object->origin_id)) || (!empty($_POST['origin']) && !empty($_POST['originid']))){
				
				if($_POST['origin'] == "propal"){
					$table = "propaldet";
					$propal = new Propal($this->db);
					$propal->fetch($_POST['originid']);
					
					foreach($propal->lines as $line){
						if($line->rang == $object->rang)
							$originid = $line->rowid;
					}
	        	}
				elseif($object->origin == "commande"){
					$table = "commandedet";
					$originid = $object->origin_id;
				}
				
				$prix = $object->subprice;
				$tva_tx = $object->tva_tx;
				$remise = $object->remise_percent;
				$idProd = $object->fk_product;
				
				$resql = $this->db->query("SELECT poids, tarif_poids FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".$originid);
				$res = $this->db->fetch_object($resql);
				
				$poids = $res->tarif_poids;
				$weight_units = $res->poids;
				
				$qte_totale = $object->qty * $poids * pow(10, $weight_units);
			
			}//Création directement a partir du formulaire pour addline
			elseif(!empty($_POST['poids'])){ // Si poids renseigné alors recherche prix par conditionnement
				$poids = $_POST['poids'];
				$weight_units = $_POST['weight_units'];
				$idProd = 0;
				if(!empty($_POST['idprod'])) $idProd = $_POST['idprod'];
				if(!empty($_POST['productid'])) $idProd = $_POST['productid'];
				
				// Ajout d'un produit/service existant
				if(!empty($idProd)){
					
					// Quantité totale de produit ajoutée dans la ligne
					$qte_totale = $_POST['qty'] * $poids * pow(10, $weight_units);
					
					$sql = "SELECT quantite, unite, prix, unite_value, tva_tx, remise_percent
							FROM ".MAIN_DB_PREFIX."tarif_conditionnement
							WHERE fk_product = ".$idProd."
							ORDER BY unite_value DESC, quantite DESC";
					
					$resql = $this->db->query($sql);
					if($resql) { // Prix par conditionnement
						$found = false;
						while($res = $this->db->fetch_object($resql)){
							$qte_totale_grille = $res->quantite * pow(10, $res->unite_value);
							if($qte_totale_grille <= $qte_totale) {
								//Récupération de la remise
								
								if(!empty($res->remise_percent) && empty($remise))
									$remise = $res->remise_percent;
								elseif($remise != $res->remise_percent)
									$remise = $res->remise_percent;
								
								$prix = $res->prix;
								$tva_tx = $res->tva_tx;
								
								$found = true;
								break;
							}
						}
						
						//Quantité en dehors de la grille alors retourner erreur
						if(!$found) {
							$this->db->rollback();
							$this->db->rollback();
							$object->error = "Quantité trop faible";
							return -1;
						}
					} else { // Pas de grille de tarif, on prend le prix unitaire
						$prix = $object->price;
						$tva_tx = $object->tva_tx;
					}
				}
			}/* else if (false) { // TODO : Prix par quantité
				
			}*/
			
			/*$product = new Product($this->db);
			$product->fetch($idProd);
			
			$object->subprice = $prix;
			$object->price = $prix; // Deprecated in Dolibarr
			$object->tva_tx = $tva_tx;
			$object->fk_parent_line = NULL;
			$object->remise_percent = $remise;
			$object->remise = $remise; // Deprecated in Dolibarr
			
			if(get_class($object) == 'FactureLigne') $object->update($user, true);
			else $object->update(true);
			
			//MAJ des totaux de la ligne de commande
			$object->total_ht = ($qte_totale * $prix / pow(10, $product->weight_units)) * (1 - $remise / 100);
			$object->total_tva = ($object->total_ht * (1 + ($tva_tx/100))) - $object->total_ht;
			$object->total_ttc = $object->total_ht + $object->total_tva;
			$object->update_total();
			
			if(get_class($object) == 'PropaleLigne') $table = 'propaldet';
			if(get_class($object) == 'OrderLine') $table = 'commandedet';
			if(get_class($object) == 'FactureLigne') $table = 'facturedet'; 
			$this->db->query("UPDATE ".MAIN_DB_PREFIX.$table." SET tarif_poids = ".$poids.", poids = ".$weight_units." WHERE rowid = ".$object->rowid);
			
			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->rowid);
		}

		return 1;*/
	}
}
