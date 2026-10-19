<?php

declare(strict_types=1);

namespace Fmt;

/** The report of a run in upstream's formats: txt, json, checkstyle, junit, gitlab. Files are listed in path order. */
final class Reporter
{
    public const FORMATS = ['txt', 'json', 'checkstyle', 'junit', 'gitlab'];

    /**
     * @param Outcome[] $changed
     */
    public static function render(string $format, array $changed, bool $dry, bool $withFixers, int $filesCount, float $seconds, float $mb): string
    {
        if ($format === 'json') {
            return self::json($changed, $withFixers, $seconds, $mb);
        }
        if ($format === 'checkstyle') {
            return self::checkstyle($changed);
        }
        if ($format === 'junit') {
            return self::junit($changed, $withFixers, $seconds);
        }
        if ($format === 'gitlab') {
            return self::gitlab($changed);
        }
        return self::txt($changed, $dry, $withFixers, $filesCount, $seconds, $mb);
    }

    /** @param Outcome[] $changed */
    private static function txt(array $changed, bool $dry, bool $withFixers, int $filesCount, float $seconds, float $mb): string
    {
        $out = '';
        $i = 0;
        foreach ($changed as $o) {
            $i++;
            $out .= \sprintf('%4d) %s', $i, $o->path);
            if ($withFixers) {
                $out .= ' (' . \implode(', ', $o->applied) . ')';
            }
            if ($o->diff !== '') {
                $out .= "\n      ---------- begin diff ----------\n" . \rtrim($o->diff, "\n") . "\n      ----------- end diff -----------\n";
            }
            $out .= "\n";
        }
        return $out . "\n" . \sprintf("%s %d of %d %s in %.3f seconds, %.2f MB memory used\n", $dry ? 'Found' : 'Fixed', $i, $filesCount,
            $dry ? 'files that can be fixed' : 'files', $seconds, $mb);
    }

    /** @param Outcome[] $changed */
    private static function json(array $changed, bool $withFixers, float $seconds, float $mb): string
    {
        $files = [];
        foreach ($changed as $o) {
            $f = ['name' => $o->path];
            if ($withFixers) {
                $f['appliedFixers'] = $o->applied;
            }
            if ($o->diff !== '') {
                $f['diff'] = $o->diff;
            }
            $files[] = $f;
        }
        return \json_encode(['about' => Cli::ABOUT, 'files' => $files, 'time' => ['total' => \round($seconds, 3)], 'memory' => \round($mb, 3)],
            \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    }

    private static function x(string $s): string
    {
        return \htmlspecialchars($s, \ENT_XML1 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param Outcome[] $changed */
    private static function checkstyle(array $changed): string
    {
        $o = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<checkstyle version=\"" . self::x(Cli::ABOUT) . "\">\n";
        foreach ($changed as $f) {
            $o .= '  <file name="' . self::x($f->path) . "\">\n";
            foreach ($f->applied as $a) {
                $o .= '    <error severity="warning" source="PHP-CS-Fixer.' . self::x($a) . '" message="Found violation(s) of type: ' . self::x($a) . "\"/>\n";
            }
            $o .= "  </file>\n";
        }
        return $o . "</checkstyle>\n";
    }

    /** @param Outcome[] $changed */
    private static function junit(array $changed, bool $withFixers, float $seconds): string
    {
        $n = \count($changed);
        $assertions = 0;
        $cases = '';
        foreach ($changed as $f) {
            $assertions += \count($f->applied);
            $name = \str_replace('.', '_DOT_', (string) \preg_replace('@\.php$@', '', $f->path));
            $body = "Wrong code style\n";
            if ($withFixers) {
                $body = "applied fixers:\n---------------\n";
                foreach ($f->applied as $a) {
                    $body .= '* ' . $a . "\n";
                }
            }
            if ($f->diff !== '') {
                $body .= "\nDiff:\n---------------\n\n" . $f->diff;
            }
            $cases .= '    <testcase name="' . self::x($name) . '" file="' . self::x($f->path) . '" assertions="' . \count($f->applied) . "\">\n"
                . '      <failure type="code_style"><![CDATA[' . \str_replace(']]>', ']]]]><![CDATA[>', \trim($body)) . "]]></failure>\n    </testcase>\n";
        }
        $head = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<testsuites>\n  <testsuite name=\"PHP CS Fixer\"";
        if ($n === 0) {
            $head .= ' tests="1" assertions="1" failures="0" errors="0"';
            $cases = "    <testcase name=\"All OK\" assertions=\"1\"/>\n";
        } else {
            $head .= ' tests="' . $n . '" assertions="' . $assertions . '" failures="' . $assertions . '" errors="0"';
        }
        if ($seconds > 0) {
            $head .= \sprintf(' time="%.3f"', $seconds);
        }
        return $head . ">\n    <properties>\n      <property name=\"about\" value=\"" . self::x(Cli::ABOUT) . "\"/>\n    </properties>\n" . $cases . "  </testsuite>\n</testsuites>\n";
    }

    /** @param Outcome[] $changed */
    private static function gitlab(array $changed): string
    {
        $rep = [];
        foreach ($changed as $f) {
            $line = 0;
            if (\preg_match('/^@@ -(\d+)/m', $f->diff, $m) === 1) {
                $line = (int) $m[1];
            }
            foreach ($f->applied as $a) {
                $rep[] = ['check_name' => 'PHP-CS-Fixer.' . $a, 'description' => $a, 'content' => ['body' => Cli::ABOUT],
                    'categories' => ['Style'], 'fingerprint' => \md5($f->path . $a), 'severity' => 'minor',
                    'location' => ['path' => $f->path, 'lines' => ['begin' => $line, 'end' => $line]]];
            }
        }
        return \json_encode($rep, \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    }
}
