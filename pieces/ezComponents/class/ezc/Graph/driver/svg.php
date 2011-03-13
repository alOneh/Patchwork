<?php /***** vi: set encoding=utf-8 expandtab shiftwidth=4: ****************
 *
 * Add a workaround for a bug in DOMDocument->save() wrongly sending headers.
 *
 * @copyright Copyright (C) 2005, 2006 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 */

class ezcGraphSvgDriver extends self
{
    function render($file)
    {
        $this->createDocument();
        $this->drawAllTexts();
        if (ob_get_level() && 'php://output' == $file) echo $this->dom->saveXML();
        else $this->dom->save( $file );
    }
}
