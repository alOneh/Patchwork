<?php /*********************************************************************
 *
 *   Copyright : (C) 2006 Nicolas Grekas. All rights reserved.
 *   Email     : nicolas.grekas+patchwork@espci.org
 *   License   : http://www.gnu.org/licenses/gpl.txt GNU/GPL, see COPYING
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/


class
{
	static function negociate()
	{
		ob_start('ob_gzhandler');

		header('Expires: ' . gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + CIA_MAXAGE) . ' GMT');
		header('Cache-Control: max-age=' . CIA_MAXAGE .',public');
		header('Content-Type: text/html; charset=UTF-8');
		header('Vary: Accept-Language', false);

		$lang = explode('__', $_SERVER['CIA_HOME'], 2);
		$lang = implode(self::HTTP_Best_Language(explode('|', $GLOBALS['CONFIG']['lang_list'])), $lang);
		$lang = htmlspecialchars($lang);

		?><html><head><title>Loading ...</title><script type="text/javascript">/*<![CDATA[*/w=document;if(!/safari|msie [0-5]\./i.test(navigator.userAgent)&&!/(^|; )JS=[12](; |$)/.test(w.cookie))w.cookie='JS=1; path=/'//]]></script><meta http-equiv="refresh" content="0; URL=<?php echo $lang?>" /></head><body onload="location.replace('<?php echo $lang?>')"><i>Loading <a href="<?php echo $lang?>"><?php echo $lang?></a> ...</i></body></html><?php

		exit;
	}

	static function HTTP_Best_Language($supported)
	{
		$candidates = array();

		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $item)
		{
			$item = explode(';q=', $item);
			if ($item[0] = trim($item[0])) $candidates[ $item[0] ] = isset($item[1]) ? (double) trim($item[1]) : 1;
		}

		$lang = $supported[0];
		$qMax = 0;

		foreach ($candidates as $l => &$q) if (
			$q > $qMax
			&& (
				in_array($l, $supported)
				|| (
					($tiret = strpos($l, '-'))
					&& in_array($l = substr($l, 0, $tiret), $supported)
				)
			)
		)
		{
			$qMax = $q;
			$lang = $l;
		}

		return $lang;
	}
}