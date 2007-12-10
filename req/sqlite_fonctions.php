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


/*
 * Des fonctions pour les requetes SQL
 *  
 */
function _sqlite_init_functions(&$sqlite){
	
	if (!$sqlite) return false;

	
	$fonctions = array(
		'CONCAT'		=> array( '_sqlite_func_concat'			,2),
		
		'DAYOFMONTH'	=> array( '_sqlite_func_dayofmonth'		,1),
		
		'FIND_IN_SET'	=> array( '_sqlite_func_find_in_set'	,2),

		'IF'			=> array( '_sqlite_func_if' 			,3),
		'INSERT'		=> array( '_sqlite_func_insert'			,4),		
		'INSTR'			=> array( '_sqlite_func_instr'			,2),

		'LEAST'			=> array( '_sqlite_func_least'			,3), // nb d'arguments ?
		'LEFT'			=> array( '_sqlite_func_left'			,2),
#		'LENGTH'		=> array( 'strlen'						,1), // present v1.0.4
#		'LOWER'			=> array( 'strtolower'					,1), // present v2.4
#		'LTRIM'			=> array( 'ltrim'						,1), // present en theorie

		'NOW'			=> array( '_sqlite_func_now'			,0),
		
		'MD5'			=> array( 'md5'							,1),
		'MONTH'			=> array( '_sqlite_func_month'			,1),
	
		'RAND'			=> array( '_sqlite_func_rand'			,0), // sinon random() v2.4
		'REGEXP_MATCH'	=> array( '_sqlite_func_regexp_match'	,2), // critere REGEXP supporte a partir de v3.3.2
		'REGEXP_REPLACE'=> array( '_sqlite_func_regexp_replace'	,3),
		'RIGHT'			=> array( '_sqlite_func_right'			,2),
#		'RTRIM'			=> array( 'rtrim'						,1), // present en theorie

		'SETTYPE'		=> array( 'settype'						,2), // CAST present en v3.2.3
		
		'TO_DAYS'		=> array( '_sqlite_func_to_days'		,1),
#		'TRIM'			=> array( 'trim'						,1), // present en theorie

		'UNIX_TIMESTAMP'=> array( '_sqlite_func_unix_timestamp'	,1),
#		'UPPER'			=> array( 'strtoupper'					,1), // present v2.4		

		'VIDE'			=> array( '_sqlite_func_vide'			,0), // du vide pour SELECT 0 as x ... ORDER BY x -> ORDER BY vide()
			
		'YEAR'			=> array( '_sqlite_func_year'			,1)
	);
	

	foreach ($fonctions as $f=>$r){
		_sqlite_add_function($sqlite, $f, $r);
	}

	#spip_log('functions sqlite chargees ');
}

// permet au besoin de charger des fonctions ailleurs par _sqlite_init_functions();
function _sqlite_add_function(&$sqlite, &$f, &$r){
	if (_sqlite_is_version(3, $sqlite)){
		isset($r[1])
			?$sqlite->sqliteCreateFunction($f, $r[0], $r[1])
			:$sqlite->sqliteCreateFunction($f, $r[0]);
	} else {
		isset($r[1])
			?sqlite_create_function($sqlite, $f, $r[0], $r[1])	
			:sqlite_create_function($sqlite, $f, $r[0]);
	}
}

//
// SQLite : fonctions sqlite -> php
// entre autre auteurs : mlebas
//

function _sqlite_func_concat ($a, $b) {
    return $a.$b;
}


function _sqlite_func_dayofmonth ($d) {
    if (!$d){
    	 $result = date("j");
	} else {
    	preg_match(";^([0-9]{4})-([0-9]+)-([0-9]+) .*$;", $d, $f);
    	$result = $f[3];
	}
	#spip_log("Passage avec DAYOFMONTH : $d, $result",'debug');
    return $result;
}


function _sqlite_func_find_in_set($num, $set) {
  $rank=0;
  foreach (explode(",",$set) as $v) {
   if ($v == $num) return (++$rank);
   $rank++;
  }
  return 0;
}


function _sqlite_func_if ($bool, $oui, $non) {
    return ($bool)?$oui:$non;
}


/*
 * INSERT(chaine, index, longueur, chaine) 	MySQL
 * Retourne une chaine de caracteres a partir d'une chaine dans laquelle "sschaine"
 *  a ete inseree a la position "index" en remplacant "longueur" caracteres.
 */ 
function _sqlite_func_insert ($s, $index, $longueur, $chaine) {
    return 
    	  substr($s,0, $index) 
    	. $chaine 
    	. substr(substr($s, $index), $longueur);
}


function _sqlite_func_instr ($s, $search) {
    return strpos($s,$search);
}


function _sqlite_func_least () {
	$numargs = func_num_args();
	$arg_list = func_get_args();
	$least=$arg_list[0];
	for ($i = 0; $i < $numargs; $i++) {
		if ($arg_list[$i] < $least) $least=$arg_list[$i];
	}
	#spip_log("Passage avec LEAST : $least",'debug');
	return $least;
}


function _sqlite_func_left ($s, $lenght) {
    return substr($s,$lenght);
}


function _sqlite_func_now(){
	$result = date("Y-m-d H:i:s", strtotime("now"));
	#spip_log("Passage avec NOW : $result",'debug');
	return $result;
}


function _sqlite_func_month ($d) {
	#spip_log("Passage avec MONTH : $d",'debug');
    if (!$d) return date("n");
    preg_match(";^([0-9]{4})-([0-9]+).*$;", $d, $f);
    return $f[2];
}


function _sqlite_func_rand() {
  return rand();
}


function _sqlite_func_right ($s, $lenght) {
    return substr($s,0 - $lenght);
}


function _sqlite_func_regexp_match($quoi, $cherche) {
	$return = preg_match('%'.$cherche.'%', $quoi);
	#spip_log("regexp_replace : $quoi, $cherche, $remplace, $return",'debug');
	return $return;
}


function _sqlite_func_regexp_replace($quoi, $cherche, $remplace) {
	$return = preg_replace('%'.$cherche.'%', $remplace, $quoi);
	#spip_log("regexp_replace : $quoi, $cherche, $remplace, $return",'debug');
	return $return;
}


function _sqlite_func_to_days ($d) {
	$result = date("z", _sqlite_func_unix_timestamp($d));
	#spip_log("Passage avec TO_DAYS : $d, $result",'debug');
	return $result;
}


function _sqlite_func_unix_timestamp($d) {
	//2005-12-02 20:53:53
	#spip_log("Passage avec UNIX_TIMESTAMP : $d",'debug');
	// mktime ( [int hour [, int minute [, int second [, int month [, int day [, int year [, int is_dst]]]]]]] )
    if (!$d) return mktime();
    preg_match(";^([0-9]{4})-([0-9]+)-([0-9]+) ([0-9]+):([0-9]+):([0-9]+).*$;", $d, $f);
    return mktime($f[4],$f[5],$f[6],$f[2],$f[3],$f[1]);
}


function _sqlite_func_year ($d) {
    if (!$d){
    	 $result = date("Y");
    } else {
    	preg_match(";^([0-9]{4}).*$;", $d, $f);
    	$result = $f[1];
    }
    spip_log("Passage avec YEAR : $d, $result",'debug');
    return $result;
}


function _sqlite_func_vide(){
	return;
}



?>
