<?php

namespace ACPT\Utils\PHP\PHPEval;

use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class PhpEval
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @return PhpEval
     */
    public static function getInstance()
    {
        if(self::$instance == null){
            self::$instance = new PhpEval();
        }

        return self::$instance;
    }

    /**
     * Maths constructor.
     *
     * @param $data
     */
    private function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Extract PHP strings from mixed HTML code.
     *
     * @param string $code
     * @return array
     */
    private function extractPHPStrings(string $code): array
    {
        preg_match_all('/\<\?php(.*?)\?\>/is', $code, $matches);

        return $matches[0] ?? [];
    }

    /**
     * Evaluate PHP contained in mixed HTML code.
     * Example: <p><?php echo 'Hello world'; ?></p>
     *
     * @param string $phpCode
     * @param array $attributes
     * @return string|null
     */
    public function evaluate(string $phpCode, array $attributes = []): ?string
    {
        $phpStrings = $this->extractPHPStrings($phpCode);

        foreach ($phpStrings as $phpString) {
            $phpCode = str_replace($phpString, $this->evaluatePHPString($phpString, $attributes), $phpCode);
        }

        return $phpCode;
    }

    /**
     * Evaluate PHP string.
     *
     * @param string $phpCode
     * @param array $attributes
     * @return string|null
     */
    private function evaluatePHPString(string $phpCode, array $attributes = []): ?string
    {
        // Code to be evaluated
        $wrappedCode = <<<PHP
{$phpCode}
PHP;

        $ast = $this->parser->parse($wrappedCode);

        // 1. Validation
        $errors = $this->validateBeforeExecution($wrappedCode);
        if (!empty($errors)) {
            return null;
        }

        // 2. Safe execution
        ob_start();
        $interpreter = new SafeInterpreter();
        $interpreter->execute($ast);

        return ob_get_clean();
    }

    /**
     * Validate code before execution.
     *
     * @param string $code
     * @return array
     */
    private function validateBeforeExecution(string $code): array
    {
        try {
            $ast = $this->parser->parse($code);

            $traverser = new \PhpParser\NodeTraverser();
            $visitor = new SafeNodeVisitor();

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            return $visitor->getErrors();

        } catch (\PhpParser\Error $e) {
            return [['message' => $e->getMessage()]];
        }
    }

    /**
     * @param string $code
     * @return array
     */
    public function getErrors(string $code): array
    {
        try {
            $this->parser->parse($code);

            return [];
        } catch (Error $error) {
            return [
                'file'    => $error->getFile(),
                'code'    => $error->getCode(),
                'message' => $error->getRawMessage(),
                'line'    => $error->getStartLine(),
                'column'  => $error->getAttributes()['startColumn'] ?? 'unknown',
                'full'    => $error->getMessage()
            ];
        }
    }
}