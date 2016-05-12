#!/usr/local/bin/php
<?php
	
	//TODO récupérer les taux pour chaque entité en fonction de leur devise respective
	//TODO récupérer les taux de conversion uniquement pour les devises sélectionné en conf
	
	
	if(!defined('INC_FROM_DOLIBARR')) {
	       
	   define('INC_FROM_CRON_SCRIPT', true);
	   chdir(dirname(__FILE__));
	   require('../../config.php');
       
    }
	
	dol_include_once('/multidevise/class/class.currency.php');
	
	$url_list = TCurrenty_list_source;
	$url_rate =  strtr(TCurrenty_rate_source,array('{app_id}'=>TCurrenty_app_id));
	
	$TCurrency = url_get_content($url_list);
	
	$PDOdb=new TPDOdb;	
		
	foreach($TCurrency as $currency=>$label) {
		
		$c=new TCurrency;
	
		$c->loadByCode($PDOdb, $currency);
		
		$c->name = $label;
		$c->code = $currency;
		
		$c->save($PDOdb);
		
	}

	$TRate = url_get_content($url_rate);
	
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
				
					if($c->loadByCode($PDOdb, $currency)) {
						$c->addRate($rate,$id_entity);
						
						$c->save($PDOdb);
					}
				}
			}
		}
		else{ // Toutes les devises
			foreach($TRate->rates as $currency=>$rate) {
				echo "$currency $id_entity<br>";
				$rate = $rate * $coefRate;
				
				$c=new TCurrency;
			
				if($c->loadByCode($PDOdb, $currency)) {
					$c->addRate($rate,$id_entity);
					
					$c->save($PDOdb);
				}
			}
		}
	}

function url_get_content($url) {
	$TResults = array();
	
	if (ini_get('allow_url_fopen')) {
		$TResults = json_decode(file_get_contents($url));
	} else {
		if (function_exists('curl_init')) {
			$TResults = json_decode(load_curl($url));
		} else {
			$TResults = json_decode(fetchURL($url));
		}
	}
	
	return $TResults;
}

// Fonction appelée si allow_url_fopen = Off
function fetchURL($url){
	$url_parsed = parse_url($url);
	
	$host = $url_parsed['host'];
	$port = $url_parsed['port'];
	$path = $url_parsed['path'];
	
	if (!$port) {
		$port = 80;
	}
	
	if ($url_parsed['query'] != '') {
		$path.="?".$url_parsed['query'];
	}
	
	$out = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";
	$fp  = @fsockopen($host, $port, $errno, $errstr, 30);
	
	if (!$fp) {
		return 0;
		exit;
	}
	
	fwrite($fp, $out);
	$in = '';
	$header = '';
	
	// Passer l'en-tête HTML
	do {
		$header .= fgets ($fp, 4096 );
	} while (strpos($header, "\r\n\r\n" ) === false);
	
	// Lecture du contenu du fichier
	while (!feof($fp)) {
		$s = fgets($fp, 4096);
		$in.=$s;
	}
	
	fclose($fp);
	return $in;
}

function load_curl($url) {
	// Initialisation
	$curl = curl_init($url);
	
	// Option permettant de récupérer directement sous forme de chaine la valeur retournée par curl_exec()
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	
	$result = curl_exec($curl);
	
	curl_close($curl);
	
	return $result;
}

