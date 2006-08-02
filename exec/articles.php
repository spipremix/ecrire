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

include_spip('inc/presentation');
include_spip('inc/texte');
include_spip('inc/rubriques');
include_spip('inc/mots');
include_spip('inc/date');
include_spip('inc/documents');
include_spip('inc/forum');
include_spip('base/abstract_sql');

function exec_articles_dist()
{
	global $change_accepter_forum, $change_petition, $changer_virtuel,  $cherche_auteur, $ids, $cherche_mot, $debut, $id_article, $id_article_bloque, $id_parent, $id_rubrique_old, $id_trad_new,  $langue_article, $lier_trad, $new, $nom_select, $nouv_mot, $supp_mot, $virtuel; 
	global  $connect_id_auteur, $connect_statut, $options, $spip_display, $spip_lang_left, $spip_lang_right, $dir_lang;

	$id_parent = intval($id_parent);
	$lier_trad = intval($lier_trad);
	$supp_mot = intval($supp_mot);
	if (!($id_article=intval($id_article))) {
		if ($new!='oui')  redirige_par_entete("./");
		$id_article = insert_article($id_parent);
	}

	pipeline('exec_init',array('args'=>array('exec'=>'articles','id_article'=>$id_article),'data'=>''));

	$row = spip_fetch_array(spip_query("SELECT statut, id_rubrique FROM spip_articles WHERE id_article=$id_article"));

	if (!$row) {
	   // cas du numero hors table
		$titre = _T('public:aucun_article');
		debut_page("&laquo; $titre &raquo;", "documents", "articles");
		debut_grand_cadre();
		fin_grand_cadre();
		echo $titre;
		exit;
	}

	$id_rubrique = $row['id_rubrique'];
	$statut_article = $row['statut'];
	$statut_rubrique = acces_rubrique($id_rubrique);

	$flag_auteur = spip_num_rows(spip_query("SELECT id_auteur FROM spip_auteurs_articles WHERE id_article=$id_article AND id_auteur=$connect_id_auteur LIMIT 1"));

	$flag_modifiable = $flag_auteur OR $statut_rubrique;

	if ($flag_modifiable AND $id_parent)
 // Les redacteurs ont le droit de changer la rubrique destination
 // avant la publication de l'article, mais plus apres
			$id_rubrique = $id_parent;

	$flag_editable = $flag_modifiable OR ($flag_auteur AND ($statut_article == 'prepa' OR $statut_article == 'prop' OR $statut_article == 'poubelle'));

	if ($flag_editable) {
   // id_article_bloque,  globale dans inc/presentation 
		$id_article_bloque =  articles_set($id_article, $id_rubrique, $flag_modifiable);

	// renvoyer vers la page de l'article
		if ($new == 'oui'
		AND ! $lier_trad  # sauf dans le cas d'un lier_trad car le code de mise a jour du lien est au meme endroit que l'affichage (a corriger).
		    )
			redirige_par_entete(
				generer_url_ecrire('articles', 'id_article='.$id_article, '&'));
	}

	// recharger apres mise a jour de articles_set
	$row = spip_fetch_array(spip_query("SELECT * FROM spip_articles WHERE id_article='$id_article'"));

	$id_article = $row["id_article"];
	$surtitre = $row["surtitre"];
	$titre = $row["titre"];
	$soustitre = $row["soustitre"];
	$id_rubrique = $row["id_rubrique"];
	$descriptif = $row["descriptif"];
	$nom_site = $row["nom_site"];
	$url_site = $row["url_site"];
	$chapo = $row["chapo"];
	$texte = $row["texte"];
	$ps = $row["ps"];
	$date = $row["date"];
	$statut_article = $row["statut"];
	$maj = $row["maj"];
	$date_redac = $row["date_redac"];
	$visites = $row["visites"];
	$referers = $row["referers"];
	$extra = $row["extra"];
	$id_trad = $row["id_trad"];
	$id_version = $row["id_version"];
	
	// aucun doc implicitement inclus au d�part.
	inclus_non_articles($id_article);
	
	debut_page("&laquo; $titre &raquo;", "documents", "articles", "", "", $id_rubrique);

	debut_grand_cadre();

	afficher_hierarchie($id_rubrique);

	fin_grand_cadre();

	if (!$row) {echo $titre; exit;}

//
// Affichage de la colonne de gauche
//

debut_gauche();

boite_info_articles($id_article, $statut_article, $visites, $id_version);

//
// Logos de l'article et Boites de configuration avancee
//


// pour l'affichage du virtuel
$virtuel = '';
if (substr($chapo, 0, 1) == '=') {
	$virtuel = substr($chapo, 1);
}

boites_de_config_articles($id_article, $id_rubrique, $flag_editable,
			  $change_accepter_forum, $change_petition,
			  $changer_virtuel, $virtuel);
 
 echo pipeline('affiche_gauche',array('args'=>array('exec'=>'articles','id_article'=>$id_article),'data'=>''));

//
// Affichage de la colonne de droite
//

creer_colonne_droite();
 echo pipeline('affiche_droite',array('args'=>array('exec'=>'articles','id_article'=>$id_article),'data'=>''));

debut_droite();

changer_typo('','article'.$id_article);

debut_cadre_relief();

//
// Titre, surtitre, sous-titre
//

 $modif = titres_articles($titre, $statut_article,$surtitre, $soustitre, $descriptif, $url_site, $nom_site, $flag_editable, $id_article, $id_rubrique);


 echo "<div class='serif' align='$spip_lang_left'>";

 debut_cadre_couleur();
 dates_articles($id_article, $id_rubrique, $flag_editable, $statut_article, $date, $date_redac);
 fin_cadre_couleur();

//
// Liste des auteurs de l'article
//

echo "<a name='auteurs'></a>";

if ($flag_editable AND $options == 'avancees') {
	$bouton = bouton_block_invisible("auteursarticle");
}

debut_cadre_enfonce("auteur-24.gif", false, "", $bouton._T('texte_auteurs').aide ("artauteurs"));

//
// complement de action/ajouter.php pour notifier la recherche d'auteur
//

 $bouton_creer_auteur =  ($GLOBALS['connect_statut'] == '0minirezo' 
			  AND $GLOBALS['connect_toutes_rubriques']);

 if ($cherche_auteur) {

	echo "<p align='$spip_lang_left'>";
	debut_boite_info();
	rechercher_auteurs_articles($cherche_auteur, $ids,  $id_article);

	if ($bouton_creer_auteur) {

		echo "<div style='width: 200px;'>";
		$nom = rawurlencode($cherche_auteur);
		icone_horizontale(_T('icone_creer_auteur'), generer_url_ecrire("auteur_infos","ajouter_id_article=$id_article&nom=$nom&redirect=" . generer_url_retour("articles","id_article=$id_article")), "redacteurs-24.gif", "creer.gif");
		echo "</div> ";
		$bouton_creer_auteur = false;
	}

	fin_boite_info();
	echo '</p>';
 }

//
// Afficher les auteurs
//

$les_auteurs = afficher_auteurs_articles($id_article, $flag_editable);

//
// Ajouter un auteur
//

 if ($flag_editable AND $options == 'avancees') {
	echo debut_block_invisible("auteursarticle");
	echo "<table width='100%'><tr>";

	if ($bouton_creer_auteur) {

		echo "<td width='200'>";
		icone_horizontale(_T('icone_creer_auteur'), generer_url_ecrire("auteur_infos","ajouter_id_article=$id_article&redirect=" .generer_url_retour("articles","id_article=$id_article")), "redacteurs-24.gif", "creer.gif");
		echo "</td>";
		echo "<td width='20'>&nbsp;</td>";
	}

	echo "<td>";

	echo ajouter_auteurs_articles($id_article, $les_auteurs, $bouton_creer_auteur);
	echo "</td></tr></table>";

	echo fin_block();
 }
fin_cadre_enfonce(false);

//
// Liste des mots-cles de l'article
//

if ($options == 'avancees' AND $GLOBALS['meta']["articles_mots"] != 'non') {
  formulaire_mots('articles', $id_article, $nouv_mot, $supp_mot, $cherche_mot, $flag_editable);
}

 langues_articles($id_article, $langue_article, $flag_editable, $id_rubrique, $id_trad, $dir_lang, $nom_select, $lier_trad,  $id_trad_new);

 echo pipeline('affiche_milieu',array('args'=>array('exec'=>'articles','id_article'=>$id_article),'data'=>''));

 if ($statut_rubrique)
   echo debut_cadre_relief('', true),
     afficher_statut_articles($id_article, $id_rubrique, $statut_article),
     fin_cadre_relief('', true);

 afficher_corps_articles($virtuel, $chapo, $texte, $ps, $extra);

if ($flag_editable) {
	echo "\n\n<div align='$spip_lang_right'><br />";
	bouton_modifier_articles($id_article, $id_rubrique, $modif,_T('texte_travail_article', $modif), "warning-24.gif", "");
	echo "</div>";
}

//
// Documents associes a l'article
//

 if ($spip_display != 4)
 afficher_documents_non_inclus($id_article, "article", $flag_editable);

 if ($flag_auteur AND  $statut_article == 'prepa' AND !$statut_rubrique)
	echo demande_publication($id_article);

 echo "</div>";
 echo "</div>";
 fin_cadre_relief();

 affiche_forums_article($id_article, $id_rubrique, $titre, $debut);

fin_page();

}

