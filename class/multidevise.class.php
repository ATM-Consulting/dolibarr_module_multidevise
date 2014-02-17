<?php
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