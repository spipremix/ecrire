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

// Affiche le portfolio et les documents lies a l'article (ou a la rubrique)
// => Nouveau : au lieu de les ignorer, on affiche desormais avec un fond gris
// les documents et images inclus dans le texte.

// http://doc.spip.org/@inc_documenter_dist
function inc_documenter_dist(
	$doc,		# tableau des documents ou numero de l'objet attachant
	$type = "article",	# article ou rubrique ?
	$ancre = 'portfolio',	# album d'images ou de documents ?
	$flag = false,	# a-t-on le droit de modifier ?
	$couleur='',		# couleur des cases du tableau
	$appelant =''		# pour le rappel (cf plugin)
) {
	global $spip_lang_left, $spip_lang_right;

	if (is_int($doc)) {
		if ($ancre == 'portfolio') {
			$lies = spip_query("SELECT docs.*,l.id_$type,l.vu FROM spip_documents AS docs, spip_documents_".$type."s AS l WHERE l.id_$type=$doc AND l.id_document=docs.id_document AND docs.mode='document' AND docs.extension IN ('gif', 'jpg', 'png') ORDER BY 0+docs.titre, docs.date");
			$toile = 'toile_claire';
			$couleur = 'bordure_claire_';
		} else {
			$lies = spip_query("SELECT docs.*,l.id_$type,l.vu FROM spip_documents AS docs, spip_documents_".$type."s AS l WHERE l.id_$type=$doc AND l.id_document=docs.id_document AND docs.mode='document' AND docs.extension NOT IN ('gif', 'jpg', 'png') ORDER BY 0+docs.titre, docs.date");
			$toile = 'toile_gris_fort';
			$couleur = 'bordure_grise_';
		}

		$documents = array();
		while ($document = spip_fetch_array($lies))
			$documents[] = $document;
	} else
		$documents = $doc;

	if (!$documents) return '';

	$class = "${couleur}basse $couleur$spip_lang_left";

	charger_generer_url();
	// la derniere case d'une rangee
	$bord_droit = ($ancre == 'portfolio' ? 2 : 1);
	$case = 0;
	$res = '';

	$tourner = charger_fonction('tourner', 'inc');
	$legender = charger_fonction('legender', 'inc');

	// Pour les doublons d'article et en mode ajax, il faut faire propre()
	/*if ($type=='article'
	AND !isset($GLOBALS['doublons_documents_inclus'])
	AND is_int($doc)) {
		$r = spip_fetch_array(spip_query("SELECT chapo,texte FROM spip_articles WHERE id_article="._q($doc)));
		propre(join(" ",$r));
	}*/

	foreach ($documents as $document) {
		$id_document = $document['id_document'];

		if (isset($document['script']))
			$script = $document['script']; # pour plugin Cedric
		else
		  // ref a $exec inutilise en standard
		  $script = $appelant ? $appelant : $GLOBALS['exec'];

		$style = ($document['vu']=='oui') ? ' background-color: #cccccc;':'';

		$deplier = in_array($id_document, explode(',', _request('show_docs')));

		if (!$case)
			$res .= "<tr style='border-top: 1px solid black;'>";
		else if ($case == $bord_droit)
			$class .= " ${couleur}$spip_lang_right";
		$res .= "\n<td  class='$class' style='width:33%; text-align: $spip_lang_left; $style' valign='top'>"
		.  $tourner($id_document, $document, $script, $flag, $type)
		. (!$flag  ? '' :
		   $legender($id_document, $document, $script, $type, $document["id_$type"], $ancre, $deplier))
		. (!isset($document['info']) ? '' :
		       ("<div class='verdana1'>".$document['info']."</div>"))
		. "</td>\n";

		$case++;
				
		if ($case > $bord_droit) {
			  $case = 0;
			  $res .= "</tr>\n";
		}

	}

	// fermer la derniere ligne
	if ($case) {
		$res .= "<td class='$couleur$spip_lang_left'>&nbsp;</td>";
		$res .= "</tr>";
	}

	$s = ($ancre =='documents' ? '': '-');
	if (is_int($doc)) {
		$head = "\n<div style='padding: 4px; color: black;' class='verdana2 $toile'>\n<b>".majuscules(_T("info_$ancre"))."</b></div>";

		if (count($documents) > 3) {
			$head .= "<div style='background-color: #dddddd; padding: 4px; color: black; text-align: right' class='arial1'>"
			. ajax_action_auteur('documenter', "$s$doc/$type", $script, "id_$type=$doc&s=$s&type=$type",array(_T('lien_tout_supprimer')))
			. "</div>\n";
		}
	} else $head = '';

	$res = $head
	. "\n<table width='100%' cellspacing='0' cellpadding='4'>"
	. $res
	. "</table>";	  

	return ajax_action_greffe("documenter", "$s$doc", $res, '');
}
