<?php

require __DIR__.'../../vendor/autoload.php';

Class fizzBuzz
{
    public function fizzBuzz() {

        $result = '';
        
        for ($i = 1; $i <= 500; $i++) {
            $input[] = $i;
        }
        foreach ($input as $number) {
            if (self::isPrime($number)) {
                $result .=  "\n FIZZBUZZ++";
            } else {
                if ($number % 15 == 0) {
                    $result .=  "\n FIZZBUZZ";
                } else {
                    if ($number % 3 == 0 ) {
                        $result .=  "\n FIZZ";
                    } else if ($number % 5 == 0 ) {
                        $result .=  "\n BUZZ";
                    } else {
                        $result .=  "\n $number";
                    }
                } 
            }
        }

        return $result;
    }

    public function isPrime($num) {

        if ($num == 1)
        return 0;
        for ($i = 2; $i <= $num/2; $i++)
        {
           if ($num % $i == 0)
           return 0;
        }
        return 1;
    }
}

$fizz = new fizzBuzz();
$result = $fizz->fizzBuzz();
echo $result;
file_put_contents('fizz.log', $result, FILE_APPEND);
