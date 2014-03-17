<?php
class TMultidevise{
	
	static function doActionsMultidevise(&$parameters, &$object, &$action, &$hookmanager) {
		global $langs, $db, $conf, $user;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('propalcard',explode(':',$parameters['context']))
			|| in_array('expeditioncard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))){
			
        	if ($action == 'builddoc')
			{
				// 1 - Dans le haut du document
				 
				//Modification des prix si la devise est différente
				if(!in_array('expeditioncard',explode(':',$parameters['context']))){
					
					$resl = $db->query('SELECT devise_code FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id);
					$res = $db->fetch_object($resl);
					$devise_change = false;
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
						
						$resl = $db->query('SELECT devise_pu, devise_mt_ligne FROM '.MAIN_DB_PREFIX.$object->table_element_line.' WHERE rowid = '.$line->rowid);
						$res = $db->fetch_object($resl);

						if($res){
							
							$line->tva_tx = 0;
							$line->subprice = round($res->devise_pu,2);
							$line->price = round($res->devise_pu,2);
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
							$result=delivery_order_pdf_create($db, $object, $object->modelpdf, $outputlangs);
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