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

// http://doc.spip.org/@init_var_mode
function init_var_mode(){
	static $done = false;
	if (!$done) {
		// On fixe $GLOBALS['var_mode']
		$GLOBALS['var_mode'] = false;
		$GLOBALS['var_preview'] = false;
		$GLOBALS['var_images'] = false;
		if (isset($_GET['var_mode'])) {
			// tout le monde peut calcul/recalcul
			if ($_GET['var_mode'] == 'calcul'
			OR $_GET['var_mode'] == 'recalcul')
				$GLOBALS['var_mode'] = $_GET['var_mode'];
		
			// preview et debug necessitent une autorisation
			else if ($_GET['var_mode'] == 'preview'
			OR $_GET['var_mode'] == 'debug') {
				include_spip('inc/autoriser');
				if (autoriser(
					($_GET['var_mode'] == 'preview')
						? 'previsualiser'
						: 'debug'
				)) {
					// preview ?
					if ($_GET['var_mode'] == 'preview') {
						// forcer le compilo et ignorer les caches existants
						$GLOBALS['var_mode'] = 'recalcul';
						// truquer les boucles et ne pas enregistrer de cache
						$GLOBALS['var_preview'] = true;
					}
					// seul cas ici: 'debug'
					else { 
						$GLOBALS['var_mode'] = $_GET['var_mode'];
					}
					spip_log($GLOBALS['visiteur_session']['nom']
						. " ".$GLOBALS['var_mode']);
				}
				// pas autorise ?
				else {
					// si on n'est pas connecte on se redirige
					if (!$GLOBALS['visiteur_session']) {
						include_spip('inc/headers');
						redirige_par_entete(generer_url_public('login',
						'url='.rawurlencode(
						parametre_url(self(), 'var_mode', $_GET['var_mode'], '&')
						), true));
					}
					// sinon tant pis
				}
			}
			else if ($_GET['var_mode'] == 'images'){
				// forcer le compilo et ignorer les caches existants
				$GLOBALS['var_mode'] = 'calcul';
				// indiquer qu'on doit recalculer les images
				$GLOBALS['var_images'] = true;
			}
		}		
		$done = true;
	}
}

