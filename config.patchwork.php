<?php // vi: set encoding=utf-8 expandtab shiftwidth=4:


// Basic pieces of patchwork

#patchwork pieces/pForm
#patchwork pieces/toolbox


// Default settings

$CONFIG += array(

    // General
    'debug.allowed'  => true,
    'debug.password' => '',
    'debug.scream'   => false, // Disable the silencing error control operator, defaults to the DEBUG_SCREAM constant if any
    'turbo'          => false, // Run patchwork at full speed, at the cost of source code desynchronisation
    'umask'          => false, // Set the user file creation mode mask

    // Patchwork
    'clientside'      => true,      // Enable browser-side page rendering when available
    'i18n.lang_list'  => '',        // List of available languages ('en|fr' for example)
    'maxage'          => 2678400,   // Max age (in seconds) for HTTP ressources caching
    'P3P'             => 'CUR ADM', // P3P - Platform for Privacy Preferences
    'xsendfile'       => false,     // "X-Sendfile" enabling pattern
    'document.domain' => '',        // Value of document.domain for clientside cross subdomain communication

    // Session
    'session.save_path'     => /*<*/PATCHWORK_ZCACHE/*>*/,
    'session.cookie_path'   => 'auto',
    'session.cookie_domain' => 'auto',
    'session.auth_vars'     => array(), // Set of session vars used for authentication or authorization
    'session.group_vars'    => array(), // Set of session vars whose values define user groups

    // Translation adapter
    'translator.adapter' => false,
    'translator.options' => array(),

);


// Setup patchwork's environment

/**/ /*<*/patchwork_bootstrapper::alias('header', 'patchwork::header', array('$s', '$replace' => true, '$response_code' => null))/*>*/;

defined('DEBUG') || define('DEBUG', $CONFIG['debug.allowed'] && (!$CONFIG['debug.password'] || isset($_COOKIE['debug_password']) && $CONFIG['debug.password'] == $_COOKIE['debug_password']) ? 1 : 0);
defined('TURBO') || define('TURBO', !DEBUG && $CONFIG['turbo']);

empty($CONFIG['umask']) || umask($CONFIG['umask']);
empty($CONFIG['xsendfile']) && isset($_SERVER['PATCHWORK_XSENDFILE']) && $CONFIG['xsendfile'] = $_SERVER['PATCHWORK_XSENDFILE'];

/**/if (IS_WINDOWS && !function_exists('__patchwork_file_exists'))
/**/{
        if (DEBUG)
        {
/**/        // Replace file_exists() on Windows to check if character case is strict
/**/
/**/        /*<*/patchwork_bootstrapper::alias('file_exists',   'patchwork_PHP_winfs::file_exists',   array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_file',       'patchwork_PHP_winfs::is_file',       array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_dir',        'patchwork_PHP_winfs::is_dir',        array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_link',       'patchwork_PHP_winfs::is_link',       array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_executable', 'patchwork_PHP_winfs::is_executable', array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_readable',   'patchwork_PHP_winfs::is_readable',   array('$file'))/*>*/;
/**/        /*<*/patchwork_bootstrapper::alias('is_writable',   'patchwork_PHP_winfs::is_writable',   array('$file'))/*>*/;
        }
/**/}


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
*/

$a = strpos($_SERVER['REQUEST_URI'], '?');
$a = false === $a ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $a);
$a = rawurldecode($a);

if (false !== strpos($a, '/.'))
{
    $j = explode('/', substr($a, 1));
    $r = array();
    $v = false;

    foreach ($j as $j) switch ($j)
    {
    case '..': $r && array_pop($r);
    case '.' : $v = true; break;
    default  : $r[] = rawurlencode($j);
    }

    if ($v)
    {
        $r = '/' . ($r ? implode('/', $r) . ('.' === $j || '..' === $j ? '/' : '') : '');
        '' !== $_SERVER['QUERY_STRING'] && $r .= '?' . $_SERVER['QUERY_STRING'];
        patchwork_bad_request("Please resolve references to '.' and '..' before issuing your request.", $r);
    }
}

