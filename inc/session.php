<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2014                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

/**
 * Gestion de l'authentification par sessions
 * à utiliser pour valider l'accès (bloquant)
 * ou pour reconnaître un utilisateur (non bloquant)
 *
 * @package SPIP\Core\Session
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


$GLOBALS['visiteur_session'] = array(); # globale decrivant l'auteur

/**
 * 3 actions sur les sessions, selon le type de l'argument:
 * - numerique: efface toutes les sessions de l'auteur (retour quelconque)
 * - tableau: cree une session pour l'auteur decrit et retourne l'identifiant
 * - bool: predicat de validite de la session indiquee par le cookie
 *
 * http://doc.spip.org/@inc_session_dist
 *
 * @param int|array|bool $auteur
 * @return bool|null|void
 */
function inc_session_dist($auteur=false)
{
	if (is_numeric($auteur))
		return supprimer_sessions($auteur, $auteur > 0);
	else if (is_array($auteur))
		return ajouter_session($auteur);
	else
		return verifier_session($auteur);
}


/**
 * Supprimer toutes les vieilles sessions d'un auteur
 *
 * Cette fonction efface toutes les sessions appartenant a l'auteur
 * On en profite pour effacer toutes les sessions
 * creees il y a plus de 4*_RENOUVELLE_ALEA
 * Tenir compte de l'ancien format ou les noms commencaient par "session_"
 * et du meme coup des repertoires plats
 * Attention : id_auteur peut etre negatif (cas des auteurs temporaires pendant le dump)
 *
 * http://doc.spip.org/@supprimer_sessions
 *
 * @param int $id_auteur
 * 		Identifiant d'auteur dont on veut supprimer les sessions
 * @param bool $toutes
 * 		Supprimer aussi les vieilles sessions des autres auteurs ?
 * @param bool $actives
 * 		false pour ne pas supprimer les sessions valides de $id_auteur.
 * 		false revient donc a uniquement supprimer les vieilles sessions !
 */
function supprimer_sessions($id_auteur, $toutes=true, $actives=true) {

	spip_log("supprimer sessions auteur $id_auteur","session");
	if ($toutes OR $id_auteur!==$GLOBALS['visiteur_session']['id_auteur']) {
		if ($dir = opendir(_DIR_SESSIONS)){
			$t = time()  - (4*_RENOUVELLE_ALEA);
			while(($f = readdir($dir)) !== false) {
				if (preg_match(",^[^\d-]*(-?\d+)_\w{32}\.php[3]?$,", $f, $regs)){
					$f = _DIR_SESSIONS . $f;
					if (($actives AND $regs[1] == $id_auteur) OR ($t > filemtime($f)))
						spip_unlink($f);
				}
			}
		}
	}
	else {
		verifier_session();
		spip_unlink(fichier_session('alea_ephemere', true));
	}

	// forcer le recalcul de la session courante
	spip_session(true);
}

/**
 * Ajoute une session pour l'auteur décrit par un tableau issu d'un SELECT-SQL
 *
 * @param array $auteur
 *     Description de la session de l'auteur. Peut contenir (par exemple)
 *     les clés : id_auteur, nom, login, email, statut, lang, ...
 * @return bool|string
 */
