<?php

//
// Ce fichier ne sera execute qu'une fois
if (defined("_ECRIRE_INC_ACCES")) return;
define("_ECRIRE_INC_ACCES", "1");


function creer_pass_aleatoire($longueur = 8, $sel = "") {
	$seed = (double) (microtime() + 1) * time();
	mt_srand($seed);
	srand($seed);

	for ($i = 0; $i < $longueur; $i++) {
		if (!$s) {
			$s = mt_rand();
			if (!$s) $s = rand();
			$s = substr(md5(uniqid($s).$sel), 0, 16);
		}
		$r = unpack("Cr", pack("H2", $s.$s));
		$x = $r['r'] & 63;
		if ($x < 10) $x = chr($x + 48);
		else if ($x < 36) $x = chr($x + 55);
		else if ($x < 62) $x = chr($x + 61);
		else if ($x == 63) $x = '/';
		else $x = '.';
		$pass .= $x;
		$s = substr($s, 2);
	}
	$pass = ereg_replace("[./]", "a", $pass);
	$pass = ereg_replace("[I1l]", "L", $pass);
	$pass = ereg_replace("[0O]", "o", $pass);
	return $pass;
}


//
// low-security : un ensemble de fonctions pour gerer de l'identification
// faible via les URLs (suivi RSS, iCal...)
//
function low_sec($id_auteur) {
	if (!$id_auteur = intval($id_auteur)) return; // jamais trop prudent ;)
	$query = "SELECT * FROM spip_auteurs WHERE id_auteur = $id_auteur";
	$result = spip_query($query);

	if ($row = spip_fetch_array($result)) {
		$low_sec = $row["low_sec"];
		if (!$low_sec) {
			$low_sec = creer_pass_aleatoire();
			spip_query("UPDATE spip_auteurs SET low_sec = '$low_sec' WHERE id_auteur = $id_auteur");
		}
		return $low_sec;
	}
}

function afficher_low_sec ($id_auteur, $action='') {
	return substr(md5($action.low_sec($id_auteur)),0,8);
}

function verifier_low_sec ($id_auteur, $cle, $action='') {
	return ($cle == afficher_low_sec($id_auteur, $action));
}

function effacer_low_sec($id_auteur) {
	spip_query("UPDATE spip_auteurs SET low_sec = '' WHERE id_auteur = $id_auteur");
}



function initialiser_sel() {
	global $htsalt;

	$htsalt = '$1$'.creer_pass_aleatoire();
}


function ecrire_logins($fichier, $tableau_logins) {
	reset($tableau_logins);

	while(list($login, $htpass) = each($tableau_logins)) {
		if ($login && $htpass) {
			fputs($fichier, "$login:$htpass\n");
		}
	}
}


function ecrire_acces() {
	$htaccess = $GLOBALS['dir_ecrire'].'.htaccess';
	$htpasswd = $GLOBALS['dir_ecrire'].'data/.htpasswd';

	// si .htaccess existe, outrepasser spip_meta
	if ((lire_meta('creer_htpasswd') == 'non') AND !@file_exists($htaccess)) {
		@unlink($htpasswd);
		@unlink($htpasswd."-admin");
		return;
	}

	$query = "SELECT login, htpass FROM spip_auteurs WHERE statut != '5poubelle' AND statut!='6forum'";
	$result = spip_query_db($query);	// attention, il faut au prealable se connecter a la base (necessaire car utilise par install.php3)
	$logins = array();
	while($row = spip_fetch_array($result)) $logins[$row['login']] = $row['htpass'];

	$fichier = @fopen($htpasswd, "w");
	if ($fichier) {
		ecrire_logins($fichier, $logins);
		fclose($fichier);
	} else {
		@header ("Location: ../spip_test_dirs.php3");
		exit;
	}

	$query = "SELECT login, htpass FROM spip_auteurs WHERE statut = '0minirezo'";
	$result = spip_query_db($query);

	$logins = array();
	while($row = spip_fetch_array($result)) $logins[$row['login']] = $row['htpass'];

	$fichier = fopen("$htpasswd-admin", "w");
	ecrire_logins($fichier, $logins);
	fclose($fichier);
}


function generer_htpass($pass) {
	global $htsalt, $flag_crypt;
	if ($flag_crypt) return crypt($pass, $htsalt);
	else return '';
}


initialiser_sel();


?>
