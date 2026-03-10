<?php

namespace ACPT\Utils\PHP\PHPEval;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class SafeNodeVisitor extends NodeVisitorAbstract
{
    private array $errors = [];

    /**
     * Only these functions may be called from evaluated code.
     * Keep this list small and intentionally "read-only".
     */
    private array $allowedFunctions = [
        'get_the_ID',
        'get_the_permalink',
        'get_permalink',

        // If you allow output-related helpers, prefer escaping ones:
        'esc_url',
        'esc_html',

        // Consider carefully: filters can trigger third-party callbacks.
        // 'apply_filters',
    ];

    /**
     * Block access to superglobals (common data-exfil and request-tampering vectors).
     */
    private array $disallowedVariables = [
        '_GET',
        '_POST',
        '_REQUEST',
        '_COOKIE',
        '_SERVER',
        '_ENV',
        '_FILES',
        'GLOBALS',
        'http_response_header',
        'php_errormsg',
    ];

    /**
     * List of allowed node types processed by the application.
     */
    private array $allowedNodes = [
        Node\Stmt\Expression::class,
        Node\Stmt\Echo_::class,
        Node\Stmt\Return_::class,
        Node\Stmt\If_::class,
        Node\Stmt\ElseIf_::class,
        Node\Stmt\Nop::class,
        Node\Stmt\For_::class,
        Node\Stmt\Foreach_::class,

        // Expressions
        Node\Expr\BinaryOp::class,
        Node\Expr\BooleanNot::class,
        Node\Expr\ConstFetch::class,
        Node\Expr\Ternary::class,

        Node\Expr\Variable::class,

        Node\Expr\Array_::class,
        Node\Expr\ArrayItem::class,
        Node\Expr\ArrayDimFetch::class,
        Node\Expr\PostInc::class,
        Node\Expr\PostDec::class,

        Node\Expr\Assign::class,
        Node\Expr\FuncCall::class,
        Node\Arg::class,
        Node\Name::class,

        // Scalars
        Node\Scalar\String_::class,
        Node\Scalar\LNumber::class,
        Node\Scalar\DNumber::class,
    ];

    public function enterNode(Node $node)
    {
        // Extra security checks for specific nodes:

        // 1) Prevent reading superglobals / special globals
        if ($node instanceof Node\Expr\Variable) {
            if (is_string($node->name) && in_array($node->name, $this->disallowedVariables, true)) {
                $this->errors[] = [
                    'message' => 'Disallowed variable: $' . $node->name,
                    'line' => $node->getStartLine(),
                ];
                return null;
            }
        }

        // 2) Allow only selected function calls
        if ($node instanceof Node\Expr\FuncCall) {
            // Disallow variable function calls like $fn()
            if (!($node->name instanceof Node\Name)) {
                $this->errors[] = [
                    'message' => 'Disallowed dynamic function call',
                    'line' => $node->getStartLine(),
                ];
                return null;
            }

            $functionName = $node->name->toString();

            if (!in_array($functionName, $this->allowedFunctions, true)) {
                $this->errors[] = [
                    'message' => 'Disallowed function: ' . $functionName,
                    'line' => $node->getStartLine(),
                ];
                return null;
            }

            // If the function is allowed, still continue to node-type allowlist below
            // (so args/names must also be allowed nodes).
        }

        // Node-type allowlist (generic)
        foreach ($this->allowedNodes as $allowed) {
            if ($node instanceof $allowed) {
                return null;
            }
        }

        $this->errors[] = [
            'message' => 'Disallowed node: ' . $node::class,
            'line' => $node->getStartLine()
        ];

        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
