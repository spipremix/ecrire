<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2005                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/


if (!defined("_ECRIRE_INC_VERSION")) return;
include_ecrire ("inc_meta");

//
// Appliquer les valeurs par defaut pour les options non initialisees
//
function init_config() {
	// langue par defaut du site = langue d'installation (cookie spip_lang) sinon francais
	if (!$lang = $GLOBALS['spip_lang'])
		$lang = 'fr';

	$liste_meta = array(
		'activer_breves' => 'oui',
		'config_precise_groupes' => 'non',
		'mots_cles_forums' =>  'non',
		'articles_surtitre' => 'oui',
		'articles_soustitre' => 'oui',
		'articles_descriptif' => 'oui',
		'articles_chapeau' => 'oui',
		'articles_ps' => 'oui',
		'articles_redac' => 'non',
		'articles_mots' => 'oui',
		'post_dates' => 'oui',
		'articles_urlref' => 'non',
		'creer_preview' => 'non',
		'taille_preview' => 150,
		'articles_modif' => 'non',

		'activer_sites' => 'oui',
		'proposer_sites' => 0,
		'activer_syndic' => 'oui',
		'visiter_sites' => 'non',
		'moderation_sites' => 'non',

		'forums_publics' => 'posteriori',
		'accepter_inscriptions' => 'non',
		'accepter_visiteurs' => 'non',
		'prevenir_auteurs' => 'non',
		'suivi_edito' => 'non',
		'quoi_de_neuf' => 'non',
		'forum_prive_admin' => 'non',

		'activer_moteur' => 'oui',
		'articles_versions' => 'non',
		'articles_ortho' => 'non',
		'preview' => 'non',
		'activer_statistiques' => 'oui',

		'documents_article' => 'oui',
		'documents_rubrique' => 'non',
		'charset' => 'iso-8859-1',

		'creer_htpasswd' => 'non',
		'creer_htaccess' => 'non',

		'langue_site' => $lang,

		'multi_articles' => 'non',
		'multi_rubriques' => 'non',
		'multi_secteurs' => 'non',
		'gerer_trad' => 'non',
		'langues_multilingue' => $GLOBALS['all_langs']
	);
	while (list($nom, $valeur) = each($liste_meta)) {
		if (!$GLOBALS['meta'][$nom]) {
			ecrire_meta($nom, $valeur);
			$modifs = true;
		}
	}

	// Cas particulier : charset regle a utf-8 uniquement si nouvelle installation
	if ($GLOBALS['meta']['nouvelle_install'] == 'oui') {
		//ecrire_meta('charset', 'utf-8');
		effacer_meta('nouvelle_install');
		$modifs = true;
	}

	if ($modifs) ecrire_metas();
}


function avertissement_config() {
	global $spip_lang_right, $spip_lang_left;
	debut_boite_info();

	echo "<div class='verdana2' align='justify'>
	<p align='center'><B>"._T('avis_attention')."</B></p>",
	  http_img_pack("warning.gif", addslashes(_T('avis_attention')), "width='48' height='48' align='$spip_lang_right' style='padding-$spip_lang_left: 10px;'");

	echo _T('texte_inc_config');

	echo "</div>";

	fin_boite_info();
	echo "<p>&nbsp;<p>";
}


function bouton_radio($nom, $valeur, $titre, $actif = false, $onClick="") {
	static $id_label = 0;
	
	if (strlen($onClick) > 0) $onClick = " onClick=\"$onClick\"";
	$texte = "<input type='radio' name='$nom' value='$valeur' id='label_$id_label'$onClick";
	if ($actif) {
		$texte .= ' checked="checked"';
		$titre = '<b>'.$titre.'</b>';
	}
	$texte .= " /> <label for='label_$id_label'>$titre</label>\n";
	$id_label++;
	return $texte;
}


function afficher_choix($nom, $valeur_actuelle, $valeurs, $sep = "<br />") {
	while (list($valeur, $titre) = each($valeurs)) {
		$choix[] = bouton_radio($nom, $valeur, $titre, $valeur == $valeur_actuelle);
	}
	echo "\n".join($sep, $choix);
}


//
// Gestion des modifs
//

