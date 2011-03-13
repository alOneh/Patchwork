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


class agent_fckeditor_upload extends agent_fckeditor_browser
{
    public $get = 'Type:c:File|Image|Flash|Media';

    function compose($o)
    {
        $this->get->Command = 'FileUpload';
        $this->get->CurrentFolder = '/';

        $o = parent::compose($o);

        if (isset($o->currentUrl)) $o->url = $o->currentUrl . $o->filename;

        return $o;
    }
}
