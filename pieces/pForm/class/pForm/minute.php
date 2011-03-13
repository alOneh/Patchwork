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


class pForm_minute extends pForm_text
{
    protected

    $maxlength = 2,
    $maxint = 59;


    protected function get()
    {
        $a = parent::get();
        $a->onchange = "this.value=this.value/1||'';if(this.value<0||this.value>{$this->maxint})this.value=''";
        return $a;
    }
}
