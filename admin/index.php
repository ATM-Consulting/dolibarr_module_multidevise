<?php

$res=@include("../config.php");						// For root directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
include('../class/class.currency.php');

$langs->load("admin");
$langs->load('multidevise@multidevise');

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action');

$ATMdb = new Tdb;

/*
 * Action
 */
if(isset($_REQUEST['action']) && $_REQUEST['action'] == "modtaux"){
	foreach($_REQUEST['id_devise'] as $id_devise){
		if(isset($_REQUEST["newtaux_currency_".$id_devise])){
			$Trate = new TCurrencyRate();
			$Trate->rate = $_REQUEST["newtaux_currency_".$id_devise];
			$Trate->id_currency = $id_devise;
			$Trate->id_entity = $conf->entity;
			$Trate->dt_sync = strtotime(date('Y-m-d'));
			$Trate->save($ATMdb);
		}
	}
}
/*
 * View
 */

llxHeader('',$langs->trans("Multidevises"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("Multidevises"),$linkback,'multidevise@multidevise');

print '<br>';

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
print '<form method="post" action="">';
print '<input type="hidden" name="action" value="modtaux" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Devises").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Taux de Conversions").'</td>'."\n";
print '<td align="center" width="200">Action</td>';
print '</tr>';

$sql = "SELECT cr.id_currency, cr.rowid, c.name, c.code, cr.rate, cr.dt_sync
		FROM ".MAIN_DB_PREFIX."currency_rate AS cr
		LEFT JOIN ".MAIN_DB_PREFIX."currency AS c ON (cr.id_currency = c.rowid)
		WHERE cr.id_entity = ".$conf->entity."
		AND cr.dt_sync = (SELECT MAX(cr2.dt_sync) FROM ".MAIN_DB_PREFIX."currency_rate AS cr2 WHERE cr.id_currency = cr2.id_currency)
		GROUP BY cr.id_currency
		ORDER BY cr.dt_sync DESC, c.name ASC";
		
$ATMdb->Execute($sql);

$cpt = 0;
while($ATMdb->Get_line()){
	$class = ($cpt%2) ? "pair" : "impair";
	print '<tr class="'.$class.'">';
	print '<td>'.$ATMdb->Get_field('name').' ('.$ATMdb->Get_field('code').')</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	
	print '<td align="center" width="100">'.$ATMdb->Get_field('rate').'</td>';
	print '<td align="center" width="100">';
	print '<a id="currency_'.$ATMdb->Get_field('id_currency').'" onclick="modTaux($(this).attr(\'id\'));">
		   	<img border="0" title="Modifier" alt="Modifier" src="'.DOL_URL_ROOT.'/theme/eldy/img/edit.png">
		   </a>';
	print '</td>';
	print '</tr>';
	$cpt++;
}

print '</table>';
print '</form>';

// Footer
llxFooter();
// Close database handler
$db->close();
