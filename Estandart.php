<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 09.09.2019
 * Time: 16:13
 */

namespace App\Lib\ExportRates;

/**
 * Class Estandart
 * @package App\Lib\ExportRates
 */
class Estandart extends Export
{
    protected $type = 'XML';
    protected $xmlRoot = 'rates';
    protected $flagInOut= true;
    protected $minAmountName='minamount';
    protected $maxAmountName='maxamount';

    protected $signature = [
        'UAH' => 'UAH',
        'BTC' => 'BTC',
        'BCH' => 'BCH',
        'LTC' => 'LTC',
        'ETH' => 'ETH',
        'RUBQC'=>'RUB',
    ];

    /**
     * @return mixed|void
     */
    public function generateReport()
    {
        $arr = $this->rateTable;
        $this->result['item'] = [];
        foreach ($arr as $key => $val) {
            $this->setValue($key, $val);
            $answer = $this->generateAnswer();
            $answer['from'] = $this->from;
            $answer['to'] = $this->to;
            array_push($this->result['item'], $answer);
        }
    }
}