function demande_publication($id_article)
{
	return debut_cadre_relief('',true) .
		"<center>" .
		"<b>" ._T('texte_proposer_publication') . "</b>" .
		aide ("artprop") .
		redirige_action_auteur("instituer", 
			"article-$id_article-prop",
			'articles',
			"id_article=$id_article",
			("<input type='submit' class='fondo' value=\"" . 
			    _T('bouton_demande_publication') .
			    "\" />\n"),
			"method='post'") .
		"</center>" .
		fin_cadre_relief(true);
}

function boite_info_articles($id_article, $statut_article, $visites, $id_version)
{
  global $connect_statut, $options, $flag_revisions;

	debut_boite_info();
 
	echo "<div align='center'>\n";

	echo "<font face='Verdana,Arial,Sans,sans-serif' size='1'><b>"._T('info_numero_article')."</b></font>\n";
	echo "<br /><font face='Verdana,Arial,Sans,sans-serif' size='6'><b>$id_article</b></font>\n";

	voir_en_ligne('article', $id_article, $statut_article);

	if ($connect_statut == "0minirezo" AND $statut_article == 'publie' AND $visites > 0 AND $GLOBALS['meta']["activer_statistiques"] != "non" AND $options == "avancees"){
	icone_horizontale(_T('icone_evolution_visites', array('visites' => $visites)), generer_url_ecrire("statistiques_visites","id_article=$id_article"), "statistiques-24.gif","rien.gif");
}

	if ((($GLOBALS['meta']["articles_versions"]=='oui') && $flag_revisions)
		AND $id_version>1 AND $options == "avancees") {
	icone_horizontale(_T('info_historique_lien'), generer_url_ecrire("articles_versions","id_article=$id_article"), "historique-24.gif", "rien.gif");
}

	// Correction orthographique
	if ($GLOBALS['meta']['articles_ortho'] == 'oui') {
		$js_ortho = "onclick=\"window.open(this.href, 'spip_ortho', 'scrollbars=yes, resizable=yes, width=740, height=580'); return false;\"";
		icone_horizontale(_T('ortho_verifier'), generer_url_ecrire("articles_ortho", "id_article=$id_article"), "ortho-24.gif", "rien.gif", 'echo', $js_ortho);
	}

	echo "</div>\n";
	
	fin_boite_info();
}

function formulaire_petition($id_article, $nb_signatures)
{
	global $spip_lang_right;

	$petition = spip_fetch_array(spip_query("SELECT * FROM spip_petitions WHERE id_article=$id_article"));

	$email_unique=$petition["email_unique"];
	$site_obli=$petition["site_obli"];
	$site_unique=$petition["site_unique"];
	$message=$petition["message"];
	$texte_petition=$petition["texte"];

	if ($petition) {
		$menu = array(
			'on' => _T('bouton_radio_petition_activee'),
			'off'=> _T('bouton_radio_supprimer_petition')
		);
		$val_menu = 'on';
	} else {
		$menu = array(
			'off'=> _T('bouton_radio_pas_petition'),
			'on' => _T('bouton_radio_activer_petition')
		);
		$val_menu = 'off';
	}

	$res = '';
	foreach ($menu as $val => $desc) {
		$res .= "<option" . (($val_menu == $val) ? " selected" : '') . " value='$val'>".$desc."</option>\n";
	}

	$res = "<select name='change_petition'
		class='fondl' style='font-size:10px;'
		onChange=\"setvisibility('valider_petition', 'visible');\"
		>\n$res</select>\n";


	if ($petition) {
		if ($nb_signatures) {
			$res .= "<br />\n" .
			icone_horizontale($nb_signatures.'&nbsp;'. _T('info_signatures'), generer_url_ecrire("controle_petition","id_article=$id_article",'', false), "suivi-petition-24.gif", "");
		}

		$res .= "<br />\n";

		if ($email_unique=="oui")
			$res .= "<input type='checkbox' name='email_unique' value='oui' id='emailunique' checked>";
		else
			$res .="<input type='checkbox' name='email_unique' value='oui' id='emailunique'>";
		$res .=" <label for='emailunique'>"._T('bouton_checkbox_signature_unique_email')."</label><BR>";
		if ($site_obli=="oui")
			$res .="<input type='checkbox' name='site_obli' value='oui' id='siteobli' checked>";
		else
			$res .="<input type='checkbox' name='site_obli' value='oui' id='siteobli'>";
		$res .=" <label for='siteobli'>"._T('bouton_checkbox_indiquer_site')."</label><BR>";
		if ($site_unique=="oui")
			$res .="<input type='checkbox' name='site_unique' value='oui' id='siteunique' checked>";
		else
			$res .="<input type='checkbox' name='site_unique' value='oui' id='siteunique'>";
		$res .=" <label for='siteunique'>"._T('bouton_checkbox_signature_unique_site')."</label><BR>";
		if ($message=="oui")
			$res .="<input type='checkbox' name='message' value='oui' id='message' checked>";
		else
			$res .="<input type='checkbox' name='message' value='oui' id='message' />";
		$res .=" <label for='message'>"._T('bouton_checkbox_envoi_message')."</label>";

		$res .=_T('texte_descriptif_petition')."&nbsp;:<BR />";
		$res .="<TEXTAREA NAME='texte_petition' CLASS='forml' ROWS='4' COLS='10' wrap=soft>";
		$res .=entites_html($texte_petition);
		$res .="</TEXTAREA>\n";

		$res .="<span align='$spip_lang_right'>";
	} else $res .="<span class='visible_au_chargement' id='valider_petition'>";
	$res .="<INPUT TYPE='submit' CLASS='fondo' VALUE='"._T('bouton_changer')."' STYLE='font-size:10px' />";
	$res .="</span>";

	return generer_action_auteur('petitionner', $id_article, generer_url_ecrire('articles', "id_article=$id_article&change_petition=oui"), $res," method='POST'");

}

