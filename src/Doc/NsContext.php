<?php

declare(strict_types=1);

namespace Fmt\Doc;

/** The namespace and the `use` imports a docblock is read in (upstream's NamespaceAnalysis + NamespaceUseAnalysis list). */
final class NsContext
{
    /**
     * @param string[] $short short names of the class imports, in order
     * @param string[] $full their full names
     */
    public function __construct(
        public string $ns = '',
        public array $short = [],
        public array $full = [],
    ) {
    }
}
