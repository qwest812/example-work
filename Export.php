<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 30.08.2019
 * Time: 17:24
 */

namespace App\Lib\ExportRates;


use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * Class Export
 * @package App\Lib\ExportRates
 */
abstract class Export
{
    /**
     * @var string
     * CARDUAH
     */
    protected $from = '';
    /**
     * @var string
     * BTC
     */
    protected $to = '';
    /**
     * @var array
     * Data from API
     */
    protected $rateTable = [];
    /**
     * @var array
     */
    protected $rests = [];
    /**
     * @var string
     */
    protected $type = 'JSON';
    /**
     * @var bool
     * in and out to report
     */
    protected $flagInOut = false;
    /**
     * @var bool
     */
    protected $flagAddNameCurrencyToMinMaxAmount = false;

    ////Fee
    /**
     * @var int
     */
    public $inFix = 0;
    /**
     * @var int
     */
    public $inPer = 0;
    /**
     * @var int
     */
    public $outFix = 0;
    /**
     * @var int
     */
    public $outPer = 0;
    /**
     * @var int
     */
    public $price = 0;
    /**
     * @var int
     */
    public $in = 0;
    /**
     * @var int
     */
    public $out = 0;
    /**
     * @var int
     */
    public $amount = 0;
    /**
     * @var int
     */
    public $in_min_amount = 0;
    /**
     * @var int
     */
    public $in_max_amount = 0;
    /**
     * @var string
     * path
     */
    public $site = '';
    /**
     * @var string
     */
    public $amountName = 'amount';
    /**
     * @var string
     */
    protected $minAmountName = 'in_min_amount';
    /**
     * @var string
     */
    protected $maxAmountName = 'in_max_amount';
    /**
     * @var array
     */
    protected $structure = [];
    //kurses
    /**
     * @var array
     * default
     */
    protected $signature = [
        'UAH' => 'UAH',
        'BTC' => 'BTC',
        'BCH' => 'BCH',
        'LTC' => 'LTC',
        'ETH' => 'ETH',
    ];

    /**
     * @var array
     * default
     */
    protected $fiatCodes = [
        'UAH', 'RUB', 'CARDUAH', 'CARDRUB',
    ];
    /**
     * @var array
     */
    protected $result = [];
    /**
     * @var string
     * Root name path
     */
    protected $xmlRoot = 'root';

    /**
     * @return mixed
     */
    abstract public function generateReport();

    /**
     * Export constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $dataApi = \HotcoinApi::getRates();

        $this->rateTable = $dataApi['table'];
        $this->rests = $dataApi['rests'];
        $this->generateReport();

    }

    /**
     * @param $code
     * @return bool
     */
    public function isFiat($code)
    {
        return in_array($code, $this->fiatCodes);
    }

    /**
     * @param $key
     * @param $val
     */
    public function setValue($key, $val)
    {
        $pars = explode('-', $key);
        $nameFrom = $pars[0];
        $nameTo = $pars[1];
        $this->from = $this->chooseSignature($nameFrom);
        $this->to = $this->chooseSignature($nameTo);
        $this->price = $val['price'];
        $this->inFix = ($val['fees']['in_fix']);
        $this->outFix = ($val['fees']['out_fix']);
        $this->inPer = ($val['fees']['in_per']);
        $this->outPer = ($val['fees']['out_per']);

//        $this->in_max_amount = (float)$this->format_amount($val['limits']['max_out']);
        if ($this->isFiat($nameFrom)) {

            $this->in_max_amount = (float)$this->format_amount($val['limits']['max'], 2);
            $this->in_min_amount = (float)$this->format_amount($val['limits']['min'], 2);
        } else {

            $this->in_max_amount = (float)$this->format_amount($val['limits']['max'], 4);
            $this->in_min_amount = (float)$this->format_amount($val['limits']['min'], 4);
        }
        if ($this->isFiat($nameTo)) {
            $this->amount = (float)$this->format_amount($this->rests[$nameTo], 2);
        } else {
            $this->amount = (float)$this->format_amount($this->rests[$nameTo], 4);
        }
        $this->setNameCurrencyMinMaxAmount();
        $this->setPrice();
    }

    /**
     * @param $number
     * @param int $scale
     * @return float
     */
    public function format_amount($number, $scale = 6)
    {
        $mul = 100000000;
        $commission = floatval($number) * $mul;
        return (float)bcdiv($commission, $mul, $scale);

    }

    /**
     * @return array
     */
    protected function generateAnswer()
    {
        $arr = $this->flagInOut ? [
            $this->amountName => $this->amount,
            'in' => 1,
            'out' => 1,
        ] : [
            $this->amountName => $this->amount,
        ];

        if ($this->minAmountName) {
            $arr[$this->minAmountName] = $this->in_min_amount;
        }
        if ($this->maxAmountName) {
            $arr[$this->maxAmountName] = $this->in_max_amount;
        }
        if ($this->in > 1) {
            $arr  ['in'] = $this->format_amount($this->in);

        } else {
            $arr['out'] = $this->format_amount($this->out);
        }
        return $arr;
    }

    /**
     * Set price with fee
     */
    public function setPrice()
    {
        $price = $this->price;
        $outFix = $this->outFix;
        $outPer = $this->outPer;
        $inPer = $this->inPer;
        $inFix = $this->inFix;
        $flag = $this->flagInOut;
        if ($price < 1) {
            $sumIn = $outFix > 0 ? 1 + $outFix : 1; //outFix
            $sumIn = $outPer > 0 ? $sumIn + ($sumIn * $outPer / 100) : $sumIn; //outPer
            $sumIn = $sumIn / $price; // price
            $sumIn = $inFix > 0 ? $sumIn + $inFix : $sumIn;// inFix
            $ss = $sumIn * $inPer / 100;
            $sumIn = $inPer > 0 ? $sumIn + $ss : $sumIn;
            $this->in = $this->format_amount($sumIn);
            $this->out = $flag ? 1 : 0;
        } else {
            $val = $inFix > 0 ? 1 - $inFix : 1;
            $val = $inPer > 0 ? $val - $val * ($inPer / 100) : $val;
            $sumOut = $price * $val;
            $sumOut = $outFix > 0 ? $sumOut - $outFix : $sumOut;
            $sumOut = $outPer > 0 ? $sumOut - $outPer / 100 * $sumOut : $sumOut;
            $this->out = $this->format_amount($sumOut);
            $this->in = $flag ? 1 : 0;
        }
        $this->price = (float)$price;
    }

    /**
     * @return array
     */

    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @return array
     */
    public function view()
    {
        $data = $this->result;
        $type = $this->type;
        switch ($type) {
            case 'XML':
                return \Response::make(ArrayToXml::convert($data, $this->xmlRoot), '200')
                    ->header('Content-Type', 'text/xml');
                break;
            case 'JSON':
                return response()->json($data);

            default:
                return $data;

        }
    }

    /**
     * @param $currency
     * @return mixed
     */
    public function chooseSignature($currency)
    {
        $signature = $this->getSignature();


        return !empty($signature[$currency]) ? $signature[$currency] : $currency;
    }

    /**
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * change name min adn max amount
     */
    protected function setNameCurrencyMinMaxAmount()
    {
        $minAmount = &$this->in_min_amount;
        $maxAmount = &$this->in_max_amount;
        if ($this->flagAddNameCurrencyToMinMaxAmount) {
            $minAmount = $minAmount . ' ' . $this->from;
            $maxAmount = $maxAmount . ' ' . $this->from;
        }
    }


}