function boites_de_config_articles($id_article, $id_rubrique, $flag_editable,
				   $change_accepter_forum, $change_petition,
				   $changer_virtuel, $virtuel)
{
  global $connect_statut, $options, $spip_lang_right, $spip_display;

// Logos de l'article

  if ($id_article AND $flag_editable AND ($spip_display != 4)) {
	  include_spip('inc/chercher_logo');
	  echo afficher_boite_logo('id_article', $id_article,
			      _T('logo_article').aide ("logoart"), _T('logo_survol'), 'articles');
  }

//
// Boites de configuration avancee
//

	if ($options == "avancees" && $connect_statut=='0minirezo' && $flag_editable) {
	  echo "<p>";
	  debut_cadre_relief("forum-interne-24.gif");


	  $nb_forums = spip_fetch_array(spip_query("SELECT COUNT(*) AS count FROM spip_forum WHERE id_article=$id_article 	AND statut IN ('publie', 'off', 'prop')"));

	  $nb_signatures = spip_fetch_array(spip_query("SELECT COUNT(*) AS count FROM spip_signatures WHERE id_article=$id_article AND statut IN ('publie', 'poubelle')"));

	  $nb_forums = $nb_forums['count'];
	  $nb_signatures = $nb_signatures['count'];
	  $visible = $change_accepter_forum || $change_petition
		|| $nb_forums || $nb_signatures;

	echo "<div class='verdana1' style='text-align: center;'><b>";
	if ($visible)
		echo bouton_block_visible("forumpetition");
	else
		echo bouton_block_invisible("forumpetition");
	echo _T('bouton_forum_petition') .aide('confforums');
	echo "</b></div>";
	if ($visible)
		echo debut_block_visible("forumpetition");
	else
		echo debut_block_invisible("forumpetition");

	echo "<font face='Verdana,Arial,Sans,sans-serif' size='1'>\n";

	// Forums

	if ($nb_forums) {
		echo "<br />\n";
		icone_horizontale(_T('icone_suivi_forum', array('nb_forums' => $nb_forums)), generer_url_ecrire("articles_forum","id_article=$id_article"), "suivi-forum-24.gif", "");
	}

	// Reglage existant
	$forums_publics = get_forums_publics($id_article);

	// Modification du reglage ?
	if (isset($change_accepter_forum)
	AND $change_accepter_forum <> $forums_publics) {
		$forums_publics = $change_accepter_forum;
		modifier_forums_publics($id_article, $forums_publics);
	}

	// Afficher le formulaire de modification du reglage
	echo formulaire_modification_forums_publics($id_article, $forums_publics, generer_url_ecrire("articles","id_article=$id_article"));

	// Petitions

	echo formulaire_petition($id_article, $nb_signatures);

	echo fin_block();

	fin_cadre_relief();

	// Redirection (article virtuel)
	debut_cadre_relief("site-24.gif");
	$visible = ($changer_virtuel || $virtuel);

	echo "\n<div class='verdana1' style='text-align: center;'><b>";
	if ($visible)
		echo bouton_block_visible("redirection");
	else
		echo bouton_block_invisible("redirection");
	echo _T('bouton_redirection');
	echo aide ("artvirt");
	echo "</b></div>";
	if ($visible)
		echo debut_block_visible("redirection");
	else
		echo debut_block_invisible("redirection");

	echo generer_url_post_ecrire("articles", "id_article=$id_article");
	echo "\n<input type='hidden' name='changer_virtuel' value='oui' />";
	$virtuelhttp = ($virtuel ? "" : "http://");

	echo "<input type='text' name='virtuel' class='formo' style='font-size:9px;' value=\"$virtuelhttp$virtuel\" size='40' /><br />\n";
	echo "<font face='Verdana,Arial,Sans,sans-serif' size='2'>";
	echo "(<b>"._T('texte_article_virtuel')."&nbsp;:</b> "._T('texte_reference_mais_redirige').")";
	echo "</font>";
	echo "\n<div align='$spip_lang_right'><input type='submit' class='fondo' value='"._T('bouton_changer')."' style='font-size:10px' /></div>";
	echo "</form>";
	echo fin_block();

	fin_cadre_relief();
 }

//
// Articles dans la meme rubrique
//

meme_rubrique_articles($id_rubrique, $id_article, $options);

}

function meme_rubrique_articles($id_rubrique, $id_article, $options, $order='date', $limit=30)
{
	global $spip_lang_right, $spip_lang_left;

	$vos_articles = spip_query("SELECT id_article, titre, statut FROM spip_articles WHERE id_rubrique=$id_rubrique AND (statut = 'publie' OR statut = 'prop') AND id_article != $id_article ORDER BY $order DESC LIMIT $limit");
	if (spip_num_rows($vos_articles) > 0) {
			echo "<div>&nbsp;</div>";
			echo "<div class='bandeau_rubriques' style='z-index: 1;'>";
			bandeau_titre_boite2(_T('info_meme_rubrique'), "article-24.gif");
			echo "<div class='plan-articles'>";
			while($row = spip_fetch_array($vos_articles)) {
				$ze_article = $row['id_article'];
				$ze_titre = typo($row['titre']);
				$ze_statut = $row['statut'];
				
				if ($options == "avancees") {
					$numero = "<div class='arial1' style='float: $spip_lang_right; color: black; padding-$spip_lang_left: 4px;'><b>"._T('info_numero_abbreviation')."$ze_article</b></div>";
				}
				echo "<a class='$ze_statut' style='font-size: 10px;' href='" . generer_url_ecrire("articles","id_article=$ze_article") . "'>$numero$ze_titre</a>";
			}
			echo "</div>";
			echo "</div>";
		}
}

function bouton_modifier_articles($id_article, $id_rubrique, $flag_modif, $mode, $ip, $im)
{
	if ($flag_modif) {
	  icone(_T('icone_modifier_article'), generer_url_ecrire("articles_edit","id_article=$id_article"), $ip, $im);
		echo "<font face='arial,helvetica,sans-serif' size='2'>$mode</font>";
		echo aide("artmodif");
	}
	else {
		icone(_T('icone_modifier_article'), generer_url_ecrire("articles_edit","id_article=$id_article"), "article-24.gif", "edit.gif");
	}

}

function titres_articles($titre, $statut_article,$surtitre, $soustitre, $descriptif, $url_site, $nom_site, $flag_editable, $id_article, $id_rubrique)
{
	global  $dir_lang, $spip_lang_left, $connect_id_auteur;

	$logo_statut = "puce-".puce_statut($statut_article).".gif";
	
	echo "\n<table cellpadding=0 cellspacing=0 border=0 width='100%'>";
	echo "<tr width='100%'><td width='100%' valign='top'>";
	
	if ($surtitre) {
		echo "<span $dir_lang><font face='arial,helvetica' size=3><b>";
		echo typo($surtitre);
		echo "</b></font></span>\n";
	 }
	 
	gros_titre($titre, $logo_statut);
	
	if ($soustitre) {
		echo "<span $dir_lang><font face='arial,helvetica' size=3><b>";
		echo typo($soustitre);
		echo "</b></font></span>\n";
	}
	
	
	if ($descriptif OR $url_site OR $nom_site) {
		echo "<p><div align='$spip_lang_left' style='padding: 5px; border: 1px dashed #aaaaaa; background-color: #e4e4e4;' $dir_lang>";
		echo "<font size=2 face='Verdana,Arial,Sans,sans-serif'>";
		$texte_case = ($descriptif) ? "{{"._T('info_descriptif')."}} $descriptif\n\n" : '';
		$texte_case .= ($nom_site.$url_site) ? "{{"._T('info_urlref')."}} [".$nom_site."->".$url_site."]" : '';
		echo propre($texte_case);
		echo "</font>";
		echo "</div>";
	}
	
	
	if ($statut_article == 'prop') {
		echo "<P><FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=2 COLOR='red'><B>"._T('text_article_propose_publication')."</B></FONT></P>";
	}
	
	echo "</td>";
	
	$flag_modif = false;
	$modif = array();

	if ($flag_editable) {
		echo "<td>". http_img_pack('rien.gif', " ", "width='5'") . "</td>\n";
		echo "<td align='center'>";
	

		// Est-ce que quelqu'un a deja ouvert l'article en edition ?
		unset($modif);
		if ($GLOBALS['meta']['articles_modif'] != 'non') {
			include_spip('inc/drapeau_edition');
			$modif = qui_edite($id_article, 'article');
			if ($modif['id_auteur_modif'] == $connect_id_auteur)
				unset($modif);
		}

		bouton_modifier_articles($id_article, $id_rubrique, $modif, _T('avis_article_modifie', $modif), "article-24.gif", "edit.gif");
		echo "</td>";
	}
	echo "</tr></table>\n";
	echo "<div>&nbsp;</div>";
	return $modif;
}


