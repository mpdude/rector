<?php

declare(strict_types=1);

namespace Rector\ReadWrite\Contract;

use PhpParser\Node\Expr;

interface ReadNodeAnalyzerInterface
{
    public function supports(Expr $node): bool;

    public function isRead(Expr $node): bool;
}
