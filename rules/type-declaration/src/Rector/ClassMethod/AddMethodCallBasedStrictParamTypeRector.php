<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\Core\Rector\AbstractRector;
use Rector\NodeCollector\ValueObject\ArrayCallable;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\PHPStan\Type\TypeFactory;
use Rector\NodeTypeResolver\PHPStan\TypeComparator;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @sponsor Thanks https://spaceflow.io/ for sponsoring this rule - visit them on https://github.com/SpaceFlow-app
 *
 * @see \Rector\TypeDeclaration\Tests\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector\AddMethodCallBasedStrictParamTypeRectorTest
 */
final class AddMethodCallBasedStrictParamTypeRector extends AbstractRector
{
    /**
     * @var TypeFactory
     */
    private $typeFactory;

    /**
     * @var TypeComparator
     */
    private $typeComparator;

    public function __construct(TypeFactory $typeFactory, TypeComparator $typeComparator)
    {
        $this->typeFactory = $typeFactory;
        $this->typeComparator = $typeComparator;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change param type to strict type of passed expression', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function getById($id)
    {
    }
}

class CallerClass
{
    public function run(SomeClass $someClass)
    {
        $someClass->getById($this->getId());
    }

    public function getId(): int
    {
        return 1000;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function getById(int $id)
    {
    }
}

class CallerClass
{
    public function run(SomeClass $someClass)
    {
        $someClass->getById($this->getId());
    }

    public function getId(): int
    {
        return 1000;
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
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $classMethodCalls = $this->nodeRepository->findCallsByClassMethod($node);
        $classParameterTypes = $this->getCallTypesByPosition($classMethodCalls);

        foreach ($classParameterTypes as $position => $argumentStaticType) {
            if ($this->shouldSkipArgumentStaticType($node, $argumentStaticType, $position)) {
                continue;
            }

            $phpParserTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($argumentStaticType);
            if ($phpParserTypeNode === null) {
                continue;
            }

            // update parameter
            $node->params[$position]->type = $phpParserTypeNode;
        }

        return $node;
    }

    /**
     * @param MethodCall[]|StaticCall[]|ArrayCallable[] $calls
     * @return Type[]
     */
    private function getCallTypesByPosition(array $calls): array
    {
        $staticTypesByArgumentPosition = [];
        foreach ($calls as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall) {
                continue;
            }

            foreach ($call->args as $position => $arg) {
                $argValueType = $this->getStaticType($arg->value);

                // 1. is defined in param?
                $classMethod = $arg->getAttribute(AttributeKey::METHOD_NODE);
                if ($arg->value instanceof Variable) {
                    $argumentVariableName = $this->nodeNameResolver->getName($arg->value);
                    if ($classMethod instanceof ClassMethod) {
                        foreach ($classMethod->getParams() as $param) {
                            if (! $this->nodeNameResolver->isName($param->var, $argumentVariableName)) {
                                continue;
                            }

                            if ($param->type === null) {
                                // docblock defined, remove it
                                $argValueType = new MixedType();
                                continue;
                            }

                            $paramType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($param->type);
                            if ($this->typeComparator->areTypesEqual($paramType, $argValueType)) {
                                continue;
                                // type is matchign! keep it
                            }
                            // docblock defined, remove it
                            $argValueType = new MixedType();
                        }
                    }
                }

                $staticTypesByArgumentPosition[$position][] = $argValueType;
            }
        }

        // unite to single type
        $staticTypeByArgumentPosition = [];
        foreach ($staticTypesByArgumentPosition as $position => $staticTypes) {
            $staticTypeByArgumentPosition[$position] = $this->typeFactory->createMixedPassedOrUnionType($staticTypes);
        }

        return $staticTypeByArgumentPosition;
    }

    private function shouldSkipArgumentStaticType(
        ClassMethod $classMethod,
        Type $argumentStaticType,
        int $position
    ): bool {
        if ($argumentStaticType instanceof MixedType) {
            return true;
        }

        if (! isset($classMethod->params[$position])) {
            return true;
        }

        $parameter = $classMethod->params[$position];
        if ($parameter->type === null) {
            return false;
        }

        $parameterStaticType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($parameter->type);
        // already completed → skip
        return $parameterStaticType->equals($argumentStaticType);
    }
}