// http://doc.spip.org/@refuser_traiter_formulaire_ajax
function refuser_traiter_formulaire_ajax(){
	if ($v=_request('var_ajax')
	  AND $v=='form'
		AND $form = _request('formulaire_action')
		AND $args = _request('formulaire_action_args')
		AND decoder_contexte_ajax($args,$form)!==false) {
		// on est bien dans le contexte de traitement d'un formulaire en ajax
		// mais traiter ne veut pas
		// on le dit a la page qui va resumbit
		// sans ajax
		include_spip('inc/actions');
		ajax_retour('noajax',false);
		exit;
	}
}
// http://doc.spip.org/@traiter_formulaires_dynamiques
function traiter_formulaires_dynamiques($get=false){
	static $post = array();
	static $done = false;
	if ($get) return $post; // retourner a la demande les messages et erreurs stockes en debut de hit
	if (!$done) {
		$done = true;
		if ($action = _request('action')) {
			include_spip('base/abstract_sql'); // chargement systematique pour les actions
			include_spip('inc/autoriser'); // chargement systematique pour les actions
			include_spip('inc/headers');
			if (($v=_request('var_ajax'))
			 AND ($v!=='form')
			 AND ($args = _request('var_ajax_env'))
			 AND ($url = _request('redirect'))){
				$url = parametre_url($url,'var_ajax',$v,'&');
				$url = parametre_url($url,'var_ajax_env',$args,'&');
				set_request('redirect',$url);
			}
			$var_f = charger_fonction($action, 'action');
			$var_f();
			if ($GLOBALS['redirect']
			OR $GLOBALS['redirect'] = _request('redirect')){
				$url = urldecode($GLOBALS['redirect']);
				if (($v=_request('var_ajax'))
				 AND ($v!=='form')
				 AND ($args = _request('var_ajax_env'))) {
					$url = parametre_url($url,'var_ajax',$v,'&');	
					$url = parametre_url($url,'var_ajax_env',$args,'&');	
				}
				redirige_par_entete($url);
			}
			if (!headers_sent()
			AND !ob_get_length())
				http_status(204); // No Content
			exit;
		}

		// traiter les appels de bloc ajax (ex: pagination)
		if ($v = _request('var_ajax')
		AND $v !== 'form'
		AND $args = _request('var_ajax_env')) {
			include_spip('inc/filtres');
			if ($args = decoder_contexte_ajax($args)
			AND $fond = $args['fond']) {
				include_spip('public/parametrer');
				$contexte = calculer_contexte();
				$contexte = array_merge($args, $contexte);
				$page = evaluer_fond($fond,$contexte);
				include_spip('inc/actions');
				ajax_retour($page['texte']);
			}
			else {
				include_spip('inc/actions');
				ajax_retour('signature ajax bloc incorrecte');
			}
			exit();
		}

		// traiter les formulaires dynamniques charger/verifier/traiter
		if ($form = _request('formulaire_action')
		AND $args = _request('formulaire_action_args')) {
			include_spip('inc/filtres');
			if (($args = decoder_contexte_ajax($args,$form))!==false) {
				$verifier = charger_fonction("verifier","formulaires/$form/",true);
				$post["erreurs_$form"] = pipeline(
				  'formulaire_verifier',
					array(
						'args'=>array('form'=>$form,'args'=>$args),
						'data'=>$verifier?call_user_func_array($verifier,$args):array())
					);
				if ((count($post["erreurs_$form"])==0)){
					$rev = "";
					if ($traiter = charger_fonction("traiter","formulaires/$form/",true))
						$rev = call_user_func_array($traiter,$args);
					$rev = pipeline(
				  'formulaire_traiter',
					array(
						'args'=>array('form'=>$form,'args'=>$args),
						'data'=>$rev)
					);
					// traiter peut retourner soit un message, soit un array(editable,message)
					if (is_array($rev)) {
						$post["editable_$form"] = reset($rev);
						$post["message_ok_$form"] = end($rev);
					} else
						$post["message_ok_$form"] = $rev;
				}
				// si le formulaire a ete soumis en ajax, on le renvoie direct !
				if (_request('var_ajax')){
					if (find_in_path('formulaire_.php','balise/',true)) {
						include_spip('inc/actions');
						array_unshift($args,$form);
						ajax_retour(inclure_balise_dynamique(call_user_func_array('balise_formulaire__dyn',$args),false),false);
						exit;
					}
				}
			} else {
				include_spip('inc/actions');
				ajax_retour('signature ajax form incorrecte');
				exit;
			}
		}
	}
}

// fonction principale declenchant tout le service
// elle-meme ne fait que traiter les cas particuliers, puis passe la main.
// http://doc.spip.org/@public_assembler_dist
function public_assembler_dist($fond, $connect='') {
	  global $forcer_lang, $ignore_auth_http;

	// multilinguisme
	if ($forcer_lang AND ($forcer_lang!=='non') AND !_request('action')) {
		include_spip('inc/lang');
		verifier_lang_url();
	}
	if ($l = isset($_GET['lang'])) {
		$l = lang_select($_GET['lang']);
	}

	traiter_formulaires_dynamiques();
	
	// si signature de petition, l'enregistrer avant d'afficher la page
	// afin que celle-ci contienne la signature
	if (isset($_GET['var_confirm'])) {
		$reponse_confirmation = charger_fonction('reponse_confirmation','formulaires/signature');
		$reponse_confirmation($_GET['var_confirm']);
	}
	
	init_var_mode();
	
	if ($l) lang_select();
	return assembler_page ($fond, $connect);
}

