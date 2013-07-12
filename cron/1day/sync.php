<?

	require('../../config.php');
	include(ROOT.'custom/multidevise/class/class.currency.php');
	
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
	list($from, $to) = explode('-', TCurrenty_from_to_rate);
	
	$fromRate = 0;
	$toRate = 0;
	$coefRate = 0;
	//print_r($TRate);
	foreach($TRate->rates as $currency=>$rate) {
		if($currency==$from) $fromRate = $rate;
		if($currency==$to) $toRate = $rate;
	}

	$coefRate = $fromRate / $toRate; // transform USD cof to EUR coef

	print "$from = $fromRate, $to = $toRate :: coef = $coefRate";
	foreach($TRate->rates as $currency=>$rate) {

		$rate = $rate * $coefRate;
		
		$c=new TCurrency;
	
		if($c->loadByCode($db, $currency)) {
			$c->addRate($rate);
			
			$c->save($db);
		}

	}	