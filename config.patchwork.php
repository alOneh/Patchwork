<?php


// Import basic pieces of patchwork.

#import pieces/iaForm/pieces/*
#import pieces/lingua
#import pieces/iaMail
#import pieces/toolbox
#import pieces/ie7


/* All default settings */

$CONFIG += array(

	// Debug features
#	'DEBUG_ALLOWED'  => true,
#	'DEBUG_PASSWORD' => '',

#	'lang_list' => '',		// List of available languages ('en|fr' for example)
#	'maxage' => 2678400,	// Max age (in seconds) for HTTP ressources caching
#	'turbo' => false,		// Run patchwork at full speed, at the cost of source code synchronism
#	'clientside' => true,	// Enable browser-side page rendering when available
#	'umask' => umask(),		// Set the user file creation mode mask
#	'P3P => 'CUR ADM',		// P3P - Platform for Privacy Preferences

	// Session cookie
#	'session.cookie_path'   => '/',
#	'session.cookie_domain' => '',

	// Translation tables adapter config.
#	'translate_adapter' => false,
#	'translate_options' => array(),

);