<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
	
	require('../config.php');
	require('../class/multidevise.class.php');
	require('../class/class.currency.php');

	$ATMdb=new TPDOdb;
	$ATMdb->debug=true;

	$o=new TMultideviseClient;
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
	
	$o=new TMultidevisePaiement;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrency;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrencyRate;
	$o->init_db_by_vars($ATMdb);
	
	