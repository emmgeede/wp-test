<?php

namespace ACPT\Utils\PHP;

use ACPT\Core\Helper\Strings;

class Maths
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var array|null
     */
    private ?array $data = [];

    /**
     * @var array|null
     */
    private ?array $functions = [];

    /**
     * @param array|null $data
     * @return Maths
     */
    public static function getInstance(?array $data = [])
    {
        if(
            (self::$instance !== null and self::$instance->data != $data) or
            self::$instance == null
        ){
            self::$instance = new Maths($data);
        }

        return self::$instance;
    }

    /**
     * Maths constructor.
     *
     * @param $data
     */
    private function __construct(?array $data = [])
    {
        $this->flattenData($data);
        $this->functions = $this->getFunctions();
    }

    /**
     * @return array
     */
    private function getFunctions()
    {
        return [
            'sum' => ['ref' => [$this, 'sum'], 'arc' => null],
            'mult' => ['ref' => [$this, 'mult'], 'arc' => null],
            'min' => ['ref' => 'min', 'arc' => null],
            'max' => ['ref' => 'max', 'arc' => null],
            'avg' => ['ref' => [$this, 'avg'], 'arc' => null],
            'round' => ['ref' => 'round', 'arc' => 2],
            'pow' => ['ref' => 'pow', 'arc' => 2],
            'perc' => ['ref' => [$this, 'perc'], 'arc' => 2],
        ];
    }

    /**
     * @param array|null $data
     */
    private function flattenData(?array $data = [])
    {
        foreach ($data as $i => $d){
            foreach ($d as $k => $r){
                $letter = Strings::indexToLetters($k);
                $key = $letter . ($i+1);

                // evaluate nested mathematics expressions
                if(is_string($r['value'])){
                    $matches = $this->captureMathExpressions($r['value']);

                    if(!empty($matches[0])){
                        $r['value'] = $this->eval($r['value']);
                    }
                }

                $value = (is_numeric($r['value'])) ? floatval($r['value']) : $r['value'];
                $this->data[strtolower($key)] = $value;
            }
        }
    }

    /**
     * @param $expr
     *
     * @return string
     */
    public function eval($expr)
    {
        // fetch every =(EXPR)
        $matches = $this->captureMathExpressions($expr);

        if(empty($matches[0])){
            return $expr;
        }

        $evaluator = new \Matex\Evaluator();

        foreach ($matches[0] as $i => $m){

            if(!isset( $matches[1][$i])){
                continue;
            }

            $e = strtolower($matches[1][$i]);

            // Evaluate field range expressions:
            // SUM(A1:C1) ---> SUM(A1,B1,C1)
            if(Strings::contains(":", $e)){
                foreach ($this->functions as $function => $callback){
                    if(empty($callback['arc'])){

                        preg_match_all('/'.$function.'\((.*)\)/', $e, $smatches);

                        if(empty($smatches)){
                            continue;
                        }

                        foreach ($smatches[0] as $k => $sm){
                            $se = strtolower($smatches[1][$k]);
                            $e = str_replace($se, $this->transformFieldRangeExpression($se), $e);
                        }
                    }
                }
            }

            $evaluator->functions = $this->functions;
            $evaluator->variables = $this->data;

            try {
                $expr = str_replace($m, $evaluator->execute($e), $expr);
            } catch (\Exception $exception){
                return $exception->getMessage();
            }
        }

        return $expr;
    }

    /**
     * @param $input
     * @return array|array[]
     */
    private function captureMathExpressions($input)
    {
        $regex = '/(?P<full>=\((?P<inner>(?:[^()]+|(?&inner))*)\))/';

        if (preg_match_all($regex, $input, $m) && !empty($m[0])) {
            $result = [
                0 => $m[0],         // full matches: "=(...)", ...
                1 => $m['inner'],   // inner content without the outer "=()"
            ];
        } else {
            // If regex produced no matches OR PCRE doesn't support (?&name),
            // fall back to a simple, deterministic parser that balances parentheses.
            $fullMatches = [];
            $innerMatches = [];

            $len = strlen($input);
            for ($i = 0; $i < $len; $i++) {
                // look for the literal sequence "=("
                if ($input[$i] === '=' && isset($input[$i+1]) && $input[$i+1] === '(') {
                    $start = $i;         // start index of "=("
                    $j = $i + 2;         // position after the opening '('
                    $depth = 1;          // we've seen the first '('
                    // scan forward to find matching ')'
                    while ($j < $len && $depth > 0) {
                        if ($input[$j] === '(') {
                            $depth++;
                        } elseif ($input[$j] === ')') {
                            $depth--;
                        }
                        $j++;
                    }

                    // if depth == 0 we've found a match (j is index after the closing ')')
                    if ($depth === 0) {
                        $full = substr($input, $start, $j - $start);
                        // inner: strip leading '=(' and trailing ')'
                        $inner = substr($full, 2, -1);
                        $fullMatches[] = $full;
                        $innerMatches[] = $inner;
                        // continue scanning after the closing ')'
                        $i = $j - 1;
                    } else {
                        // unmatched parentheses — stop scanning further from this point
                        break;
                    }
                }
            }

            $result = [
                0 => $fullMatches,
                1 => $innerMatches,
            ];
        }

        return $result;
    }

    /**
     * @param $expr
     *
     * @return string
     */
    private function transformFieldRangeExpression($expr)
    {
        $splitRange = Strings::splitRange($expr);

        if (empty($splitRange)){
            return $expr;
        }

        return implode(",", $splitRange);
    }

    /**
     * @param mixed ...$arguments
     *
     * @return float|int
     */
    public function avg(...$arguments)
    {
        return array_sum($arguments) / Arrays::count($arguments);
    }

    /**
     * @param mixed ...$arguments
     *
     * @return float|int
     */
    public function sum(...$arguments)
    {
        return array_sum($arguments);
    }

    /**
     * @param mixed ...$arguments
     *
     * @return float|int
     */
    public function mult(...$arguments)
    {
        return array_product($arguments);
    }

    /**
     * @param $number
     * @param $perc
     *
     * @return float|int
     */
    public function perc($number, $perc)
    {
        return ($number * $perc)/100;
    }
}