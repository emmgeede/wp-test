<?php

namespace ACPT\Utils\PHP\PHPEval;

use PhpParser\Node;

class SafeInterpreter
{
    private array $variables = [];

    private int $maxLoopIterations = 10000;

    public function execute(array $ast): ?string
    {
        foreach ($ast as $node) {
            $result = $this->evaluateNode($node);
            if (is_array($result) && ($result['__return__'] ?? false) === true) {
                return $result['value'] ?? null;
            }
        }

        return $this->variables['result'] ?? null;
    }

    /**
     * @param Node[] $stmts
     * @return array|null Returns ['__return__' => true, 'value' => mixed] when a return is hit.
     */
    private function executeStatements(array $stmts): ?array
    {
        foreach ($stmts as $stmt) {
            $result = $this->evaluateNode($stmt);
            if (is_array($result) && ($result['__return__'] ?? false) === true) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Assign only to supported targets (keeps writes constrained).
     *
     * @param Node\Expr $target
     * @param mixed $value
     * @throws \Exception
     */
    private function assignToTarget(Node\Expr $target, $value): void
    {
        if ($target instanceof Node\Expr\Variable && is_string($target->name)) {
            $this->variables[$target->name] = $value;
            return;
        }

        throw new \Exception("Unsupported assignment target: " . $target::class);
    }

    /**
     * @param Node $node
     * @return array|null Return marker array when a return statement is evaluated.
     */
    private function evaluateNode(Node $node): ?array
    {
        if ($node instanceof Node\Stmt\Echo_) {
            foreach ($node->exprs as $expr) {
                echo $this->evaluateExpr($expr);
            }
            return null;
        }

        if ($node instanceof Node\Stmt\Expression) {
            $this->evaluateExpr($node->expr);
            return null;
        }

        if ($node instanceof Node\Stmt\Return_) {
            return [
                '__return__' => true,
                'value' => $node->expr ? $this->evaluateExpr($node->expr) : null,
            ];
        }

        if ($node instanceof Node\Stmt\If_) {
            if ($this->toBool($this->evaluateExpr($node->cond))) {
                return $this->executeStatements($node->stmts);
            }

            foreach ($node->elseifs as $elseif) {
                if ($this->toBool($this->evaluateExpr($elseif->cond))) {
                    return $this->executeStatements($elseif->stmts);
                }
            }

            if ($node->else !== null) {
                return $this->executeStatements($node->else->stmts);
            }

            return null;
        }

        if ($node instanceof Node\Stmt\For_) {
            foreach ($node->init as $initExpr) {
                $this->evaluateExpr($initExpr);
            }

            $iterations = 0;

            while (true) {
                if (++$iterations > $this->maxLoopIterations) {
                    throw new \Exception("Max loop iterations exceeded in for-loop");
                }

                // In PHP, empty condition list means "true"
                $condValue = true;
                if (!empty($node->cond)) {
                    foreach ($node->cond as $condExpr) {
                        $condValue = $this->evaluateExpr($condExpr);
                    }
                }

                if (!$this->toBool($condValue)) {
                    break;
                }

                $result = $this->executeStatements($node->stmts);
                if (is_array($result) && ($result['__return__'] ?? false) === true) {
                    return $result;
                }

                foreach ($node->loop as $loopExpr) {
                    $this->evaluateExpr($loopExpr);
                }
            }

            return null;
        }

        if ($node instanceof Node\Stmt\Foreach_) {
            if ($node->byRef) {
                throw new \Exception("Foreach by reference is not supported");
            }

            $iterable = $this->evaluateExpr($node->expr);

            if (!is_array($iterable)) {
                return null;
            }

            $iterations = 0;

            foreach ($iterable as $k => $v) {
                if (++$iterations > $this->maxLoopIterations) {
                    throw new \Exception("Max loop iterations exceeded in foreach-loop");
                }

                if ($node->keyVar !== null) {
                    $this->assignToTarget($node->keyVar, $k);
                }

                $this->assignToTarget($node->valueVar, $v);

                $result = $this->executeStatements($node->stmts);
                if (is_array($result) && ($result['__return__'] ?? false) === true) {
                    return $result;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function toBool($value): bool
    {
        return (bool)$value;
    }

    /**
     * Assigns a value to a supported assignment target.
     *
     * @param Node\Expr $expr
     * @return mixed
     * @throws \Exception
     */
    private function evaluateExpr(Node\Expr $expr)
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\DNumber) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = strtolower($expr->name->toString());
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
            if ($name === 'null') {
                return null;
            }
            throw new \Exception("Unsupported constant: " . $expr->name->toString());
        }

        if ($expr instanceof Node\Expr\BooleanNot) {
            return !$this->toBool($this->evaluateExpr($expr->expr));
        }

        if ($expr instanceof Node\Expr\Variable) {
            $name = $expr->name;

            if (!isset($this->variables[$name])) {
                return null;
            }

            return $this->variables[$name];
        }

        if ($expr instanceof Node\Expr\BinaryOp\Plus) {
            return $this->evaluateExpr($expr->left)
                + $this->evaluateExpr($expr->right);
        }

        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->evaluateExpr($expr->left)
                . $this->evaluateExpr($expr->right);
        }

        // Add support for: $i++
        if ($expr instanceof Node\Expr\PostInc) {
            if (!($expr->var instanceof Node\Expr\Variable) || !is_string($expr->var->name)) {
                throw new \Exception("Unsupported post-increment target: " . $expr->var::class);
            }

            $name = $expr->var->name;
            $current = $this->variables[$name] ?? 0;
            $this->variables[$name] = $current + 1;

            return $current; // post-inc returns old value
        }

        if ($expr instanceof Node\Expr\PostDec) {
            if (!($expr->var instanceof Node\Expr\Variable) || !is_string($expr->var->name)) {
                throw new \Exception("Unsupported post-decrement target: " . $expr->var::class);
            }

            $name = $expr->var->name;
            $current = $this->variables[$name] ?? 0;
            $this->variables[$name] = $current - 1;

            return $current;
        }

        if ($expr instanceof Node\Expr\FuncCall) {
            // SafeNodeVisitor should already block dynamic calls, but let's be defensive:
            if (!($expr->name instanceof Node\Name)) {
                throw new \Exception("Unsupported function call type: " . $expr->name::class);
            }

            $functionName = $expr->name->toString();

            $args = [];
            foreach ($expr->args as $arg) {
                $args[] = $this->evaluateExpr($arg->value);
            }

            return call_user_func_array($functionName, $args);
        }

        if ($expr instanceof Node\Expr\Array_) {
            $result = [];

            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }

                // $item is Node\Expr\ArrayItem
                $value = $this->evaluateExpr($item->value);

                if ($item->key !== null) {
                    $key = $this->evaluateExpr($item->key);
                    $result[$key] = $value;
                } else {
                    $result[] = $value;
                }
            }

            return $result;
        }

        if ($expr instanceof Node\Expr\ArrayDimFetch) {
            $var = $this->evaluateExpr($expr->var);

            if (!is_array($var)) {
                return null;
            }

            if ($expr->dim === null) {
                return null;
            }

            $dim = $this->evaluateExpr($expr->dim);

            if (!array_key_exists($dim, $var)) {
                return null;
            }

            return $var[$dim];
        }

        // KEEP only this safe Assign handler
        if ($expr instanceof Node\Expr\Assign) {
            $value = $this->evaluateExpr($expr->expr);

            if (!($expr->var instanceof Node\Expr\Variable) || !is_string($expr->var->name)) {
                throw new \Exception("Unsupported assignment target: " . $expr->var::class);
            }

            $this->variables[$expr->var->name] = $value;
            return $value;
        }

        // Add support for: $i < 10
        if ($expr instanceof Node\Expr\BinaryOp\Smaller) {
            return $this->evaluateExpr($expr->left) < $this->evaluateExpr($expr->right);
        }

        if ($expr instanceof Node\Expr\BinaryOp\SmallerOrEqual) {
            return $this->evaluateExpr($expr->left) <= $this->evaluateExpr($expr->right);
        }

        if ($expr instanceof Node\Expr\BinaryOp\Greater) {
            return $this->evaluateExpr($expr->left) > $this->evaluateExpr($expr->right);
        }

        if ($expr instanceof Node\Expr\BinaryOp\GreaterOrEqual) {
            return $this->evaluateExpr($expr->left) >= $this->evaluateExpr($expr->right);
        }

        if ($expr instanceof Node\Expr\BinaryOp\Equal) {
            return $this->evaluateExpr($expr->left) === $this->evaluateExpr($expr->right);
        }

        throw new \Exception("Unsupported expression: " . $expr::class);
    }
}
