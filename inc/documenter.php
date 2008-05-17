<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2008                                                *
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
	$ignore_flag = false,	# IGNORE, remplace par autoriser(modifier,document)
	$couleur='',		# IGNORE couleur des cases du tableau
	$appelant =''		# pour le rappel (cf plugin)
) {
	if (is_int($doc)) {
		$table = 'spip_documents_' . $type . 's';
		if (!id_table_objet($table)) {
				spip_log("documenter: $type table inconnue");
				$type = 'article';
				$table = 'spip_documents_' . $type . 's';
		}
		$prim = 'id_' . $type;
		$img = ($ancre == 'portfolio') ? '' : " NOT";
		$select = "D.id_document, D.id_vignette, D.extension, D.titre,  D.date,  D.descriptif,  D.fichier,  D.taille, D.largeur,  D.hauteur,  D.mode,  D.distant, L.vu, L." .$prim;
		$from = "spip_documents AS D LEFT JOIN $table AS L ON  L.id_document=D.id_document"; 
		$where = "L.$prim=$doc AND D.mode='document' AND D.extension $img IN ('gif', 'jpg', 'png')";
		$order = "0+D.titre, D.date";
		$docs = rows_as_array($select, $from, $where, '', $order);
	} else $docs = $doc;

	if (!$docs) return '';

	$tous = (count($docs) > 3);
	$res = documenter_boucle($docs, $type, $ancre, $tous, $appelant);
	$s = ($ancre =='documents' ? '': '-');

	if (is_int($doc))
		$res = documenter_bloc($doc, $res, $s, $script, $ancre, $tous, $type);
	return ajax_action_greffe("documenter", "$s$doc", $res);
}

// http://doc.spip.org/@rows_as_array
function rows_as_array($select, $from, $where='', $groupby='', $orderby='')
{
	$q = sql_select($select, $from, $where, $groupby, $orderby);
	$res = array();
	while ($r = sql_fetch($q)) $res[] = $r;
	return $res;
}

// http://doc.spip.org/@documenter_bloc
function documenter_bloc($id, $res, $s, $script, $ancre, $tous, $type)
{
	if ($tous) {
		$tous = "<div class='lien_tout_supprimer'>"
			. ajax_action_auteur('documenter', "$s$id/$type", $script, "id_$type=$id&s=$s&type=$type",array(_T('lien_tout_supprimer')))
			. "</div>\n";
	} else $tous = '';

	$bouton = bouton_block_depliable(majuscules(_T("info_$ancre")),true,"portfolio_$ancre");

	return debut_cadre("$ancre","","",$bouton)
		. debut_block_depliable(true,"portfolio_$ancre")
		. $tous
		. $res
		. fin_block()
		. fin_cadre();
}

// http://doc.spip.org/@documenter_boucle
function documenter_boucle($documents, $type, $ancre, &$tous_autorise, $appelant)
{
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
		$r = sql_fetsel("chapo,texte", "spip_articles", "id_article=".sql_quote($doc));
		propre(join(" ",$r));
	}*/

	$show_docs = explode(',', _request('show_docs'));

	foreach ($documents as $document) {
		$id_document = $document['id_document'];

		if (isset($document['script']))
			$script = $document['script']; # pour plugin Cedric
		else
		  // ref a $exec inutilise en standard
		  $script = $appelant ? $appelant : $GLOBALS['exec'];

		if (!$case)
			$res .= "<tr>";

		$flag = autoriser('modifier', 'document', $id_document);
		$tous_autorises &= $flag;
		$vu = ($document['vu']=='oui') ? ' vu':'';

		$res .= "\n<td  class='document$vu'>"
		.  $tourner($id_document, $document, $script, $flag, $type)
		. (!$flag  ? '' :
		   $legender($id_document, $document, $script, $type, $document["id_$type"], $ancre, in_array($id_document, $show_docs)))
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
		$res .= "<td></td>";
		$res .= "</tr>";
	}

	return "\n<table width='100%' cellspacing='0' cellpadding='4'>"
	. $res
	. "</table>";
}
?>
