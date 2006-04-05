<?php

require_once 'Mail.php';
require_once 'Mail/mime.php';

// $error_reporting = error_reporting(0);
// error_reporting($error_reporting);

class iaMail extends Mail_mime
{
	protected $options;

	static function send($headers, $body, $options = null)
	{
		$mail = new iaMail($options);

		$mail->headers($headers);
		$mail->setTxtBody($body);

		$mail->doSend();
	}

	static function sendAgent($headers, $agent, $argv = array(), $options = null)
	{
		$mail = new iaMail_agent($agent, $argv, $options);
		$mail->headers($headers);
		$mail->doSend();
	}


	function __construct($options = null)
	{
		parent::__construct();

		$this->options = $options;

		$this->_build_params['text_encoding'] = '8bit';
		$this->_build_params['html_charset'] = 'UTF-8';
		$this->_build_params['text_charset'] = 'UTF-8';
		$this->_build_params['head_charset'] = 'UTF-8';
	}

	function doSend()
	{
		$message_id = CIA::uniqid();

		$this->_headers['Message-Id'] = "<{$message_id}@iaMail>";

		$this->setObserver('reply', 'Reply-To', $message_id);
		$this->setObserver('bounce', 'Return-Path', $message_id);

		$body =& $this->get($this->options);
		$headers =& $this->headers();

		$to = DEBUG ? 'webmaster' : $headers['To'];
		unset($headers['To']);

		$mail = Mail::factory('mail', isset($headers['Return-Path']) ? '-f ' . escapeshellarg($headers['Return-Path']) : '' );
		$mail->send($to, $headers, $body);
	}

	// The original _encodeHeaders of Mail_mime is bugged !
	function _encodeHeaders($input)
	{
		$ns = "[^\(\)<>@,;:\"\/\[\]\r\n]*";

		foreach ($input as $hdr_name => $hdr_value)
		{
			$input[$hdr_name] = preg_replace_callback("/{$ns}(?:[\\x80-\\xFF]{$ns})+/", array($this, '_encodeHeaderWord'), $hdr_value);
		}

		return $input;
	}

	function _encodeHeaderWord($word)
	{
		$word = preg_replace('/[=_\?\x00-\x1F\x80-\xFF]/e', '"=".strtoupper(dechex(ord("\0")))', $word[0]);

		preg_match('/^( *)(.*?)( *)$/', $word, $w);

		$word =& $w[2];
		$word = str_replace(' ', '_', $word);

		$start = '=?' . $this->_build_params['head_charset'] . '?Q?';
		$offsetLen = strlen($start) + 2;

		$w[1] .= $start;

		while ($offsetLen + strlen($word) > 75)
		{
			$w[1] .= substr($word, 0, 75 - $offsetLen) . "?={$this->_eol} {$start}";
			$word = substr($word, 75 - $offsetLen);
		}

		return $w[1] . $word . '?=' . $w[3];
	}

	function setObserver($event, $header, $message_id)
	{
		if (!isset($this->options['on' . $event])) return;

		if (isset($this->options[$event . '_email'])) $email = $this->options[$event . '_email'];
		else if (isset($GLOBALS['CONFIG'][$event . '_email'])) $email = $GLOBALS['CONFIG'][$event . '_email'];

		if (isset($this->options[$event . '_url'])) $url = $this->options['reply_url'];
		else if (isset($GLOBALS['CONFIG'][$event . '_url'])) $url = $GLOBALS['CONFIG'][$event . '_url'];
		
		if (!isset($email)) E("{$event}_email has not been configured.");
		else if (!isset($url)) E("{$event}_url has not been configured.");
		else
		{
			$email = sprintf($email, $message_id);

			if (isset($this->headers[$header])) $this->headers[$header] .= ', ' . $email;
			else $this->headers[$header] = $email;


			require_once 'HTTP/Request.php';

			$r = new HTTP_Request( CIA::getUri($url) );
			$r->setMethod(HTTP_REQUEST_METHOD_POST);
			$r->addPostData('message_id', $message_id);
			$r->addPostData("{$event}_on{$event}", CIA::getUri($this->options['on' . $event]));
			$r->sendRequest();
		}
	}
}