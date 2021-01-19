<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\Rector\Identical;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Rector\AbstractRector;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\TypeDeclaration\Tests\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector\FlipTypeControlToUseExclusiveTypeRectorTest
 */
final class FlipTypeControlToUseExclusiveTypeRector extends AbstractRector
{
    /**
     * @var PhpDocTagRemover
     */
    private $phpDocTagRemover;

    public function __construct(PhpDocTagRemover $phpDocTagRemover)
    {
        $this->phpDocTagRemover = $phpDocTagRemover;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flip type control to use exclusive type',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeClass
{
    public function __construct(array $values)
    {
        /** @var PhpDocInfo|null $phpDocInfo */
        $phpDocInfo = $functionLike->getAttribute(AttributeKey::PHP_DOC_INFO);
        if ($phpDocInfo === null) {
            return;
        }
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class SomeClass
{
    public function __construct(array $values)
    {
        $phpDocInfo = $functionLike->getAttribute(AttributeKey::PHP_DOC_INFO);
        if (! $phpDocInfo instanceof PhpDocInfo) {
            return;
        }
    }
}
CODE_SAMPLE
                ),

            ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Identical::class];
    }

    /**
     * @param Identical $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isNull($node->left) && ! $this->isNull($node->right)) {
            return null;
        }

        $variable = $this->isNull($node->left)
            ? $node->right
            : $node->left;

        $assign = $this->getVariableAssign($node, $variable);
        if (! $assign instanceof Assign) {
            return null;
        }

        $expression = $assign->getAttribute(AttributeKey::PARENT_NODE);
        if (! $expression instanceof Expression) {
            return null;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($expression);
        $type = $phpDocInfo->getVarType();

        if (! $type instanceof UnionType) {
            return null;
        }

        /** @var Type[] $types */
        $types = $this->getTypes($type);
        if ($this->skipNotNullOneOf($types)) {
            return null;
        }

        return $this->processConvertToExclusiveType($types, $variable, $phpDocInfo);
    }

    /**
     * @param Type[] $types
     */
    private function processConvertToExclusiveType(array $types, Expr $expr, PhpDocInfo $phpDocInfo): ?BooleanNot
    {
        $type = $types[0] instanceof NullType
            ? $types[1]
            : $types[0];

        if (! $type instanceof FullyQualifiedObjectType) {
            return null;
        }

        /** @var VarTagValueNode $tagValueNode */
        $tagValueNode = $phpDocInfo->getVarTagValueNode();
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $tagValueNode);

        return new BooleanNot(new Instanceof_($expr, new FullyQualified($type->getClassName())));
    }

    /**
     * @return Type[]
     */
    private function getTypes(UnionType $unionType): array
    {
        $types = $unionType->getTypes();
        if (count($types) > 2) {
            return [];
        }

        return $types;
    }

    private function getVariableAssign(Identical $identical, Expr $expr): ?Node
    {
        return $this->betterNodeFinder->findFirstPrevious($identical, function (Node $node) use ($expr): bool {
            if (! $node instanceof Assign) {
                return false;
            }

            return $this->areNodesEqual($node->var, $expr);
        });
    }

    /**
     * @param Type[] $types
     */
    private function skipNotNullOneOf(array $types): bool
    {
        if ($types === []) {
            return true;
        }

        if ($types[0] === $types[1]) {
            return true;
        }
        if ($types[0] instanceof NullType) {
            return false;
        }
        return ! $types[1] instanceof NullType;
    }
}