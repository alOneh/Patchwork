<?php /***** vi: set encoding=utf-8 expandtab shiftwidth=4: ****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


// If you want to use your patchwork application in CLI scripts:
// - set PATCHWORK_BOOTPATH to your patchwork application directory
// - set $_SERVER['PATCHWORK_BASE'] as needed
// - for multilanguage applications, set $_SERVER['PATCHWORK_LANG']
// - and include this present file


empty($_SERVER['PATCHWORK_LANG']) && $_SERVER['PATCHWORK_LANG'] = '';

$_GET = array('p:' => 'k:' . $_SERVER['PATCHWORK_LANG']);
$_COOKIE = $_POST = array();

$url = explode('__', $_SERVER['PATCHWORK_BASE'], 2);
$url = implode($_SERVER['PATCHWORK_LANG'], $url);

if (!preg_match("'^http(s?)://([-.:a-z0-9]+)(/(?:\?|.+[/\?])?)$'D", $url, $url))
{
    throw new Exception("Invalid \$_SERVER['PATCHWORK_BASE']");
}

if ($url[1]) $_SERVER['HTTPS'] = 'on';
else unset($_SERVER['HTTPS']);

$_SERVER['PATCHWORK_REQUEST'] = '/patchworkCli';
$_SERVER['QUERY_STRING'] = 'p:=k:' . $_SERVER['PATCHWORK_LANG'];
$_SERVER['HTTP_HOST'] = $url[2];
$_SERVER['REQUEST_URI'] = $url[3] . 'patchworkCli?' . $_SERVER['QUERY_STRING'];
$_SERVER['REQUEST_METHOD'] = 'GET';

if (empty($_SERVER['SERVER_PORT']))
{
    $h = strstr($_SERVER['HTTP_HOST'], ':');
    $_SERVER['SERVER_PORT'] = false !== $h
        ? (string)(int) substr($h, 1)
        : (isset($_SERVER['HTTPS']) ? '443' : '80');
}

empty($_SERVER['SERVER_ADDR' ]) && $_SERVER['SERVER_ADDR' ] = '127.0.0.1';
empty($_SERVER['REMOTE_ADDR' ]) && $_SERVER['REMOTE_ADDR' ] = '127.0.0.1';
empty($_SERVER['REQUEST_TIME']) && $_SERVER['REQUEST_TIME'] = time();

function apache_setenv() {}

define('TURBO', false);

ob_start();
require PATCHWORK_BOOTPATH . '/.patchwork.php';
ob_end_clean();
