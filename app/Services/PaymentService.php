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
    public function getPaymentUrl($payment_gateway, $transaction, $paymentGatewayMethod)
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

            case 'pay-superfin':
                $params = [
                    'version'  => "2.0",
                    'merId'  => $payment_gateway->payment_app_number,
                    'transDate' => date_format($transaction->created_at, 'Ymd'),
                    'seqId' => $transaction->transaction_number,
                    'transTime' => date_format($transaction->created_at, 'His'),
                    'amount' => (string) $transaction->conversion_amount,
                    'notifyUrl' => route('psp_deposit_callback'),
                    'payType' => "00",
                    'buyerId' => (string) $transaction->user_id,
                ];

                // Remove null/empty values
                $filtered = array_filter($params, fn($v) => $v !== null && $v !== '');

                // Sort by key (ASCII order)
                ksort($filtered);

                $stringA = urldecode(http_build_query($filtered));

                $privateKeyPath = storage_path('app/keys/psp_private.pem');
                $privateKey = file_get_contents($privateKeyPath);

                if (!$privateKey) {
                    throw new Exception('Private key file not found or unreadable.');
                }

                $signature = null;
                $success = openssl_sign($stringA, $signature, $privateKey);

                if (!$success) {
                    throw new Exception('Failed to generate RSA signature.');
                }

                // Encode signature (usually Base64 for transmission)
                $params['sign'] = base64_encode($signature);

                $response = Http::asForm()
                    ->post("$payment_gateway->payment_url/pay", $params);

                $responseData = $response->json();

                if (isset($responseData['code']) && $responseData['code'] == "0000") {
                    // success → get the URL from data
                    $payment_url = $responseData['payResult'] ?? null;

                    if ($payment_url) {
                        break;
                    }

                    // code == 0 but url missing → throw exception
                    throw new Exception('Missing checkout URL in gateway response: ' . json_encode($responseData));
                }

                Log::info('PSP Pay response code: ' . $responseData['code']);
                Log::info('PSP Pay response msg: ' . $responseData['msg']);

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now()
                ]);

                // error case → throw exception with message
                throw new Exception('Gateway request failed: CODE - ' . $responseData['code'] . '; MSG - ' . ($responseData['msg'] ?? json_encode($responseData)));

            case 'hypay':
                $params = [
                    'mch_no'  => $transaction->transaction_number,
                    'sync_url' => route('depositReturn'),
                    'async_url' => route('hypay_deposit_callback'),
                    'true_name' => $transaction->user->name,
                    'phone' => $transaction->user->phone_number,
                    'order_amount' => (float) $transaction->conversion_amount,
                    'amount' => (float) $transaction->amount,
                    'pay_channel' => 'card'
                ];

                $timestamp = now()->timestamp;
                $bodyString = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $msg = "/api/place/orders/checkout$timestamp$bodyString";

                $headers = [
                    'ACCESS-KEY'  => $payment_gateway->payment_app_key,
                    'ACCESS-SIGN'  => hash_hmac('sha256', $msg, $payment_gateway->secondary_key),
                    'ACCESS-TIMESTAMP'  => $timestamp,
                ];
                $headers['Content-Type'] = 'application/json';

                $response = Http::withHeaders($headers)
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

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now(),
                ]);

                // error case → throw exception with message
                throw new Exception('Gateway request failed: ' . ($responseData['message'] ?? json_encode($responseData)));

            case 'zpay':
                $params = [
                    'merchantCode'  => $payment_gateway->payment_app_number,
                    'merchantKey' => $payment_gateway->payment_app_key,
                    'currency' => 'VND',
                    'paymentID' => $transaction->transaction_number,
                    'responseURL' => route('zpay_deposit_callback'),
                    'amount' => $transaction->conversion_amount,
                    'bankType' => 'BANK_QR',
                    'remark' => 'Deposit'
                ];

                $scaled_amount = $params['amount'] * pow(10, 2);

                $signature = hash('sha256', "{$params['merchantCode']}&{$params['merchantKey']}&{$params['currency']}&{$params['paymentID']}&{$params['responseURL']}&$scaled_amount");

                $params['signature'] = strtoupper(base64_encode($signature));
                Log::info('ZPay sign: ' . $params['signature']);

                $response = Http::asForm()
                    ->post("$payment_gateway->payment_url/auth", $params);

                $responseData = $response->json();

                if (isset($responseData['status']) && $responseData['status'] == 200) {
                    // success → get the URL from data
                    $payment_url = $responseData['redirect_url'] ?? null;

                    if ($payment_url) {
                        break;
                    }

                    // code == 200 but url missing → throw exception
                    throw new Exception('Missing redirect URL in gateway response: ' . json_encode($responseData));
                }

                Log::info('ZPay response status: ' . $responseData['status']);
                Log::info('ZPay response message: ' . $responseData['message']);

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now()
                ]);

                // error case → throw exception with message
                throw new Exception('Gateway request failed: CODE - ' . $responseData['status'] . '; Message - ' . ($responseData['message'] ?? json_encode($responseData)));

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
}
