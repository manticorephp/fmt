<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\NoAliasFunctionsFixer (priority 40, risky). A global call of a function alias of the configured sets is renamed to its master function
 * (`mktime()` / `gmmktime()` without arguments become `time()`). Text rule over the T_STRING of the call.
 */
final class NoAliasFunctions extends TextRule
{
    private const SETS = [
        '@internal' => [
            'diskfreespace' => 'disk_free_space',

            'dns_check_record' => 'checkdnsrr',
            'dns_get_mx' => 'getmxrr',

            'session_commit' => 'session_write_close',

            'stream_register_wrapper' => 'stream_wrapper_register',
            'set_file_buffer' => 'stream_set_write_buffer',
            'socket_set_blocking' => 'stream_set_blocking',
            'socket_get_status' => 'stream_get_meta_data',
            'socket_set_timeout' => 'stream_set_timeout',
            'socket_getopt' => 'socket_get_option',
            'socket_setopt' => 'socket_set_option',

            'chop' => 'rtrim',
            'close' => 'closedir',
            'doubleval' => 'floatval',
            'fputs' => 'fwrite',
            'get_required_files' => 'get_included_files',
            'ini_alter' => 'ini_set',
            'is_double' => 'is_float',
            'is_integer' => 'is_int',
            'is_long' => 'is_int',
            'is_real' => 'is_float',
            'is_writeable' => 'is_writable',
            'join' => 'implode',
            'key_exists' => 'array_key_exists',
            'magic_quotes_runtime' => 'set_magic_quotes_runtime',
            'pos' => 'current',
            'show_source' => 'highlight_file',
            'sizeof' => 'count',
            'strchr' => 'strstr',
            'user_error' => 'trigger_error',
        ],

        '@IMAP' => [
            'imap_create' => 'imap_createmailbox',
            'imap_fetchtext' => 'imap_body',
            'imap_header' => 'imap_headerinfo',
            'imap_listmailbox' => 'imap_list',
            'imap_listsubscribed' => 'imap_lsub',
            'imap_rename' => 'imap_renamemailbox',
            'imap_scan' => 'imap_listscan',
            'imap_scanmailbox' => 'imap_listscan',
        ],

        '@ldap' => [
            'ldap_close' => 'ldap_unbind',
            'ldap_modify' => 'ldap_mod_replace',
        ],

        '@mysqli' => [
            'mysqli_execute' => 'mysqli_stmt_execute',
            'mysqli_set_opt' => 'mysqli_options',
            'mysqli_escape_string' => 'mysqli_real_escape_string',
        ],

        '@pg' => [
            'pg_exec' => 'pg_query',
        ],

        '@oci' => [
            'oci_free_cursor' => 'oci_free_statement',
        ],

        '@odbc' => [
            'odbc_do' => 'odbc_exec',
            'odbc_field_precision' => 'odbc_field_len',
        ],

        '@mbreg' => [
            'mbereg' => 'mb_ereg',
            'mbereg_match' => 'mb_ereg_match',
            'mbereg_replace' => 'mb_ereg_replace',
            'mbereg_search' => 'mb_ereg_search',
            'mbereg_search_getpos' => 'mb_ereg_search_getpos',
            'mbereg_search_getregs' => 'mb_ereg_search_getregs',
            'mbereg_search_init' => 'mb_ereg_search_init',
            'mbereg_search_pos' => 'mb_ereg_search_pos',
            'mbereg_search_regs' => 'mb_ereg_search_regs',
            'mbereg_search_setpos' => 'mb_ereg_search_setpos',
            'mberegi' => 'mb_eregi',
            'mberegi_replace' => 'mb_eregi_replace',
            'mbregex_encoding' => 'mb_regex_encoding',
            'mbsplit' => 'mb_split',
        ],

        '@openssl' => [
            'openssl_get_publickey' => 'openssl_pkey_get_public',
            'openssl_get_privatekey' => 'openssl_pkey_get_private',
        ],

        '@sodium' => [
            'sodium_crypto_scalarmult_base' => 'sodium_crypto_box_publickey_from_secretkey',
        ],

        '@exif' => [
            'read_exif_data' => 'exif_read_data',
        ],

        '@ftp' => [
            'ftp_quit' => 'ftp_close',
        ],

        '@posix' => [
            'posix_errno' => 'posix_get_last_error',
        ],

        '@pcntl' => [
            'pcntl_errno' => 'pcntl_get_last_error',
        ],

        '@time' => [
            'mktime' => 'time',
            'gmmktime' => 'time',
        ],
    ];

    /** @var array<string, string> */
    private array $aliases = [];
    /** @var array<string, int> alias => the argument count it needs (@time) */
    private array $needArgs = [];
    private NativeFunctionCasing $calls;

    /** @param string[] $sets */
    public function __construct(array $sets)
    {
        $this->calls = new NativeFunctionCasing();
        $all = \in_array('@all', $sets, true);
        foreach (self::SETS as $name => $set) {
            if (!$all && !\in_array($name, $sets, true)) {
                continue;
            }
            foreach ($set as $from => $to) {
                $this->aliases[$from] = $to;
                if ($name === '@time') {
                    $this->needArgs[$from] = 0;
                } else {
                    unset($this->needArgs[$from]);
                }
            }
        }
    }

    public function name(): string
    {
        return 'no_alias_functions';
    }

    public function priority(): int
    {
        return 40;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_STRING];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_STRING || $i + 1 >= $s->n || $s->kind[$i + 1] !== 40 || $s->ct[$i + 1] !== 0) {
            return null;
        }
        $lower = \strtolower($s->text($i));
        if (!isset($this->aliases[$lower])) {
            return null;
        }
        if (!$this->calls->isGlobalCallAt($s, $i, $lower)) {
            return null;
        }
        if (isset($this->needArgs[$lower])) {
            $close = $s->pair[$i + 1];
            $count = $close === $i + 2 ? 0 : 1;
            for ($j = $i + 2; $j < $close; $j++) {
                $k = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if (\Fmt\TypeWalk::closer($k) >= 0 && $s->pair[$j] > $j) {
                    $j = $s->pair[$j];
                    continue;
                }
                if ($k === 44 && $j + 1 < $close) {
                    $count++;
                }
            }
            if ($count !== $this->needArgs[$lower]) {
                return null;
            }
        }
        return $this->aliases[$lower];
    }
}
