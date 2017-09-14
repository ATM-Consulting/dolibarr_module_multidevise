<?php

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');

}

global $db;

$TEntityId = _getTEntityId();

$now = date('Y-m-d H:i:s');
$error = 0;
$TError = array();

$db->begin();

echo '<b>BEGIN</b><br /><br />';

foreach ($TEntityId as $fk_entity)
{
	echo 'entity = '.$fk_entity.'...';
	
	$select = 'SELECT \''.$now.'\', code, name, '.$fk_entity.', 0
			FROM '.MAIN_DB_PREFIX.'currency
			WHERE code NOT IN (
				SELECT code 
				FROM '.MAIN_DB_PREFIX.'multicurrency
				WHERE entity = '.$fk_entity.'
			)';
	
	$insert = 'INSERT INTO '.MAIN_DB_PREFIX.'multicurrency (date_create, code, name, entity, fk_user) '.$select;
	
	echo $insert.'<br />';
	$resql = $db->query($insert);
	if (!$resql)
	{
		$error++;
		$TError[] = $db->lasterror();
	}
}


if (!$error)
{
	$sql = 'SELECT 
			(SELECT count(*) FROM '.MAIN_DB_PREFIX.'currency_rate) as count_old_rates,
			(SELECT count(*) FROM '.MAIN_DB_PREFIX.'multicurrency_rate) as count_new_rates';
	
	$resql = $db->query($sql);
	if (!$resql)
	{
		$error++;
		$TError[] = $db->lasterror();
	}
	
	if (!$error && $db->num_rows($resql) > 0)
	{
		$obj = $db->fetch_object($resql);
		// Si mon count() des anciens taux est > au count() des nouveaux taux, c'est que le script n'a probablement pas encore été appelé
		if ($obj->count_old_rates > $obj->count_new_rates)
		{
			echo '<br />Insert rates<br />';
			$select = 'SELECT cr.dt_sync, cr.rate, mc.rowid as fk_multicurrency, cr.id_entity as fk_entity
					FROM '.MAIN_DB_PREFIX.'currency_rate cr
					INNER JOIN '.MAIN_DB_PREFIX.'currency c ON (c.rowid = cr.id_currency)
					INNER JOIN '.MAIN_DB_PREFIX.'multicurrency mc ON (mc.code = c.code AND mc.entity = cr.id_entity)';

			$insert = 'INSERT INTO '.MAIN_DB_PREFIX.'multicurrency_rate (date_sync, rate, fk_multicurrency, entity) '.$select;

			echo $insert.'<br />';
			$resql = $db->query($insert);
			if (!$resql)
			{
				$error++;
				$TError[] = $db->lasterror();
			}
		}
	}
	
}

// TODO update llx_societe @see TMultideviseClient
$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe s
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = s.devise_code AND m.entity=s.entity)
		SET s.multicurrency_code=s.devise_code, s.fk_multicurrency=m.rowid';
$resql = $db->query($sql);

// TODO update llx_product_price @see TMultideviseProductPrice



// TODO update llx_propal @see TMultidevisePropal

// TODO update llx_propaldet @see TMultidevisePropaldet

// TODO update llx_commande @see TMultideviseCommande

// TODO update llx_commandedet @see TMultideviseCommandedet

// TODO update llx_facture @see TMultideviseFacture

// TODO update llx_facturedet @see TMultideviseFacturedet

// TODO update llx_commande_fournisseur @see TMultideviseCommandeFournisseur

// TODO update llx_commande_fournisseurdet @see TMultideviseCommandeFournisseurdet

// TODO update llx_facture_fourn @see TMultideviseFactureFournisseur

// TODO update llx_facture_fourn_det @see TMultideviseFactureFournisseurdet

// TODO update llx_paiement_facture @see TMultidevisePaiementFacture

// TODO update llx_paiementfourn_facturefourn @see TMultidevisePaiementFactureFournisseur


if (!$error)
{
	$db->commit();
	echo '<b>COMMIT</b><br />';
}
else
{
	$db->rollback();
	echo '<b>ROLLBACK</b><br />';
	pre($TError, true);
}



function _getTEntityId()
{
	global $db;
	
	$TId = array();
	
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'entity';
	$resql = $db->query($sql);
	
	if ($resql)
	{
		while ($obj = $db->fetch_object($resql))
		{
			$TId[] = $obj->rowid;
		}
	}
	else
	{
		// table llx_entity doesn't exists, this mean no multicompany
		$TId[] = 1;
	}
	
	return $TId;
}



