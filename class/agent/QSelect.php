<?php

class agent_QSelect extends agent_bin
{
	protected $template = 'QSelect/table.js';

	public function render()
	{
		CIA::header('Content-Type: text/javascript; charset=UTF-8');

		return parent::render();
	}
}
