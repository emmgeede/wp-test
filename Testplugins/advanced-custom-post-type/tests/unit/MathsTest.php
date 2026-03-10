<?php

namespace ACPT\Tests;

use ACPT\Constants\BelongsTo;
use ACPT\Constants\Logic;
use ACPT\Constants\Operator;
use ACPT\Core\Helper\Uuid;
use ACPT\Core\Models\Belong\BelongModel;
use ACPT\Utils\PHP\Logics;
use ACPT\Utils\PHP\Maths;

class MathsTest extends AbstractTestCase
{
    /**
     * @test
     */
    public function notEvaluateStrings()
    {
        $maths = Maths::getInstance($this->getData());

        $this->assertEquals($maths->eval("ciao"), "ciao");
    }

    /**
     * @test
     */
    public function evaluateSimpleArithmeticStrings()
    {
        $maths = Maths::getInstance($this->getData());

        $this->assertEquals($maths->eval("=(2+5) USD"), "7 USD");
        $this->assertEquals($maths->eval("=(2+5)"), "7");
        $this->assertEquals($maths->eval("=(5-3)"), "2");
        $this->assertEquals($maths->eval("=(4*4-2)"), "14");
        $this->assertEquals($maths->eval("=(4/2)"), "2");
        $this->assertEquals($maths->eval("=(8*2)"), "16");
        $this->assertEquals($maths->eval("=(10 % 4)"), "2");
        $this->assertEquals($maths->eval("=(PERC(100,2))"), "2");
        $this->assertEquals($maths->eval("=((10000 + (PERC(10000, 4.88) * 5)) / 60)"), "207.33333333333");
    }

    /**
     * @test
     */
    public function evaluateArithmeticStrings()
    {
        $maths = Maths::getInstance($this->getData());

        $this->assertEquals($maths->eval("=(SUM(2,5))"), "7");
        $this->assertEquals($maths->eval("=(MULT(2,5))"), "10");
        $this->assertEquals($maths->eval("=(MULT(10/2))"), "5");

        // sum a row
        $this->assertEquals($maths->eval("=(A2+B2+C2)"), "12");
        $this->assertEquals($maths->eval("=(SUM(A2+B2+C2))"), "12");
        $this->assertEquals($maths->eval("=(SUM(A2+B2-C2))"), "-2");
        $this->assertEquals($maths->eval("=(SUM(A2:C2))"), "12");

        // sum a col
        $this->assertEquals($maths->eval("=(A2+A3+A4)"), "6");
        $this->assertEquals($maths->eval("=(SUM(A2,A3,A4))"), "6");
        $this->assertEquals($maths->eval("=(SUM(A2:A4))"), "6");
    }

    /**
     * @test
     */
    public function evaluateMoreComplexStrings()
    {
        $data = [
            0 => [
                0 => [
                    'value'    => 'Double click to edit',
                    'settings' => [],
                ],
                1 => [
                    'value'    => 'Double click to edit',
                    'settings' => [],
                ],
            ],
            1 => [
                [
                    'value'    => '27',
                    'settings' => [],
                ],
                [
                    'value'    => '4',
                    'settings' => [],
                ],
            ],
            2 => [
                [
                    'value'    => '9',
                    'settings' => [],
                ],
                [
                    'value'    => '2',
                    'settings' => [],
                ],
            ],
            3 => [
                [
                    'value'    => '=(A3/A2)',
                    'settings' => [],
                ],
                array(
                    'value'    => '',
                    'settings' => array(),
                ),
            ],
            4 => [
                [
                    'value'    => '=(A4*3)',
                    'settings' => [],
                ],
                [
                    'value'    => '=(A4*2)',
                    'settings' => [],
                ],
            ],
        ];

        $maths = Maths::getInstance($data);
        $expr = "=((A4 + (A3 * A2 * B2)) / B3)";

        $this->assertEquals($maths->eval($expr), "486.16666666667");
        $this->assertEquals($maths->eval("=(B5)"), "0.66666666666666");
        $this->assertEquals($maths->eval("=(A5)"), "0.99999999999999");

    }

    /**
     * @test
     */
    public function evaluateOtherFunctions()
    {
        $maths = Maths::getInstance($this->getData());

        $this->assertEquals($maths->eval("=(PERC(A3,50))"), "1");
        $this->assertEquals($maths->eval("=(PERC(100,50))"), "50");
        $this->assertEquals($maths->eval("=(POW(A3,2))"), "4");
        $this->assertEquals($maths->eval("=(AVG(A2,A3,A4))"), "2");
        $this->assertEquals($maths->eval("=(MAX(A2,A3,A4))"), "3");
        $this->assertEquals($maths->eval("=(MIN(A2,A3,A4))"), "1");
        $this->assertEquals($maths->eval("=(ROUND(AVG(A2,A3,A4),2))"), "2");

        $this->assertEquals($maths->eval("=(MAX(A2:C2))"), "7");
        $this->assertEquals($maths->eval("=(MIN(A2:C2))"), "1");
        $this->assertEquals($maths->eval("=(AVG(A2:C2))"), "4");

        $this->assertEquals($maths->eval("=(MAX(A2:A4))"), "3");
        $this->assertEquals($maths->eval("=(MIN(A2:A4))"), "1");
        $this->assertEquals($maths->eval("=(AVG(A2:A4))"), "2");
    }

    /**
     * @return array
     */
    private function getData()
    {
        return [
            0 =>
                [
                    0 =>
                        [
                            'value'    => 'Double click to edit',
                            'settings' => [],
                        ],
                    1 =>
                        [
                            'value'    => 'Double click to edit',
                            'settings' => [],
                        ],
                    2 =>
                        [
                            'value'    => 'Double click to edit',
                            'settings' => [],
                        ],
                ],
            1 =>
                [
                    0 =>
                        [
                            'value'    => '1',
                            'settings' => [],
                        ],
                    1 =>
                        [
                            'value'    => '4',
                            'settings' => [],
                        ],
                    2 =>
                        [
                            'value'    => '7',
                            'settings' => [],
                        ],
                ],
            2 =>
                [
                    0 =>
                        [
                            'value'    => '2',
                            'settings' => [],
                        ],
                    1 =>
                        [
                            'value'    => '6',
                            'settings' => [],
                        ],
                    2 =>
                        [
                            'value'    => '8',
                            'settings' => [],
                        ],
                ],
            3 =>
                [
                    0 =>
                        [
                            'value'    => '3',
                            'settings' => [],
                        ],
                    1 =>
                        [
                            'value'    => '5',
                            'settings' => [],
                        ],
                    2 =>
                        [
                            'value'    => '9',
                            'settings' => [],
                        ],
            ],
            4 =>
                [
                    0 =>
                        [
                            'value'    => '=(SUM(A1:A3))',
                            'settings' => [],
                        ],
                    1 =>
                        [
                            'value'    => '=(SUM(B1:B3))',
                            'settings' => [],
                        ],
                    2 =>
                        [
                            'value'    => '=(SUM(C1:C3))',
                            'settings' => [],
                        ],
                ],
        ];
    }
}