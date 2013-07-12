<?php
class TMultideviseClient extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'societe');
		parent::add_champs('fk_devise','type=entier;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePropal extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'propal');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePropaldet extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'propaldet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseFacture extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facture');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}
class TMultideviseFacturedet extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'facturedet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommande extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commande');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_taux,devise_mt_total','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultideviseCommandedet extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'commandedet');
		parent::add_champs('fk_devise','type=entier;');
		parent::add_champs('devise_pu,devise_mt_ligne','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TMultidevisePaiement extends TObjetStdDolibarr {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'paiement');
		parent::add_champs('devise_taux,devise_mt_paiement','type=float;');
		
		parent::_init_vars();
		parent::start();
	}
}