// fonction pour l'envoi de fichier
// http://doc.spip.org/@envoyer_page
function envoyer_page($fond, $contexte)
{
	$page = inclure_page($fond, $contexte);
	if (!is_array($page['entetes'])) {
		include_spip('inc/headers');
		redirige_par_entete(generer_url_public('404'));
	}
	envoyer_entetes($page['entetes']);
	echo $page['texte'];
}

// Envoyer les entetes, en retenant ceux qui sont a usage interne
// et demarrent par X-Spip-...
// http://doc.spip.org/@envoyer_entetes
function envoyer_entetes($entetes) {
	foreach ($entetes as $k => $v)
	#	if (strncmp($k, 'X-Spip-', 7))
			@header("$k: $v");
}


//
// calcule la page et les entetes
//
// http://doc.spip.org/@assembler_page
function assembler_page ($fond, $connect='') {
	global $flag_preserver,$lastmodified,
		$use_cache;

	// Cette fonction est utilisee deux fois
	$cacher = charger_fonction('cacher', 'public');
	// Garnir ces quatre parametres avec les infos sur le cache
	// Si un resultat est retourne, c'est un message d'impossibilite
	$res = $cacher(NULL, $use_cache, $chemin_cache, $page, $lastmodified);
	if ($res) {return array('texte' => $res);}

	if (!$chemin_cache || !$lastmodified) $lastmodified = time();

	$headers_only = ($_SERVER['REQUEST_METHOD'] == 'HEAD');

	// Pour les pages non-dynamiques (indiquees par #CACHE{duree,cache-client})
	// une perennite valide a meme reponse qu'une requete HEAD (par defaut les
	// pages sont dynamiques)
	if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
	AND !$GLOBALS['var_mode']
	AND $chemin_cache
	AND isset($page['entetes'])
	AND isset($page['entetes']['Cache-Control'])
	AND strstr($page['entetes']['Cache-Control'],'max-age=')
	AND !strstr($_SERVER['SERVER_SOFTWARE'],'IIS/')
	) {
		$since = preg_replace('/;.*/', '',
			$_SERVER['HTTP_IF_MODIFIED_SINCE']);
		$since = str_replace('GMT', '', $since);
		if (trim($since) == gmdate("D, d M Y H:i:s", $lastmodified)) {
			$page['status'] = 304;
			$headers_only = true;
		}
	}

	// Si requete HEAD ou Last-modified compatible, ignorer le texte
	// et pas de content-type (pour contrer le bouton admin de inc-public)
	if ($headers_only) {
		$page['entetes']["Connection"] = "close";
		$page['texte'] = "";
	} else {
		// si la page est prise dans le cache
		if (!$use_cache)  {
			// Informer les boutons d'admin du contexte
			$GLOBALS['contexte'] = $page['contexte'];
		}
		// sinon analyser le contexte & calculer la page
		else {
			$parametrer = charger_fonction('parametrer', 'public');
			$page = $parametrer($fond, '', $chemin_cache, $connect);

			// Ajouter les scripts avant de mettre en cache
			$page['insert_js_fichier'] = pipeline("insert_js",array("type" => "fichier","data" => array()));
			$page['insert_js_inline'] = pipeline("insert_js",array("type" => "inline","data" => array()));

			// Stocker le cache sur le disque
			if ($chemin_cache)
				$cacher(NULL, $use_cache, $chemin_cache, $page, $lastmodified);
		}

		if ($chemin_cache) $page['cache'] = $chemin_cache;

		auto_content_type($page);

		$flag_preserver |=  headers_sent();

		// Definir les entetes si ce n'est fait 
		if (!$flag_preserver) {
			if ($GLOBALS['flag_ob']) {
				// Si la page est vide, produire l'erreur 404 ou message d'erreur pour les inclusions
				if (trim($page['texte']) === ''
				AND $GLOBALS['var_mode'] != 'debug'
				AND !isset($page['entetes']['Location']) // cette page realise une redirection, donc pas d'erreur
				) {
					$page = message_erreur_404();
				}
				// pas de cache client en mode 'observation'
				if ($GLOBALS['var_mode']) {
					$page['entetes']["Cache-Control"]= "no-cache,must-revalidate";
					$page['entetes']["Pragma"] = "no-cache";
				}
			}
		}
	}

	// Entete Last-Modified:
	// eviter d'etre incoherent en envoyant un lastmodified identique
	// a celui qu'on a refuse d'honorer plus haut (cf. #655)
	if ($lastmodified
	AND !isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
	AND !isset($page['entetes']["Last-Modified"]))
		$page['entetes']["Last-Modified"]=gmdate("D, d M Y H:i:s", $lastmodified)." GMT";

	return $page;
}

