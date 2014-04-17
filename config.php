<?php

	require('config.default.php');

	define('TCurrenty_app_id', '8b986d8b3d514db8a519fb6914687512');
	define('TCurrenty_list_source', 'http://openexchangerates.org/api/currencies.json');
	define('TCurrenty_rate_source', 'http://openexchangerates.org/api/latest.json?app_id={app_id}');
	define('TCurrenty_activate', 'all'); // liste des devises disponible => DEVISE_1,DEVISE_2,DEVISE_N sinon "all" pour toutes
	define('TCurrenty_from_to_rate', 'USD-EUR-1'); //DEVISE_BASE - DEVISE_ENTITE - ID_ENTITE
	define('BUY_PRICE_IN_CURRENCY',1);
