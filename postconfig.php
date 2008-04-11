<?php /*********************************************************************
 *
 *   Copyright : (C) 2007 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/



/**** Post-configuration stage 0 ****/

$patchwork_appId = (int) /*<*/sprintf('%020d', __patchwork_bootstrapper::$appId)/*>*/;
define('PATCHWORK_PATH_TOKEN', /*<*/__patchwork_bootstrapper::$token/*>*/);


$CONFIG += array(
	'debug.allowed'  => true,
	'debug.password' => '',
	'turbo' => false,
);

defined('DEBUG') || define('DEBUG', $CONFIG['debug.allowed'] && (!$CONFIG['debug.password'] || isset($_COOKIE['debug_password']) && $CONFIG['debug.password'] == $_COOKIE['debug_password']) ? 1 : 0);
define('TURBO', !DEBUG && $CONFIG['turbo']);

unset($CONFIG['debug.allowed'], $CONFIG['debug.password'], $CONFIG['turbo']);


isset($CONFIG['umask']) && umask($CONFIG['umask']);


// file_exists replacement on Windows
// Fix a bug with long file names.
// In debug mode, checks if character case is strict.

/*#>*/if ('\\' === DIRECTORY_SEPARATOR)
/*#>*/{
		if (/*<*/version_compare(PHP_VERSION, '5.2', '<')/*>*/ || DEBUG)
		{
			if (DEBUG)
			{
				function win_file_exists($file)
				{
					if (file_exists($file) && $realfile = realpath($file))
					{
						$file = strtr($file, '/', '\\');

						$i = strlen($file);
						$j = strlen($realfile);

						while ($i-- && $j--)
						{
							if ($file[$i] != $realfile[$j])
							{
								if (lowerascii($file[$i]) === lowerascii($realfile[$j]) && !(0 === $i && ':' === substr($file, 1, 1))) trigger_error("Character case mismatch between requested file and its real path ({$file} vs {$realfile})");
								break;
							}
						}

						return true;
					}
					else return false;
				}
			}
			else
			{
				function win_file_exists($file) {return file_exists($file) && (strlen($file) < 100 || realpath($file));}
			}

			function win_is_file($file)       {return win_file_exists($file) && is_file($file);}
			function win_is_dir($file)        {return win_file_exists($file) && is_dir($file);}
			function win_is_link($file)       {return win_file_exists($file) && is_link($file);}
			function win_is_executable($file) {return win_file_exists($file) && is_executable($file);}
			function win_is_readable($file)   {return win_file_exists($file) && is_readable($file);}
			function win_is_writable($file)   {return win_file_exists($file) && is_writable($file);}
		}
/*#>*/}


// __autoload(): the magic part

/*#>*/copy(__patchwork_bootstrapper::$pwd . 'autoloader.php', './.patchwork.autoloader.php');
/*#>*/
/*#>*/if ('\\' === DIRECTORY_SEPARATOR)
/*#>*/{
/*#>*/	$a = new COM('Scripting.FileSystemObject');
/*#>*/	$a->GetFile(__patchwork_bootstrapper::$cwd . '.patchwork.autoloader.php')->Attributes |= 2; // Set hidden attribute
/*#>*/}

function __autoload($searched_class)
{
	$a = lowerascii($searched_class);

	if (TURBO && $a =& $GLOBALS['patchwork_autoload_cache'][$a])
	{
		if (is_int($a))
		{
			$b = $a;
			unset($a);
			$a = $b - /*<*/__patchwork_bootstrapper::$offset/*>*/;

			$b = $searched_class;
			$i = strrpos($b, '__');
			false !== $i && 0 === strcspn(substr($b, $i+2), '0123456789') && $b = substr($b, 0, $i);

			$a = $b . '.php.' . DEBUG . (0>$a ? -$a . '-' : $a);
		}

		$a = './.class_' . $a . /*<*/'.' . __patchwork_bootstrapper::$token . '.zcache.php'/*>*/;

		$GLOBALS[/*<*/'a' . __patchwork_bootstrapper::$token/*>*/] = false;

		if (file_exists($a))
		{
			patchwork_include($a);

			if (class_exists($searched_class, false)) return;
		}
	}

	class_exists('__patchwork_autoloader', false) || require TURBO ? './.patchwork.autoloader.php' : /*<*/__patchwork_bootstrapper::$pwd . 'autoloader.php'/*>*/;

	__patchwork_autoloader::autoload($searched_class);
}


// patchworkProcessedPath(): private use for the preprocessor (in files in the include_path)