function dates_articles($id_article, $id_rubrique, $flag_editable, $statut_article, $date, $date_redac)
{

	global $spip_lang_left, $spip_lang_right, $options;

	if (ereg("([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2})", $date_redac, $regs)) {
		$annee_redac = $regs[1];
		$mois_redac = $regs[2];
		$jour_redac = $regs[3];
		$heure_redac = $regs[4];
		$minute_redac = $regs[5];
		if ($annee_redac > 4000) $annee_redac -= 9000;
	}

	if (ereg("([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2})", $date, $regs)) {
		$annee = $regs[1];
		$mois = $regs[2];
		$jour = $regs[3];
		$heure = $regs[4];
		$minute = $regs[5];
	}

  if ($flag_editable AND $options == 'avancees') {

	if ($statut_article == 'publie') {

		echo redirige_action_auteur("dater", 
			"$id_article",
			'articles',
			"id_article=$id_article",
			(bouton_block_invisible("datepub") .
 "<b><span class='verdana1'>".
 _T('texte_date_publication_article').
 '</span> ' . 
 majuscules(affdate($date)) .
 "</b>".
 aide('artdate') . 
 debut_block_invisible("datepub") .
 "<div style='margin: 5px; margin-$spip_lang_left: 20px;'>" .
 afficher_jour($jour, "name='jour' size='1' class='fondl' onChange=\"setvisibility('valider_date', 'visible')\"", true) .
 afficher_mois($mois, "name='mois' size='1' class='fondl' onChange=\"setvisibility('valider_date', 'visible')\"", true) .
 afficher_annee($annee, "name='annee' size='1' class='fondl' onChange=\"setvisibility('valider_date', 'visible')\"") .
 ' - ' .
 afficher_heure($heure, "name='heure' size='1' class='fondl' onChange=\"setvisibility('valider_date', 'visible')\"") .
 afficher_minute($minute, "name='minute' size='1' class='fondl' onChange=\"setvisibility('valider_date', 'visible')\"") .
 "<span class='visible_au_chargement' id='valider_date'>" .
 " &nbsp; <input type='submit' class='fondo' value='".
 _T('bouton_changer')."' />" .
 "</span>" .
 "</div>" .
 fin_block()) ,
			"method='post'"); 
	}
	else {
		echo "<div><b> <span class='verdana1'>"._T('texte_date_creation_article').'</span> ';
		echo majuscules(affdate($date))."</b>".aide('artdate')."</div>";
	}

	$possedeDateRedac=($annee_redac.'-'.$mois_redac.'-'.$jour_redac != '0000-00-00');
	if (($options == 'avancees' AND $GLOBALS['meta']["articles_redac"] != 'non')
	OR $possedeDateRedac) {
		if ($possedeDateRedac)
			$date_affichee = majuscules(affdate($date_redac))
#			." " ._T('date_fmt_heures_minutes', array('h' =>$heure_redac, 'm'=>$minute_redac))
			;
		else
			$date_affichee = majuscules(_T('jour_non_connu_nc'));

		echo redirige_action_auteur("dater", 
			"$id_article",
			'articles',
			"id_article=$id_article",
			(bouton_block_invisible('dateredac') .
 "<b>" .
 "<span class='verdana1'>" .
 majuscules(_T('texte_date_publication_anterieure')) .
'</span> '.
 $date_affichee .
 " " .
 aide('artdate_redac') .
 "</b>" .
 debut_block_invisible('dateredac') .
 "<div style='margin: 5px; margin-$spip_lang_left: 20px;'>" .
 '<table cellpadding="0" cellspacing="0" border="0" width="100%">' .
 '<tr><td align="$spip_lang_left">' .
 '<input type="radio" name="avec_redac" value="non" id="avec_redac_on"' .
 ($possedeDateRedac ? '' : ' checked="checked"') .
 " onClick=\"setvisibility('valider_date_prec', 'visible')\"" .
 ' /> <label for="avec_redac_on">'.
 _T('texte_date_publication_anterieure_nonaffichee').
 '</label>' .
 '<br /><input type="radio" name="avec_redac" value="oui" id="avec_redac_off"' .
 (!$possedeDateRedac ? '' : ' checked="checked"') .
 " onClick=\"setvisibility('valider_date_prec', 'visible')\"" .
 ' /> <label for="avec_redac_off">'.
 _T('bouton_radio_afficher').
 ' :</label> ' .
 afficher_jour($jour_redac, "name='jour_redac' class='fondl' onChange=\"setvisibility('valider_date_prec', 'visible')\"", true) .
 afficher_mois($mois_redac, "name='mois_redac' class='fondl' onChange=\"setvisibility('valider_date_prec', 'visible')\"", true) .
 "<input type='text' name='annee_redac' class='fondl' value='".$annee_redac."' size='5' maxlength='4' onClick=\"setvisibility('valider_date_prec', 'visible')\"/>" .

 '<div align="center">' .
 afficher_heure($heure_redac, "name='heure_redac' class='fondl' onChange=\"setvisibility('valider_date_prec', 'visible')\"", true) .
 afficher_minute($minute_redac, "name='minute_redac' class='fondl' onChange=\"setvisibility('valider_date_prec', 'visible')\"", true) .
 "</div>\n" .

 '</td><td align="$spip_lang_right">' .
 "<span class='visible_au_chargement' id='valider_date_prec'>" .
 '<input type="submit" name="Changer" class="fondo" value="'.
 _T('bouton_changer').'" />' .
 "</span>" .
 '</td></tr>' .
 '</table>' .
 '</div>' .
 fin_block()),
					    " method='post'");
	}
  } else {

	echo "<div style='text-align:center;'><b> <span class='verdana1'>",
	  (($statut_article == 'publie') ?
	   _T('texte_date_publication_article') :
	   _T('texte_date_creation_article')),
	  "</span> ",
	  majuscules(affdate($date))."</b>".aide('artdate')."</div>";

	if ($annee_redac.'-'.$mois_redac.'-'.$jour_redac != '0000-00-00') {
	  echo "<div style='text-align:center;'><b> <span class='verdana1'>",
	    _T('texte_date_publication_anterieure'),
	    "</span> ",
	    ' : ',
	    majuscules(affdate($date_redac)),
	    "</b>",
	    aide('artdate_redac'),
	    "</div>";
	}
 }
}


