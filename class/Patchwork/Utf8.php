<?php /***** vi: set encoding=utf-8 expandtab shiftwidth=4: ****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/lgpl.txt GNU/LGPL
 *
 *   This library is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU Lesser General Public
 *   License as published by the Free Software Foundation; either
 *   version 3 of the License, or (at your option) any later version.
 *
 ***************************************************************************/

namespace Patchwork;

use Normalizer;

/* UTF-8 Grapheme Cluster aware string manipulations.
 *
 * See also:
 * - http://phputf8.sf.net/ and its "see also" section
 * - http://annevankesteren.nl/2005/05/unicode
 * - http://www.unicode.org/reports/tr29/
 *
 */

class Utf8
{
    static function isUtf8($s)
    {
        return @iconv('UTF-8', 'UTF-8', $s) === (string) $s;
    }

    // Unicode to Code Page conversion using best fit mappings
    // See http://www.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/WindowsBestFit/

    static function bestFit($cp, $s, $placeholder = '')
    {
        if ('' === $s) return '';

        static $map = array();
        static $ulen_mask = array("\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4);

        $cp = (string) (int) $cp;
        $result = '9' === $cp[0] ? $s . $s : $s;

        if (isset($map[$cp])) $cp =& $map[$cp];
        else if (file_exists($i = patchworkPath('data/utf8/bestfit' . $cp . '.ser')))
        {
            $map[$cp] = unserialize(file_get_contents($i));
            $cp =& $map[$cp];
        }
        else
        {
            trigger_error('No "Best Fit" mapping found for given Code Page (' . $cp . ').');

            $cp = array();
        }

        $i = $j = 0;
        $len = strlen($s);

        while ($i < $len)
        {
            if ($s[$i] < "\x80") $uchr = $s[$i++];
            else
            {
                $ulen = $ulen_mask[$s[$i] & "\xF0"];
                $uchr = substr($s, $i, $ulen);
                $i += $ulen;
            }

            $uchr = isset($cp[$uchr]) ? $cp[$uchr] : $placeholder;

            isset($uchr[0]) && $result[$j++] = $uchr[0];
            isset($uchr[1]) && $result[$j++] = $uchr[1];
        }

        return substr($result, 0, $j);
    }


    protected static $commonCaseFold = array(
        array('µ','ſ',"\xCD\x85",'ς',"\xCF\x90","\xCF\x91","\xCF\x95","\xCF\x96","\xCF\xB0","\xCF\xB1","\xCF\xB5","\xE1\xBA\x9B","\xE1\xBE\xBE"),
        array('μ','s','ι',       'σ','β',       'θ',       'φ',       'π',       'κ',       'ρ',       'ε',       "\xE1\xB9\xA1",'ι'           )
    );

    // Unicode transformation for caseless matching
    // see http://unicode.org/reports/tr21/tr21-5.html

    static function strtocasefold($s, $full = true, $turkish = false)
    {
        $s = str_replace(self::$commonCaseFold[0], self::$commonCaseFold[1], $s);

        if ($turkish)
        {
            false !== strpos($s, 'I') && $s = str_replace('I', 'ı', $s);
            $full && false !== strpos($s, 'İ') && $s = str_replace('İ', 'i', $s);
        }

        if ($full)
        {
            static $fullCaseFold = false;
            $fullCaseFold || $fullCaseFold = unserialize(file_get_contents(patchworkPath('data/utf8/caseFolding_full.ser')));

            $s = str_replace($fullCaseFold[0], $fullCaseFold[1], $s);
        }

        return self::strtolower($s);
    }

    // Generic case sensitive collation support for self::strnatcmp()

    static function strtonatfold($s)
    {
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        return preg_replace('/\p{Mn}+/u', '', $s);
    }

    // Here is the quasi complete set of native PHP string functions that need UTF-8 awareness
    // Missing are printf-family functions and number_format

    static function strlen($s) {return grapheme_strlen($s);}
    static function substr($s, $start, $len = INF) {return INF === $len ? grapheme_substr($start, $len) : grapheme_substr($start, $len, $length);}
    static function strpos  ($s, $needle, $offset = 0) {return grapheme_strpos  ($s, $needle, $offset);}
    static function stripos ($s, $needle, $offset = 0) {return grapheme_stripos ($s, $needle, $offset);}
    static function strrpos ($s, $needle, $offset = 0) {return grapheme_strrpos ($s, $needle, $offset);}
    static function strripos($s, $needle, $offset = 0) {return grapheme_strripos($s, $needle, $offset);}
    static function strstr  ($s, $needle, $before_needle = false) {return grapheme_strstr ($s, $needle, $before_needle);}
    static function stristr ($s, $needle, $before_needle = false) {return grapheme_stristr($s, $needle, $before_needle);}
    static function strrchr ($s, $needle, $before_needle = false) {return mb_strrchr ($s, $needle, $before_needle, 'UTF-8');}
    static function strrichr($s, $needle, $before_needle = false) {return mb_strrichr($s, $needle, $before_needle, 'UTF-8');}

    static function strtolower($s, $form = /*<*/Normalizer::FORM_C/*>*/) {return Normalizer::isNormalized($s = mb_strtolower($s, 'UTF-8'), $form) ? $s : Normalizer::normalize($s, $form);}
    static function strtoupper($s, $form = /*<*/Normalizer::FORM_C/*>*/) {return Normalizer::isNormalized($s = mb_strtoupper($s, 'UTF-8'), $form) ? $s : Normalizer::normalize($s, $form);}

    static function htmlentities    ($s, $quote_style = ENT_COMPAT) {return htmlentities    ($s, $quote_style, 'UTF-8');}
    static function htmlspecialchars($s, $quote_style = ENT_COMPAT) {return htmlspecialchars($s, $quote_style, 'UTF-8');}

    static function wordwrap($s, $width = 75, $break = "\n", $cut = false)
    {
        // This implementation could be extended
        // to handle unicode word boundaries,
        // but that's enough work for today.

        $width = (int) $width;
        $s = explode($break, $s);

        $iLen = count($s);
        $result = array();
        $line = '';
        $lineLen = 0;

        for ($i = 0; $i < $iLen; ++$i)
        {
            $words = explode(' ', $s[$i]);
            $line && $result[] = $line;
            $line = $words[0];
            $jLen = count($words);

            for ($j = 1; $j < $jLen; ++$j)
            {
                $w = $words[$j];
                $wLen = grapheme_strlen($w);

                if ($lineLen + $wLen < $width)
                {
                    $line .= ' ' . $w;
                    $lineLen += $wLen + 1;
                }
                else
                {
                    $result[] = $line;
                    $line = '';
                    $lineLen = 0;

                    if ($cut && $wLen > $width)
                    {
                        $w = self::getGraphemeClusters($w);

                        do
                        {
                            $result[] = implode('', array_slice($w, 0, $width));
                            $line = implode('', $w = array_slice($w, $width));
                            $lineLen = $wLen -= $width;
                        }
                        while ($wLen > $width);

                        $w = implode('', $w);
                    }

                    if ($wLen)
                    {
                        $line = $w;
                        $lineLen = $wLen;
                    }
                }
            }
        }

        $line && $result[] = $line;

        return implode($break, $result);
    }

    static function chr($c)
    {
        $c %= 0x200000;

        return $c < 0x80    ? chr($c) : (
               $c < 0x800   ? chr(0xC0 | $c>> 6) . chr(0x80 | $c     & 0x3F) : (
               $c < 0x10000 ? chr(0xE0 | $c>>12) . chr(0x80 | $c>> 6 & 0x3F) . chr(0x80 | $c    & 0x3F) : (
                              chr(0xF0 | $c>>18) . chr(0x80 | $c>>12 & 0x3F) . chr(0x80 | $c>>6 & 0x3F) . chr(0x80 | $c & 0x3F)
        )));
    }

    static function count_chars($s, $mode = 1)
    {
        if (1 != $mode && 3 != $mode) trigger_error(__METHOD__ . '(): allowed $mode are 1 or 3', E_USER_ERROR);
        $s = self::getGraphemeClusters($s);
        $s = array_count_values($s);
        return 1 == $mode ? $s[0] : implode('', $s[0]);
    }

    static function ltrim($s, $charlist = INF)
    {
        $charlist = INF === $charlist ? '\s' : self::rxClass($charlist);
        return preg_replace("/^{$charlist}+/u", '', $s);
    }

    static function ord($s)
    {
        $s = unpack('C*', substr($s, 0, 6));
        $a = $s ? $s[1] : 0;

        return 240 <= $a && $a <= 255 ? (($a-240) << 18) + (($s[2]-128) << 12) + (($s[3]-128) << 6) + $s[4]-128 : (
               224 <= $a && $a <= 239 ? (($a-224) << 12) + (($s[2]-128) <<  6) +   $s[3]-128 : (
               192 <= $a && $a <= 223 ? (($a-192) <<  6) +   $s[2]-128 : (
               $a)));
    }

    static function rtrim($s, $charlist = INF)
    {
        $charlist = INF === $charlist ? '\s' : self::rxClass($charlist);
        return preg_replace("/{$charlist}+$/u", '', $s);
    }

    static function trim($s, $charlist = INF) {return self::rtrim(self::ltrim($s, $charlist), $charlist);}

    static function html_entity_decode($s, $quote_style = ENT_COMPAT)
    {
        static $map = array(
            array('&QUOT;','&LT;','&AMP;','&TRADE;','&COPY;','&GT;','&REG;','&apos;'),
            array('&quot;','&lt;','&amp;','&trade;','&copy;','&gt;','&reg;','&#039;')
        );

        return html_entity_decode(str_replace($map[0], $map[1], $s), $quote_style, 'UTF-8');
    }

    static function get_html_translation_table($table = HTML_SPECIALCHARS, $quote_style = ENT_COMPAT)
    {
        if (HTML_ENTITIES === $table)
        {
            static $entities;
            isset($entities) || $entities = unserialize(file_get_contents(patchworkPath('data/utf8/htmlentities.ser')));
            return $entities + get_html_translation_table(HTML_SPECIALCHARS, $quote_style);
        }
        else return get_html_translation_table($table, $quote_style);
    }

    static function str_ireplace($search, $replace, $subject, &$count = null)
    {
        $subject = preg_replace('/' . preg_quote($search, '/') . '/ui', $replace, $subject, -1, $replace);
        $count = $replace;
        return $subject;
    }

    static function str_pad($s, $len, $pad = ' ', $type = STR_PAD_RIGHT)
    {
        $slen = grapheme_strlen($s);
        if ($len <= $slen) return $s;

        $padlen = grapheme_strlen($pad);
        $freelen = $len - $slen;
        $len = $freelen % $padlen;

        if (STR_PAD_RIGHT === $type) return $s . str_repeat($pad, $freelen / $padlen) . ($len ? grapheme_substr($pad, 0, $len) : '');
        if (STR_PAD_LEFT  === $type) return      str_repeat($pad, $freelen / $padlen) . ($len ? grapheme_substr($pad, 0, $len) : '') . $s;

        if (STR_PAD_BOTH === $type)
        {
            $freelen /= 2;

            $type = ceil($freelen);
            $len = $type % $padlen;
            $s .= str_repeat($pad, $type / $padlen) . ($len ? grapheme_substr($pad, 0, $len) : '');

            $type = floor($freelen);
            $len = $type % $padlen;
            return str_repeat($pad, $type / $padlen) . ($len ? grapheme_substr($pad, 0, $len) : '') . $s;
        }

        trigger_error(__METHOD__ . '(): Padding type has to be STR_PAD_LEFT, STR_PAD_RIGHT, or STR_PAD_BOTH.');
    }

    static function str_shuffle($s)
    {
        $s = self::getGraphemeClusters($s);
        shuffle($s);
        return implode('', $s);
    }

    static function str_split($s, $len = 1)
    {
        $len = (int) $len;
        if ($len < 1) return str_split($s, $len);

        $s = self::getGraphemeClusters($s);
        if (1 === $len) return $s;

        $a = array();
        $j = -1;

        foreach ($s as $i => $s)
        {
            if ($i % $len) $a[$j] .= $s;
            else $a[++$j] = $s;
        }

        return $a;
    }

    static function str_word_count($s, $format = 0, $charlist = '')
    {
        $charlist = self::rxClass($charlist, '\pL');
        $s = preg_split("/({$charlist}+(?:[\p{Pd}’']{$charlist}+)*)/u", $s, -1, PREG_SPLIT_DELIM_CAPTURE);

        $charlist = array();
        $len = count($s);

        if (1 == $format) for ($i = 1; $i < $len; $i+=2) $charlist[] = $s[$i];
        else if (2 == $format)
        {
            $offset = grapheme_strlen($s[0]);
            for ($i = 1; $i < $len; $i+=2)
            {
                $charlist[$offset] = $s[$i];
                $offset += grapheme_strlen($s[$i]) + grapheme_strlen($s[$i+1]);
            }
        }
        else $charlist = ($len - 1) / 2;

        return $charlist;
    }

    static function strcmp       ($a, $b) {return (string) $a === (string) $b ? 0 : strcmp(Normalizer::normalize($a, Normalizer::FORM_D), Normalizer::normalize($b, Normalizer::FORM_D));}
    static function strnatcmp    ($a, $b) {return (string) $a === (string) $b ? 0 : strnatcmp(self::strtonatfold($a), self::strtonatfold($b));}
    static function strcasecmp   ($a, $b) {return self::strcmp   (self::strtocasefold($a), self::strtocasefold($b));}
    static function strnatcasecmp($a, $b) {return self::strnatcmp(self::strtocasefold($a), self::strtocasefold($b));}
    static function strncasecmp  ($a, $b, $len) {return self::strncmp(self::strtocasefold($a), self::strtocasefold($b), $len);}
    static function strncmp      ($a, $b, $len) {return self::strcmp(grapheme_substr($a, 0, $len), grapheme_substr($b, 0, $len));}

    static function strcspn($s, $charlist, $start = 0, $len = INF)
    {
        if ('' === (string) $mask) return null;
        if ($start || INF !== $len) $s = grapheme_substr($s, $start, $len);

        return preg_match('/^(.*?)' . self::rxClass($mask) . '/us', $s, $len) ? grapheme_strlen($len[1]) : grapheme_strlen($s);
    }

    static function strpbrk($s, $charlist)
    {
        return preg_match('/' . self::rxClass($charlist) . '.*/us', $s, $s) ? $s[0] : false;
    }

    static function strrev($s)
    {
        $s = self::getGraphemeClusters($s);
        return implode('', array_reverse($s));
    }

    static function strspn($s, $mask, $start = INF, $len = INF)
    {
        if (INF !== $start || INF !== $len) $s = grapheme_substr($s, $start, $len);
        return preg_match('/^' . self::rxClass($mask) . '+/u', $s, $s) ? grapheme_strlen($s[0]) : 0;
    }

    static function strtr($s, $from, $to = INF)
    {
        if (INF !== $to)
        {
            $from = self::getGraphemeClusters($from);
            $to   = self::getGraphemeClusters($to);

            $a = count($from);
            $b = count($to);

                 if ($a > $b) $from = array_slice($from, 0, $b);
            else if ($a < $b) $to   = array_slice($to  , 0, $a);

            $from = array_combine($from, $to);
        }

        return strtr($s, $from);
    }

    static function substr_compare($a, $b, $offset, $len = INF, $i = 0)
    {
        $a = grapheme_substr($a, $offset, $len);
        return $i ? self::strcasecmp($a, $b) : self::strcmp($a, $b);
    }

    static function substr_count($s, $needle, $offset = 0, $len = INF)
    {
        return substr_count(grapheme_substr($s, $offset, $len), $needle);
    }

    static function substr_replace($s, $replace, $start, $len = INF)
    {
        $s       = self::getGraphemeClusters($s);
        $replace = self::getGraphemeClusters($replace);

        if (INF === $len) $len = count($s);

        array_splice($s, $start, $len, $replace);

        return implode('', $s);
    }

    static function ucfirst($s)
    {
        $c = iconv_substr($s, 0, 1, 'UTF-8');
        return self::ucwords($c) . substr($s, strlen($c));
    }

    static function ucwords($s)
    {
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    static function getGraphemeClusters($s)
    {
        preg_match_all(self::GRAPHEME_CLUSTER_RX, $s, $s);
        return $s[0];
    }


    protected static function rxClass($s, $class = '')
    {
        $class = array($class);

        foreach (self::getGraphemeClusters($s) as $s)
        {
            if ('-' === $s) $class[0] = '-' . $class[0];
            else if (!isset($s[2])) $class[0] .= preg_quote($s, '/');
            else if (1 === iconv_strlen($s, 'UTF-8')) $class[0] .= $s;
            else $class[] = $s;
        }

        $class[0] = '[' . $class[0] . ']';

        return 1 === count($class) ? $class[0] : ('(?:' . implode('|', $class) . ')');
    }

    // (CRLF|([ZWNJ-ZWJ]|T+|L*(LV?V+|LV|LVT)T*|L+|[^Control])[Extend]*|[Control])
    const GRAPHEME_CLUSTER_RX = '/(?:\r\n|(?:[ -~\x{200C}\x{200D}]|[ᆨ-ᇹ]+|[ᄀ-ᅟ]*(?:[가개갸걔거게겨계고과괘괴교구궈궤귀규그긔기까깨꺄꺠꺼께껴꼐꼬꽈꽤꾀꾜꾸꿔꿰뀌뀨끄끠끼나내냐냬너네녀녜노놔놰뇌뇨누눠눼뉘뉴느늬니다대댜댸더데뎌뎨도돠돼되됴두둬뒈뒤듀드듸디따때땨떄떠떼뗘뗴또똬뙈뙤뚀뚜뚸뛔뛰뜌뜨띄띠라래랴럐러레려례로롸뢔뢰료루뤄뤠뤼류르릐리마매먀먜머메며몌모뫄뫠뫼묘무뭐뭬뮈뮤므믜미바배뱌뱨버베벼볘보봐봬뵈뵤부붜붸뷔뷰브븨비빠빼뺘뺴뻐뻬뼈뼤뽀뽜뽸뾔뾰뿌뿨쀄쀠쀼쁘쁴삐사새샤섀서세셔셰소솨쇄쇠쇼수숴쉐쉬슈스싀시싸쌔쌰썌써쎄쎠쎼쏘쏴쐐쐬쑈쑤쒀쒜쒸쓔쓰씌씨아애야얘어에여예오와왜외요우워웨위유으의이자재쟈쟤저제져졔조좌좨죄죠주줘줴쥐쥬즈즤지짜째쨔쨰쩌쩨쪄쪠쪼쫘쫴쬐쬬쭈쭤쮀쮜쮸쯔쯰찌차채챠챼처체쳐쳬초촤쵀최쵸추춰췌취츄츠츼치카캐캬컈커케켜켸코콰쾌쾨쿄쿠쿼퀘퀴큐크킈키타태탸턔터테텨톄토톼퇘퇴툐투퉈퉤튀튜트틔티파패퍄퍠퍼페펴폐포퐈퐤푀표푸풔풰퓌퓨프픠피하해햐햬허헤혀혜호화홰회효후훠훼휘휴흐희히]?[ᅠ-ᆢ]+|[가-힣])[ᆨ-ᇹ]*|[ᄀ-ᅟ]+|[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}])[\p{Mn}\p{Me}\x{09BE}\x{09D7}\x{0B3E}\x{0B57}\x{0BBE}\x{0BD7}\x{0CC2}\x{0CD5}\x{0CD6}\x{0D3E}\x{0D57}\x{0DCF}\x{0DDF}\x{200C}\x{200D}\x{1D165}\x{1D16E}-\x{1D172}]*|[\p{Cc}\p{Cf}\p{Zl}\p{Zp}])/u';
}
