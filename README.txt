TODO

	******************************* AJOUTER UN CRON ********************************************************************************
	
	0	2	*	*	*	php /var/www/dolibarr/htdocs/custom/multidevise/cron/1day/sync.php

	 ****************************** AJOUTER LES APPELS DE HOOK DANS LE FICHIER htdocs/compta/paiement.php **************************
	

	1 ) ligne 60 environ => juste avant de traitement des actions
	
		$object=new Facture($db);
		$extrafields = new ExtraFields($db);
		
		// Load object
		if ($facid > 0)
		{
			$ret=$object->fetch($id);
		}
		
		// Initialize technical object to manage hooks of paiements. Note that conf->hooks_modules contains array array
		$hookmanager = new HookManager($db);
		$hookmanager->initHooks(array('paiementcard'));
		
		$parameters=array('socid'=>$socid);
		$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks 
		

	2 ) ligne 507 environ => avant la création de l'en-tête du tableau listant les facture en attente de paiement
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('formAddObjectLine',$parameters,$facture,$action);    // Note that $action and $object may have been modified by hook
		

	3 ) ligne 597 environ => juste avant la fermeture de </tr> dans la boucle affichant les lignes de facture en attente de paiement 
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$objp,$action); // Note that $action and $object may have been modified by hook
		

	4 ) ligne 734 environ => juste avant la fermeture de </tr> dans la boucle affichant les lignes de paiement déjà effectué
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$objp,$action); // Note that $action and $object may have been modified by hook
	
	
	****************************** AJOUTER LES APPELS DE HOOK DANS LE FICHIER htdocs/fourn/facture/paiement.php **************************
	
	
	2 ) ligne 280 environ => avant la création de l'en-tête du tableau listant les facture en attente de paiement
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('formAddObjectLine',$parameters,$facture,$action);    // Note that $action and $object may have been modified by hook
		

	3 ) ligne 323 environ => entre les fermetures de </td></tr> dans la boucle affichant les lignes de facture en attente de paiement 
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$objp,$action); // Note that $action and $object may have been modified by hook
		

	4 ) ligne 511 environ => juste avant la fermeture de </tr> dans la boucle affichant les lignes de paiement déjà effectué
	
		$parameters=array();
		$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$objp,$action); // Note that $action and $object may have been modified by hook

	
	
	
	******************************** AJOUTER L'APPEL DE DU DOACTION POUR LE HOOK EXPEDITION htdocs/expedition/fiche.php ******************************
	
	
	1 ) Ligne 75 environ => juste avant le traitement des différentes actions
	
		(GETPOST('id'))? $object->fetch(GETPOST('id')): "" ;
	 	
	 	/*
		  * Actions
		 */
	 	
		$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
	
	
	******************************** AJOUTER L'APPEL DE HOOK SUR LA FICHE COMMANDE FOURNISSEUR htdocs/fourn/commande/fiche.php
	
	1 ) Ligne 1586 environ => remplacer la ligne d'ouverture de tr par 
		
			print '<tr id="row-'.$line->id.'" '.$bc[$var].'>';
		
	2 ) Ligne 1652 environ => juste avant la fermeture de "</tr>" de la ligne de commande
	
			if (is_object($hookmanager))
			{
				$parameters=array('fk_parent_line'=>$line->fk_parent_line, 'line'=>$line,'var'=>$var,'num'=>$num,'i'=>$i);
				$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$object,$action);
			}
			
	******************************** AJOUTER L'APPEL DE HOOK SUR LA FICHE FACTURE FOURNISSEUR htdocs/fourn/facture/fiche.php
	
	1 ) Ligne 1953 environ => remplacer la ligne d'ouverture de tr par 
		
			print '<tr id="row-'.$object->lines[$i]->rowid.'" '.$bc[$var].'>';
	
		
	2 ) Ligne 1647 environ => juste avant la fermeture de "</tr>" de la ligne de commande
	
			if (is_object($hookmanager))
			{
				$parameters=array('fk_parent_line'=>$line->fk_parent_line, 'line'=>$line,'var'=>$var,'num'=>$num,'i'=>$i);
				$reshook=$hookmanager->executeHooks('printObjectLine',$parameters,$object,$action);
			}
			
	************************** AJOUTER L'APPEL DU TRIGGER LORS DE LA SUPPRESSION D'UNE LIGNE DE COMMANDE FOURNISSEUR ******************
	
	1 ) Ligne 1410 dans la fonction deleteline dans htdocs/custom/fourn/class/fournisseur.commande.php
	
			global $conf, $langs, $user;
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface=new Interfaces($this->db);
			$result = $interface->run_triggers('LINEORDER_SUPPLIER_DELETE',$this,$user,$langs,$conf);
			if ($result < 0) {
				$error++; $this->errors=$interface->errors;
			}
			// Fin appel triggers
			
	***********************  DEPLACER L'APPEL DU TRIGGER DE SUPPRESSION DE LIGNE FACTURE FOUNISSEUR *************************
	
	1) Ligne 1262 à 1272 déplcer le code suivant juste après la requête de suppresion :
	
			if (! $error && ! $notrigger)
	        {
		    	// Appel des triggers
		    	include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		    	$interface=new Interfaces($this->db);
		    	$result=$interface->run_triggers('LINEBILL_SUPPLIER_DELETE',$this,$user,$langs,$conf);
		    	if ($result < 0) {
		    		$error++; $this->errors=$interface->errors;
		    	}
	        	// Fin appel triggers
	        }
 