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
	
	$o=new TMultidevisePaiementFacture;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrency;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TCurrencyRate;
	$o->init_db_by_vars($ATMdb);
	
	$url_list = TCurrenty_list_source;
	$url_rate =  strtr(TCurrenty_rate_source,array('{app_id}'=>TCurrenty_app_id));
	
	$TCurrency = json_decode( file_get_contents($url_list) );
	
	$db=new TPDOdb;	
		
	foreach($TCurrency as $currency=>$label) {
		
		$c=new TCurrency;
	
		$c->loadByCode($db, $currency);
		
		$c->name = $label;
		$c->code = $currency;
		
		$c->save($db);
		
	}

	$TRate = json_decode( file_get_contents($url_rate) );
	
	//Récupération des devises à convertir
	$TFromTo = explode(',',TCurrenty_from_to_rate);
	
	foreach($TFromTo as $fromto){
		echo "$fromto<br>";
		list($from, $to, $id_entity) = explode('-', $fromto);
		
		$fromRate = 0;
		$toRate = 0;
		$coefRate = 0;
		//print_r($TRate);
		foreach($TRate->rates as $currency=>$rate) {
			if($currency==$from) $fromRate = $rate;
			if($currency==$to) $toRate = $rate;
		}
	
		$coefRate = $fromRate / $toRate; // transform $from cof to $to coef
	
		print "$from = $fromRate, $to = $toRate :: coef = $coefRate<br>";
		
		//Récupération des devises activent
		if(strpos(TCurrenty_activate, ',')){
			$TRateActivate = explode(',',TCurrenty_activate);
			foreach($TRate->rates as $currency=>$rate) {
				
				if(in_array($currency, $TRateActivate)){
					echo "$currency $id_entity<br>";
					$rate = $rate * $coefRate;
					
					$c=new TCurrency;
				
					if($c->loadByCode($db, $currency)) {
						$c->addRate($rate,$id_entity);
						
						$c->save($db);
					}
				}
			}
		}
		else{ // Toutes les devises
			foreach($TRate->rates as $currency=>$rate) {
				echo "$currency $id_entity<br>";
				$rate = $rate * $coefRate;
				
				$c=new TCurrency;
			
				if($c->loadByCode($db, $currency)) {
					$c->addRate($rate,$id_entity);
					
					$c->save($db);
				}
			}
		}
	}
	
	