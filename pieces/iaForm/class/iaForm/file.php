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


class extends iaForm_text
{
	protected $type = 'file';
	public $isfile = true;
	public $isdata = false;

	protected function init(&$param)
	{
		$this->valid_args[] = $this->maxlength = isset($param['maxlength']) ? (int) $param['maxlength'] : 0;

		$this->valid = isset($param['valid']) ? $param['valid'] : '';
		if (!$this->valid) $this->valid = 'file';

		$i = 0;
		while(isset($param[$i])) $this->valid_args[] =& $param[$i++];

		$this->status = VALIDATE::getFile($_FILES[$this->name], $this->valid, $this->valid_args);
		$this->value = $this->status;
	}

	protected function addJsValidation($a)
	{
		$a->_valid = new loop_array(array('string', isset($this->valid_args[0]) ? $this->valid_args[0] : ''));
		return $a;
	}
}