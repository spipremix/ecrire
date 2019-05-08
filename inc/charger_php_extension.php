<?php

/**
 * Chargement d'une extension PHP
 *
 * @package SPIP\Core\Outils
 **/

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Permettait de charger un module PHP dont le nom est donné en argument
 *
 * @deprecated Utiliser la fonction native `extension_loaded($module)`
 * @note
 *     La fonction `dl()` n'est plus active à partir de PHP 5.3.
 *     On ne peut plus charger à la volée les modules PHP (hors CLI dans certaines configurations)
 *
 * @param string $module
 *     Nom du module à charger (tel que 'mysql')
 * @return bool
 *     true en cas de succes
 **/
function inc_charger_php_extension_dist($module) {
	if (extension_loaded($module)) {
		return true;
	}
	return false;
}