function ajouter_session($auteur) {
	// Si le client a deja une session valide pour son id_auteur
	// on conserve le meme fichier

	// Attention un visiteur peut avoir une session et un id=0,
	// => ne pas melanger les sessions des differents visiteurs
	$id_auteur = isset($auteur['id_auteur']) ? intval($auteur['id_auteur']) : 0;

	// Si ce n'est pas un inscrit (les inscrits ont toujours des choses en session)
	// on va vérifier s'il y a vraiment des choses à écrire
	if (!$id_auteur){
		// On supprime les données de base pour voir le contenu réel de la session
		$auteur_verif = $auteur;
		if (isset($auteur_verif['id_auteur'])) unset($auteur_verif['id_auteur']);
		if (isset($auteur_verif['hash_env'])) unset($auteur_verif['hash_env']);
		if (isset($auteur_verif['ip_change'])) unset($auteur_verif['ip_change']);
		if (isset($auteur_verif['date_session'])) unset($auteur_verif['date_session']);

		// Les variables vraiment nulle ne sont pas à prendre en compte non plus
		foreach($auteur_verif as $variable=>$valeur){
			if ($valeur === null){
				unset($auteur_verif[$variable]);
			}
		}

		// Si après ça la session est vide alors on supprime l'éventuel fichier et on arrête là
		if (!$auteur_verif){
			if (isset($_COOKIE['spip_session']) AND isset($_SESSION[$_COOKIE['spip_session']]))
				unset($_SESSION[$_COOKIE['spip_session']]);
			if (isset($_COOKIE['spip_session']))
				unset($_COOKIE['spip_session']);
			return false;
		}
	}

	if (!isset($_COOKIE['spip_session'])
	  OR !preg_match(',^'.$id_auteur.'_,', $_COOKIE['spip_session'])){
		$_COOKIE['spip_session'] = $id_auteur.'_'.md5(uniqid(rand(),true));
	}

	// Maintenant on sait qu'on a des choses à écrire
	// On s'assure d'avoir au moins ces valeurs
	$auteur['id_auteur'] = $id_auteur;
	if (!isset($auteur['hash_env'])) $auteur['hash_env'] = hash_env();
	if (!isset($auteur['ip_change'])) $auteur['ip_change'] = false;

	if (!isset($auteur['date_session'])) $auteur['date_session'] = time();
	if (isset($auteur['prefs']) and is_string($auteur['prefs']))
		$auteur['prefs'] = unserialize($auteur['prefs']);

	// les sessions anonymes sont stockees dans $_SESSION
	if (!$id_auteur){
		if (!isset($_SESSION[$_COOKIE['spip_session']]))
			session_start();
		$_SESSION[$_COOKIE['spip_session']] = preparer_ecriture_session($auteur);
	}
	else {
		$fichier_session = fichier_session('alea_ephemere');
		if (!ecrire_fichier_session($fichier_session, $auteur)) {
			spip_log('Echec ecriture fichier session '.$fichier_session,"session"._LOG_HS);
			include_spip('inc/minipres');
			echo minipres();
			exit;
		}
	}

	// poser le cookie de session SPIP
	include_spip('inc/cookie');
	$duree = _RENOUVELLE_ALEA *
	  (!isset($auteur['cookie'])
	    ? 2 : (is_numeric($auteur['cookie'])
			? $auteur['cookie'] : 20));
	spip_setcookie(
		'spip_session',
		$_COOKIE['spip_session'],
		time() + $duree
		);
	spip_log("ajoute session $fichier_session cookie $duree","session");

	# on en profite pour purger les vieilles sessions anonymes abandonnees
	# supprimer_sessions(0, true, false);

	return $_COOKIE['spip_session'];
}


/**
 * Verifie si le cookie spip_session indique une session valide.
 * Si oui, la decrit dans le tableau $visiteur_session et retourne id_auteur
 * La rejoue si IP change puis accepte le changement si $change=true
 *
 * Retourne false en cas d'echec, l'id_auteur de la session si defini, null sinon
 *
 * http://doc.spip.org/@verifier_session
 *
 * @param bool $change
 * @return bool|int|null
 */
function verifier_session($change=false) {
	// si pas de cookie, c'est fichu
	if (!isset($_COOKIE['spip_session']))
		return false;

	$fichier_session = "";

	// est-ce une session anonyme ?
	if (!intval($_COOKIE['spip_session'])){
		session_start();
		if (!isset($_SESSION[$_COOKIE['spip_session']]) OR !is_array($_SESSION[$_COOKIE['spip_session']]))
			return false;
		$GLOBALS['visiteur_session'] = $_SESSION[$_COOKIE['spip_session']];
	}
	else {
		// Tester avec alea courant
		$fichier_session = fichier_session('alea_ephemere', true);
		if ($fichier_session AND @file_exists($fichier_session)) {
			include($fichier_session);
		} else {
			// Sinon, tester avec alea precedent
			$fichier_session = fichier_session('alea_ephemere_ancien', true);
			if (!$fichier_session OR !@file_exists($fichier_session)) return false;

			// Renouveler la session avec l'alea courant
			include($fichier_session);
			spip_log('renouvelle session '.$GLOBALS['visiteur_session']['id_auteur'],"session");
			spip_unlink($fichier_session);
			ajouter_session($GLOBALS['visiteur_session']);
		}
	}

	// Compatibilite ascendante : auteur_session est visiteur_session si
	// c'est un auteur SPIP authentifie (tandis qu'un visiteur_session peut
	// n'etre qu'identifie, sans aucune authentification).

	if (isset($GLOBALS['visiteur_session']['id_auteur']) AND $GLOBALS['visiteur_session']['id_auteur'])
		$GLOBALS['auteur_session'] = &$GLOBALS['visiteur_session'];


	// Si l'adresse IP change, inc/presentation mettra une balise image
	// avec un URL de rappel demandant a changer le nom de la session.
	// Seul celui qui a l'IP d'origine est rejoue
	// ainsi un eventuel voleur de cookie ne pourrait pas deconnecter
	// sa victime, mais se ferait deconnecter par elle.
	if (hash_env() != $GLOBALS['visiteur_session']['hash_env']) {
		if (!$GLOBALS['visiteur_session']['ip_change']) {
			define('_SESSION_REJOUER',rejouer_session());
			$GLOBALS['visiteur_session']['ip_change'] = true;
			ajouter_session($GLOBALS['visiteur_session']);
		} else if ($change) {
			spip_log("session non rejouee, vol de cookie ?","session");
		}
	} else if ($change) {
		spip_log("rejoue session $fichier_session ".$_COOKIE['spip_session'],"session");
		if ($fichier_session)
			spip_unlink($fichier_session);
		$GLOBALS['visiteur_session']['ip_change'] = false;
		unset($_COOKIE['spip_session']);
		ajouter_session($GLOBALS['visiteur_session']);
	}

	// Si la session a ete initiee il y a trop longtemps, elle est annulee
	if (isset($GLOBALS['visiteur_session'])
	AND defined('_AGE_SESSION_MAX')
	AND _AGE_SESSION_MAX > 0
	AND time() - @$GLOBALS['visiteur_session']['date_session'] > _AGE_SESSION_MAX) {
		unset($GLOBALS['visiteur_session']);
		return false;
	}

	return is_numeric($GLOBALS['visiteur_session']['id_auteur'])
		? $GLOBALS['visiteur_session']['id_auteur']
		: null;
}

