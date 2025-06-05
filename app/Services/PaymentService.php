<?php

namespace App\Services;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * @throws ConnectionException
     */
    public function getPaymentUrl($payment_gateway, $transaction)
    {
        $payment_app_name = $payment_gateway->payment_app_name;

        switch ($payment_app_name) {
            case 'payme-bank':
                $params = [
                    'partner_id'          => $payment_gateway->payment_app_number,
                    'timestamp'           => Carbon::now()->timestamp,
                    'random'              => Str::random(14),
                    'partner_order_code'  => $transaction->transaction_number,
                    'amount'              => $transaction->conversion_amount,
                    'notify_url'          => route('depositCallback'),
                    'return_url'          => route('depositReturn'),
                ];

                $data = [
                    $params['partner_id'],
                    $params['timestamp'],
                    $params['random'],
                    $params['partner_order_code'],
                    $params['amount'],
                    '', '', // bank placeholders
                    $params['notify_url'],
                    $params['return_url'],
                    '',
                    $payment_gateway->payment_app_key
                ];

                $params['sign'] = md5(implode(':', $data));

                $baseUrl = $payment_gateway->payment_url . '/gateway/bnb/createVA.do';
                $payment_url = $this->buildAndRequestUrl($baseUrl, $params);
                break;

            case 'payment-hot':
                $params = [
                    'merchant_id'  => $payment_gateway->payment_app_number,
                    'order_code'   => $transaction->transaction_number,
                    'total_amount' => $transaction->conversion_amount,
                    'currency'     => 'VND',
                    'language'     => app()->getLocale(),
                    'timestamp'    => Carbon::now()->timestamp,
                    'content'      => 'deposit',
                ];

                $params['signature'] = $this->generatePaymentHotSignature($params, $payment_gateway->payment_app_key);

                $payment_url = $payment_gateway->payment_url . "?" . http_build_query($params);
                break;

            default:
                $params = [
                    'partner_id'          => $payment_gateway->payment_app_number,
                    'timestamp'           => Carbon::now()->timestamp,
                    'random'              => Str::random(14),
                    'partner_order_code'  => $transaction->transaction_number,
                    'order_currency'      => 0,
                    'order_language'      => 'en_ww',
                    'guest_id'            => md5('SuperFX' . Auth::id()),
                    'amount'              => $transaction->amount,
                    'notify_url'          => route('depositCallback'),
                    'return_url'          => route('depositReturn'),
                ];

                $data = [
                    $params['partner_id'],
                    $params['timestamp'],
                    $params['random'],
                    $params['partner_order_code'],
                    $params['order_currency'],
                    $params['order_language'],
                    $params['guest_id'],
                    $params['amount'],
                    $params['notify_url'],
                    $params['return_url'],
                    '',
                    $payment_gateway->payment_app_key
                ];

                $params['sign'] = md5(implode(':', $data));

                $baseUrl = $transaction->payment_account_type == 'erc20'
                    ? $payment_gateway->payment_url . '/gateway/usdt/createERC20.do'
                    : $payment_gateway->payment_url . '/gateway/usdt/createTRC20.do';

                $payment_url = $this->buildAndRequestUrl($baseUrl, $params);
                break;
        }

        return $payment_url;
    }

    /**
     * @throws ConnectionException
     */
    private function buildAndRequestUrl($baseUrl, $params)
    {
        $redirectUrl = $baseUrl . "?" . http_build_query($params);
        Log::debug("POST URL : " . $redirectUrl);

        $response = Http::get($redirectUrl);
        $responseData = $response->json();

        return $responseData['data']['payment_url'] ?? null;
    }

    private function generatePaymentHotSignature($params, $merchantKey): string
    {
        ksort($params);
        $concatenatedString = implode('', $params);
        $hashParams = hash('sha256', $concatenatedString . $merchantKey, true);
        return base64_encode($hashParams);
    }
}
