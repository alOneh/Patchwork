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


class extends converter_abstract
{
	protected

	$cols = 78;


	protected static

	$charMap = array(
		'┼' => '+', '├' => '|', '┬' => '-', '┌' => '+', '┤' => '|', '│' => '|', '┐' => '+', '┴' => '-',
		'└' => '+', '─' => '-', '┘' => '+', '┼' => '+', '┠' => '|', '┯' => '-', '┏' => '+', '┨' => '|',
		'┃' => '|', '┓' => '+', '┷' => '-', '┗' => '+', '━' => '-', '┛' => '+', '•' => '*', '□' => '+',
		'☆' => 'o', '○' => '#', '■' => '@', '★' => '-', '◎' => '=', '●' => 'x', '△' => '%', '●' => '*',
		'○' => 'o', '□' => '#', '●' => '#', '≪ ↑ ↓ ' => '<=UpDn ',
	),
	$textAnchor = array();


	function __construct($cols = false)
	{
		$cols && $this->cols = (int) $cols;
	}

	function convertData($html)
	{
		// Inline URLs
		$html = preg_replace_callback(
			'#<a\b[^>]*\shref="([^"]*)"[^>]*>(.*?)</a\b[^>]*>#isu',
			array(__CLASS__, 'buildTextAnchor'),
			$html
		);

		// Remove <sub> and <sup> tags
		$html = preg_replace('#<(/?)su[bp]\b([^>]*)>#iu' , '<$1span$2>', $html);

		// Style according to the Netiquette
		$html = preg_replace('#<(?:b|strong)\b[^>]*>(\s*)#iu' , '$1*', $html);
		$html = preg_replace('#(\s*)</(?:b|strong)\b[^>]*>#iu', '*$1', $html);
		$html = preg_replace('#<u\b[^>]*>(\s*)#iu' , '$1_', $html);
		$html = preg_replace('#(\s*)</u\b[^>]*>#iu', '_$1', $html);

		$file = tempnam('.', 'converter');

		p::writeFile($file, $html);

		$html = escapeshellarg($file);
		$html = `w3m -dump -cols {$this->cols} -T text/html -I UTF-8 -O UTF-8 {$html}`;
		$html = strtr($html, self::$charMap);

		$html = strtr($html, self::$textAnchor);
		self::$textAnchor = array();

		unlink($file);

		return FILTER::get($html, 'text');
	}

	function convertFile($file)
	{
		$html = file_get_contents($file);

		return $this->convertData($html);
	}

	protected static function buildTextAnchor($m)
	{
		$a = $m[2];
		$m = trim($m[1]);
		$m = preg_replace('"^mailto:\s*"i', '', $m);

		$b = false !== strpos($m, '&') ? html_entity_decode($m, ENT_COMPAT, 'UTF-8') : $m;
		$b = preg_replace_callback('"[^-a-zA-Z0-9_.~,/?:@&=+$#%]+"', array(__CLASS__, 'rawurlencodeCallback'), $b);
		$len = strlen($b);

		$c = '';
		do $c .= md5(mt_rand());
		while (strlen($c) < $len);
		$c = substr($c, 0, $len);

		self::$textAnchor[$c] = $b;

		if (false === stripos($a, $m)) $a .= " &lt;{$c}&gt;";
		else $a = str_ireplace($m, $c, $a);

		return $a;
	}

	protected static function rawurlencodeCallback($m)
	{
		return rawurlencode($m[0]);
	}
}
