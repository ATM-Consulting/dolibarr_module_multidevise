<?php

$res=@include("../config.php");						// For root directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->load("admin");
$langs->load('multidevise@multidevise');

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action');

/*
 * Action
 */


/*
 * View
 */

llxHeader('',$langs->trans("Multidevises"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("Multidevises"),$linkback,'multidevise@multidevise');

print '<br>';

$form=new Form($db);
$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Devises").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Taux de Conversions").'</td>'."\n";
print '<td align="center" width="100">Action</td>';
print '</tr>';

$ATMdb = new Tdb;

$sql = "SELECT DISTINCT(cr.id_currency), cr.rowid, c.name, c.code, cr.rate, cr.dt_sync
		FROM ".MAIN_DB_PREFIX."currency AS c
			LEFT JOIN ".MAIN_DB_PREFIX."currency_rate AS cr ON (cr.id_currency = c.rowid)
		WHERE cr.id_entity = ".$conf->entity."
		ORDER BY cr.dt_sync DESC, c.name ASC";
		
$ATMdb->Execute($sql);

$cpt = 0;
while($ATMdb->Get_line()){
	$class = ($cpt%2) ? "pair" : "impair";
	print '<tr class="'.$class.'">';
	print '<td>'.$ATMdb->Get_field('name').' ('.$ATMdb->Get_field('code').')</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	
	print '<td align="center" width="100">'.$ATMdb->Get_field('rate').'</td>';
	print '<td align="center" width="100"><input type="button" value="modifier"></td>';
	print '</tr>';
	$cpt++;
}

print '</table>';

// Footer
llxFooter();
// Close database handler
$db->close();
