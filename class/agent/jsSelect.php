<?php // vim: set enc=utf-8 ai noet ts=4 sw=4 fdm=marker:

class extends agent_bin
{
	protected $maxage = -1;
	protected $template = 'form/jsSelect.js';

	protected $param = array();

	function control()
	{
		CIA::header('Content-Type: text/javascript; charset=UTF-8');
	}

	function compose($o)
	{
		unset($this->param['valid']);
		unset($this->param['firstItem']);
		unset($this->param['multiple']);

		$this->form = new iaForm($o, '', true, '');
		$this->form->add('select', 'select', $this->param);

		return $o;
	}
}