function appliquer_modifs_config() {
	global $clean_link, $connect_id_auteur;
	global $adresse_site, $email_webmaster, $email_envoi, $post_dates, $tester_proxy, $test_proxy, $http_proxy, $activer_moteur;
	global $forums_publics, $forums_publics_appliquer;
	global $charset, $charset_custom, $langues_auth;

	$adresse_site = ereg_replace("/$", "", $adresse_site);

	// Purger les squelettes si un changement de meta les affecte
	if ($post_dates AND ($post_dates != $GLOBALS['meta']["post_dates"]))
		$purger_skel = true;
	if ($forums_publics AND ($forums_publics != $GLOBALS['meta']["forums_publics"]))
		$purger_skel = true;

	// Appliquer les changements de moderation forum
	// forums_publics_appliquer : futur, saufnon, tous
	$accepter_forum = substr($forums_publics,0,3);
	$requete_appliquer = ($forums_publics_appliquer == 'saufnon') ?
		"UPDATE spip_articles SET accepter_forum='$accepter_forum'
		WHERE accepter_forum != 'non'" : 
		(($forums_publics_appliquer == 'tous') ?
			"UPDATE spip_articles SET accepter_forum='$accepter_forum'" : '');
	if ($requete_appliquer) spip_query($requete_appliquer);

	if ($accepter_forum == 'abo')
		ecrire_meta('accepter_visiteurs', 'oui');

	// Test du proxy : $tester_proxy est le bouton "submit"

	// http_proxy : ne pas prendre en compte la modif si le password est '****'
	if (preg_match(',:\*\*\*\*@,', $http_proxy))
		$http_proxy = $GLOBALS['meta']['http_proxy'];

	if ($tester_proxy) {
		if (!$test_proxy) {
			echo _T('info_adresse_non_indiquee');
			exit;
		} else {
			include_ecrire('inc_distant');
			$page = recuperer_page($test_proxy, true);
			if ($page)
				echo "<pre>".entites_html($page)."</pre>";
			else
				echo _T('info_impossible_lire_page', array('test_proxy' => $test_proxy))." <tt>".no_password_proxy_url($http_proxy)."</tt>.".aide('confhttpproxy');
			exit;
		}
	}

	// Activer le moteur : dresser la liste des choses a indexer
	if ($activer_moteur == 'oui' AND ($activer_moteur != $GLOBALS['meta']["activer_moteur"])) {
		include_ecrire('inc_index');
		creer_liste_indexation();
	}

	if ($langues_auth) {
		$GLOBALS['langues_multilingue'] = join($langues_auth, ",");
	}

	if (isset($email_webmaster))
		ecrire_meta("email_webmaster", $email_webmaster);
	if (isset($email_envoi))
		ecrire_meta("email_envoi", $email_envoi);
	if ($charset == 'custom') $charset = $charset_custom;

	$liste_meta = array(
		'nom_site',
		'adresse_site',

		'activer_breves',
		'config_precise_groupes',
		'mots_cles_forums',
		'articles_surtitre',
		'articles_soustitre',
		'articles_descriptif',
		'articles_chapeau',
		'articles_ps',
		'articles_redac',
		'articles_mots',
		'post_dates',
		'articles_urlref',
		'creer_preview',
		'taille_preview',
		'articles_modif',

		'activer_sites',
		'proposer_sites',
		'activer_syndic',
		'visiter_sites',
		'moderation_sites',
		'http_proxy',

		'forums_publics',
		'accepter_inscriptions',
		'accepter_visiteurs',
		'prevenir_auteurs',
		'suivi_edito',
		'adresse_suivi',
		'adresse_suivi_inscription',
		'quoi_de_neuf',
		'adresse_neuf',
		'jours_neuf',
		'forum_prive_admin',

		'activer_moteur',
		'articles_versions',
		'articles_ortho',
		'preview',
		'activer_statistiques',

		'documents_article',
		'documents_rubrique',

		'charset',
		'multi_articles',
		'multi_rubriques',
		'multi_secteurs',
		'gerer_trad',
		'langues_multilingue'
	);
	// Modification du reglage accepter_inscriptions => vider le cache
	// (pour repercuter la modif sur le panneau de login)
	if (isset($GLOBALS['accepter_inscriptions'])
	AND ($GLOBALS['accepter_inscriptions']
	!= $GLOBALS['meta']['accepter_inscriptions'])) {
		include_ecrire('inc_invalideur');
		suivre_invalideur("1"); # tout effacer
	}

	while (list(,$i) = each($liste_meta))
		if (isset($GLOBALS[$i])) ecrire_meta($i, $GLOBALS[$i]);

	// langue_site : la globale est mangee par inc_version
	if ($lang = $GLOBALS['changer_langue_site']) {
		$lang2 = $GLOBALS['spip_lang'];
		if (changer_langue($lang)) {
			ecrire_meta('langue_site', $lang);
			changer_langue($lang2);
		}
	}

	ecrire_metas();

	// modifs de secu (necessitent une authentification ftp)
	$liste_meta = array(
			    'creer_htpasswd',
			    'creer_htaccess'
	);
	while (list(,$i) = each($liste_meta))
	  if (isset($GLOBALS[$i]) AND ($GLOBALS[$i] != $GLOBALS['meta'][$i]))
			$modif_secu=true;
	if ($modif_secu) {
		$admin = _T('info_modification_parametres_securite');
		include_ecrire ("inc_admin");
		debut_admin($admin);
		reset($liste_meta);
		while (list(,$i) = each($liste_meta))
			if (isset($GLOBALS[$i])) ecrire_meta($i, $GLOBALS[$i]);
		ecrire_metas();
		fin_admin($admin);
	}

	if ($purger_skel) {
		redirige_par_entete("../spip_cache.php3?purger_squelettes=oui&id_auteur=$connect_id_auteur&hash=" .
				    calculer_action_auteur("purger_squelettes") .
				    "&redirect=".  _DIR_RESTREINT_ABS .
				    urlencode($clean_link->getUrl()));
	}
}


// Ne pas afficher la partie 'password' du proxy
function no_password_proxy_url($http_proxy) {
	if ($p = @parse_url($http_proxy)
	AND $p['pass']) {
		$p['pass'] = '****';
		$http_proxy = glue_url($p);
	}
	return $http_proxy;
}


// Function glue_url : le pendant de parse_url 
function glue_url ($url){
	if (!is_array($url)){
		return false;
	}
	// scheme
	$uri = (!empty($url['scheme'])) ? $url['scheme'].'://' : '';
	// user & pass
	if (!empty($url['user'])){
		$uri .= $url['user'].':'.$url['pass'].'@';
	}
	// host
	$uri .= $url['host'];
	// port
	$port = (!empty($url['port'])) ? ':'.$url['port'] : '';
	$uri .= $port;
	// path
	$uri .= $url['path'];
// fragment or query
	if (isset($url['fragment'])){
		$uri .= '#'.$url['fragment'];
	} elseif (isset($url['query'])){
		$uri .= '?'.$url['query'];
	}
	return $uri;
}

?>
