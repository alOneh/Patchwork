/***************************************************************************
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
 *   Original version by Ma Bingyao <andot@ujn.edu.cn>
 *   http://www.coolcode.cn/?p=128
 *
 ***************************************************************************/


xxtea = {
	encrypt: function($str, $key)
	{
		if ('' == $str) return '';

		$key = unescape(encodeURI($key));
		$str = unescape(encodeURI($str));

		var $v = xxtea.$str2long($str, 1),
			$k = xxtea.$str2long($key, 0),
			$n = $v.length - 1,

			$z = $v[$n],
			$y = $v[0],
			$delta = 0x9E3779B9,

			$mx, $e, $p,
			$q = Math.floor(6 + 52 / ($n + 1)),
			$sum = 0;

		while ($q-- > 0)
		{
			$sum = $sum + $delta & 0xffffffff;
			$e = $sum >>> 2 & 3;

			for ($p = 0; $p < $n; ++$p)
			{
				$y = $v[$p + 1];
				$mx = ($z >>> 5 ^ $y << 2) + ($y >>> 3 ^ $z << 4) ^ ($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z);
				$z = $v[$p] = $v[$p] + $mx & 0xffffffff;
			}

			$y = $v[0];
			$mx = ($z >>> 5 ^ $y << 2) + ($y >>> 3 ^ $z << 4) ^ ($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z);
			$z = $v[$p] = $v[$p] + $mx & 0xffffffff;
		}

		return xxtea.$long2str($v, 0);
	},

	decrypt: function($str, $key)
	{
		if ('' == $str) return '';

		$key = unescape(encodeURI($key));

		var $v = xxtea.$str2long($str, 0),
			$k = xxtea.$str2long($key, 0),
			$n = $v.length - 1,

			$z = $v[$n - 1],
			$y = $v[0],
			$delta = 0x9E3779B9,

			$mx, $e, $p,
			$q = Math.floor(6 + 52 / ($n + 1)),
			$sum = $q * $delta & 0xffffffff;

		while ($sum != 0)
		{
			$e = $sum >>> 2 & 3;

			for ($p = $n; $p > 0; --$p)
			{
				$z = $v[$p - 1];
				$mx = ($z >>> 5 ^ $y << 2) + ($y >>> 3 ^ $z << 4) ^ ($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z);
				$y = $v[$p] = $v[$p] - $mx & 0xffffffff;
			}

			$z = $v[$n];
			$mx = ($z >>> 5 ^ $y << 2) + ($y >>> 3 ^ $z << 4) ^ ($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z);
			$y = $v[$p] = $v[$p] - $mx & 0xffffffff;

			$sum = $sum - $delta & 0xffffffff;
		}

		$str = xxtea.$long2str($v, 1);

		return decodeURIComponent(escape($str));
	},

	$long2str: function($v, $w)
	{
		var $vl = $v.length,
			$sl = $v[$vl - 1] & 0xffffffff,
			$i = 0;

		for (; $i < $vl; ++$i) $v[$i] = String.fromCharCode(
			$v[$i] & 0xff,
			$v[$i] >>> 8 & 0xff,
			$v[$i] >>> 16 & 0xff,
			$v[$i] >>> 24 & 0xff
		);

		$v = $v.join('');

		return $w ? $v.substring(0, $sl) : $v;
	},

	$str2long: function($s, $w)
	{
		var $len = $s.length,
			$v = [];
			$i = 0;

		for (; $i < $len; $i += 4)
			$v[$i >> 2] = $s.charCodeAt($i)
				| $s.charCodeAt($i + 1) << 8
				| $s.charCodeAt($i + 2) << 16
				| $s.charCodeAt($i + 3) << 24;

		$w && $v.push($len);

		return $v;
	}
}