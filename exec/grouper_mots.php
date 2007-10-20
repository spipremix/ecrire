<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2007                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('inc/actions');

// http://doc.spip.org/@exec_grouper_mots_dist
function exec_grouper_mots_dist()
{
	exec_grouper_mots_args(intval(_request('id_groupe')));
}

function exec_grouper_mots_args($id_groupe)
{
	$cpt = sql_countsel("spip_mots", "id_groupe=$id_groupe");
	if (!$cpt) 
	  ajax_retour('') ;
	else {
	  $grouper_mots = charger_fonction('grouper_mots', 'inc');
	  ajax_retour($grouper_mots($id_groupe, $cpt));
	}
}
?>
