#!/usr/local/bin/php
<?php
	
	//TODO récupérer les taux de conversion uniquement pour les devises sélectionné en conf
	
	define('INC_FROM_CRON_SCRIPT', true);
	chdir(dirname(__FILE__));
    
    require('../../config.php');
	dol_include_once('/multidevise/class/class.currency.php');
	
	$url_list = $conf->global->MULTICURRENCY_LIST_SOURCE;
	$url_rate =  $conf->global->MULTICURRENCY_RATE_SOURCE;
	
	$TCurrency = json_decode( file_get_contents($url_list) );
	
	$PDOdb=new TPDOdb;	
		
	foreach($TCurrency as $currency=>$label) {
		
		$c=new TCurrency;
	
		$c->loadByCode($PDOdb, $currency);
		
		$c->name = $label;
		$c->code = $currency;
		
		$c->save($PDOdb);
		
	}

	$TRate = json_decode( file_get_contents($url_rate) );
	
    //Récupération des devises à convertir
    $TFromTo=array();
    if($conf->multicompany->enabled) {
            
        $Tab = $PDOdb->ExecuteAsArray("SELECT e.rowid as entity,c.value as currency 
            FROM ".MAIN_DB_PREFIX."entity e
            LEFT JOIN ".MAIN_DB_PREFIX."const c ON (e.rowid=c.entity AND c.name='MAIN_MONNAIE')");
         
        foreach($Tab as $row) {
            $TFromTo[] = array('USD', $row->currency, $row->entity);
        }
            
    }
    else{
     
        $TFromTo[] = array('USD', $conf->currency, 1);
    
    }    
    
	foreach($TFromTo as $fromto){
		
		list($from, $to, $id_entity) =  $fromto;
		print "$from, $to, $id_entity<br>";
        
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
		if(!empty($conf->global->MULTICURRENCY_FILTE)){
			$TRateActivate = explode(',',$conf->global->MULTICURRENCY_FILTE);
			foreach($TRate->rates as $currency=>$rate) {
				
				if(in_array($currency, $TRateActivate)){
					echo "$currency $rate $id_entity<br>";
					$rate = $rate * $coefRate;
					
					$c=new TCurrency;
				
					if($c->loadByCode($PDOdb, $currency)) {
						$c->addRate($rate,$id_entity);
						
						$c->save($PDOdb);
					}
				}
			}
		}
		else{ // Toutes les devises
			foreach($TRate->rates as $currency=>$rate) {
				echo "$currency $rate $id_entity<br>";
				$rate = $rate * $coefRate;
				
				$c=new TCurrency;
			
				if($c->loadByCode($PDOdb, $currency)) {
					$c->addRate($rate,$id_entity);
					
					$c->save($PDOdb);
				}
			}
		}
	}	
