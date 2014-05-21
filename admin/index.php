<?php

include_once('../config.php');

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
include_once('../class/class.currency.php');

$langs->load("admin");
$langs->load('multidevise@multidevise');

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action');

$ATMdb = new TPDOdb;

/*
 * Action
 */
if(isset($_REQUEST['action']) && $_REQUEST['action'] == "modtaux"){
	foreach($_REQUEST['id_devise'] as $id_devise){
		if(isset($_REQUEST["newtaux_currency_".$id_devise])){
			$Trate = new TCurrencyRate();
			$Trate->rate = price2num($_REQUEST["newtaux_currency_".$id_devise]);
			$Trate->id_currency = $id_devise;
			$Trate->id_entity = $conf->entity;
			$Trate->dt_sync = time();
			$Trate->save($ATMdb);
		}
	}
}

/*
 * View
 */

llxHeader('',$langs->trans("MulticurrencySetupPage"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("MulticurrencyExchangeRatesSetup"),$linkback,'multidevise@multidevise');

if(isset($_REQUEST['action']) && $_REQUEST['action'] == "updateall"){
	?>
	<script type="text/javascript">
		$(document).ready(function(){
			$.ajax('<?php echo dol_buildpath('/multidevise/cron/1day/sync.php',2); ?>');
		});
	</script>
	<?php
}

print '<br>';

print '<div class="tabsAction">';
print '<a class="butAction" href="?action=updateall">Actualiser les taux</a>';
print '</div>';

?>
<script type="text/javascript">
	function modTaux(currency_id){
		focus = $('#'+currency_id).parent().prev();
		id_taux = currency_id.split('_');
		id_taux = id_taux[1];
		taux = $(focus).html();
		$('#'+currency_id).parent().empty().append('<input type="submit" value="valider" /><input type="button" value="annuler" onclick="document.location.href=\'\'" />');
		$(focus).empty();
		$(focus).append('<input type="hidden" name="id_devise[]" value="'+id_taux+'" /><input type="text" value="'+taux+'" name="newtaux_'+currency_id+'" id="newtaux_'+currency_id+'" />');
	}
</script>
<?php

$form=new Form($db);
$var=true;
print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="modtaux" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Currency").'</td>'."\n";
print '<td align="center" width="100">'.$langs->trans("ExchangeRate").'</td>'."\n";
print '<td align="center" width="200">'.$langs->trans("Action").'</td>'."\n";
print '</tr>';

$sql = "SELECT cr.id_currency, cr.rowid, c.name, c.code, cr.rate, cr.dt_sync
		FROM ".MAIN_DB_PREFIX."currency_rate AS cr
		LEFT JOIN ".MAIN_DB_PREFIX."currency AS c ON (cr.id_currency = c.rowid)
		WHERE cr.id_entity = ".$conf->entity."
		AND cr.dt_sync = (SELECT MAX(cr2.dt_sync) FROM ".MAIN_DB_PREFIX."currency_rate AS cr2 WHERE cr.id_currency = cr2.id_currency)
		GROUP BY cr.id_currency
		ORDER BY cr.dt_sync DESC, c.name ASC";
		
$ATMdb->Execute($sql);

$var = true;
while($ATMdb->Get_line()){
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$ATMdb->Get_field('name').' ('.$ATMdb->Get_field('code').')</td>'."\n";
	print '<td align="center">'.price($ATMdb->Get_field('rate')).'</td>'."\n";
	print '<td align="center">'."\n";
	print '<a id="currency_'.$ATMdb->Get_field('id_currency').'" onclick="modTaux($(this).attr(\'id\'));" href="#currency_'.$ATMdb->Get_field('id_currency').'">';
	print img_edit();
	print '</a>';
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</form>';

// Footer
llxFooter();
// Close database handler
$db->close();
