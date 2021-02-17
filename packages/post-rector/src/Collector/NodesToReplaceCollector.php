<?php

declare(strict_types=1);

namespace Rector\PostRector\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use Rector\PostRector\Contract\Collector\NodeCollectorInterface;

final class NodesToReplaceCollector implements NodeCollectorInterface
{
    /**
     * @var Node[][]
     */
    private $nodesToReplace = [];

    public function addReplaceNodeWithAnotherNode(Assign $node, Expr $replaceWith): void
    {
        $this->nodesToReplace[] = [$node, $replaceWith];
    }

    public function isActive(): bool
    {
        return $this->nodesToReplace !== [];
    }

    /**
     * @return Node[][]
     */
    public function getNodes(): array
    {
        return $this->nodesToReplace;
    }
}
