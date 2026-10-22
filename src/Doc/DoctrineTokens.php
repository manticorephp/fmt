<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/**
 * PhpCsFixer\Doctrine\Annotation\DocLexer + Tokens over a plain list of DoctrineToken: `createFromDocComment` splits a docblock into text (T_NONE) and the Doctrine
 * annotations in it (`@Name` and a balanced `( .. )`), `getAnnotationEnd` finds where an annotation ends.
 */
final class DoctrineTokens
{
    public const T_NONE = 1;
    public const T_INTEGER = 2;
    public const T_STRING = 3;
    public const T_FLOAT = 4;
    public const T_IDENTIFIER = 100;
    public const T_AT = 101;
    public const T_CLOSE_CURLY_BRACES = 102;
    public const T_CLOSE_PARENTHESIS = 103;
    public const T_COMMA = 104;
    public const T_EQUALS = 105;
    public const T_NAMESPACE_SEPARATOR = 107;
    public const T_OPEN_CURLY_BRACES = 108;
    public const T_OPEN_PARENTHESIS = 109;
    public const T_COLON = 112;
    public const T_MINUS = 113;

    private const REGEX = '/([a-z_\\\\][a-z0-9_\:\\\\]*[a-z_][a-z0-9_]*)|((?:[+-]?[0-9]+(?:[\.][0-9]+)*)(?:[eE][+-]?[0-9]+)?)|("(?:""|[^"])*+")|\s+|\*+|(.)/iu';

    private static function type(string &$value): int
    {
        if ($value[0] === '"') {
            $value = \str_replace('""', '"', \substr($value, 1, \strlen($value) - 2));
            return self::T_STRING;
        }
        switch ($value) {
            case '@':
                return self::T_AT;
            case ',':
                return self::T_COMMA;
            case '(':
                return self::T_OPEN_PARENTHESIS;
            case ')':
                return self::T_CLOSE_PARENTHESIS;
            case '{':
                return self::T_OPEN_CURLY_BRACES;
            case '}':
                return self::T_CLOSE_CURLY_BRACES;
            case '=':
                return self::T_EQUALS;
            case ':':
                return self::T_COLON;
            case '-':
                return self::T_MINUS;
            case '\\':
                return self::T_NAMESPACE_SEPARATOR;
        }
        if ($value[0] === '_' || $value[0] === '\\' || !Preg::match('/[^A-Za-z]/', $value[0])) {
            return self::T_IDENTIFIER;
        }
        if (\is_numeric($value)) {
            return \str_contains($value, '.') || \str_contains(\strtolower($value), 'e') ? self::T_FLOAT : self::T_INTEGER;
        }
        return self::T_NONE;
    }

    /**
     * DocLexer::scan (upstream splits the input with PREG_SPLIT_DELIM_CAPTURE | NO_EMPTY | OFFSET_CAPTURE).
     * @return DoctrineToken[]|null null when the pattern fails on the subject (invalid UTF-8: upstream's Preg throws)
     */
    private static function scan(string $input): ?array
    {
        $matches = \preg_split(self::REGEX, $input, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_OFFSET_CAPTURE);
        if ($matches === false) {
            return null;
        }
        $out = [];
        foreach ($matches as $match) {
            $first = (string) $match[0];
            $out[] = new DoctrineToken(self::type($first), $first, (int) $match[1]);
        }
        return $out;
    }

