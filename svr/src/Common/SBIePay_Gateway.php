<?php

namespace LWMIS\Common;

class SBIePay_Gateway
{
  private string $TRANSACTION_URL = '';
  private string $DOUBLE_VERIFY_URL = '';
  private string $TRANSACTION_PAYOUT_API_URL = '';
  private string $TRANSACTION_MIS_API_URL = '';
  private string $RETURN_URL = '';
  private string $merchantId = '1000112';
  private string $operatingMode = 'DOM';
  private string $merchantCountry = 'IN';
  private string $merchantCurrency = 'INR';
  private string $otherDetails = 'NA';
  private string $merchantCustomerId = 'NA';
  private string $paymentMode = 'NB';
//  private string $accessMedium = 'NB';
  private string $accessMedium = 'ONLINE';
  private string $source = 'ONLINE';
  private string $transactionSource = 'ONLINE';
//  private string $successUrl = 'https://test.sbiepay.sbi/secure/sucess3.jsp';
//  private string $failUrl = 'https://test.sbiepay.sbi/secure/fail3.jsp';
  private string $aggregatorId = 'SBIEPAY';
  private string $key = '';


  public function __construct()
  {
    if (\LWMIS\Common\MyConfig::IS_TESTING) {
      $this->TRANSACTION_URL = 'https://test.sbiepay.sbi/secure/AggregatorHostedListener';
      $this->DOUBLE_VERIFY_URL = 'https://test.sbiepay.sbi/payagg/statusQuery/getStatusQuery';
      $this->key = 'A7C9F96EEE0602A61F184F4F1B92F0566B9E61D98059729AD3229F882E81C3A';
      $this->TRANSACTION_PAYOUT_API_URL = 'https://test.sbipay.sbi/payagg/transactionPayoutAPI/getTransactionPayoutAPI';
      $this->TRANSACTION_MIS_API_URL = 'https://test.sbiepay.sbi/payagg/MISSettleReport/transactionMISAPI';
      $this->RETURN_URL = '';
    } else {
      $this->TRANSACTION_URL = '';
      $this->DOUBLE_VERIFY_URL = '';
      $this->key = '';
      $this->TRANSACTION_PAYOUT_API_URL = 'https://www.sbipay.sbi/payagg/transactionPayoutAPI/getTransactionPayoutAPI';
      $this->TRANSACTION_MIS_API_URL = 'https://www.sbiepay.sbi/payagg/MISSettleReport/transactionMISAPI';
      $this->RETURN_URL = '';
    }
  }

  public function getTransactionUrl(): string
  {
    return $this->TRANSACTION_URL;
  }

  /**
   * @return string
   */
  public function getMerchantId(): string
  {
    return $this->merchantId;
  }

  /**
   * @throws \Exception
   */
  public function decrypt($cipherText): array
  {
    $algo = 'aes-128-cbc';
    $iv = substr($this->key, 0, 16);
    $cipherText = base64_decode($cipherText);

    $plain_text = openssl_decrypt(
      $cipherText,
      $algo,
      $this->key,
      OPENSSL_RAW_DATA,
      $iv
    );

    if (!$plain_text) {
      self::log_sbi_tran('Warning', 'Decrypt Failed', $plain_text);
      throw new \Exception("Decrypt Failed", 1);
    }

    self::log_sbi_tran('Info', 'Decrypted', $plain_text);
    return explode("|", $plain_text);
  }

  /**
   * @throws \Exception
   */
  static function log_sbi_tran($msgType, $msgGroup, $message): void
  {
    $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
    if (!file_exists($file_path)) {
      mkdir($file_path, 0777, true);
    }
    $log_file_name = $file_path . "sbi.transactions-" . $date->format('Y-m-d') . ".log";

    $txt = "[{$date->format('Y-m-d H:i:s A')}] [{$msgType}] [{$msgGroup}] [{$message}]" . PHP_EOL;
    file_put_contents($log_file_name, $txt, FILE_APPEND);
  }