/**/if ($a = patchwork_bootstrapper::isPathInfoSupported())
/**/{
        switch (true)
        {
        case isset($_SERVER['REDIRECT_PATCHWORK_REQUEST']): $r = $_SERVER['REDIRECT_PATCHWORK_REQUEST']; break;
        case isset($_SERVER['PATCHWORK_REQUEST'])         : $r = $_SERVER['PATCHWORK_REQUEST']         ; break;
        case isset($_SERVER['ORIG_PATH_INFO'])            : $r = $_SERVER['ORIG_PATH_INFO']            ; break;
        case isset($_SERVER['PATH_INFO'])                 : $r = $_SERVER['PATH_INFO']                 ; break;

        case '/' === substr($a, -1): $a .= basename(isset($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : $_SERVER['SCRIPT_NAME']);
        default: $r = '';
        }

        $a .= '/';
/**/}
/**/else
/**/{
        $r = $_SERVER['QUERY_STRING'];
        $j = strpos($r, '?');
        false !== $j || $j = strpos($r, '&');

        if (false !== $j)
        {
            $r = substr($r, 0, $j);
            $_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], $j+1);

            parse_str($_SERVER['QUERY_STRING'], $_GET);

/**/        if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc())
/**/        {
                $k = array(&$_GET);
                for ($i = 0, $j = 1; $i < $j; ++$i)
                {
                    foreach ($k[$i] as &$v)
                    {
                        if (is_array($v)) $k[$j++] =& $v;
                        else
                        {
/**/                        if (ini_get_bool('magic_quotes_sybase'))
                                $v = str_replace("''", "'", $v);
/**/                        else
                                $v = stripslashes($v);
                        }
                    }

                    reset($k[$i]);
                    unset($k[$i]);
                }

                unset($k, $v);
/**/        }
        }
        else if ('' !== $r)
        {
            $_SERVER['QUERY_STRING'] = '';

            reset($_GET);
            $j = key($_GET);
            unset($_GET[$j]);
        }

        $j = explode('/', urldecode($r));
        $r = array();
        $v = 0;

        foreach ($j as $j)
        {
            if ('.' === $j) continue;
            if ('..' === $j) $r ? array_pop($r) : ++$v;
            else $r[]= $j;
        }

        $r = implode('/', $r);

        if ($v)
        {
            '/' !== substr($a, -1) && $a .= '/';
            $a = preg_replace("'[^/]*/{1,{$v}}$'", '', $a);
            '' === $a && $a = '/';
            $a = str_replace('%2F', '/', rawurlencode($a . $r));
            '' !== $_SERVER['QUERY_STRING'] && $a .= '?' . $_SERVER['QUERY_STRING'];

            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $a);

            exit;
        }
/**/}

$r = preg_replace("'/[./]*/'", '/', '/' . $r . '/');
$a = preg_replace("'/[./]*/'", '/', '/' . $a);

/**/if ($a && IS_WINDOWS)
/**/{
        // Workaround for http://bugs.php.net/44001

        if ('/' !== $r && false !== strpos($a, './') && false === strpos($r, './'))
        {
            $r = explode('/', $r);
            $j = count($r) - 1;

            $a = explode('/', strrev($a), $j);

            for ($i = 0; $i < $j; ++$i) $r[$j - $i] .= str_repeat('.', strspn($a[$i], '.'));

            $a = strrev(implode('/', $a));
            $r = implode('/', $r);
        }
/**/}

$_SERVER['PATCHWORK_REQUEST'] = (string) substr($r, 1, -1);

isset($_SERVER['REDIRECT_PATCHWORK_BASE']) && $_SERVER['PATCHWORK_BASE'] = $_SERVER['REDIRECT_PATCHWORK_BASE'];
isset($_SERVER['REDIRECT_PATCHWORK_LANG']) && $_SERVER['PATCHWORK_LANG'] = $_SERVER['REDIRECT_PATCHWORK_LANG'];

