<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2006                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

function exec_legender_dist()
{
	global $id_document, $id, $type, $ancre, $script;
	$id = intval($id);
	$id_document = intval($id_document);

	if (!($type == 'article' 
		? acces_article($id)
		: acces_rubrique($id))) {
		spip_log("Tentative d'intrusion de " . $GLOBALS['auteur_session']['nom'] . " dans " . $GLOBALS['exec']);
		include_spip('inc/minipres');
		minipres(_T('info_acces_interdit'));
	}

	$legender = charger_fonction('legender', 'inc');
	ajax_retour($legender($id_document, array(), $script, $type, $id, $ancre));
}
?>
