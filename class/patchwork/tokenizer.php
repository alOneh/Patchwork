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
defined('T_DIR' )         || patchwork_tokenizer::defineNewToken('T_DIR');
defined('T_NS_C')         || patchwork_tokenizer::defineNewToken('T_NS_C');
defined('T_NAMESPACE')    || patchwork_tokenizer::defineNewToken('T_NAMESPACE');
defined('T_NS_SEPARATOR') || patchwork_tokenizer::defineNewToken('T_NS_SEPARATOR');

// Match closing braces opened with T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES
patchwork_tokenizer::defineNewToken('T_CURLY_CLOSE');

// Match data after T_HALT_COMPILER
patchwork_tokenizer::defineNewToken('T_COMPILER_HALTED');


class patchwork_tokenizer
{
	protected

	$dependencyName = null,
	$dependencies = array(),

	$line   = 0,
	$tokens = array(),
	$index  = 0,
	$types  = array(),
	$codes  = array(),
	$prevType,
	$anteType,
	$tokenRegistry    = array(),
	$callbackRegistry = array();


	private

	$parents = array(),
	$errors  = array(),
	$nextRegistryIndex = 0,

	$registryIndex     = 0;


	protected static

	$sugar = array(
		T_WHITESPACE  => 1,
		T_COMMENT     => 1,
		T_DOC_COMMENT => 1,
	);


	function __construct(self $parent = null)
	{
		$this->dependencyName || $this->dependencyName = get_class($this);
		$this->dependencies = (array) $this->dependencies;

		if ($parent)
		{
			$v = array(
				'line',
				'tokens',
				'index',
				'types',
				'texts',
				'prevType',
				'anteType',
				'tokenRegistry',
				'callbackRegistry',
				'parents',
				'errors',
				'nextRegistryIndex',
			);

			foreach ($v as $v) $this->$v =& $parent->$v;
		}
		else $parent = $this;

		foreach ($this->dependencies as $k => $v)
		{
			unset($this->dependencies[$k]);

			if (is_string($k))
			{
				$c = (array) $v;
				$v = $k;
			}
			else $c = array();

			$k = strtolower('\\' !== $v[0] ? __CLASS__ . '_' . $v : substr($v, 1));

			if (!isset($this->parents[$k]))
			{
				return trigger_error(get_class($this) . ' failed dependency: ' . $v);
			}

			$this->dependencies[$v] = $this->parents[$k];

			foreach ($c as $c) $this->$c =& $this->parents[$k]->$c;
		}

		$k = strtolower($this->dependencyName);
		$this->parents[$k] = $this;

		$this->nextRegistryIndex += 65536;
		$this->registryIndex = $this->nextRegistryIndex;

		empty($this->callbacks) || $this->register();
	}

	function getErrors()
	{
		return $this->errors;
	}

	function parse($code)
	{
		if ('' === $code) return array();

		$tRegistry =& $this->tokenRegistry;
		$cRegistry =& $this->callbackRegistry;

		$this->tokens = $this->getTokens($code);
		unset($code);

		$line     =& $this->line;     $line     = 1;
		$tokens   =& $this->tokens;
		$i        =& $this->index;    $i        = 0;
		$types    =& $this->types;    $types    = array();
		$texts    =& $this->texts;    $texts    = array('');
		$prevType =& $this->prevType; $prevType = false;
		$anteType =& $this->anteType; $anteType = false;

		$j        = 0;
		$curly    = 0;
		$strCurly = array();

		while (isset($tokens[$i]))
		{
			$t =& $tokens[$i];
			unset($tokens[$i++]);

			$lines = 0;
			$typed = 1;

			if (isset($t[1]))
			{
				switch ($t[0])
				{
				case T_WHITESPACE:
				case T_COMMENT:
				case T_DOC_COMMENT:
					$typed = 0;
					// No break;

				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_OPEN_TAG_WITH_ECHO:
				case T_INLINE_HTML:
				case T_CLOSE_TAG:
				case T_OPEN_TAG:
					$lines = substr_count($t[1], "\n");
					break;

				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
					$strCurly[] = $curly;
					$curly = 0;
					break;

				case T_HALT_COMPILER:
					$lines = 2;
					$curly = $i;

					// Skip 3 tokens: "(", ")" then ";" or T_CLOSE_TAG
					do while (isset($tokens[$i], self::$sugar[$tokens[$i][0]])) ++$i;
					while ($lines-- > 0 && ++$i);

					$lines = $i + 1;
					$strCurly = array();

					// Everything after is merged into one T_COMPILER_HALTED
					while (isset($tokens[++$i]))
					{
						$strCurly[] = isset($tokens[$i][1]) ? $tokens[$i][1] : $tokens[$i];
						unset($tokens[$i]);
					}

					$strCurly && $tokens[$lines] = array(T_COMPILER_HALTED, implode('', $strCurly));

					$i = $curly;
					$curly = $lines = 0;
					$strCurly = array();
					break;
				}
			}
			else
			{
				$t = array($t, $t);

				switch ($t[0])
				{
				case '{': ++$curly; break;
				case '}':
					if (0 > --$curly)
					{
						$t[0]  = T_CURLY_CLOSE;
						$curly = array_pop($strCurly);
					}
					break;
				}
			}

			if (isset($tRegistry[$t[0]]) || ($cRegistry && $typed))
			{
				$t[2] = array($k = $t[0] => 1);

				if (empty($tRegistry[$k])) $callbacks = $cRegistry;
				else if ($cRegistry && $typed)
				{
					$callbacks = $cRegistry + $tRegistry[$k];
					ksort($callbacks);
				}
				else $callbacks = $tRegistry[$k];

				do
				{
					foreach ($callbacks as $k => $c)
					{
						unset($callbacks[$k]);

						if (false === $k = $c[0]->{$c[1]}($t)) continue 3;
						else if (null !== $k && ($t[2][$k] = 1) && isset($tRegistry[$k]))
						{
							$callbacks += $tRegistry[$k];
							ksort($callbacks);
							continue 2;
						}
					}

					break;
				}
				while (1);
			}

			$texts[++$j] =& $t[1];
			$line += $lines;

			if ($typed)
			{
				$anteType  = $prevType;
				$types[$j] = $prevType = $t[0];
			}
		}

		// Free memory thanks to copy-on-write
		$j     = $texts;
		$types = $texts = array();
		$line = 0;

		return $j;
	}