if (isset($_SERVER['PATCHWORK_BASE']))
{
    if (0 === strncmp($_SERVER['PATCHWORK_BASE'], '/', 1)) $_SERVER['PATCHWORK_BASE'] = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PATCHWORK_BASE'];

    if (!isset($_SERVER['PATCHWORK_LANG']))
    {
        $k = explode('__', $_SERVER['PATCHWORK_BASE'], 2);
        if (2 === count($k))
        {
            $k = '#' . preg_quote($k[0], '#') . $b . '#';
            preg_match($k, 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $a, $k)
                && $_SERVER['PATCHWORK_LANG'] = (string) array_search($k[1], $CONFIG['i18n.lang_list']);
        }
        else if (PATCHWORK_I18N) switch (substr($_SERVER['PATCHWORK_BASE'], -1))
        {
        case '/':
        case '?': $_SERVER['PATCHWORK_BASE'] .= '__/'; break;
        default:
/**/        if ($a)
                $_SERVER['PATCHWORK_BASE'] .= '/__/';
/**/        else
                $_SERVER['PATCHWORK_BASE'] .= '?__/';
        }
    }
}
else
{
    $a = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $a;

/**/if ($a)
        $_SERVER['PATCHWORK_BASE'] = substr($a, 0, -strlen($r)) . '/' . (PATCHWORK_I18N ? '__/' : '');
/**/else
        $_SERVER['PATCHWORK_BASE'] = $a . '?' . (PATCHWORK_I18N ? '__/' : '');
}

if (isset($_SERVER['PATCHWORK_LANG']))
{
    $a =& $CONFIG['i18n.lang_list'];
    $b =& $_SERVER['PATCHWORK_LANG'];

    isset($a[$b]) || $b = (string) array_search($b, $a);

    unset($a, $b);
}
else if ('__/' === substr($_SERVER['PATCHWORK_BASE'], -3) && preg_match("#^/{$b}/#", $r, $a))
{
    $_SERVER['PATCHWORK_LANG'] = array_search($a[1], $CONFIG['i18n.lang_list']);
    $_SERVER['PATCHWORK_REQUEST'] = (string) substr($r, strlen($a[1])+2, -1);
}
else $_SERVER['PATCHWORK_LANG'] = '';

reset($CONFIG['i18n.lang_list']);
PATCHWORK_I18N || $_SERVER['PATCHWORK_LANG'] = key($CONFIG['i18n.lang_list']);

$a = 'auto' === $CONFIG['session.cookie_path'];
$b = 'auto' === $CONFIG['session.cookie_domain'];

if ($a || $b)
{
    if (preg_match("'^(https?://)([^/:]+)(\.?(?::[^/_]*)?)(/(?:[^?#/]*/)*)'", $_SERVER['PATCHWORK_BASE'], $k))
    {
        if ($k[0] = strrpos($k[0], '__'))
        {
            $k[0] -= strlen($k[1]);
            $k[1]  = strlen($k[2]);
        }
        else $k[1] = 0;

        if ($a)
        {
            if ($k[0] >= $k[1])
            {
                $k[4] = substr($k[4], 0, $k[0] - $k[1] - strlen($k[3]));
                $a = strrpos($k[4], '/');
                $CONFIG['session.cookie_path'] = $a ? substr($k[4], 0, $a) : '/';
            }
            else $CONFIG['session.cookie_path'] = $k[4];
        }

        if ($b)
        {
            if ($k[0] < $k[1])
            {
                $k[2] = substr($k[2], $k[0]+2);
                $a = strpos($k[2], '.');
                $CONFIG['session.cookie_domain'] = false !== $a ? substr($k[2], $a) : '';
            }
            else $CONFIG['session.cookie_domain'] = '';
        }

        unset($k);
    }
    else
    {
        $a
            ? ($CONFIG['session.cookie_path']   = '/')
            : ($CONFIG['session.cookie_domain'] = '' );
    }
}

if (strtr($_SERVER['PATCHWORK_BASE'], '<>&"', '----') !== $_SERVER['PATCHWORK_BASE'])
{
    die('Patchwork error: Base URL can not contain special HTML character (' . htmlspecialchars($_SERVER['PATCHWORK_BASE']) . ')');
}
