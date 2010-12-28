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


class patchwork_tokenizer_staticState extends patchwork_tokenizer
{
	protected

	$stateCallbacks = array(
		0 => array(),
		1 => array(
			'tagEOState1'  => array(T_MULTILINE_SUGAR => T_WHITESPACE),
			'tagEOState1b' => array(T_MULTILINE_SUGAR => T_COMMENT   ),
		),
		2 => array(
			'tagEOState2'     => T_COMMENT,
			'tagEOExpression' => array(T_CLOSE_TAG, ';'),
		),
		3 => array(
			'tagEOState3' => T_COMMENT,
		),
	),
	$state = 2,
	$transition = array();


	function __construct(parent $parent = null)
	{
		parent::__construct($parent);

		$this->register($this->stateCallbacks[2]);
	}

	function getStaticCode($code, $codeVarname)
	{
		$state  = 2;
		$O = $codeVarname . '[1]=';
		$o = '';
		$j = 0;

		foreach ($this->transition as $i => $transition)
		{
			do
			{
				$o .= $code[$j];
				unset($code[$j]);
			}
			while (++$j < $i);

			switch ($state)
			{
			case 1: $O .= $o; break;
			case 2: $O .= self::export($o); break;
			case 3: $O .= $o . ')."' . str_repeat('\n', substr_count($o, "\n")) . '"'; break;
			}

			switch ($transition[0])
			{
			case 1: 2 === $state && $O .= ';'; break;
			case 2: $O .= 3 !== $state ? (2 === $state ? ';' : ' ') . $codeVarname . '[' . $transition[1] . ']=' : '.'; break;
			case 3: $O .= '.patchwork_tokenizer::export('; break;
			}

			$state = $transition[0];
			$o = '';
		}

		$this->transition = array();
		$o = implode('', $code);

		switch ($state)
		{
		case 1: $O .= $o; break;
		case 2: $O .= self::export($o) . ';'; break;
		case 3: $O .= $o . ')."' . str_repeat('\n', substr_count($o, "\n")) . '";'; break;
		}

		return $O;
	}

	function setState($state)
	{
		end($this->type);
		$this->transition[key($this->type)+1] = array($state, $this->line);

		if ($this->state === 2) $this->unregister($this->stateCallbacks[1]);
		if ($this->state === $state) return;

		$this->unregister($this->stateCallbacks[$this->state]);
		$this->  register($this->stateCallbacks[$state]);

		$this->state = $state;
	}

	function tagEOState2(&$token)
	{
		if ('/*<*/' === $token[1])
		{
			$this->setState(3);
			return false;
		}
		else if ('/**/' === $token[1] && "\n" === substr(end($this->code), -1))
		{
			$this->setState(1);
			return false;
		}
	}

	function tagEOExpression(&$token)
	{
		$this->unregister(array(__FUNCTION__ => $this->stateCallbacks[2][__FUNCTION__]));
		$this->  register($this->stateCallbacks[1]);
	}

	function tagEOState1 (&$token) {$this->setState(2);}
	function tagEOState1b(&$token) {"\n" === substr($token[1], -1) && $this->setState(2);}

	function tagEOState3(&$token)
	{
		if ('/*>*/' === $token[1])
		{
			$this->setState(2);
			return false;
		}
	}
}
