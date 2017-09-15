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
		pre($db->lasterror());
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
		pre($db->lasterror());
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
				pre($db->lasterror());
			}
		}
	}
	
}

// TODO update llx_societe @see TMultideviseClient
echo 'Update '.MAIN_DB_PREFIX.'societe ...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe s
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = s.devise_code AND m.entity = s.entity)
		SET s.multicurrency_code=s.devise_code, s.fk_multicurrency=m.rowid';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_product_price @see TMultideviseProductPrice
echo 'Update '.MAIN_DB_PREFIX.'product_price step 1...';
// 1er update pour init la devise code par défaut
$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_price pp
	SET pp.devise_code = (SELECT c.value FROM '.MAIN_DB_PREFIX.'const c WHERE c.name = \'MAIN_MONNAIE\' AND c.entity = pp.entity)
	WHERE pp.devise_code IS NULL';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

echo 'Update '.MAIN_DB_PREFIX.'product_price step 2...';
// 2eme update pour avoir le bon code devise + fk_multicurrency
$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_price pp
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = pp.devise_code AND m.entity = pp.entity)
		SET	 pp.multicurrency_code = pp.devise_code
			,pp.fk_multicurrency = m.rowid';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

echo 'Update '.MAIN_DB_PREFIX.'product_price step 3...';
// 3eme update pour s'occuper du prix HT, taux et prix TTC

// TODO requête fausse à débug ... :/
$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_price pp,
		(
			SELECT mr.rate
			FROM llx_multicurrency_rate mr
			INNER JOIN llx_product_price pp2 ON (pp2.fk_multicurrency = mr.fk_multicurrency)
			WHERE mr.date_sync >= ALL (
				SELECT MAX(mr2.date_sync)
				FROM llx_multicurrency_rate mr2
				INNER JOIN llx_product_price pp3 ON (pp3.fk_multicurrency = mr2.fk_multicurrency)
			)
		) t

		SET pp.multicurrency_price = pp.price * t.rate
			,pp.multicurrency_tx = t.rate
			,pp.multicurrency_price_ttc = pp.price_ttc * t.rate
		
		WHERE pp.fk_multicurrency = t.fk_multicurrency
';


$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_propal @see TMultidevisePropal
echo 'Update '.MAIN_DB_PREFIX.'propal...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal p
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = p.devise_code AND m.entity = p.entity)
		SET p.multicurrency_code = p.devise_code
			,p.fk_multicurrency = m.rowid
			,p.multicurrency_tx = p.devise_taux
			,p.multicurrency_total_ht = p.devise_mt_total
			,p.multicurrency_total_tva = p.tva * p.devise_taux
			,p.multicurrency_total_ttc = p.total * p.devise_taux';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_propaldet @see TMultidevisePropaldet
echo 'Update '.MAIN_DB_PREFIX.'propaldet...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'propaldet pd
		INNER JOIN '.MAIN_DB_PREFIX.'propal p ON (p.rowid = pd.fk_propal)
		SET pd.fk_multicurrency = p.fk_multicurrency
			,pd.multicurrency_code = p.multicurrency_code
			,pd.multicurrency_subprice = pd.devise_pu
			,pd.multicurrency_total_ht = pd.devise_mt_ligne
			,pd.multicurrency_total_tva = pd.total_tva * p.multicurrency_tx
			,pd.multicurrency_total_ttc = pd.total_ttc * p.multicurrency_tx';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_commande @see TMultideviseCommande
echo 'Update '.MAIN_DB_PREFIX.'commande...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande c
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = c.devise_code AND m.entity = c.entity)
		SET c.multicurrency_code = c.devise_code
			,c.fk_multicurrency = m.rowid
			,c.multicurrency_tx = c.devise_taux
			,c.multicurrency_total_ht = c.devise_mt_total
			,c.multicurrency_total_tva = c.tva * c.devise_taux
			,c.multicurrency_total_ttc = c.total_ttc * c.devise_taux';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_commandedet @see TMultideviseCommandedet
echo 'Update '.MAIN_DB_PREFIX.'commandedet...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'commandedet cd
		INNER JOIN '.MAIN_DB_PREFIX.'commande c ON (c.rowid = cd.fk_commande)
		SET cd.fk_multicurrency = c.fk_multicurrency
			,cd.multicurrency_code = c.multicurrency_code
			,cd.multicurrency_subprice = cd.devise_pu
			,cd.multicurrency_total_ht = cd.devise_mt_ligne
			,cd.multicurrency_total_tva = cd.total_tva * c.multicurrency_tx
			,cd.multicurrency_total_ttc = cd.total_ttc * c.multicurrency_tx';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_facture @see TMultideviseFacture