function langues_articles($id_article, $langue_article, $flag_editable, $id_rubrique, $id_trad, $dir_lang, $nom_select, $lier_trad,  $id_trad_new)
{

  global $connect_statut, $couleur_claire, $options, $connect_toutes_rubriques, $spip_lang_right;

  if (($GLOBALS['meta']['multi_articles'] == 'oui')
	OR (($GLOBALS['meta']['multi_rubriques'] == 'oui') AND ($GLOBALS['meta']['gerer_trad'] == 'oui'))) {

	$langue_article = spip_fetch_array(spip_query("SELECT lang FROM spip_articles WHERE id_article=$id_article"));

	$langue_article = $langue_article['lang'];
	if ($GLOBALS['meta']['gerer_trad'] == 'oui')
		$titre_barre = _T('titre_langue_trad_article');
	else
		$titre_barre = _T('titre_langue_article');

	$titre_barre .= "&nbsp; (".traduire_nom_langue($langue_article).")";

	debut_cadre_enfonce('langues-24.gif', false, "", bouton_block_invisible('languesarticle,ne_plus_lier,lier_traductions').$titre_barre);


	// Choix langue article
	if ($GLOBALS['meta']['multi_articles'] == 'oui' AND $flag_editable) {
		echo debut_block_invisible('languesarticle');

		$row = spip_fetch_array(spip_query("SELECT lang FROM spip_rubriques WHERE id_rubrique=$id_rubrique"));
		$langue_parent = $row['lang'];

		if (!$langue_parent)
			$langue_parent = $GLOBALS['meta']['langue_site'];
		if (!$langue_article)
			$langue_article = $langue_parent;

		debut_cadre_couleur();
		echo "<div style='text-align: center;'>",
			menu_langues('changer_lang', $langue_article, _T('info_multi_cet_article').' ', $langue_parent, redirige_action_auteur('instituer', "langue_article-$id_article-$id_rubrique","articles","id_article=$id_article")),
			"</div>\n";
		fin_cadre_couleur();

		echo fin_block();
	}


	// Gerer les groupes de traductions
	if ($GLOBALS['meta']['gerer_trad'] == 'oui') {
		if ($flag_editable AND _request('supp_trad') == 'oui') { // Ne plus lier a un groupe de trad
			spip_query("UPDATE spip_articles SET id_trad=0 WHERE id_article = $id_article");

			// Verifier si l'ancien groupe ne comporte plus qu'un seul article. Alors mettre a zero.
			$cpt = spip_fetch_array(spip_query("SELECT COUNT(*) AS n FROM spip_articles WHERE id_trad = $id_trad"));
			if ($cpt['n'] == 1)
				spip_query("UPDATE spip_articles SET id_trad = 0 WHERE id_trad = $id_trad");

			$id_trad = 0;
		}

		// Changer article de reference de la trad
		if ($id_trad_new = intval($id_trad_new)
		AND $id_trad_old = intval(_request('id_trad_old'))  # bizarre
		AND $connect_statut=='0minirezo'
		AND $connect_toutes_rubriques) { 
			spip_query("UPDATE spip_articles SET id_trad = $id_trad_new WHERE id_trad = $id_trad_old");
			$id_trad = $id_trad_new;
		}

		if ($flag_editable AND $lier_trad > 0) { // Lier a un groupe de trad
			$result_lier = spip_query("SELECT id_trad FROM spip_articles WHERE id_article=$lier_trad");

			if ($row = spip_fetch_array($result_lier)) {
				$id_lier = $row['id_trad'];

				if ($id_lier == 0) { // Si l'article vise n'a pas deja de traduction, creer nouveau id_trad
					$nouveau_trad = $lier_trad;
				}
				else {
					if ($id_lier == $id_trad) $err = "<div>"._T('trad_deja_traduit')."</div>";
					$nouveau_trad = $id_lier;
				}

				spip_query("UPDATE spip_articles SET id_trad = $nouveau_trad WHERE id_article = $lier_trad");
				if ($id_lier > 0)
					spip_query("UPDATE spip_articles SET id_trad = $nouveau_trad WHERE id_trad = $id_lier");
				spip_query("UPDATE spip_articles SET id_trad = $nouveau_trad WHERE id_article = $id_article");
				if ($id_trad > 0)
					spip_query("UPDATE spip_articles SET id_trad = $nouveau_trad WHERE id_trad = $id_trad");

				$id_trad = $nouveau_trad;
			}
			else
				$err .= "<div>"._T('trad_article_inexistant')."</div>";

			if ($err) echo "<font color='red' size=2' face='verdana,arial,helvetica,sans-serif'>$err</font>";
		}


		// Afficher la liste des traductions
		$ret = false;
		if ($id_trad != 0) {
			$result_trad = spip_query("SELECT id_article, id_rubrique, titre, lang, statut FROM spip_articles WHERE id_trad = $id_trad");
			
			$table='';
			while ($row = spip_fetch_array($result_trad)) {
				$vals = '';
				$id_article_trad = $row["id_article"];
				$id_rubrique_trad = $row["id_rubrique"];
				$titre_trad = $row["titre"];
				$lang_trad = $row["lang"];
				$statut_trad = $row["statut"];

				changer_typo($lang_trad);
				$titre_trad = "<span $dir_lang>$titre_trad</span>";

				if ($ifond == 1) {
					$ifond = 0;
					$bgcolor = "white";
				} else {
					$ifond = 1;
					$bgcolor = $couleur_claire;
				}


				$vals[] = http_img_pack("puce-".puce_statut($statut_trad).'.gif', "", "width='7' height='7' border='0' NAME='statut'");
				
				if ($id_article_trad == $id_trad) {
				  $vals[] = http_img_pack('langues-12.gif', "", "width='12' height='12' border='0'");
					$titre_trad = "<b>$titre_trad</b>";
				} else {
				  if ($connect_statut=='0minirezo'
				  AND $connect_toutes_rubriques)
				  	$vals[] = "<a href='" . generer_url_ecrire("articles","id_article=$id_article&id_trad_old=$id_trad&id_trad_new=$id_article_trad&id_rubrique=$id_rubrique_trad") . "'>". 
				    http_img_pack('langues-off-12.gif', _T('trad_reference'), "width='12' height='12' border='0'", _T('trad_reference')) . "</a>";
				  else $vals[] = http_img_pack('langues-off-12.gif', "", "width='12' height='12' border='0'");
				}

				$ret = true;

				$s = typo($titre_trad);
				if ($id_article_trad != $id_article) 
					$s = "<a href='" . generer_url_ecrire("articles","id_article=$id_article_trad&id_rubrique=$id_rubrique_trad") . "'>$s</a>";
				if ($id_article_trad == $id_trad)
					$s .= " "._T('trad_reference');

				$vals[] = $s;
				$vals[] = traduire_nom_langue($lang_trad);
				$table[] = $vals;
			}

			// changer_typo($spip_lang); (probleme d'affichage rtl?)

			// bloc traductions
			if (count($vals) > 0) {

				echo "<div class='liste'>";
				bandeau_titre_boite2(_T('trad_article_traduction'),'');
				echo "<table width='100%' cellspacing='0' border='0' cellpadding='2'>";
				//echo "<tr bgcolor='#eeeecc'><td colspan='4' class='serif2'><b>"._T('trad_article_traduction')."</b></td></tr>";

				$largeurs = array(7, 12, '', 100);
				$styles = array('', '', 'arial2', 'arial2');
				echo afficher_liste($largeurs, $table, $styles);

				echo "</table>";
				echo "</div>";

			}

			changer_typo($langue_article);
		}

		echo debut_block_invisible('lier_traductions');

		echo "<table width='100%'><tr>";
		if ($flag_editable AND $options == "avancees" AND !$ret) {
			// Formulaire pour lier a un article
			echo "<td class='arial2' width='60%'>";

			echo "<form action='" . generer_url_ecrire("articles","id_article=$id_article") . "' method='post' style='margin:0px; padding:0px;'>";
			echo _T('trad_lier');
			echo "<div align='$spip_lang_right'><input type='text' class='fondl' name='lier_trad' size='5'> <INPUT TYPE='submit' NAME='Valider' VALUE='"._T('bouton_valider')."' CLASS='fondl'></div>";
			echo "</form>";
			echo "</td>\n";
			echo "<td background='' width='10'> &nbsp; </td>";
			echo "<td background='" . _DIR_IMG_PACK . "tirets-separation.gif' width='2'>". http_img_pack('rien.gif', " ", "width='2' height='2'") . "</td>";
			echo "<td background='' width='10'> &nbsp; </td>";
		}
		echo "<td>";
		icone_horizontale(_T('trad_new'), generer_url_ecrire("articles_edit","new=oui&lier_trad=$id_article&id_rubrique=$id_rubrique"), "traductions-24.gif", "creer.gif");
		echo "</td>";
		if ($flag_editable AND $options == "avancees" AND $ret) {
			echo "<td background='' width='10'> &nbsp; </td>";
			echo "<td background='" . _DIR_IMG_PACK . "tirets-separation.gif' width='2'>". http_img_pack('rien.gif', " ", "width='2' height='2'") . "</td>";
			echo "<td background='' width='10'> &nbsp; </td>";
			echo "<td>";
			icone_horizontale(_T('trad_delier'), generer_url_ecrire("articles","id_article=$id_article&supp_trad=oui"), "traductions-24.gif", "supprimer.gif");
			echo "</td>\n";
		}

		echo "</tr></table>";

		echo fin_block();
	}

	fin_cadre_enfonce();
  }
}

