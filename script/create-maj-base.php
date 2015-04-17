<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	if(!defined('INC_FROM_DOLIBARR')) {
        define('INC_FROM_CRON_SCRIPT', true);
        require('../config.php');
    }
    
	dol_include_once('/multidevise/class/class.currency.php');

    $ATMdb=new TPDOdb;
    if(!defined('INC_FROM_DOLIBARR'))$ATMdb->debug=true;

	$o=new TMultideviseClient;
	$o->init_db_by_vars($ATMdb);

	$o=new TMultideviseProductPrice;
	$o->init_db_by_vars($ATMdb);

	
	$o=new TMultidevisePropal;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultidevisePropaldet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseFacture;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseFacturedet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseCommande;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseCommandedet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseCommandeFournisseur;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseCommandeFournisseurdet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseFactureFournisseur;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultideviseFactureFournisseurdet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultidevisePaiementFacture;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TMultidevisePaiementFactureFournisseur;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrency;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrencyRate;
	$o->init_db_by_vars($ATMdb);
	