<?php
/*
* Normal Distribution - Statistical Distributions for PHP
*
* Copyright (C) 2015 Giuseppe Burtini <joe@iterative.ca>.
*
* Other credits
* Box, Muller 1958 for the rand() method
* Michael Nickerson (2004), Thomas Ziegler for the icdf function.
*/
namespace gburtini\Distributions;

use gburtini\Distributions\Interfaces\DistributionInterface;
use gburtini\Distributions\Accessories\ErfFunction;
//use gburtini\Distributions\Distribution;

class Normal extends Distribution implements DistributionInterface
{
    // TODO: implement a skewness, kurtosis normal.
    protected $mean;
    protected $variance;
    protected $skewness;
    protected $kurtosis;

    public function mean()
    {
        return $this->mean;
    }
    public function variance()
    {
        return $this->variance;
    }
    public function sd()
    {
        return sqrt($this->variance);
    }

    public function __construct($mean = 0, $variance = 1, $skewness = 0, $kurtosis = 0)
    {
        static::validateParameters($mean, $variance, $skewness, $kurtosis);

        $this->mean = floatval($mean);
        $this->variance = floatval($variance);
        //$this->skewness = floatval($skewness);
        //$this->kurtosis = floatval($kurtosis);
    }

    public function rand()
    {
        return self::draw($this->mean, $this->variance);
    }
    public static function draw($mean, $variance)
    {
        return self::boxMuller()*sqrt($variance) + $mean;
    }
    public function pdf($x)
    {
        $z = ($x - $this->mean)/$this->variance;

        return exp(-$z*$z/2) / ($this->variance * M_SQRTPI * M_SQRT2);
    }

    public function cdf($x)
    {
        $d = $x - $this->mean;
        return 0.5 * (1 + ErfFunction::val($d / (sqrt($this->variance) * sqrt(2))));
    }
        
    public function icdf($p)
    {
        // Inverse ncdf approximation by Peter John Acklam, implementation adapted to
        // PHP by Michael Nickerson, using Dr. Thomas Ziegler's C implementation as
        // a guide.  http://home.online.no/~pjacklam/notes/invnorm/index.html
        // I have not checked the accuracy of this implementation.  Be aware that PHP
        // will truncate the coeficcients to 14 digits.

        // You have permission to use and distribute this function freely for
        // whatever purpose you want, but please show common courtesy and give credit
        // where credit is due.

        // Input parameter is $p - probability - where 0 < p < 1.

        // Coefficients in rational approximations
        $a = array(1 => -3.969683028665376e+01, 2 => 2.209460984245205e+02,
                3 => -2.759285104469687e+02, 4 => 1.383577518672690e+02,
                5 => -3.066479806614716e+01, 6 => 2.506628277459239e+00);

        $b = array(1 => -5.447609879822406e+01, 2 => 1.615858368580409e+02,
                3 => -1.556989798598866e+02, 4 => 6.680131188771972e+01,
                5 => -1.328068155288572e+01);

        $c = array(1 => -7.784894002430293e-03, 2 => -3.223964580411365e-01,
                3 => -2.400758277161838e+00, 4 => -2.549732539343734e+00,
                5 => 4.374664141464968e+00, 6 => 2.938163982698783e+00);

        $d = array(1 => 7.784695709041462e-03, 2 => 3.224671290700398e-01,
                3 => 2.445134137142996e+00, 4 => 3.754408661907416e+00);

        // Define break-points.
        $pLow =  0.02425; //Use lower region approx. below this
        $pHigh = 1 - $pLow; //Use upper region approx. above this

        if (0 < $p && $p < $pLow) {
            // Rational approximation for lower region.

            $q = sqrt(-2 * log($p));
            $x = ((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) *
                $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) * $q + $d[4]) *
                $q + 1);
        } elseif ($pLow <= $p && $p <= $pHigh) {
            // Rational approximation for central region.

            $q = $p - 0.5;
            $r = $q * $q;
            $x = ((((($a[1] * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) *
                $r + $a[6]) * $q / ((((($b[1] * $r + $b[2]) * $r + $b[3]) * $r +
                $b[4]) * $r + $b[5]) * $r + 1);
        } elseif ($pHigh < $p && $p < 1) {
            // Rational approximation for upper region.

            $q = sqrt(-2 * log(1 - $p));
            $x = -((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q +
                $c[5]) * $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) *
                $q + $d[4]) * $q + 1);
        } else if($p === 1) {
            return INF;
        } else {
            throw new \InvalidArgumentException("Could not estimate ICDF, p={$p} is outside safe estimation region.");
        }

        return $this->mean + $this->sd() * $x;
        // END inverse ncdf implementation.
    }

    // TODO: Ziggurat algorithm to draw values: https://en.wikipedia.org/wiki/Ziggurat_algorithm
    /**
     * @return double
     */
    public static function boxMuller() {
        // Box-Muller transform (Box, Muller 1958).
        $u = mt_rand()/mt_getrandmax();
        $v = mt_rand()/mt_getrandmax();

        return (double) sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    public static function validateParameters($m, $v, $s, $k)
    {
        if (!is_numeric($m) || !is_numeric($v) || !is_numeric($s) || !is_numeric($k)) {
            throw new \InvalidArgumentException("Non-numeric parameter in normal distribution (" . self::renderParameters(compact('m', 'v', 's', 'k')) . ").");
        }
        if ($v <= 0) {
            throw new \InvalidArgumentException("Variance must be strictly positive (it is σ-squared after all!). Currently, \$v = " . var_export($v, true) . ".");
        }
    }


//    /**
//     * @return double|double[]
//     */
//    public function skewness()
//    {
//        // TODO: Implement skewness() method.
//    }
//
//    /**
//     * @return double|double[]
//     */
//    public function kurtosis()
//    {
//        // TODO: Implement kurtosis() method.
//    }
}