function rechercher_auteurs_articles($cherche_auteur, $ids, $id_article)
{
	if (!$ids) {
		echo "<B>"._T('texte_aucun_resultat_auteur', array('cherche_auteur' => $cherche_auteur)).".</B><BR />";
	}
	elseif ($ids == -1) {
		echo "<B>"._T('texte_trop_resultats_auteurs', array('cherche_auteur' => $cherche_auteur))."</B><BR />";
	}
	elseif (!strpos($ids,',')) {

		$row = spip_fetch_array(spip_query("SELECT nom FROM spip_auteurs WHERE id_auteur=$ids"));
		echo "<B>"._T('texte_ajout_auteur')."</B><BR /><UL><LI><FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=2><B><FONT SIZE=3>".typo($row['nom'])."</FONT></B></UL>";
	}
	else {
		$ids = preg_replace('/[^0-9,]/','',$ids); // securite
		$result = spip_query("SELECT * FROM spip_auteurs WHERE id_auteur IN ($ids) ORDER BY nom");

		echo "<B>"._T('texte_plusieurs_articles', array('cherche_auteur' => $cherche_auteur))."</B><BR />";
		echo "<UL class='verdana1'>";
		while ($row = spip_fetch_array($result)) {
				$id_auteur = $row['id_auteur'];
				$nom_auteur = $row['nom'];
				$email_auteur = $row['email'];
				$bio_auteur = $row['bio'];

				echo "<li><b>".typo($nom_auteur)."</b>";

				if ($email_auteur) echo " ($email_auteur)";
				echo " | <A href='", redirige_action_auteur('ajouter', "$id_article-$id_auteur","articles","id_article=$id_article#auteurs") . "'>",_T('lien_ajouter_auteur'),"</A>";

				if (trim($bio_auteur)) {
					echo "<br />".couper(propre($bio_auteur), 100)."\n";
				}
				echo "</li>\n";
			}
		echo "</UL>";
	}

}

function afficher_auteurs_articles($id_article, $flag_editable)
{
	global $connect_statut, $options,$connect_id_auteur;

	$les_auteurs = array();

	$result = spip_query("SELECT * FROM spip_auteurs AS auteurs, spip_auteurs_articles AS lien WHERE auteurs.id_auteur=lien.id_auteur AND lien.id_article=$id_article GROUP BY auteurs.id_auteur ORDER BY auteurs.nom");

	if (spip_num_rows($result)) {
		echo "<div class='liste'>";
		echo "<table width='100%' cellpadding='3' cellspacing='0' border='0' background=''>";
		$table = array();
		while ($row = spip_fetch_array($result)) {
			$vals = array();
			$id_auteur = $row["id_auteur"];
			$nom_auteur = $row["nom"];
			$email_auteur = $row["email"];
			if ($bio_auteur = attribut_html(propre(couper($row["bio"], 100))))
			  $bio_auteur = " TITLE=\"$bio_auteur\"";
			$url_site_auteur = $row["url_site"];
			$statut_auteur = $row["statut"];
			if ($row['messagerie'] == 'non' OR $row['login'] == '') $messagerie = 'non';
			
			$les_auteurs[] = $id_auteur;

			$vals[] = bonhomme_statut($row);

			$vals[] = "<a href='" . generer_url_ecrire('auteurs_edit', "id_auteur=$id_auteur") . "' $bio_auteur>".typo($nom_auteur)."</a>";

			$vals[] = bouton_imessage($id_auteur);
		
			if ($email_auteur) $vals[] =  "<A href='mailto:$email_auteur'>"._T('email')."</A>";
			else $vals[] =  "&nbsp;";

			if ($url_site_auteur) $vals[] =  "<A href='$url_site_auteur'>"._T('info_site_min')."</A>";
			else $vals[] =  "&nbsp;";

			$cpt = spip_fetch_array(spip_query("SELECT COUNT(articles.id_article) AS n FROM spip_auteurs_articles AS lien, spip_articles AS articles WHERE lien.id_auteur=$id_auteur AND articles.id_article=lien.id_article AND articles.statut IN " . ($connect_statut == "0minirezo" ? "('prepa', 'prop', 'publie', 'refuse')" : "('prop', 'publie')") . " GROUP BY lien.id_auteur"));

			$nombre_articles = intval($cpt['n']);

			if ($nombre_articles > 1) $vals[] =  $nombre_articles.' '._T('info_article_2');
			else if ($nombre_articles == 1) $vals[] =  _T('info_1_article');
			else $vals[] =  "&nbsp;";

			if ($flag_editable AND ($connect_id_auteur != $id_auteur OR $connect_statut == '0minirezo') AND $options == 'avancees') {
				$vals[] =  "<a href='" . redirige_action_auteur('supprimer', "auteur_article-$id_auteur-$id_article","articles","id_article=$id_article#auteurs") . "'>"._T('lien_retirer_auteur')."&nbsp;". http_img_pack('croix-rouge.gif', "X", "width='7' height='7' border='0' align='middle'") . "</a>";
			} else {
			  $vals[] = "";
			}
		
			$table[] = $vals;
		}
	
	$largeurs = array('14', '', '', '', '', '', '');
	$styles = array('arial11', 'arial2', 'arial11', 'arial11', 'arial11', 'arial11', 'arial1');
	echo afficher_liste($largeurs, $table, $styles);

	
	echo "</table></div>\n";

	$les_auteurs = join(',', $les_auteurs);
	}
	return $les_auteurs ;
}


