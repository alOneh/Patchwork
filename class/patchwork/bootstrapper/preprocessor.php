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


class patchwork_bootstrapper_preprocessor__0
{
    protected $parser;

    function staticPass1($file)
    {
        if ('' === $code = file_get_contents($file)) return '';

        $p = new patchwork_PHP_Parser_normalizer;
        $p = $this->parser = new patchwork_PHP_Parser_staticState($p);

        if( (defined('DEBUG') && DEBUG)
            && !empty($GLOBALS['CONFIG']['debug.scream'])
                || (defined('DEBUG_SCREAM') && DEBUG_SCREAM) )
        {
            new patchwork_PHP_Parser_scream($p);
        }

        $code = $p->getRunonceCode($code);

        if ($p = $p->getErrors())
        {
            $p = $p[0];
            $p = addslashes("{$p[0]} in {$file}") . ($p[1] ? " on line {$p[1]}" : '');

            $code .= "die('Patchwork error: {$p}');";
        }

        return $code;
    }

    function staticPass2()
    {
        if (empty($this->parser)) return '';
        $code = substr($this->parser->getRuntimeCode(), 5);
        $this->parser = null;
        return $code;
    }
}