/**
 * Lire une valeur dans la session SPIP
 *
 * @api
 * @param string $nom
 * @return mixed
 */
function session_get($nom) {
	return isset($GLOBALS['visiteur_session'][$nom]) ? $GLOBALS['visiteur_session'][$nom] : null;
}


/**
 * Ajouter une donnée dans la session SPIP
 *
 * @api
 * @param string $nom
 * @param null $val
 * @return void
 */
function session_set($nom, $val=null) {
	if (is_null($val)){
		// rien a faire
		if (!isset($GLOBALS['visiteur_session'][$nom])) return;
		unset($GLOBALS['visiteur_session'][$nom]);
		actualiser_sessions($GLOBALS['visiteur_session'], array($nom));
	}
	else {
		// On ajoute la valeur dans la globale
		$GLOBALS['visiteur_session'][$nom] = $val;
		actualiser_sessions($GLOBALS['visiteur_session']);
	}
}


/**
 * Mettre a jour les sessions existantes pour un auteur
 * Quand on modifie une fiche auteur on appelle cette fonction qui va
 * mettre a jour les fichiers de session de l'auteur en question.
 * (auteurs identifies seulement)
 *
 * Ne concerne que les sessions des auteurs loges (id_auteur connu)
 *
 * @param array $auteur
 * @param array $supprimer_cles
 *     Liste des clés à supprimer des tableaux de sessions
 */
function actualiser_sessions($auteur, $supprimer_cles = array()) {

	$id_auteur = isset($auteur['id_auteur']) ? intval($auteur['id_auteur']) : 0;
	$id_auteur_courant = isset($GLOBALS['visiteur_session']['id_auteur']) ? intval($GLOBALS['visiteur_session']['id_auteur']) : 0;

	// si l'auteur est celui de la session courante, verifier/creer la session si besoin
	$fichier_session_courante = "";
	if ($id_auteur == $id_auteur_courant){
		ajouter_session($auteur);
		if ($id_auteur){
			$fichier_session_courante = fichier_session('alea_ephemere');
		}
	}

	// si session anonyme on ne fait rien d'autre ici : les sessions anonymes sont non partagees
	if (!$id_auteur)
		return;

	// memoriser l'auteur courant (celui qui modifie la fiche)
	$sauve = $GLOBALS['visiteur_session'];

	// .. mettre a jour les sessions de l'auteur cible
	// attention au $ final pour ne pas risquer d'embarquer un .php.jeton temporaire
	// cree par une ecriture concurente d'une session (fichier atomique temporaire)
	$sessions = preg_files(_DIR_SESSIONS, '/'.$id_auteur.'_.*\.php$');

	// 1ere passe : lire et fusionner les sessions
	foreach($sessions as $session) {
		$GLOBALS['visiteur_session'] = array();
		// a pu etre supprime entre le preg initial et le moment ou l'on arrive la (concurrence)
		if ($session !== $fichier_session_courante
		  AND @file_exists($session)){
			include $session; # $GLOBALS['visiteur_session'] est alors l'auteur cible

			$auteur = array_merge($GLOBALS['visiteur_session'], $auteur);
		}
	}

	// supprimer les eventuelles cles dont on ne veut plus
	foreach ($supprimer_cles as $cle) {
		unset($auteur[$cle]);
	}

	$auteur_session = preparer_ecriture_session($auteur);

	// seconde passe : ecrire les sessions qui ne sont pas a jour
	foreach($sessions as $session) {
		$GLOBALS['visiteur_session'] = array();
		// a pu etre supprime entre le preg initial et le moment ou l'on arrive la (concurrence)
		if (@file_exists($session)){
			include $session; # $GLOBALS['visiteur_session'] est alors l'auteur cible

			// est-ce que cette session est a mettre a jour ?
			if ($auteur_session != $GLOBALS['visiteur_session']){
				ecrire_fichier_session($session, $auteur);
			}
		}
	}

	if ($id_auteur == $id_auteur_courant){
		$GLOBALS['visiteur_session'] = $auteur;
		$GLOBALS['auteur_session'] = &$GLOBALS['visiteur_session'];
	}
	else {
		// restaurer l'auteur courant
		$GLOBALS['visiteur_session'] = $sauve;
	}

}


