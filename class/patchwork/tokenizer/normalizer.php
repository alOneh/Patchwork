<?php /***** vi: set encoding=utf-8 expandtab shiftwidth=4: ****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


patchwork_tokenizer::createToken('T_ENDPHP'); // end of the source code


class patchwork_tokenizer_normalizer extends patchwork_tokenizer
{
    protected

    $lfLineEndings = true,
    $checkUtf8     = true,
    $stripUtf8Bom  = true,
    $callbacks = array(
        'tagOpenEchoTag'  => T_OPEN_TAG_WITH_ECHO,
        'tagOpenTag'      => T_OPEN_TAG,
        'tagCloseTag'     => T_CLOSE_TAG,
        'fixVar'          => T_VAR,
        'tagHaltCompiler' => T_HALT_COMPILER,
    );


    protected function getTokens($code)
    {
        if ($this->lfLineEndings && false !== strpos($code, "\r"))
        {
            $code = str_replace("\r\n", "\n", $code);
            $code = strtr($code, "\r", "\n");
        }

        if ($this->checkUtf8 && !preg_match('//u', $code))
        {
            $this->setError("File encoding is not valid UTF-8", E_USER_WARNING);
        }

        if ($this->stripUtf8Bom && 0 === strncmp($code, "\xEF\xBB\xBF", 3))
        {
            // substr_replace() is for mbstring overloading resistance
            $code = substr_replace($code, '', 0, 3);
            $this->setError("Stripping UTF-8 Byte Order Mark", E_USER_NOTICE);
        }

        $code = parent::getTokens($code);

        $last = array_pop($code);

        $code[] = T_CLOSE_TAG === $last[0] ? ';' : $last;
        T_INLINE_HTML === $last[0] && $code[] = array(T_OPEN_TAG, '<?php ');
        $code[] = array(T_ENDPHP, '');

        return $code;
    }

    protected function tagOpenEchoTag(&$token)
    {
        $this->tagOpenTag($token);

        return $this->tokensUnshift(
            array(T_OPEN_TAG, $token[1]),
            array(T_ECHO, 'echo')
        );
    }

    protected function tagOpenTag(&$token)
    {
        $token[1] = substr_count($token[1], "\n");
        $token[1] = '<?php' . ($token[1] ? str_repeat("\n", $token[1]) : ' ');
    }

    protected function tagCloseTag(&$token)
    {
        $token[1] = substr_count($token[1], "\n");
        $token[1] = str_repeat("\n", $token[1]) . '?'.'>';
    }

    protected function fixVar(&$token)
    {
        return $this->tokensUnshift(array(T_PUBLIC, 'public'));
    }

    protected function tagHaltCompiler(&$token)
    {
        $this->unregister(array(__FUNCTION__ => T_HALT_COMPILER));
        return $this->tokensUnshift(array(T_ENDPHP, ''), ';', $token);
    }
}