//
// 2 fonctions pour compatibilite arriere. Sont probablement superflues
//

// http://doc.spip.org/@auto_content_type
function auto_content_type($page)
{
	global $flag_preserver;
	if (!isset($flag_preserver))
	  {
		$flag_preserver = preg_match("/header\s*\(\s*.content\-type:/isx",$page['texte']) || (isset($page['entetes']['Content-Type']));
	  }
}

// http://doc.spip.org/@inclure_page
function inclure_page($fond, $contexte_inclus, $connect='') {
	global $lastmodified;
	if (!defined('_PAS_DE_PAGE_404'))
		define('_PAS_DE_PAGE_404',1);

	$contexte_inclus['fond'] = $fond; // securite, necessaire pour calculer correctement le cache

	// Si on a inclus sans fixer le critere de lang, on prend la langue courante
	if (!isset($contexte_inclus['lang']))
		$contexte_inclus['lang'] = $GLOBALS['spip_lang'];

	if ($contexte_inclus['lang'] != $GLOBALS['meta']['langue_site']) {
		$lang_select = lang_select($contexte_inclus['lang']);
	} else $lang_select ='';

	init_var_mode();

	$cacher = charger_fonction('cacher', 'public');
	// Garnir ces quatre parametres avec les infos sur le cache :
	// emplacement, validite, et, s'il est valide, contenu & age
	$cacher($contexte_inclus, $use_cache, $chemin_cache, $page, $lastinclude);

	// Une fois le chemin-cache decide, on ajoute la date (et date_redac)
	// dans le contexte inclus, pour que les criteres {age} etc fonctionnent
	
	// ATTENTION : les balises dynamiques passent par la, et l'ajout de la date/heure/seconde rend
	// tout cache invalide, meme si le reste des arguments est constant
	// probleme possible de perf ici
	if (!isset($contexte_inclus['date']))
		$contexte_inclus['date'] = date('Y-m-d H:i:s');
	if (!isset($contexte_inclus['date_redac']))
		$contexte_inclus['date_redac'] = $contexte_inclus['date'];
	// il faut enlever le fond de contexte inclus car sinon il prend la main
	// dans les sous inclusions -> boucle infinie d'inclusion identique
	unset($contexte_inclus['fond']);

	// Si use_cache vaut 0, la page a ete tiree du cache et se trouve dans $page
	if (!$use_cache) {
		$lastmodified = max($lastmodified, $lastinclude);
	}
	// sinon on la calcule
	else {
		$parametrer = charger_fonction('parametrer', 'public');
		$page = $parametrer($fond, $contexte_inclus, $chemin_cache, $connect);

		$lastmodified = time();
		// et on l'enregistre sur le disque
		if ($chemin_cache
		AND $page['entetes']['X-Spip-Cache'] > 0)
			$cacher($contexte_inclus, $use_cache, $chemin_cache, $page,
				$lastmodified);
	}
	if ($lang_select) lang_select();

	return $page;
}


# Attention, un appel explicite a cette fonction suppose certains include
# (voir l'exemple de spip_inscription et spip_pass)
# $echo = faut-il faire echo ou return