    /**
     * Tokens::createFromDocComment
     * @param string[] $ignoredTags
     * @return DoctrineToken[]|null null: the lexer failed
     */
    public static function fromDocComment(string $content, array $ignoredTags): array
    {
        $tokens = [];
        $ignoredTextPosition = 0;
        $currentPosition = 0;
        $token = null;
        $len = \strlen($content);
        while (true) {
            $at = \strpos($content, '@', $currentPosition);
            if ($at === false) {
                break;
            }
            if ($at !== 0 && !Preg::match('/\s/', $content[$at - 1])) {
                $currentPosition = $at + 1;
                continue;
            }
            $lexed = self::scan(\substr($content, $at));
            if ($lexed === null) {
                return null;
            }
            $peek = 0;
            /** @var DoctrineToken[] $scanned */
            $scanned = [];
            $index = 0;
            $use = 0;
            $scopes = 0;
            while ($peek < \count($lexed)) {
                $token = $lexed[$peek++];
                if ($index === 0 && !$token->isType(self::T_AT)) {
                    break;
                }
                if ($index === 1) {
                    if (!$token->isType(self::T_IDENTIFIER) || \in_array($token->content, $ignoredTags, true)) {
                        break;
                    }
                    $use = 2;
                }
                if ($index >= 2 && $scopes === 0 && !$token->isTypeOf(self::T_NONE, self::T_OPEN_PARENTHESIS)) {
                    break;
                }
                $scanned[] = $token;
                if ($token->isType(self::T_OPEN_PARENTHESIS)) {
                    ++$scopes;
                } elseif ($token->isType(self::T_CLOSE_PARENTHESIS)) {
                    if (--$scopes === 0) {
                        $use = \count($scanned);
                        break;
                    }
                }
                ++$index;
            }
            if ($scopes !== 0) {
                break;
            }
            if ($use !== 0) {
                $ignoredLength = $at - $ignoredTextPosition;
                if ($ignoredLength !== 0) {
                    $tokens[] = new DoctrineToken(self::T_NONE, \substr($content, $ignoredTextPosition, $ignoredLength));
                }
                $lastEnd = 0;
                for ($x = 0; $x < $use; $x++) {
                    $scannedToken = $scanned[$x];
                    $token = $scannedToken->isType(self::T_STRING)
                        ? new DoctrineToken($scannedToken->type, '"' . \str_replace('"', '""', $scannedToken->content) . '"', $scannedToken->position)
                        : $scannedToken;
                    $missing = $token->position - $lastEnd;
                    if ($missing > 0) {
                        $tokens[] = new DoctrineToken(self::T_NONE, \substr($content, $at + $lastEnd, $missing));
                    }
                    $tokens[] = new DoctrineToken($token->type, $token->content);
                    $lastEnd = $token->position + \strlen($token->content);
                }
                $currentPosition = $ignoredTextPosition = $at + $token->position + \strlen($token->content);
            } else {
                $currentPosition = $at + 1;
            }
        }
        if ($ignoredTextPosition < $len) {
            $tokens[] = new DoctrineToken(self::T_NONE, \substr($content, $ignoredTextPosition));
        }
        return $tokens;
    }

    /**
     * Tokens::getNextMeaningfulToken: the next token that is not plain text, null when there is none
     * @param DoctrineToken[] $tokens
     */
    public static function nextMeaningful(array $tokens, int $index): ?int
    {
        $n = \count($tokens);
        for ($j = $index + 1; $j >= 0 && $j < $n; $j++) {
            if (!$tokens[$j]->isType(self::T_NONE)) {
                return $j;
            }
        }
        return null;
    }

    /**
     * Tokens::getAnnotationEnd
     * @param DoctrineToken[] $tokens
     */
    public static function annotationEnd(array $tokens, int $index): ?int
    {
        $n = \count($tokens);
        $current = null;
        if ($index + 2 < $n) {
            if ($tokens[$index + 2]->isType(self::T_OPEN_PARENTHESIS)) {
                $current = $index + 2;
            } elseif ($index + 3 < $n && $tokens[$index + 2]->isType(self::T_NONE) && $tokens[$index + 3]->isType(self::T_OPEN_PARENTHESIS)
                && Preg::match('/^(\R\s*\*\s*)*\s*$/', $tokens[$index + 2]->content)) {
                $current = $index + 3;
            }
        }
        if ($current !== null) {
            $level = 0;
            for (; $current < $n; ++$current) {
                if ($tokens[$current]->isType(self::T_OPEN_PARENTHESIS)) {
                    ++$level;
                } elseif ($tokens[$current]->isType(self::T_CLOSE_PARENTHESIS)) {
                    --$level;
                }
                if ($level === 0) {
                    return $current;
                }
            }
            return null;
        }
        return $index + 1;
    }

    /** @param DoctrineToken[] $tokens */
    public static function code(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $t) {
            $out .= $t->content;
        }
        return $out;
    }
}
