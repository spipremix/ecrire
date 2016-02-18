<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2016                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Evaluer la page produite par un squelette
 *
 * Évalue une page pour la transformer en texte statique
 * Elle peut contenir un < ?xml a securiser avant eval
 * ou du php d'origine inconnue
 *
 * Attention cette partie eval() doit impérativement
 * être déclenchée dans l'espace des globales (donc pas
 * dans une fonction).
 *
 * @param array $page
 * @return bool
 */
$res = true;

// Cas d'une page contenant du PHP :
if (empty($page['process_ins']) or $page['process_ins'] != 'html') {

	include_spip('inc/lang');

	// restaurer l'etat des notes avant calcul
	if (isset($page['notes'])
		and $page['notes']
		and $notes = charger_fonction("notes", "inc", true)
	) {
		$notes($page['notes'], 'restaurer_etat');
	}
	ob_start();
	if (strpos($page['texte'], '?xml') !== false) {
		$page['texte'] = str_replace('<' . '?xml', "<\1?xml", $page['texte']);
	}

	try {
		$res = eval('?' . '>' . $page['texte']);
		// error catching PHP<7
		if ($res === false
		  and function_exists('error_get_last')
		  and ($erreur = error_get_last()) ) {
			erreur_squelette($erreur['message'],array($page['source'],'',$erreur['file'],$erreur['line'],$GLOBALS['spip_lang']));
			$page['texte'] = "<!-- Erreur -->";
		}
		else {
			$page['texte'] = ob_get_contents();
		}
	}
	// error catching PHP>=7
	catch (Exception $e){
		erreur_squelette($e->getMessage(),array($page['source'],'',$e->getFile(),$e->getLine(),$GLOBALS['spip_lang']));
		$page['texte'] = "<!-- Erreur -->";
	}
	ob_end_clean();

	$page['process_ins'] = 'html';

	if (strpos($page['texte'], '?xml') !== false) {
		$page['texte'] = str_replace("<\1?xml", '<' . '?xml', $page['texte']);
	}
}

page_base_href($page['texte']);