// http://doc.spip.org/@inclure_balise_dynamique
function inclure_balise_dynamique($texte, $echo=true, $ligne=0) {
	global $contexte_inclus; # provisoire : c'est pour le debuggueur

	if (is_array($texte)) {

		list($fond, $delainc, $contexte_inclus) = $texte;

		// delais a l'ancienne, c'est pratiquement mort
		$d = isset($GLOBALS['delais']) ? $GLOBALS['delais'] : NULL;
		$GLOBALS['delais'] = $delainc;
		$GLOBALS['_INC_PUBLIC']++;
		$page = inclure_page($fond, $contexte_inclus);
		$GLOBALS['delais'] = $d;

		// Faire remonter les entetes
		if (is_array($page['entetes'])) {
			// mais pas toutes
			unset($page['entetes']['X-Spip-Cache']);
			unset($page['entetes']['Content-Type']);
			if (is_array($GLOBALS['page'])) {
				if (!is_array($GLOBALS['page']['entetes']))
					$GLOBALS['page']['entetes'] = array();
				$GLOBALS['page']['entetes'] = 
					array_merge($GLOBALS['page']['entetes'],$page['entetes']);
			}
		}

		if ($page['process_ins'] == 'html') {
				$texte = $page['texte'];
		} else {
				ob_start();
				xml_hack($page, true);
				eval('?' . '>' . $page['texte']);
				$texte = ob_get_contents();
				xml_hack($page);
				ob_end_clean();
		}
		page_base_href($texte);
		// attention $contexte_inclus a pu changer pendant l'eval ci dessus
		// on se refere a $page['contexte'] a la place
		if (isset($page['contexte']['_pipeline'])) {
			$pipe = is_array($page['contexte']['_pipeline'])?reset($page['contexte']['_pipeline']):$page['contexte']['_pipeline'];
			$contexte = is_array($page['contexte']['_pipeline'])?end($page['contexte']['_pipeline']):array();
			$contexte = array_merge($page['contexte'], $contexte);
			unset($contexte['_pipeline']); // par precaution, meme si le risque de boucle infinie est a priori nul
			if (isset($GLOBALS['spip_pipeline'][$pipe]))
				$texte = pipeline($pipe,array(
				  'data'=>$texte,
				  'args'=>$contexte));
		}
	}

	if ($GLOBALS['var_mode'] == 'debug')
		$GLOBALS['debug_objets']['resultat'][$ligne] = $texte;

	if ($echo)
		echo $texte;
	else
		return $texte;

}

// Traiter var_recherche ou le referrer pour surligner les mots
// http://doc.spip.org/@f_surligne
function f_surligne ($texte) {
	if ($GLOBALS['html']
	AND (isset($_SERVER['HTTP_REFERER']) OR isset($_GET['var_recherche']))) {
		include_spip('inc/surligne');
		$texte = surligner_mots($texte);
	}
	return $texte;
}

// Valider/indenter a la demande.
// http://doc.spip.org/@f_tidy
function f_tidy ($texte) {
	global $xhtml;

	if ($xhtml # tidy demande
	AND $GLOBALS['html'] # verifie que la page avait l'entete text/html
	AND strlen($texte)
	AND !headers_sent()) {
		# Compatibilite ascendante
		if (!is_string($xhtml)) $xhtml ='tidy';

		if (!$f = charger_fonction($xhtml, 'inc', true)) {
			spip_log("tidy absent, l'indenteur SPIP le remplace");
			$f = charger_fonction('sax', 'xml');
		}
		return $f($texte);
	}

	return $texte;
}