/**
 * Preparer le tableau de session avant ecriture
 * nettoyage de quelques variables sensibles, et appel d'un pipeline
 * @param array $auteur
 * @return array
 */
function preparer_ecriture_session($auteur){
	$row = $auteur;

	// ne pas enregistrer ces elements de securite
	// dans le fichier de session
	unset($auteur['pass']);
	unset($auteur['htpass']);
	unset($auteur['low_sec']);
	unset($auteur['alea_actuel']);
	unset($auteur['alea_futur']);

	$auteur = pipeline('preparer_fichier_session',array('args'=>array('row'=>$row),'data'=>$auteur));

	// ne pas enregistrer les valeurs vraiment nulle dans le fichier
	foreach($auteur as $variable=>$valeur){
		if ($valeur === null){
			unset($auteur[$variable]);
		}
	}

	return $auteur;
}

/**
 * Ecrire le fichier d'une session
 *
 * http://doc.spip.org/@ecrire_fichier_session
 *
 * @param string $fichier
 * @param array $auteur
 * @return bool
 */
function ecrire_fichier_session($fichier, $auteur) {

	$auteur = preparer_ecriture_session($auteur);

	// enregistrer les autres donnees du visiteur
	$texte = "<"."?php\n";
	foreach ($auteur as $var => $val)
		$texte .= '$GLOBALS[\'visiteur_session\'][\''.$var.'\'] = '
			. var_export($val,true).";\n";
	$texte .= "?".">\n";

	return ecrire_fichier($fichier, $texte);
}


/**
 * Calculer le nom du fichier session
 *
 * @param string $alea
 * @param bool $tantpis
 * @return string
 */
function fichier_session($alea, $tantpis=false) {

	if (!isset($GLOBALS['meta'][$alea])) {
		include_spip('base/abstract_sql');
		$GLOBALS['meta'][$alea]  = sql_getfetsel('valeur', 'spip_meta', "nom=" . sql_quote($alea), '','', '', '', '', 'continue');
	}

	if (!$GLOBALS['meta'][$alea]) {
		if (!$tantpis) {
			spip_log("fichier session ($tantpis): $alea indisponible","session");
			include_spip('inc/minipres');
			echo minipres();
		}
		return ''; // echec mais $tanpis
	}
	else {
		$repertoire = sous_repertoire(_DIR_SESSIONS,'',false,$tantpis);
		$c = $_COOKIE['spip_session'];
		return $repertoire . intval($c) .'_' . md5($c.' '.$GLOBALS['meta'][$alea]). '.php';
	}
}


/**
 * Code à insérer par `inc/presentation` pour rejouer la session
 *
 * @note
 *   Pourquoi insère-t-on le src par js et non directement en statique dans le HTML ?
 *   Historiquement, inséré par une balise `<script>` en r424
 *   puis modifié par `<img>` statique + js en r427
 *
 * @see action_cookie_dist() qui sera appelé
 *
 * @return string
 */
function rejouer_session()
{
	return '<img src="'.generer_url_action('cookie','change_session=oui', true).'" width="0" height="0" alt="" />';
}


/**
 * On verifie l'IP et le nom du navigateur
 *
 * @return string
 */
function hash_env() {
  static $res ='';
  if ($res) return $res;
  return $res = md5($GLOBALS['ip'] . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
}

?>
