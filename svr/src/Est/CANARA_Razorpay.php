<?php

namespace LWMIS\Est;

use Exception;
use LWMIS\Common\MyConfig;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\BadRequestError;
use Razorpay\Api\Errors\SignatureVerificationError;

class CANARA_Razorpay
{
  private Api $api;

  function __construct()
  {
    $this->api = new Api(PG_RP_KEY_ID, PG_RP_KEY_SECRET);
  }

  /**
   * @throws Exception
   */
  function createOrder(string $trnx_no_own, string $trnx_amt): array
  {
    $retObj = [];
    $data = [
      'amount' => ($trnx_amt * 100),
      'currency' => PG_RP_CURRENCY,
      'receipt' => $trnx_no_own,
      'payment_capture' => 0,
    ];

    $this->log(json_encode($data), 'createOrder', true);

    try {
      $order = $this->api->order->create($data);
      $this->log(json_encode($order->toArray()), 'createOrder', false);
    } catch (\Exception $e) {
      $this->log((string)$e, 'createOrder', true);
      $retObj['failure_reason'] = $e->getMessage();
    }

    if (isset($order) && $order->receipt === $trnx_no_own) {
      $retObj['trnx_no_own'] = $trnx_no_own;
      $retObj['trnx_amt'] = $trnx_amt;
      $retObj['rzp_order_id'] = $order->id;
      $retObj['rzp_order_tsp'] = $order->created_at;
      $retObj['rzp_key'] = PG_RP_KEY_ID;
      $retObj['rzp_currency'] = PG_RP_CURRENCY;

      $retObj['raw_data'] = json_encode($order->toArray());
    } else {
      throw new Exception("Issue with PG / receipt / order id");
    }
    return $retObj;
  }


  function capturePayment($razorpay_payment_id, $trnx_amt, $razorpay_order_id, $razorpay_signature, $is_direct = false): array
  {
    $this->log(json_encode([$razorpay_payment_id, $trnx_amt, $razorpay_order_id, $razorpay_signature, $is_direct]), 'capturePayment', true);

    $payment = null;
    $retObj = [];

    try {
      if ($is_direct === false) {
//        var_dump($razorpay_payment_id);
        $payment = $this->api->payment->fetch($razorpay_payment_id)->capture(['amount' => ($trnx_amt * 100)]);
//          var_dump('capture payments=',$payment);
//        not works for some reason
//        $generated_signature = hash_hmac('sha256',($trnx_no_own . " | " . $razorpay_payment_id),PG_RP_KEY_SECRET);
//        if ($generated_signature === $razorpay_signature){
//          //payment is successful
//        }
        $attributes = ['razorpay_signature' => $razorpay_signature, 'razorpay_payment_id' => $razorpay_payment_id, 'razorpay_order_id' => $razorpay_order_id];
        $this->api->utility->verifyPaymentSignature($attributes);
      }
      // Capture Payment
    } catch (SignatureVerificationError $e) {
      $this->log((string)$e, 'capturePayment', false);
      $retObj['status'] = 'Error';
      $retObj['error_description'] = $e->getMessage();
    } catch (BadRequestError $e) {
      $this->log((string)$e, 'capturePayment', false);
      if ($e->getMessage() === 'This payment has already been captured') {
        // If already captured then get payment details
        $payment = $this->api->payment->fetch($razorpay_payment_id);
      } else {
        $retObj['status'] = 'Error';
        $retObj['error_description'] = $e->getMessage();
      }
    } catch (\Exception $e) {
      $this->log((string)$e, 'capturePayment', false);
      $retObj['status'] = 'Error';
      $retObj['error_description'] = $e->getMessage();
    }

    if (isset($payment)) {
      $this->log(json_encode($payment->toArray()), 'capturePayment', false);
      $retObj['status'] = $payment->status;
      $retObj['error_code'] = $payment->error_code;
      $retObj['error_description'] = $payment->error_description;
      $retObj['created_at'] = $payment->created_at;
      $retObj['method'] = $payment->method;
      $retObj['raw_data'] = json_encode($payment->toArray());
    }

    return $retObj;
  }

  function getPaymentsByOrderId($rzp_order_id): array//Fetch Payments Based on Orders
  {
    $retObj = ['payments' => null, 'raw_data' => null];
    $retArray = [];

    try {
      $this->log(json_encode(['rzp_order_id' => $rzp_order_id]), 'getPayments', true);
      $payments = $this->api->order->fetch($rzp_order_id)->payments();//Fetch Payments Based on Orders
      $this->log(json_encode($payments->toArray()), 'getPayments', false);
      $retObj['raw_data'] = json_encode($payments->toArray());

      $d = [];
      foreach ($payments->items as $p) {
        $d['trnx_no_gw'] = $p->id;
        $d['trnx_dt_gw'] = $p->created_at;
        $d['status'] = $p->status;
        $d['error_code'] = $p->error_code;
        $d['error_description'] = $p->error_description;
        $retArray[] = $d;
      }

    } catch (\Exception $e) {
      $d = [];
      $d['status'] = 'Error';
      $d['error_description'] = $e->getMessage();
      $retArray[] = $d;
    }

    $retObj['payments'] = $retArray;
    return $retObj;
  }

  private function log(string $message, string $method_name, bool $is_request): void
  { //  = false
//    $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
    $date = (new MyConfig())->date;//todo:check default time zone check
    $date_str = $date->format('Y-m-d g:i:s A"');
    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
    if (!file_exists($file_path)) {
      mkdir($file_path, 0777, true);
    }
    $log_file_name = $file_path . "gw-razorpay-" . $date->format('Y-m-d') . ".log";

    $is_request_text = $is_request ? 'Request' : 'Response';
    $txt = "[{$date_str}] [{$method_name}] [{$is_request_text}] [{$message}]" . PHP_EOL;
    file_put_contents($log_file_name, $txt, FILE_APPEND);
  }


}