// Offre #INSERT_HEAD sur tous les squelettes (bourrin)
// a activer dans mes_options via :
// $spip_pipeline['affichage_final'] .= '|f_insert_head';
// http://doc.spip.org/@f_insert_head
function f_insert_head($texte) {
	if (!$GLOBALS['html']) return $texte;
	include_spip('public/admin'); // pour strripos

	($pos = stripos($texte, '</head>'))
	    || ($pos = stripos($texte, '<body>'))
	    || ($pos = 0);

	if (false === strpos(substr($texte, 0,$pos), '<!-- insert_head -->')) {
		$insert = "\n".pipeline('insert_head','<!-- f_insert_head -->')."\n";
		$texte = substr_replace($texte, $insert, $pos, 0);
	}

	return $texte;
}

// Inserer au besoin les boutons admins
// http://doc.spip.org/@f_admin
function f_admin ($texte) {
	if ($GLOBALS['affiche_boutons_admin']) {
		include_spip('public/admin');
		$texte = affiche_boutons_admin($texte);
	}
	if (_request('var_mode')=='noajax'){
		$texte = preg_replace(',(class=[\'"][^\'"]*)ajax([^\'"]*[\'"]),Uims',"\\1\\2",$texte);
	}
	return $texte;
}

// Ajoute ce qu'il faut pour les clients MSIE et leurs debilites notoires
// * gestion du PNG transparent
// * images background (TODO)
// Cf. aussi inc/presentation, fonction fin_page();
// http://doc.spip.org/@f_msie
function f_msie ($texte) {
	if (!$GLOBALS['html']) return $texte;
	if ($GLOBALS['flag_preserver']) return $texte;
	
	// test si MSIE et sinon quitte
	if (
		strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie')
		AND preg_match('/MSIE /i', $_SERVER['HTTP_USER_AGENT'])
		AND $msiefix = charger_fonction('msiefix', 'inc')
	)
		return $msiefix($texte);
	else
		return $texte;
}


// http://doc.spip.org/@message_erreur_404
function message_erreur_404 ($erreur= "") {
	if (defined('_PAS_DE_PAGE_404'))
		return "erreur";
	if (!$erreur) {
		if (isset($GLOBALS['id_article']))
		$erreur = 'public:aucun_article';
		else if (isset($GLOBALS['id_rubrique']))
		$erreur = 'public:aucune_rubrique';
		else if (isset($GLOBALS['id_breve']))
		$erreur = 'public:aucune_breve';
		else if (isset($GLOBALS['id_auteur']))
		$erreur = 'public:aucun_auteur';
		else if (isset($GLOBALS['id_syndic']))
		$erreur = 'public:aucun_site';
	}
	$contexte_inclus = array(
		'erreur' => _T($erreur),
		'lang' => $GLOBALS['spip_lang']
	);
	$page = inclure_page('404', $contexte_inclus);
	$page['status'] = 404;
	return $page;
}

// fonction permettant de recuperer le resultat du calcul d'un squelette
// pour une inclusion dans un flux
// http://doc.spip.org/@recuperer_fond
function recuperer_fond($fond, $contexte=array(), $trim=true, $connect='') {
	$options = array('trim' => $trim);

	$texte = "";
	foreach(is_array($fond) ? $fond : array($fond) as $f){
		$page = evaluer_fond($f, $contexte, $options, $connect);
		$texte .= $trim ? rtrim($page['texte']) : $page['texte'];
	}

	return $trim ? ltrim($texte) : $texte;
}

// temporairement ici : a mettre dans le futur inc/modeles
// creer_contexte_de_modele('left', 'autostart=true', ...) renvoie un array()
// http://doc.spip.org/@creer_contexte_de_modele
function creer_contexte_de_modele($args) {
	$contexte = array();
	$params = array();
	foreach ($args as $var=>$val) {
		if (is_int($var)){ // argument pas formate
			if (in_array($val, array('left', 'right', 'center'))) {
				$var = 'align';
				$contexte[$var] = $val;
			} else {
				$args = explode('=', $val);
				if (count($args)>=2) // Flashvars=arg1=machin&arg2=truc genere plus de deux args
					$contexte[trim($args[0])] = substr($val,strlen($args[0])+1);
				else // notation abregee
					$contexte[trim($val)] = trim($val);
			}
		}
		else
			$contexte[$var] = $val;
	}

	return $contexte;
}

