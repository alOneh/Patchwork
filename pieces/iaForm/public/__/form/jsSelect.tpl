{***************************************************************************
 *
 *   Copyright : (C) 2006 Nicolas Grekas. All rights reserved.
 *   Email     : nicolas.grekas+patchwork@espci.org
 *   License   : http://www.gnu.org/licenses/gpl.txt GNU/GPL, see COPYING
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************}
<!--
{*

This template displays a jsSelect control.
It has the same parameters as input.tpl

*}

IF a$autofocus
	SET a$autofocus -->autofocus<!-- END:SET
END:IF

IF a$required
	SET a$required -->required<!-- END:SET
END:IF

SET a$id -->{a$name}<!-- END:SET
SET a$class -->{a$class|default:'jsSelect'}<!-- END:SET

IF !a$title
	SET a$title
		-->{a$_caption_}<!--
	END:SET
END:IF


SET $CAPTION
	IF a$_caption_
		--><label for="{a$id}" class="{a$class}" onclick="return IlC(this)"><!--
		IF a$required --><span class="required"><!-- END:IF
		-->{a$_caption_}<!--
		IF a$required --></span><!-- END:IF
		--></label><!--
	END:IF
END:SET


SET $INPUT

	IF a$required --><span class="required"><!-- END:IF

	SET $id -->{a$name}<!-- END:SET

	--><script type="text/javascript">/*<![CDATA[*/

	a={a$|htmlArgs|js};
	m={a$multiple|js};
	i={a$_firstItem|js};
	c={a$_firstCaption|js};

	//]]></script ><script type="text/javascript" src="{base:a$_src_}"></script><script type="text/javascript">/*<![CDATA[*/

	lE=gLE({a$name|js})
	jsSelectInit(lE,[<!-- LOOP a$_value -->{$VALUE|js},<!-- END:LOOP -->0])
	lE.gS=IgSS;
	lE.cS=function(){return IcES([0<!-- LOOP a$_elements -->,{$name|js},{$onempty|js},{$onerror|js}<!-- END:LOOP -->],this.form)};<!-- IF a$autofocus -->lE.focus()<!-- END:IF -->//]]></script ><!--

	SERVERSIDE
		--><noscript><input {a$|htmlArgs}></noscript><!--
	END:SERVERSIDE

	IF a$required --></span><!-- END:IF

END:SET


SET $ERROR
	IF a$_errormsg -->{a$_beforeError_|default:g$inputBeforeError}<span class="errormsg">{a$_errormsg}</span>{a$_afterError_|default:g$inputAfterError}<!-- END:IF
END:SET


-->{a$_format_|default:g$inputFormat|echo:$CAPTION:$INPUT:$ERROR}