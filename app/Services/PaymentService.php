<?php

namespace App\Services;

use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * @throws ConnectionException
     * @throws Exception
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

            case 'hypay':
                $params = [
                    'mch_no'  => $transaction->transaction_number,
                    'sync_url' => route('depositReturn'),
                    'async_url' => route('hypay_deposit_callback'),
                    'true_name' => $transaction->user->name,
                    'phone' => $transaction->user->phone_number,
                    'order_amount' => $transaction->conversion_amount,
                    'amount' => $transaction->amount,
                ];

                $timestamp = now()->timestamp;
                $bodyString = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                Log::info('body json: ' . $bodyString);

                $msg = "/api/place/orders/checkout$timestamp$bodyString";

                $bank_headers = [
                    'ACCESS-KEY'  => $payment_gateway->payment_app_key,
                    'ACCESS-SIGN'  => hash_hmac('sha256', $msg, $payment_gateway->secondary_key),
                    'ACCESS-TIMESTAMP'  => $timestamp,
                ];

                $response = Http::withHeaders(array_merge($bank_headers, [
                    'Content-Type' => 'application/json',
                ]))
                    ->withBody($bodyString)
                    ->post("$payment_gateway->payment_url/api/place/orders/checkout");

                $responseData = $response->json();

                if (isset($responseData['code']) && $responseData['code'] === 0) {
                    // success → get the URL from data
                    $payment_url = $responseData['data']['url'] ?? null;

                    if ($payment_url) {
                        break;
                    }

                    // code == 0 but url missing → throw exception
                    throw new Exception('Missing checkout URL in gateway response: ' . json_encode($responseData));
                }

                // error case → throw exception with message
                throw new Exception('Gateway request failed: ' . ($responseData['message'] ?? json_encode($responseData)));

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