// Calcule le modele et retourne la mini-page ainsi calculee
// http://doc.spip.org/@inclure_modele
function inclure_modele($type, $id, $params, $lien, $connect='') {
	static $compteur;
	if (++$compteur>10) return ''; # ne pas boucler indefiniment

	$type = strtolower($type);

	$fond = 'modeles/'.$type;

	$params = array_filter(explode('|', $params));
	if ($params) {
		list(,$soustype) = each($params);
		$soustype = strtolower($soustype);
		if (in_array($soustype,
		array('left', 'right', 'center', 'ajax'))) {
			list(,$soustype) = each($params);
			$soustype = strtolower($soustype);
		}

		if (preg_match(',^[a-z0-9_]+$,', $soustype)) {
			$fond = 'modeles/'.$type.'_'.$soustype;
			if (!find_in_path($fond.'.html')) {
				$fond = 'modeles/'.$type;
				$class = $soustype;
			}
			// enlever le sous type des params
			$params = array_diff($params,array($soustype));
		}
	}

	// en cas d'echec : si l'objet demande a une url, on cree un petit encadre
	// avec un lien vers l'objet ; sinon on passe la main au suivant
	if (!find_in_path($fond.'.html')) {
		if (!$lien)
			$lien = calculer_url("$type$id", '', 'tout', $connect);
		if (strpos($lien[1],'spip_url') !== false)
			return false;
		else
			return '<a href="'.$lien[0].'" class="spip_modele'
				. ($class ? " $class" : '')
				. '">'.sinon($lien[2], _T('ecrire:info_sans_titre'))."</a>";
	}


	// Creer le contexte
	$contexte = array( 
		'lang' => $GLOBALS['spip_lang'], 
		'fond' => $fond, 
		'dir_racine' => _DIR_RACINE # eviter de mixer un cache racine et un cache ecrire (meme si pour l'instant les modeles ne sont pas caches, le resultat etant different il faut que le contexte en tienne compte 
	); 
	// Le numero du modele est mis dans l'environnement
	// d'une part sous l'identifiant "id"
	// et d'autre part sous l'identifiant de la cle primaire supposee
	// par la fonction table_objet, 
	// qui ne marche vraiment que pour les tables std de SPIP
	// (<site1> =>> site =>> id_syndic =>> id_syndic=1)
	$_id = 'id_' . table_objet($type);
	if (preg_match('/s$/',$_id)) $_id = substr($_id,0,-1);
	$contexte['id'] = $contexte[$_id] = $id;

	if (isset($class))
		$contexte['class'] = $class;

	// Si un lien a ete passe en parametre, ex: [<modele1>->url]
	if ($lien) {
		# un eventuel guillemet (") sera reechappe par #ENV
		$contexte['lien'] = str_replace("&quot;",'"', $lien[0]);
		$contexte['lien_class'] = $lien[1];
	}

	// Traiter les parametres
	// par exemple : <img1|center>, <emb12|autostart=true> ou <doc1|lang=en>
	$arg_list = creer_contexte_de_modele($params);
	$contexte['args'] = $arg_list; // on passe la liste des arguments du modeles dans une variable args
	$contexte = array_merge($contexte,$arg_list);

	// On cree un marqueur de notes unique lie a ce modele
	// et on enregistre l'etat courant des globales de notes...
	$enregistre_marqueur_notes = $GLOBALS['marqueur_notes'];
	$enregistre_les_notes = $GLOBALS['les_notes'];
	$enregistre_compt_note = $GLOBALS['compt_note'];
	$GLOBALS['marqueur_notes'] = substr(md5(serialize($contexte)),0,8);
	$GLOBALS['les_notes'] = '';
	$GLOBALS['compt_note'] = 0;

	// Appliquer le modele avec le contexte
	$page = evaluer_fond($contexte['fond'], $contexte, array(), $connect);
	$retour = trim($page['texte']);

	// Lever un drapeau (global) si le modele utilise #SESSION
	// a destination de public/parametrer
	if (isset($page['invalideurs'])
	AND isset($page['invalideurs']['session']))
		$GLOBALS['cache_utilise_session'] = $page['invalideurs']['session'];

	// On restitue les globales de notes telles qu'elles etaient avant l'appel
	// du modele. Si le modele n'a pas affiche ses notes, tant pis (elles *doivent*
	// etre dans le cache du modele, autrement elles ne seraient pas prises en
	// compte a chaque calcul d'un texte contenant un modele, mais seulement
	// quand le modele serait calcule, et on aurait des resultats incoherents)
	$GLOBALS['les_notes'] = $enregistre_les_notes;
	$GLOBALS['marqueur_notes'] = $enregistre_marqueur_notes;
	$GLOBALS['compt_note'] = $enregistre_compt_note;

	// Regarder si le modele tient compte des liens (il *doit* alors indiquer
	// spip_lien_ok dans les classes de son conteneur de premier niveau ;
	// sinon, s'il y a un lien, on l'ajoute classiquement
	if (strstr(' ' . ($classes = extraire_attribut($retour, 'class')).' ',
	'spip_lien_ok')) {
		$retour = inserer_attribut($retour, 'class',
			trim(str_replace(' spip_lien_ok ', ' ', " $classes ")));
	} else if ($lien)
		$retour = "<a href='".$lien[0]."' class='".$lien[1]."'>".$retour."</a>";

	// Gerer ajax
	if (isset($arg_list['ajax'])
	AND $arg_list['ajax']=='ajax'
	AND strlen($retour)) {
		$retour = "<div class='ajaxbloc env-"
			. encoder_contexte_ajax($contexte)
			. "'>\n"
			. $retour
			. "</div><!-- ajaxbloc -->\n";
	}

	$compteur--;
	return $retour;
}

