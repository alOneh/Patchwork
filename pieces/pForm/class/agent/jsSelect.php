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


class agent_jsSelect extends agent
{
    const contentType = 'text/javascript';

    protected

    $maxage = -1,
    $template = 'form/jsSelect.js',

    $param = array();


    function compose($o)
    {
        unset($this->param['valid']);
        unset($this->param['firstItem']);
        unset($this->param['multiple']);

        $this->form = new pForm($o, '', true, '');
        $this->form->add('select', 'select', $this->param);

        return $o;
    }
}