function patchworkProcessedPath($file)
{
/*#>*/	if ('\\' === DIRECTORY_SEPARATOR)
		false !== strpos($file, '\\') && $file = strtr($file, '\\', '/');

	if (false !== strpos('.' . $file, './') || /*<*/'\\' === DIRECTORY_SEPARATOR/*>*/ && ':/' === substr($file, 1, 2))
	{
		if ($f = realpath($file)) $file = $f;

		$p =& $GLOBALS['patchwork_path'];

		for ($i = /*<*/__patchwork_bootstrapper::$last + 1/*>*/; $i < /*<*/count($patchwork_path)/*>*/; ++$i)
		{
			if (substr($file, 0, strlen($p[$i])) === $p[$i])
			{
				$file = substr($file, strlen($p[$i]));
				break;
			}
		}

		if (/*<*/count($patchwork_path)/*>*/ === $i) return $f;
	}

	$file = 'class/' . $file;

	$source = resolvePath($file);

	if (false === $source) return false;

	$level = $GLOBALS['patchwork_lastpath_level'];

	$file = strtr($file, '\\', '/');
	$cache = DEBUG . (0>$level ? -$level . '-' : $level);
	$cache = './.' . strtr(str_replace('_', '%2', str_replace('%', '%1', $file)), '/', '_')
		. '.' . $cache . /*<*/'.' . __patchwork_bootstrapper::$token . '.zcache.php'/*>*/;

	if (file_exists($cache) && (TURBO || filemtime($cache) > filemtime($source))) return $cache;

	patchwork_preprocessor::execute($source, $cache, $level, false);

	return $cache;
}



/**** Post-configuration stage 1 ****/



function E($msg = '__Δms') {return patchwork::log($msg, false, false);}
function strlencmp($a, $b) {return strlen($b) - strlen($a);}


// Fix config

$CONFIG += array(
	'clientside'            => true,
	'i18n.lang_list'        => array(),
	'maxage'                => 2678400,
	'P3P'                   => 'CUR ADM',
	'xsendfile'             => false,
	'document.domain'       => '',
	'session.save_path'     => /*<*/__patchwork_bootstrapper::$zcache/*>*/,
	'session.cookie_path'   => '/',
	'session.cookie_domain' => '',
	'session.auth_vars'     => array(),
	'session.group_vars'    => array(),
	'translator.adapter'    => false,
	'translator.options'    => array(),
);


// Prepare for I18N

$a =& $CONFIG['i18n.lang_list'];
$a ? (is_array($a) || $a = explode('|', $a)) : ($a = array('' => '__'));
define('PATCHWORK_I18N', 2 <= count($a));

$b = array();

foreach ($a as $k => &$v)
{
	if (is_int($k))
	{
		$v = (string) $v;

		if (!isset($a[$v]))
		{
			$a[$v] = $v;
			$b[] = preg_quote($v, '#');
		}

		unset($a[$k]);
	}
	else $b[] = preg_quote($v, '#');
}

unset($a, $v);

usort($b, 'strlencmp');
$b = '(' . implode('|', $b) . ')';


/* patchwork's context initialization
*
* Setup needed environment variables if they don't exists :
*   $_SERVER['PATCHWORK_BASE']: application's base part of the url. Lang independant (ex. /myapp/__/)
*   $_SERVER['PATCHWORK_REQUEST']: request part of the url (ex. myagent/mysubagent/...)
*   $_SERVER['PATCHWORK_LANG']: lang (ex. en) if application is internationalized
*
* You can also define them with mod_rewrite, to get cleaner URLs for example.
*/

