<?php

class pipe_rgb
{
	static function php($r, $g, $b)
	{
		$r = CIA::string($r) - 0;
		$g = CIA::string($g) - 0;
		$b = CIA::string($b) - 0;

		return sprintf('#%02x%02x%02x', $r, $g, $b);
	}

	static function js()
	{
		?>/*<script>*/

P$<?php echo substr(__CLASS__, 5)?> = function($r, $g, $b)
{
	$r = ($r/1 || 0).toString(16);
	$g = ($g/1 || 0).toString(16);
	$b = ($b/1 || 0).toString(16);

	if ($r.length < 2) $r = '0' + $r;
	if ($g.length < 2) $g = '0' + $g;
	if ($b.length < 2) $b = '0' + $b;

	return '#' + $r + $g + $b;
}

<?php	}
}