echo 'Update '.MAIN_DB_PREFIX.'facture ...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture f
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = f.devise_code AND m.entity = f.entity)
		SET f.multicurrency_code = f.devise_code
			,f.fk_multicurrency = m.rowid
			,f.multicurrency_tx = f.devise_taux
			,f.multicurrency_total_ht = f.devise_mt_total
			,f.multicurrency_total_tva = f.tva * f.devise_taux
			,f.multicurrency_total_ttc = f.total_ttc * f.devise_taux';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_facturedet @see TMultideviseFacturedet
echo 'Update '.MAIN_DB_PREFIX.'facturedet...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet fd
		INNER JOIN '.MAIN_DB_PREFIX.'facture f ON (f.rowid = fd.fk_facture)
		SET fd.fk_multicurrency = f.fk_multicurrency
			,fd.multicurrency_code = f.multicurrency_code
			,fd.multicurrency_subprice = fd.devise_pu
			,fd.multicurrency_total_ht = fd.devise_mt_ligne
			,fd.multicurrency_total_tva = fd.total_tva * f.multicurrency_tx
			,fd.multicurrency_total_ttc = fd.total_ttc * f.multicurrency_tx';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_commande_fournisseur @see TMultideviseCommandeFournisseur
echo 'Update '.MAIN_DB_PREFIX.'commande_fournisseur...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur cf
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = cf.devise_code AND m.entity = cf.entity)
		SET cf.multicurrency_code = cf.devise_code
			,cf.fk_multicurrency = m.rowid
			,cf.multicurrency_tx = cf.devise_taux
			,cf.multicurrency_total_ht = cf.devise_mt_total
			,cf.multicurrency_total_tva = cf.tva * cf.devise_taux
			,cf.multicurrency_total_ttc = cf.total_ttc * cf.devise_taux';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_commande_fournisseurdet @see TMultideviseCommandeFournisseurdet
echo 'Update '.MAIN_DB_PREFIX.'commande_fournisseurdet...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande_fournisseurdet cfd
		INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur cf ON (cf.rowid = cfd.fk_commande)
		SET cfd.fk_multicurrency = cf.fk_multicurrency
			,cfd.multicurrency_code = cf.multicurrency_code
			,cfd.multicurrency_subprice = cfd.devise_pu
			,cfd.multicurrency_total_ht = cfd.devise_mt_ligne
			,cfd.multicurrency_total_tva = cfd.total_tva * cf.multicurrency_tx
			,cfd.multicurrency_total_ttc = cfd.total_ttc * cf.multicurrency_tx';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_facture_fourn @see TMultideviseFactureFournisseur
echo 'Update '.MAIN_DB_PREFIX.'facture_fourn...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture_fourn ff
		INNER JOIN '.MAIN_DB_PREFIX.'multicurrency m ON (m.code = ff.devise_code AND m.entity = ff.entity)
		SET ff.multicurrency_code = ff.devise_code
			,ff.fk_multicurrency = m.rowid
			,ff.multicurrency_tx = ff.devise_taux
			,ff.multicurrency_total_ht = ff.devise_mt_total
			,ff.multicurrency_total_tva = ff.total_tva * ff.devise_taux
			,ff.multicurrency_total_ttc = ff.total_ttc * ff.devise_taux';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';

// TODO update llx_facture_fourn_det @see TMultideviseFactureFournisseurdet
echo 'Update '.MAIN_DB_PREFIX.'facture_fourn_det...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture_fourn_det ffd
		INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn ff ON (ff.rowid = ffd.fk_facture_fourn)
		SET ffd.fk_multicurrency = ff.fk_multicurrency
			,ffd.multicurrency_code = ff.multicurrency_code
			,ffd.multicurrency_subprice = ffd.devise_pu
			,ffd.multicurrency_total_ht = ffd.devise_mt_ligne
			,ffd.multicurrency_total_tva = ffd.tva * ff.multicurrency_tx
			,ffd.multicurrency_total_ttc = ffd.total_ttc * ff.multicurrency_tx';
$resql = $db->query($sql);
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_paiement_facture @see TMultidevisePaiementFacture
echo 'Update '.MAIN_DB_PREFIX.'paiement_facture...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'paiement_facture
		SET multicurrency_amount = amount * devise_taux';
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


// TODO update llx_paiementfourn_facturefourn @see TMultidevisePaiementFactureFournisseur
echo 'Update '.MAIN_DB_PREFIX.'paiementfourn_facturefourn...';
$sql = 'UPDATE '.MAIN_DB_PREFIX.'paiementfourn_facturefourn
		SET multicurrency_amount = amount * devise_taux';
if (!$resql)
{
	echo 'ERROR<br />';
	$error++;
	pre($db->lasterror());
} else echo 'OK<br />';


if (!$error)
{
	$db->rollback();
	echo '<b>COMMIT</b><br />';
}
else
{
	$db->rollback();
	echo '<b>ROLLBACK</b><br />';
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