/*#>*/if (isset($_SERVER['PATCHWORK_BASE']) || isset($_SERVER['REDIRECT_PATCHWORK_BASE']))
/*#>*/{
		if (isset($_SERVER['REDIRECT_PATCHWORK_BASE']))
		{
			$_SERVER['PATCHWORK_BASE'] = $_SERVER['REDIRECT_PATCHWORK_BASE'];
			isset($_SERVER['REDIRECT_PATCHWORK_LANG'])    && $_SERVER['PATCHWORK_LANG']    = $_SERVER['REDIRECT_PATCHWORK_LANG'];
			isset($_SERVER['REDIRECT_PATCHWORK_REQUEST']) && $_SERVER['PATCHWORK_REQUEST'] = $_SERVER['REDIRECT_PATCHWORK_REQUEST'];
		}

		if ('/'  === substr($_SERVER['PATCHWORK_BASE'], 0, 1))
			$_SERVER['PATCHWORK_BASE'] = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PATCHWORK_BASE'];

		$a = explode('__', $_SERVER['PATCHWORK_BASE'], 2);
		if (2 === count($a))
		{
			if (!isset($_SERVER['PATCHWORK_LANG']))
			{
				$a = '#' . preg_quote($a[0], '#') . $b . '#';
				preg_match($a, 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . rawurldecode($_SERVER['REQUEST_URI']), $a)
					&& $_SERVER['PATCHWORK_LANG'] = array_search($a[1], $CONFIG['i18n.lang_list']);
			}

			isset($_SERVER['PATCHWORK_LANG'])    || $_SERVER['PATCHWORK_LANG']    = '';
			isset($_SERVER['PATCHWORK_REQUEST']) || $_SERVER['PATCHWORK_REQUEST'] = '';
		}

		$_SERVER['PATCHWORK_REQUEST'] = preg_replace("'/[./]*(?:/|$)'", '/', trim($_SERVER['PATCHWORK_REQUEST'], '/.'));
/*#>*/}
/*#>*/else
/*#>*/{
		$_SERVER['PATCHWORK_BASE'] = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		$_SERVER['PATCHWORK_LANG'] = $_SERVER['PATCHWORK_REQUEST'] = '';

/*#>*/	if (isset($_SERVER['PERHOST_PATH_INFO']))
/*#>*/	{
			$_SERVER['PATCHWORK_BASE'] .= substr($_SERVER['SCRIPT_NAME'], 0, -strlen($_SERVER['PERHOST_PATH_INFO'])) . '/' . (PATCHWORK_I18N ? '__/' : '');
			$_SERVER['PATCHWORK_REQUEST'] = preg_replace("'/[./]*(?:/|$)'", '/', trim($_SERVER['PERHOST_PATH_INFO'], '/.'));
/*#>*/	}
/*#>*/	else
/*#>*/	{
/*#>*/		isset($_SERVER['ORIG_PATH_INFO']) && $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
/*#>*/
/*#>*/		if (isset($_SERVER['PERDIR_PATH_INFO']))
/*#>*/		{
				if (isset($_SERVER['REDIRECT_PERDIR_PATH_INFO']))
				{
					$_SERVER['PATCHWORK_BASE'] .= substr($_SERVER['SCRIPT_NAME'], 0, -strlen($_SERVER['PERDIR_PATH_INFO']));
					$_SERVER['PATCHWORK_REQUEST'] = trim($_SERVER['REDIRECT_PERDIR_PATH_INFO'], '/.');
				}
				else $_SERVER['PATH_INFO'] = $_SERVER['PERDIR_PATH_INFO'];
/*#>*/		}
/*#>*/		else if (!isset($_SERVER['PATH_INFO']))
/*#>*/		{
/*#>*/			// Check if the webserver supports PATH_INFO
/*#>*/
/*#>*/			$h = isset($_SERVER['HTTPS']) ? 'ssl' : 'tcp';
/*#>*/			$h = fsockopen("{$h}://{$_SERVER['SERVER_ADDR']}", $_SERVER['SERVER_PORT'], $errno, $errstr, 30);
/*#>*/			if (!$h) throw new Exception("Socket error n°{$errno}: {$errstr}");
/*#>*/
/*#>*/			$a = strpos($_SERVER['REQUEST_URI'], '?');
/*#>*/			$a = false === $a ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $a);
/*#>*/			'/' === substr($a, -1) && $a .= basename($_SERVER['SCRIPT_NAME']);
/*#>*/
/*#>*/			$a  = "GET {$a}/_?exit$ HTTP/1.0\r\n";
/*#>*/			$a .= "Host: {$_SERVER['HTTP_HOST']}\r\n";
/*#>*/			$a .= "Connection: close\r\n\r\n";
/*#>*/
/*#>*/			fwrite($h, $a);
/*#>*/			$a = fgets($h, 14);
/*#>*/			fclose($h);
/*#>*/
/*#>*/			strpos($a, ' 200') && $_SERVER['PATH_INFO'] = '';
/*#>*/		}

			$a = strpos($_SERVER['REQUEST_URI'], '?');
			$a = false === $a ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $a);
			$a = rawurldecode($a);
			$a = preg_replace("'/[./]*(?:/|$)'", '/', $a);

/*#>*/		if (isset($_SERVER['PATH_INFO']) || isset($_SERVER['PERDIR_PATH_INFO']))
/*#>*/		{
				isset($_SERVER['ORIG_PATH_INFO']) && $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];

				if (isset($_SERVER['PATH_INFO']))
				{
/*#>*/				if (isset($_SERVER['PERDIR_PATH_INFO']))
						'/' === substr($a, -1) && '/' . basename($_SERVER['SCRIPT_NAME']) === $_SERVER['PATH_INFO'] && $_SERVER['PATH_INFO'] = '';
					$_SERVER['PATCHWORK_REQUEST'] = preg_replace("'/[./]*(?:/|$)'", '/', $_SERVER['PATH_INFO']);
					$_SERVER['PATCHWORK_BASE'] .= rtrim('' !== $_SERVER['PATCHWORK_REQUEST'] ? substr($a, 0, -strlen($_SERVER['PATCHWORK_REQUEST'])) : $a, '/');
					$_SERVER['PATCHWORK_REQUEST'] = trim($_SERVER['PATCHWORK_REQUEST'], '/.');
				}