	protected function setError($message, $type = E_USER_ERROR)
	{
		$this->errors[] = array($message, (int) $this->line, get_class($this), $type);
	}

	protected function register($method = null)
	{
		null === $method && $method = $this->callbacks;

		$sort = array();

		foreach ((array) $method as $method => $type)
		{
			if (is_int($method))
			{
				isset($sort['']) || $sort[''] =& $this->callbackRegistry;
				$this->callbackRegistry[++$this->registryIndex] = array($this, $type);
			}
			else foreach ((array) $type as $type)
			{
				isset($sort[$type]) || $sort[$type] =& $this->tokenRegistry[$type];
				$this->tokenRegistry[$type][++$this->registryIndex] = array($this, $method);
			}
		}

		foreach ($sort as &$sort) ksort($sort);
	}

	protected function unregister($method = null)
	{
		null === $method && $method = $this->callbacks;

		foreach ((array) $method as $method => $type)
		{
			if (is_int($method))
			{
				foreach ($this->callbackRegistry as $k => $v)
					if (array($this, $type) === $v)
						unset($this->callbackRegistry[$k]);
			}
			else foreach ((array) $type as $type)
			{
				if (isset($this->tokenRegistry[$type]))
				{
					foreach ($this->tokenRegistry[$type] as $k => $v)
						if (array($this, $method) === $v)
							unset($this->tokenRegistry[$type][$k]);

					if (!$this->tokenRegistry[$type]) unset($this->tokenRegistry[$type]);
				}
			}
		}
	}

	protected function &getNextToken($offset = 0)
	{
		$i = $this->index;

		do while (isset($this->tokens[$i], self::$sugar[$this->tokens[$i][0]])) ++$i;
		while ($offset-- > 0 && ++$i);

		isset($this->tokens[$i]) || $this->tokens[$i] = array(T_WHITESPACE, '');

		return $this->tokens[$i];
	}

	protected function tokensUnshift()
	{
		foreach (func_get_args() as $token)
			$this->tokens[--$this->index] = $token;

		return false;
	}

	protected function getTokens($code)
	{
		return token_get_all($code);
	}

	static function defineNewToken($name)
	{
		static $offset = 0;
		define($name, --$offset);
	}

	static function export($a)
	{
		switch (true)
		{
		case is_array($a):
			$i = 0;
			$b = array();

			foreach ($a as $k => $a)
			{
				if (is_int($k) && 0 <= $k)
				{
					$b[] = ($k !== $i ? $k . '=>' : '') . self::export($a);
					$i = $k + 1;
				}
				else
				{
					$b[] = self::export($k) . '=>' . self::export($a);
				}
			}

			return 'array(' . implode(',', $b) . ')';

		case is_object($a):
			return 'unserialize(' . self::export(serialize($a)) . ')';

		case is_string($a):
			if ($a !== strtr($a, "\r\n\0", '---'))
			{
				return '"'. str_replace(
					array(  "\\",   '"',   '$',  "\r",  "\n",  "\0"),
					array('\\\\', '\\"', '\\$', '\\r', '\\n', '\\0'),
					$a
				) . '"';
			}

			return "'" . str_replace(
				array(  '\\',   "'"),
				array('\\\\', "\\'"),
				$a
			) . "'";

		case true  === $a: return 'true';
		case false === $a: return 'false';
		case null  === $a: return 'null';
		case INF   === $a: return 'INF';
		case NAN   === $a: return 'NAN';
		default:           return (string) $a;
		}
	}
}