// Appeler avant et apres chaque eval()
// http://doc.spip.org/@xml_hack
function xml_hack(&$page, $echap = false) {
	if ($echap)
		$page['texte'] = str_replace('<'.'?xml', "<\1?xml", $page['texte']);
	else
		$page['texte'] = str_replace("<\1?xml", '<'.'?xml', $page['texte']);
}

// http://doc.spip.org/@page_base_href
function page_base_href(&$texte){
	if (!defined('_SET_HTML_BASE'))
		define('_SET_HTML_BASE',
			$GLOBALS['meta']['type_urls'] == 'arbo');

	if (_SET_HTML_BASE
	AND $GLOBALS['html']
	AND $GLOBALS['profondeur_url']>0){
		list($head, $body) = explode('</head>', $texte, 1);
		$insert = false;
		if (strpos($head, '<base')===false) 
			$insert = true;
		else {
			// si aucun <base ...> n'a de href c'est bon quand meme !
			$insert = true;
			include_spip('inc/filtres');
			$bases = extraire_balises($head,'base');
			foreach ($bases as $base)
				if (extraire_attribut($base,'href'))
					$insert = false;
		}
		if ($insert) {
			include_spip('inc/filtres_mini');
			// ajouter un base qui reglera tous les liens relatifs
			$base = url_absolue('./');
			if (($pos = strpos($head, '<head>')) !== false)
				$head = substr_replace($head, "\n<base href=\"$base\" />", $pos+6, 0);
			$texte = $head . (isset($body) ? '</head>'.$body : '');
			// gerer les ancres
			$base = $_SERVER['REQUEST_URI'];
			if (strpos($texte,"href='#")!==false)
				$texte = str_replace("href='#","href='$base#",$texte);
			if (strpos($texte, "href=\"#")!==false)
				$texte = str_replace("href=\"#","href=\"$base#",$texte);
		}
	}
}
?>