/*#>*/			if (!isset($_SERVER['PERDIR_PATH_INFO']))
/*#>*/			{
					else
					{
						$_SERVER['PATCHWORK_BASE'] .= $a;
						'/' === substr($a, -1) && $_SERVER['PATCHWORK_BASE'] .= basename($_SERVER['SCRIPT_NAME']);
					}
/*#>*/			}

				$_SERVER['PATCHWORK_BASE'] .= '/' . (PATCHWORK_I18N ? '__/' : '');
/*#>*/		}
/*#>*/		else
/*#>*/		{
				$_SERVER['PATCHWORK_BASE'] .= $a . '?' . (PATCHWORK_I18N ? '__/' : '');
				$_SERVER['PATCHWORK_REQUEST'] = $_SERVER['QUERY_STRING'];

				$a = strpos($_SERVER['QUERY_STRING'], '?');
				false !== $a || $a = strpos($_SERVER['QUERY_STRING'], '&');

				if (false !== $a)
				{
					$_SERVER['PATCHWORK_REQUEST'] = substr($_SERVER['QUERY_STRING'], 0, $a);
					$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], $a+1);

					$a = explode('/', urldecode($_SERVER['PATCHWORK_REQUEST']));
					$k = array();
					$v = 0;

					foreach ($a as $a)
					{
						if ('.' === $a) continue;
						if ('..' === $a) $k ? array_pop($k) : ++$v;
						else $k[]= $a;
					}

					if ($v)
					{
						$a = 4 + isset($_SERVER['HTTPS']) + 3 + strlen($_SERVER['HTTP_HOST']) + 1;
						$a = substr($_SERVER['PATCHWORK_BASE'], $a, PATCHWORK_I18N ? -4 : -1) . '/';
						$a = preg_replace("'[^/]*/{1,{$v}}$'", '', $a) . implode('/', $k);
						$a = strtr($a, array('%' => '%25', '?' => '%3F', "\r" => '%0D', "\n" => '%0A'));
						$a = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/' . $a;
						'' !== $_SERVER['QUERY_STRING'] && $a .= '?' . $_SERVER['QUERY_STRING'];

						header('HTTP/1.1 301 Moved Permanently');
						header('Location: ' . $a);

						exit;
					}

					$_SERVER['PATCHWORK_REQUEST'] = preg_replace("'/[./]*(?:/|$)'", '/', trim(implode('/', $k), '/.'));

					parse_str($_SERVER['QUERY_STRING'], $_GET);

/*#>*/				if (get_magic_quotes_gpc())
/*#>*/				{
						$a = array(&$_GET);
						$k = 1;
						for ($i = 0; $i < $k; ++$i)
						{
							foreach ($a[$i] as &$v)
							{
								if (is_array($v)) $a[$k++] =& $v;
								else
								{
/*#>*/								if (ini_get('magic_quotes_sybase'))
										$v = str_replace("''", "'", $v);
/*#>*/								else
										$v = stripslashes($v);
								}
							}

							reset($a[$i]);
							unset($a[$i]);
						}

						unset($a, $v);
/*#>*/				}
				}
				else if ('' !== $_SERVER['QUERY_STRING'])
				{
					$_SERVER['QUERY_STRING'] = '';

					reset($_GET);
					$a = key($_GET);
					unset($_GET[$a], $_GET[$a]); // Double unset against a PHP security hole
				}

				$_SERVER['PATCHWORK_FILENAME'] = basename($_SERVER['PATCHWORK_REQUEST']);
/*#>*/		}
/*#>*/	}

		if (preg_match("#^{$b}(?:/|$)(.*?)$#", $_SERVER['PATCHWORK_REQUEST'], $a))
		{
			$_SERVER['PATCHWORK_LANG']    = array_search($a[1], $CONFIG['i18n.lang_list']);
			$_SERVER['PATCHWORK_REQUEST'] = $a[2];
		}
/*#>*/}


reset($CONFIG['i18n.lang_list']);
PATCHWORK_I18N || $_SERVER['PATCHWORK_LANG'] = key($CONFIG['i18n.lang_list']);
define('PATCHWORK_DIRECT',  '_' === $_SERVER['PATCHWORK_REQUEST']);