  public function getEncryptTrans(int $totalDueAmount, string $merchantOrderNo, string $otherDetails = 'NA'): string//$otherDetails is for selected_years
  {
    $gn = new GeneralFunctions();
    $local_domain = $gn->getRequestOrigin();
    $allowed_domains = $gn->getAllowedDomains();

    if (in_array(strtolower($local_domain), $allowed_domains)) {//<- is in development stage ?
//    $response_url = 'http://data.lwmis.in/svr/payment_response.php';
      $response_url = 'http://data.lwmis.in/svr/payment_response.php';
    } else {
//      $response_url = 'https://lwmis.tnlwb.broadline.co.in/svr/payment_response.php';
      $response_url = 'https://lwmis.lwb.tn.gov.in/svr/payment_response.php';
    }

    $encTrans = $this->merchantId . '|' .
      $this->operatingMode . '|' .
      $this->merchantCountry . '|' .
      $this->merchantCurrency . '|' .
      $totalDueAmount . '|' .
//      $this->otherDetails . '|' .
      $otherDetails . '|' .
      $response_url . '|' .//success_url
      $response_url . '|' .//fail_url
//      'https://test.sbiepay.sbi/secure/fail3.jsp' . '|' .
      $this->aggregatorId . '|' .
      $merchantOrderNo . '|' .
      $this->merchantCustomerId . '|' .
      $this->paymentMode . '|' .
      $this->accessMedium . '|' .
      $this->transactionSource;

    return $this->encrypt($encTrans);
  }

  public function encrypt($data): string
  {
    $algo = 'aes-128-cbc';

    $iv = substr($this->key, 0, 16);
    $cipherText = openssl_encrypt(
      $data,
      $algo,
      $this->key,
      OPENSSL_RAW_DATA,
      $iv
    );

    return base64_encode($cipherText);
  }

  public function doubleVerify(mixed $merchantOrderNo, mixed $amount = null, mixed $atrn = null): array
  {
//    queryRequest = ATRN|Merchant ID|Merchant Order Number|Amount

//    Params required for DV,

//    queryRequest
//    aggregatorId
//    merchantId

//    ATRN - sbiepay's transaction number.

//    queryRequest(with ATRN) = ATRN|Merchant ID|Merchant Order Number|Amount
//    queryRequest(without ATRN) = |Merchant ID|Merchant Order Number|Amount
//    queryRequest(without Amount) = ATRN|Merchant ID|Merchant Order Number|

    $queryRequest = $atrn . "|" . $this->merchantId . "|" . $merchantOrderNo . "|" . $amount;
    $post_param = "queryRequest=" . $queryRequest . "&aggregatorId=" . $this->aggregatorId . "&merchantId=" . $this->merchantId;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->DOUBLE_VERIFY_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return explode("|", $result);
  }

  public function transactionPayoutAPI($data)
  {
    $date = $data->date ?? date('dmY');
    $curr_date = date('dmY');
    $queryRequest = $this->operatingMode . '|' . $this->merchantCountry . '|' . $this->merchantCurrency . '|' . '14072024';
    $post_param = "queryRequest=" . $queryRequest . "&aggregatorId=" . $this->aggregatorId . "&merchantId=" . $this->merchantId;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->TRANSACTION_PAYOUT_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

    var_dump('vd=', $result);

    curl_close($ch);
    return $result;
  }

  public function transactionMIS_API($data): array
  {
//    $transactionStatus = 'SUCCESS';//blank transaction status will fetch all the payment details.
    $transactionStatus = 'SUCCESS^PENDING^BOOKED';//blank transaction status will fetch all the payment details.
    $atrn = '';
    $gatewayTraceNo = '';
    $arrn = '';
    $merchant_order_no = '';
    $queryRequest = $this->aggregatorId . '|' . $this->merchantId . '|' . $this->operatingMode . '|' . $this->merchantCountry .
      '|' . $this->merchantCurrency . '|' . date('dmY') . '|' . $transactionStatus . '|' . $atrn . '|' . $merchant_order_no . '|' . $gatewayTraceNo . '|' . $arrn;

    $post_param = "queryRequest=" . $queryRequest;
//    $post_param = "queryRequest=" . $this->encrypt($queryRequest);
//    $post_param = $this->encrypt($post_param);
//    var_dump('qreq', $queryRequest);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->TRANSACTION_MIS_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
//    var_dump('vd=', $result);

    return $this->decrypt($result);
  }


  function decrypt_sbi_data($encdata)
  {
    $decode_str = base64_decode($encdata);
    $enc_str = substr($decode_str, 16, strlen($decode_str) - 32);
    $tag = substr($decode_str, -16);
    $str = openssl_decrypt($enc_str, $this->SBI_CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $this->IV, $tag);

    if ($str === false) {
      self::log_sbi_tran('Warning', 'Decrypt Failed', $encdata);
      throw new \Exception("Decrypt Failed", 1);
    }

    self::log_sbi_tran('Info', 'Decrypted', $str);
    $data = explode("|", $str);
    $ret_val = [];
    foreach ($data as $v) {
      $d = explode("=", $v);
      $ret_val[$d[0]] = $d[1];
    }
    return $ret_val;
  }

}
