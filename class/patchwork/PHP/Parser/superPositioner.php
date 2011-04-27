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


class patchwork_PHP_Parser_superPositioner extends patchwork_PHP_Parser
{
    protected

    $level,
    $topClass,
    $callbacks = array(
        'tagSelfParent'  => array(T_USE_CLASS, T_TYPE_HINT),
        'tagClass'       => array(T_CLASS, T_INTERFACE),
        'tagClassName'   => T_NAME_CLASS,
        'tagPrivate'     => T_PRIVATE,
        'tagRequire'     => array(T_REQUIRE_ONCE, T_INCLUDE_ONCE, T_REQUIRE, T_INCLUDE),
        'tagSpecialFunc' => T_USE_FUNCTION,
    ),
    $dependencies = array('stringInfo' => 'nsPrefix', 'classInfo' => array('class', 'namespace', 'nsResolved'), 'constantExpression' => 'expressionValue');


    function __construct(parent $parent, $level, $topClass)
    {
        if (0 <= $level) unset($this->callbacks['tagRequire']);

        parent::__construct($parent);
        $this->level    = $level;
        $this->topClass = $topClass;
    }

    protected function tagSelfParent(&$token)
    {
        switch ($token[1])
        {
        case 'self':   if (empty($this->class->name   )) return; $c = $this->class->nsName;  break;
        case 'parent': if (empty($this->class->extends)) return; $c = $this->class->extends; break;
        }

        if (empty($c) || $this->nsPrefix) return;
        $this->unshiftTokens(array(T_STRING, $c));
        return $this->namespace && $this->unshiftTokens(array(T_NS_SEPARATOR, '\\'));
    }

    protected function tagClass(&$token)
    {
        $this->register(array('tagClassOpen' => T_SCOPE_OPEN));

        if ($this->class->isFinal)
        {
            $a =& $this->types;
            end($a);
            $this->texts[key($a)] = '';
            unset($a[key($a)]);
        }
    }

    protected function tagClassName(&$token)
    {
        $this->class->suffix = '__' . (0 <= $this->level ? $this->level : '00');
        $token[1] .= $this->class->suffix;
        0 <= $this->level && $this->register(array('tagExtendsSelf' => T_USE_CLASS));
        $this->class->isTop = $this->topClass && 0 === strcasecmp(strtr($this->topClass, '\\', '_'), strtr($this->class->nsName, '\\', '_'));
    }

    protected function tagExtendsSelf(&$token)
    {
        if (0 === strcasecmp('_' . strtr($this->class->nsName, '\\', '_'), strtr($this->nsResolved, '\\', '_')))
        {
            $this->class->extendsSelf = true;
            $this->class->extends = $this->class->nsName . '__' . ($this->level ? $this->level - 1 : '00');

            $this->dependencies['stringInfo']->removeNsPrefix();

            $this->unshiftTokens(array(T_STRING, $this->class->extends));
            return $this->namespace && $this->unshiftTokens(array(T_NS_SEPARATOR, '\\'));
        }
    }

    protected function tagClassOpen(&$token)
    {
        $this->unregister(array(
            'tagExtendsSelf' => T_USE_CLASS,
            __FUNCTION__     => T_SCOPE_OPEN,
        ));
        $this->register(array('tagClassClose' => T_SCOPE_CLOSE));
    }

    protected function tagPrivate(&$token)
    {
        // "private static" methods or properties are problematic when considering class superposition.
        // To work around this, we change them to "protected static", and warn about it
        // (except for files in the include path). Side effects exist but should be rare.

        // Look backward and forward for the "static" keyword
        if (T_STATIC !== $this->lastType)
        {
            $t = $this->getNextToken();

            if (T_STATIC !== $t[0]) return;
        }

        $token = array(T_PROTECTED, 'protected');

        if (0 <= $this->level)
        {
            $this->setError("Private statics do not work with class superposition, please use protected statics instead");
        }

        return false;
    }

    protected function tagClassClose(&$token)
    {
        $c = $this->class;
        $a = strtolower(strtr($c->nsName, '\\', '_'));

        if ($this->namespace && function_exists('class_alias'))
        {
            $token[1] .= "\\class_alias('{$c->nsName}{$c->suffix}','{$a}{$c->suffix}');";
        }

        if ($c->isFinal || $c->isTop)
        {
            $token[1] = "}"
                . ($c->isFinal ? 'final' : ($c->isAbstract ? 'abstract' : ''))
                . " {$c->type} {$c->name} extends {$c->name}{$c->suffix} {" . $token[1]
                . "\$GLOBALS['c\x9D']['{$a}']=1;";

            $this->namespace
                && function_exists('class_alias')
                && $token[1] .= "\\class_alias('{$c->nsName}','{$a}');";
        }

        if ($c->isAbstract)
        {
            $token[1] .= "\$GLOBALS['_patchwork_abstract']['{$a}{$c->suffix}']=1;";
        }
    }

    protected function tagRequire(&$token)
    {
        // Every require|include inside files in the include_path
        // is preprocessed thanks to patchworkProcessedPath().

        $token['no-autoload-marker'] = true;

        if (!DEBUG && TURBO && $this->dependencies['constantExpression']->nextExpressionIsConstant())
        {
            $a = patchworkProcessedPath($this->expressionValue, true);
            $token =& $this->getNextToken();

            $token[1] = false === $a
                ? " patchworkProcessedPath({$token[1]})"
                : (' ' . self::export($a) . str_repeat("\n", substr_count($token[1], "\n")));
        }
        else
        {
            $this->unshiftTokens(
                $this->namespace ? array(T_NS_SEPARATOR, '\\') : array(T_WHITESPACE, ' '),
                array(T_STRING, 'patchworkProcessedPath'), '('
            );

            new patchwork_PHP_Parser_closeBracket($this);
        }
    }

    protected function tagSpecialFunc(&$token)
    {
        switch (strtolower($this->nsResolved))
        {
        case '\patchworkpath':
            // Append its fourth arg to patchworkPath()
            new patchwork_PHP_Parser_bracket_patchworkPath($this, $this->level);
            break;

        case '\class_exists':
        case '\interface_exists':
            // For files in the include_path, always set the 2nd arg of class|interface_exists() to true
            if (0 <= $this->level) return;
            new patchwork_PHP_Parser_bracket_classExists($this);
            break;
        }
    }
}
