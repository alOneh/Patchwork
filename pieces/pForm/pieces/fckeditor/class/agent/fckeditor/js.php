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


class agent_fckeditor_js extends agent
{
    const contentType = 'text/javascript';

    protected $maxage = -1;

    function control() {}

    function compose($o)
    {
        $o->DATA = file_get_contents(patchworkPath('public/__/fckeditor/src/fckeditor.js'));
        return $o;
    }
}
