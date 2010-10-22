<?php /*********************************************************************
 *
 *   Copyright : (C) 2010 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


// New tokens since PHP 5.3
defined('T_GOTO')         || patchwork_tokenizer::defineNewToken('T_GOTO');
defined('T_USE' )         || patchwork_tokenizer::defineNewToken('T_USE');
defined('T_DIR' )         || patchwork_tokenizer::defineNewToken('T_DIR');
defined('T_NS_C')         || patchwork_tokenizer::defineNewToken('T_NS_C');
defined('T_NAMESPACE')    || patchwork_tokenizer::defineNewToken('T_NAMESPACE');
defined('T_NS_SEPARATOR') || patchwork_tokenizer::defineNewToken('T_NS_SEPARATOR');

// New primary token matching closing braces opened with T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES
patchwork_tokenizer::defineNewToken('T_CURLY_CLOSE');

// Sub-token matching multilines T_WHITESPACE
patchwork_tokenizer::defineNewToken('T_WHITESPACE_MULTILINE');

class patchwork_tokenizer
{
	protected

	$line = 0,
	$code,
	$position,
	$tokens,
	$prevType,
	$anteType,

	$tokenRegistry    = array(),
	$callbackRegistry = array(),

	$parent,
	$depends = array(),
	$shared = array(
		'line',
		'code',
		'depends',
		'position',
		'tokens',
		'prevType',
		'anteType',
		'tokenRegistry',
		'callbackRegistry',
		'tokenizerError',
		'nextRegistryPosition',
	);


	private

	$tokenizerError       = false,
	$registryPosition     = 0,
	$nextRegistryPosition = 0;


	protected static

	$sugar = array(
		T_WHITESPACE  => 1,
		T_COMMENT     => 1,
		T_DOC_COMMENT => 1,
	);


	function __construct(self $parent = null)
	{
		$parent || $parent = $this;
		$this->initialize($parent);
	}

	protected function initialize(self $parent)
	{
		$this->parent = $parent;
		is_array($this->depends) || $this->depends = (array) $this->depends;

		foreach ($this->depends as $parent)
		{
			if (!isset($this->parent->depends[$parent]))
			{
				trigger_error(get_class($this) . ' tokenizer depends on a not initialized one: ' . $parent);
				return;
			}
		}

		$this->depends = array_flip($this->depends);
		$parent = get_class($this);

		while (!isset($this->depends[$parent]) && false !== $parent)
		{
			$this->depends[$parent] = 1;
			$parent = get_parent_class($parent);
		}

		if ($this !== $this->parent)
		{
			$this->parent->depends += $this->depends;

			foreach (array_keys($this->parent->shared) as $parent)
				$this->$parent =& $this->parent->$parent;

			$this->parent->shared += array_flip((array) $this->shared);
			$this->shared =& $this->parent->shared;
		}
		else
		{
			$this->shared = array_flip((array) $this->shared);
		}

		$this->registryPosition = $this->nextRegistryPosition;
		$this->nextRegistryPosition += 100000;

		empty($this->callbacks) || $this->register();
	}

	static function defineNewToken($name)
	{
		static $offset = 0;
		define($name, --$offset);
	}

	protected function register($method = null)
	{
		null === $method && $method = $this->callbacks;

		$sort = array();

		foreach ((array) $method as $method => $token)
		{
			if (is_int($method))
			{
				isset($sort['']) || $sort[''] =& $this->callbackRegistry;
				$this->callbackRegistry[++$this->registryPosition] = array($this, $token, 0);
			}
			else foreach ((array) $token as $s => $token)
			{
				isset($sort[$token]) || $sort[$token] =& $this->tokenRegistry[$token];
				$this->tokenRegistry[$token][++$this->registryPosition] = array($this, $method, 0 === $s || (0 < $s && is_int($s))  ? 0 : $s);
			}
		}

		foreach ($sort as &$sort) ksort($sort);
	}

	protected function unregister($method = null)
	{
		null === $method && $method = $this->callbacks;

		foreach ((array) $method as $method => $token)
		{
			if (is_int($method))
			{
				foreach ($this->callbackRegistry as $k => $v)
					if (array($this, $token, 0) === $v)
						unset($this->callbackRegistry[$k]);
			}
			else foreach ((array) $token as $s => $token)
			{
				if (isset($this->tokenRegistry[$token]))
				{
					foreach ($this->tokenRegistry[$token] as $k => $v)
						if (array($this, $method, 0 === $s || (0 < $s && is_int($s))  ? 0 : $s) === $v)
							unset($this->tokenRegistry[$token][$k]);

					if (!$this->tokenRegistry[$token]) unset($this->tokenRegistry[$token]);
				}
			}
		}
	}

	protected function setError($message)
	{
		if (!$this->tokenizerError)
		{
			$this->tokenizerError = array($message, (int) $this->line, get_class($this));
		}
	}

	function getError()
	{
		return $this->tokenizerError;
	}

	function tokenize($code)
	{
		if ($this->parent !== $this) return $this->parent->tokenize($code);

		if ('' === $code) return $code;

		$tRegistry =& $this->tokenRegistry;
		$cRegistry =& $this->callbackRegistry;

		$this->code = $this->getTokens($code);

		$line     =& $this->line;
		$code     =& $this->code;
		$i        =& $this->position;
		$tokens   =& $this->tokens;
		$prevType =& $this->prevType;
		$anteType =& $this->anteType;

		$i        = 0;
		$tokens   = array();
		$prevType = false;
		$anteType = false;

		$line     = 1;
		$curly    = 0;
		$strCurly = array();
		$deco     = '';

		while (isset($code[$i]))
		{
			$lines = 0;
			$token =& $code[$i];
			unset($code[$i++]);

			if (isset($token[1]))
			{
				switch ($token[0])
				{
				case T_OPEN_TAG:
				case T_CLOSE_TAG:
				case T_INLINE_HTML:
				case T_OPEN_TAG_WITH_ECHO:
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
					$lines = substr_count($token[1], "\n");
					break;

				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
					$strCurly[] = $curly;
					$curly = 0;
					break;
				}
			}
			else
			{
				$token = array($token, $token);

				switch ($token[0])
				{
				case '{': ++$curly; break;
				case '}':
					if (0 > --$curly)
					{
						$token[0] = T_CURLY_CLOSE;
						$curly    = array_pop($strCurly);
					}
				}
			}

			if (isset($deco[0])) $token[2] = $deco;
			else unset($token[2]);

			do
			{
				if ($cRegistry || isset($tRegistry[$token[0]]))
				{
					if (!$c = $cRegistry)
					{
						$c = $tRegistry[$token[0]];
					}
					else if (isset($tRegistry[$token[0]]))
					{
						$c += $tRegistry[$token[0]];
						ksort($c);
					}

					foreach ($c as $c)
					{
						if (0 === $c[2] || (isset($token[3]) && $token[3] === $c[2]))
						{
							if (false === $c[0]->{$c[1]}($token)) break 2;
						}
					}
				}

				$tokens[] =& $token;
				$line += $lines;
				$deco = '';

				$anteType = $prevType;
				$prevType = $token[0];
			}
			while (0);

			while (isset($code[$i][1], self::$sugar[$code[$i][0]]))
			{
				$token =& $code[$i];
				unset($code[$i++]);

				if (' ' === $token[1])
				{
					// µ-optimization
					$lines = 0;
				}
				else if ($lines = substr_count($token[1], "\n"))
				{
					T_WHITESPACE === $token[0] && $token[3] = T_WHITESPACE_MULTILINE;
				}

				if (isset($tRegistry[$token[0]]))
				{
					$token[2] = $deco;

					foreach ($tRegistry[$token[0]] as $c)
					{
						if (0 === $c[2] || (isset($token[3]) && $token[3] === $c[2]))
						{
							if (false === $c[0]->{$c[1]}($token)) continue 2;
						}
					}
				}

				$deco .= $token[1];

				$line += $lines;
			}
		}

		// Reduce memory usage thanks to copy-on-write
		$deco = $tokens;
		$tokens = array();

		return $deco;
	}

	protected function getTokens($code)
	{
		return $this->parent === $this ? token_get_all($code) : $this->parent->getTokens($code);
	}

	static function export($a, $lf = 0)
	{
		if (is_array($a))
		{
			if ($a)
			{
				$i = 0;
				$b = array();

				foreach ($a as $k => &$a)
				{
					if (is_int($k) && $k >= 0)
					{
						$b[] = ($k !== $i ? $k . '=>' : '') . self::export($a);
						$i = $k+1;
					}
					else
					{
						$b[] = self::export($k) . '=>' . self::export($a);
					}
				}

				$b = 'array(' . implode(',', $b) . ')';
			}
			else return 'array()';
		}
		else if (is_object($a))
		{
			$b = array();
			$v = (array) $a;
			foreach ($v as $k => &$v)
			{
				if ("\0" === substr($k, 0, 1)) $k = substr($k, 3);
				$b[$k] =& $v;
			}

			$b = self::export($b);
			$b = get_class($a) . '::__set_state(' . $b . ')';
		}
		else if (is_string($a))
		{
			if ($a !== strtr($a, "\r\n\0", '---'))
			{
				$b = '"'. str_replace(
					array(  "\\",   '"',   '$',  "\r",  "\n",  "\0"),
					array('\\\\', '\\"', '\\$', '\\r', '\\n', '\\0'),
					$a
				) . '"';
			}
			else
			{
				$b = "'" . str_replace(
					array('\\', "'"),
					array('\\\\', "\\'"),
					$a
				) . "'";
			}
		}
		else if (true  === $a) $b = 'true';
		else if (false === $a) $b = 'false';
		else if (null  === $a) $b = 'null';
		else if (INF   === $a) $b = 'INF';
		else $b = (string) $a;

		$lf && $b .= str_repeat("\n", $lf);

		return $b;
	}

	function __call($method, $args)
	{
		return call_user_func_array(array($this->parent, $method), $args);
	}
}
