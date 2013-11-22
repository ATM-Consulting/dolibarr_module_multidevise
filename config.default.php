<?php

	define('ROOT','/var/www/dolibarr/htdocs/');
	define('COREROOT','/var/www/ATM/atm-core/');
	define('COREHTTP','http://127.0.0.1/ATM/atm-core/');
	define('HTTP','http://127.0.0.1/dolibarr/');

	if(defined('INC_FROM_CRON_SCRIPT')) {
    	require_once(ROOT."master.inc.php");    
    }
    else{
    	require_once(ROOT."main.inc.php");
    }

    define('DB_HOST',$dolibarr_main_db_host);
    define('DB_NAME',$dolibarr_main_db_name);
    define('DB_USER',$dolibarr_main_db_user);
    define('DB_PASS',$dolibarr_main_db_pass);
    define('DB_DRIVER','mysqli');

	define('DOL_PACKAGE', true);
	define('USE_TBS', true);

	require(COREROOT.'inc.core.php');
	 
	define('DOL_ADMIN_USER', 'admin');
	define('TCurrenty_app_id', '8b986d8b3d514db8a519fb6914687512');
	define('TCurrenty_list_source', 'http://openexchangerates.org/api/currencies.json');
	define('TCurrenty_rate_source', 'http://openexchangerates.org/api/latest.json?app_id={app_id}');
	define('TCurrenty_activate', 'all'); // liste des devises disponible => DEVISE_1,DEVISE_2,DEVISE_N sinon "all" pour toutes
	define('TCurrenty_from_to_rate', 'USD-EUR-1'); //DEVISE_BASE - DEVISE_ENTITE - ID_ENTITE
