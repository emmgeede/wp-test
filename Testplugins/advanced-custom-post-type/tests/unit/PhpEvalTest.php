<?php

namespace ACPT\Tests;

use ACPT\src\Utils\PHP\PHPEval\PhpEval;

class PhpEvalTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function can_eval_simple_php_string_code()
    {
        $string = '<?php $node = 333; return $node; ?>';
        $expected = 333;

        $code = PhpEval::getInstance()->evaluate($string);
        $this->assertEquals($expected, $code);
    }
}