function ajouter_auteurs_articles($id_article, $les_auteurs)
{
	$result = spip_query("SELECT * FROM spip_auteurs WHERE " . (!$les_auteurs ? '' : "id_auteur NOT IN ($les_auteurs) AND ") . "statut!='5poubelle' AND statut!='6forum' AND statut!='nouveau' ORDER BY statut, nom");

	if (!$num = spip_num_rows($result)) return '';

	return redirige_action_auteur('ajouter', $id_article,'articles', "id_article=$id_article",
				      (
			"<span class='verdana1'><B>"._T('titre_cadre_ajouter_auteur')."&nbsp; </B></span>\n" .

			($num > 200 ? 
			 ("<input type='text' name='cherche_auteur' onClick=\"setvisibility('valider_ajouter_auteur','visible');\" CLASS='fondl' VALUE='' SIZE='20' />" .
			  "<span  class='visible_au_chargement' id='valider_ajouter_auteur'>\n<input type='submit' value='"._T('bouton_chercher')."' CLASS='fondo' /></span>") :
			 ("<select name='nouv_auteur' size='1' style='width:150px;' CLASS='fondl' onChange=\"setvisibility('valider_ajouter_auteur','visible');\">" .
			   articles_auteur_select($result) .
			   "</select>" .
			   "<span  class='visible_au_chargement' id='valider_ajouter_auteur'>" .
			   " <input type='submit' value='"._T('bouton_ajouter')."' CLASS='fondo'>" .
			   "</span>"))),
					    " method='post'");
}

function articles_auteur_select($result)
{
	global $couleur_claire ;

	$statut_old = $premiere_old = $res = '';

	while ($row = spip_fetch_array($result)) {
		$id_auteur = $row["id_auteur"];
		$nom = $row["nom"];
		$email = $row["email"];
		$statut = $row["statut"];

		$statut=str_replace("0minirezo", _T('info_administrateurs'), $statut);
		$statut=str_replace("1comite", _T('info_redacteurs'), $statut);
		$statut=str_replace("6visiteur", _T('info_visiteurs'), $statut);
				
		$premiere = strtoupper(substr(trim($nom), 0, 1));

		if ($connect_statut != '0minirezo')
			if ($p = strpos($email, '@'))
				  $email = substr($email, 0, $p).'@...';
		if ($email)
			$email = " ($email)";

		if ($statut != $statut_old) {
			$res .= "\n<OPTION VALUE=\"x\">";
			$res .= "\n<OPTION VALUE=\"x\" style='background-color: $couleur_claire;'> $statut";
		}

		if ($premiere != $premiere_old AND ($statut != _T('info_administrateurs') OR !$premiere_old))
			$res .= "\n<OPTION VALUE=\"x\">";
				
		$res .= "\n<OPTION VALUE=\"$id_auteur\">&nbsp;&nbsp;&nbsp;&nbsp;" . supprimer_tags(couper(typo("$nom$email"), 40));
		$statut_old = $statut;
		$premiere_old = $premiere;
	}
	return $res;
}

function afficher_corps_articles($virtuel, $chapo, $texte, $ps,  $extra)
{
  global $revision_nbsp, $activer_revision_nbsp, $champs_extra, $les_notes, $dir_lang;

	echo "\n\n<div align='justify' style='padding: 10px;'>";

	if ($virtuel) {
		debut_boite_info();
		echo _T('info_renvoi_article')." ".propre("<center>[->$virtuel]</center>");
		fin_boite_info();
	} else {
		$revision_nbsp = $activer_revision_nbsp;

		if (strlen($chapo) > 0) {
			echo "<div $dir_lang><b>";
			echo propre($chapo);
			echo "</b></div>\n\n";
		}

		echo "<div $dir_lang>";
#	echo reduire_image(propre($texte), 500,10000);
		echo propre($texte);
		echo "<br clear='all' />";
		echo "</div>";

		if ($ps) {
			echo debut_cadre_enfonce();
			echo "<div $dir_lang><font style='font-family:Verdana,Arial,Sans,sans-serif; font-size: small;'>";
			echo justifier("<b>"._T('info_ps')."</b> ".propre($ps));
			echo "</font></div>";
			echo fin_cadre_enfonce();
		}
		$revision_nbsp = false;

		if ($les_notes) {
			echo debut_cadre_relief();
			echo "<div $dir_lang class='arial11'>";
			echo justifier("<b>"._T('info_notes')."&nbsp;:</b> ".$les_notes);
			echo "</div>";
			echo fin_cadre_relief();
		}
		
		if ($champs_extra AND $extra) {
			include_spip('inc/extra');
			extra_affichage($extra, "articles");
		}
	}
}

function affiche_forums_article($id_article, $id_rubrique, $titre, $debut, $mute=false)
{
  global $spip_lang_left;

  echo "<BR><BR>";

  
  if (!$mute) {
    $tm = rawurlencode($titre);
    echo "\n<div align='center'>";
    icone(_T('icone_poster_message'), generer_url_ecrire("forum_envoi","statut=prive&id_article=$id_article&titre_message=$tm&url=" . generer_url_retour("articles","id_article=$id_article")), "forum-interne-24.gif", "creer.gif");
    echo "</div>";
  }

  echo "<P align='$spip_lang_left'>";

  $result_forum = spip_query("SELECT COUNT(*) AS cnt FROM spip_forum WHERE statut='prive' AND id_article='$id_article' AND id_parent=0");

  $total = 0;
  if ($row = spip_fetch_array($result_forum)) $total = $row["cnt"];

  if (!$debut) $debut = 0;
  $total_afficher = 8;
  if ($total > $total_afficher) {
	echo "<div class='serif2' align='center'>";
	for ($i = 0; $i < $total; $i = $i + $total_afficher){
		$y = $i + $total_afficher - 1;
		if ($i == $debut)
			echo "<FONT SIZE=3><B>[$i-$y]</B></FONT> ";
		else
			echo "[<A href='" . generer_url_ecrire("articles","id_article=$id_article&debut=$i") . "'>$i-$y</A>] ";
	}
	echo "</div>";
}

	$result_forum = spip_query("SELECT * FROM spip_forum WHERE statut='prive' AND id_article='$id_article' AND id_parent=0 ORDER BY date_heure DESC" .   " LIMIT $debut,$total_afficher"   );
#				   " LIMIT $total_afficher OFFSET $debut" # PG

	afficher_forum($result_forum, "articles","id_article=$id_article", $mute);

	if (!$debut) $debut = 0;
	$total_afficher = 8;
	if ($total > $total_afficher) {
	  echo "<div class='serif2' align='center'>";
	  for ($i = 0; $i < $total; $i = $i + $total_afficher){
		$y = $i + $total_afficher - 1;
		if ($i == $debut)
			echo "<FONT SIZE=3><B>[$i-$y]</B></FONT> ";
		else
			echo "[<A href='" . generer_url_ecrire("articles","id_article=$id_article&debut=$i") . "'>$i-$y</A>] ";
	  }
	  echo "</div>";
	}

	echo "</div>\n";
}

function afficher_statut_articles($id_article, $rubrique_article, $statut_article)
{
  return redirige_action_auteur("instituer", "article-$id_article",'articles', "id_article=$id_article",
	("\n<center>" . 
	"<b>" ._T('texte_article_statut') ."</b>" .
	"\n<select name='statut_nouv' size='1' class='fondl'\n" .
	"onChange=\"document.statut.src='" .
	_DIR_IMG_PACK .
	"' + puce_statut(options[selectedIndex].value);" .
	" setvisibility('valider_statut', 'visible');\">\n" .
	"<option"  . mySel("prepa", $statut_article)  ." style='background-color: white'>" ._T('texte_statut_en_cours_redaction') ."</option>\n" .
	"<option"  . mySel("prop", $statut_article)  . " style='background-color: #FFF1C6'>" ._T('texte_statut_propose_evaluation') ."</option>\n" .
	"<option"  . mySel("publie", $statut_article)  . " style='background-color: #B4E8C5'>" ._T('texte_statut_publie') ."</option>\n" .
	"<option"  . mySel("poubelle", $statut_article) .
	http_style_background('rayures-sup.gif')  . '>'  ._T('texte_statut_poubelle') ."</option>\n" .
	"<option"  . mySel("refuse", $statut_article)  . " style='background-color: #FFA4A4'>" ._T('texte_statut_refuse') ."</option>\n" .
	"</select>" .
	" &nbsp; " .
	http_img_pack("puce-".puce_statut($statut_article).'.gif', "", "border='0' NAME='statut'") .
	"  &nbsp;\n" .
	"<span class='visible_au_chargement' id='valider_statut'>" .
	"<input type='submit' value='"._T('bouton_valider')."' CLASS='fondo' />" .
	"</span>" .
	aide("artstatut") .
	"</center>"), 
			   " method='post'");
}

