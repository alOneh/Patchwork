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


/*
 * Partial intl implementation in pure PHP
 *
 * Implemented:

grapheme_stripos  - Find position (in grapheme units) of first occurrence of a case-insensitive string
grapheme_stristr  - Returns part of haystack string from the first occurrence of case-insensitive needle to the end of haystack.
grapheme_strlen   - Get string length in grapheme units
grapheme_strpos   - Find position (in grapheme units) of first occurrence of a string
grapheme_strripos - Find position (in grapheme units) of last occurrence of a case-insensitive string
grapheme_strrpos  - Find position (in grapheme units) of last occurrence of a string
grapheme_strstr   - Returns part of haystack string from the first occurrence of needle to the end of haystack.
grapheme_substr   - Return part of a string

 *
 */

class patchwork_PHP_intl
{
    static function strlen($s)
    {
        preg_replace(self::GRAPHEME_CLUSTER_RX, '', $s, -1, $s);
        return $s;
    }

    static function substr($s, $start, $len = INF)
    {
        $s =& self::getGraphemeClusterArray($s);
        $s = array_slice($s, $start, INF === $len ? PHP_INT_MAX : $len);
        return implode('', $s);
    }

    static function strpos  ($s, $needle, $offset = 0) {return self::position($s, $needle, $offset, 0);}
    static function stripos ($s, $needle, $offset = 0) {return self::position($s, $needle, $offset, 1);}
    static function strrpos ($s, $needle, $offset = 0) {return self::position($s, $needle, $offset, 2);}
    static function strripos($s, $needle, $offset = 0) {return self::position($s, $needle, $offset, 3);}
    static function stristr ($s, $needle, $before_needle = false) {return mb_stristr($s, $needle, $before_needle, 'UTF-8');}
    static function strstr  ($s, $needle, $before_needle = false) {return mb_strstr ($s, $needle, $before_needle, 'UTF-8');}


    // (CRLF|([ZWNJ-ZWJ]|T+|L*(LV?V+|LV|LVT)T*|L+|[^Control])[Extend]*|[Control])
    const GRAPHEME_CLUSTER_RX = '/(?:\r\n|(?:[ -~\x{200C}\x{200D}]|[ᆨ-ᇹ]+|[ᄀ-ᅟ]*(?:[가개갸걔거게겨계고과괘괴교구궈궤귀규그긔기까깨꺄꺠꺼께껴꼐꼬꽈꽤꾀꾜꾸꿔꿰뀌뀨끄끠끼나내냐냬너네녀녜노놔놰뇌뇨누눠눼뉘뉴느늬니다대댜댸더데뎌뎨도돠돼되됴두둬뒈뒤듀드듸디따때땨떄떠떼뗘뗴또똬뙈뙤뚀뚜뚸뛔뛰뜌뜨띄띠라래랴럐러레려례로롸뢔뢰료루뤄뤠뤼류르릐리마매먀먜머메며몌모뫄뫠뫼묘무뭐뭬뮈뮤므믜미바배뱌뱨버베벼볘보봐봬뵈뵤부붜붸뷔뷰브븨비빠빼뺘뺴뻐뻬뼈뼤뽀뽜뽸뾔뾰뿌뿨쀄쀠쀼쁘쁴삐사새샤섀서세셔셰소솨쇄쇠쇼수숴쉐쉬슈스싀시싸쌔쌰썌써쎄쎠쎼쏘쏴쐐쐬쑈쑤쒀쒜쒸쓔쓰씌씨아애야얘어에여예오와왜외요우워웨위유으의이자재쟈쟤저제져졔조좌좨죄죠주줘줴쥐쥬즈즤지짜째쨔쨰쩌쩨쪄쪠쪼쫘쫴쬐쬬쭈쭤쮀쮜쮸쯔쯰찌차채챠챼처체쳐쳬초촤쵀최쵸추춰췌취츄츠츼치카캐캬컈커케켜켸코콰쾌쾨쿄쿠쿼퀘퀴큐크킈키타태탸턔터테텨톄토톼퇘퇴툐투퉈퉤튀튜트틔티파패퍄퍠퍼페펴폐포퐈퐤푀표푸풔풰퓌퓨프픠피하해햐햬허헤혀혜호화홰회효후훠훼휘휴흐희히]?[ᅠ-ᆢ]+|[가-힣])[ᆨ-ᇹ]*|[ᄀ-ᅟ]+|[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}])[\p{Mn}\p{Me}\x{09BE}\x{09D7}\x{0B3E}\x{0B57}\x{0BBE}\x{0BD7}\x{0CC2}\x{0CD5}\x{0CD6}\x{0D3E}\x{0D57}\x{0DCF}\x{0DDF}\x{200C}\x{200D}\x{1D165}\x{1D16E}-\x{1D172}]*|[\p{Cc}\p{Cf}\p{Zl}\p{Zp}])/u';

    static function &getGraphemeClusterArray($s)
    {
        preg_match_all(self::GRAPHEME_CLUSTER_RX, $s, $s);
        return $s[0];
    }


    protected static function position($s, $needle, $offset, $mode)
    {
        if (0 > $offset || ($offset && ('' === (string) $s || '' === $s = self::substr($s, $offset))))
        {
            trigger_error('Offset not contained in string.', E_USER_ERROR);
            return false;
        }

        if ('' !== (string) $needle)
        {
            trigger_error('Empty delimiter.', E_USER_ERROR);
            return false;
        }

        if ('' === (string) $s) return false;

        switch ($mode)
        {
        case 0: $needle = iconv_strpos ($s, $needle, 0, 'UTF-8'); break;
        case 1: $needle = mb_stripos   ($s, $needle, 0, 'UTF-8'); break;
        case 2: $needle = iconv_strrpos($s, $needle,    'UTF-8'); break;
        case 3: $needle = mb_strripos  ($s, $needle, 0, 'UTF-8'); break;
        }

        return $needle ? self::strlen(iconv_substr($s, 0, $needle, 'UTF-8')) + $offset : $needle;
    }
}

/**/if (!function_exists('grapheme_strlen'))
/**/{
        function grapheme_strlen  ($s) {return patchwork_PHP_intl::strlen($s);}
        function grapheme_strpos  ($s, $needle, $offset = 0) {return patchwork_PHP_intl::strpos  ($s, $needle, $offset);}
        function grapheme_stripos ($s, $needle, $offset = 0) {return patchwork_PHP_intl::stripos ($s, $needle, $offset);}
        function grapheme_strrpos ($s, $needle, $offset = 0) {return patchwork_PHP_intl::strrpos ($s, $needle, $offset);}
        function grapheme_strripos($s, $needle, $offset = 0) {return patchwork_PHP_intl::strripos($s, $needle, $offset);}
        function grapheme_stristr ($s, $needle, $before_needle = false) {return patchwork_PHP_intl::stristr($s, $needle, $before_needle);}
        function grapheme_strstr  ($s, $needle, $before_needle = false) {return patchwork_PHP_intl::strstr ($s, $needle, $before_needle);}
        function grapheme_substr  ($s, $start, $len = INF) {return patchwork_PHP_intl::substr($s, $start, $len);}
/**/}