//
// Reunit les textes decoupes parce que trop longs
//

function trop_longs_articles($texte_plus)
{
	$nb_texte = 0;
	while ($nb_texte ++ < count($texte_plus)+1){
		$texte_ajout .= ereg_replace("<!--SPIP-->[\n\r]*","",
					     $texte_plus[$nb_texte]);
	}
	return $texte_ajout;
}

// Passer les images/docs en "inclus=non"

function inclus_non_articles($id_article)
{
  $result = spip_query("SELECT docs.id_document FROM spip_documents AS docs, spip_documents_articles AS lien WHERE lien.id_article=$id_article AND lien.id_document=docs.id_document");

  $ze_doc = array();
  while($row=spip_fetch_array($result)){
	$ze_doc[]=$row['id_document'];
}

if (count($ze_doc)>0){
	$ze_docs = join($ze_doc,",");
	spip_query("UPDATE spip_documents SET inclus='non' WHERE id_document IN ($ze_docs)");
}

}

function revisions_articles ($id_article, $id_rubrique, $change_rubrique, $titre_article) {
{
	global $connect_id_auteur, $flag_revisions, $champs_extra;

	$texte = trop_longs_articles(_request('texte_plus')) . _request('texte');
	$new = _request('new');
	$champs = array(
		'surtitre' => corriger_caracteres(_request('surtitre')),
		'titre' => $titre_article,
		'soustitre' => corriger_caracteres(_request('soustitre')),
		'descriptif' => corriger_caracteres(_request('descriptif')),
		'nom_site' => corriger_caracteres(_request('nom_site')),
		'url_site' => corriger_caracteres(_request('url_site')),
		'chapo' => corriger_caracteres(
		_request('changer_virtuel')?'='._request('virtuel') : _request('chapo')
		),
		'texte' => corriger_caracteres($texte),
		'ps' => corriger_caracteres(_request('ps')))  ;

	// Stockage des versions : creer une premier version si non-existante
	if (($GLOBALS['meta']["articles_versions"]=='oui') && $flag_revisions) {
		include_spip('inc/revisions');
		if  ($new != 'oui') {
			$query = spip_query("SELECT id_article FROM spip_versions WHERE id_article=$id_article LIMIT 1");
			if (!spip_num_rows($query)) {
				$select = join(", ", array_keys($champs));
				$query = spip_query("SELECT $select FROM spip_articles WHERE id_article=$id_article");
				$champs_originaux = spip_fetch_array($query);
				$id_version = ajouter_version($id_article, $champs_originaux, _T('version_initiale'), 0);

				// Remettre une date un peu ancienne pour la version initiale 
				if ($id_version == 1) // test inutile ?
				spip_query("UPDATE spip_versions SET date=DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id_article=$id_article AND id_version=1");
			}
		}
	}

	if ($champs_extra) {
		include_spip('inc/extra');
		$champs_extra = extra_recup_saisie("articles", _request('id_secteur'));
	}

	spip_query("UPDATE spip_articles SET surtitre=" . spip_abstract_quote($champs['surtitre']) . ", titre=" . spip_abstract_quote($champs['titre']) . ", soustitre=" . spip_abstract_quote($champs['soustitre']) . ", id_rubrique=" .			   intval($id_rubrique) .		   ", descriptif=" . spip_abstract_quote($champs['descriptif']) . ", chapo=" . spip_abstract_quote($champs['chapo']) . ", texte=" . spip_abstract_quote($champs['texte']) . ", ps=" . spip_abstract_quote($champs['ps']) . ", url_site=" . spip_abstract_quote($champs['url_site']) . ", nom_site=" . spip_abstract_quote($champs['nom_site']) . ", date_modif=NOW() " . ($champs_extra ? (", extra = " . spip_abstract_quote($champs_extra)) : '') . " WHERE id_article=$id_article");

	// Stockage des versions
	if (($GLOBALS['meta']["articles_versions"]=='oui') && $flag_revisions) {
		ajouter_version($id_article, $champs, '', $connect_id_auteur);
	}

	// marquer le fait que l'article est travaille par toto a telle date
	// une alerte sera donnee aux autres redacteurs sur exec=articles
	if ($GLOBALS['meta']['articles_modif'] != 'non') {
		include_spip('inc/drapeau_edition');
		if ($id_article)
			signale_edition ($id_article, $connect_id_auteur, 'article');
	}


	// Changer la langue heritee
	if ($id_rubrique != _request('id_rubrique_old')) {
		propager_les_secteurs();
		$row = spip_fetch_array(spip_query("SELECT lang, langue_choisie FROM spip_articles WHERE id_article=$id_article"));
		$langue_old = $row['lang'];
		$langue_choisie_old = $row['langue_choisie'];

		if ($langue_choisie_old != "oui") {
			$row = spip_fetch_array(spip_query("SELECT lang FROM spip_rubriques WHERE id_rubrique=$id_rubrique"));
			$langue_new = $row['lang'];
			if ($langue_new != $langue_old)
				spip_query("UPDATE spip_articles SET lang = '$langue_new' WHERE id_article = $id_article");
		}
	}

	calculer_rubriques();
 }
}

function insert_article($id_parent)
{
	global $connect_id_auteur;
	// Avec l'Ajax parfois id_rubrique vaut 0... ne pas l'accepter
	if (!$id_rubrique = intval($id_parent)) {
		$row = spip_fetch_array(spip_query("SELECT id_rubrique FROM spip_rubriques WHERE id_parent=0 ORDER by 0+titre,titre LIMIT 1"));
		$id_rubrique = $row['id_rubrique'];
	}

	$row = spip_fetch_array(spip_query("SELECT lang FROM spip_rubriques WHERE id_rubrique=$id_rubrique"));

	$id_article = spip_abstract_insert("spip_articles",
			"(id_rubrique, statut, date, accepter_forum, lang, langue_choisie)", 
			"($id_rubrique, 'prepa', NOW(), '" .
				substr($GLOBALS['meta']['forums_publics'],0,3)
				. "', '"
				. ($row["lang"] ? $row["lang"] : $GLOBALS['meta']['langue_site'])
				. "', 'non')");
	spip_abstract_insert('spip_auteurs_articles', "(id_auteur,id_article)", "('$connect_id_auteur','$id_article')");
	return $id_article;
}

function articles_set($id_article, $id_rubrique, $statut)
{
   if  (isset($_POST['modif_document']))
     maj_documents($id_article, 'article');

   if ($_POST['changer_virtuel']) {
     if ($virtuel = eregi_replace("^http://$", "", trim($_POST['virtuel'])))
		$chapo = corriger_caracteres("=$virtuel");
     else $chapo = $_POST['chapo'];
     spip_query("UPDATE spip_articles SET chapo=" . spip_abstract_quote($chapo) . ", date_modif=NOW() WHERE id_article=$id_article");
   }

   if (!isset($_POST['titre'])) return 0;

   if (!strlen($titre_article=corriger_caracteres($_POST['titre'])))
		$titre_article = _T('info_sans_titre');

   revisions_articles ($id_article, $id_rubrique, $statut, $titre_article);

   return $id_article;  
}

?>
