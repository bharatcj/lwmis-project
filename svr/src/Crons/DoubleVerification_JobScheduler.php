<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
\LWMIS\Common\ErrorHandler::defineErrorLevel();

doubleVerifyPay();

function doubleVerifyPay(): void
{
  $db = new \LWMIS\Common\PostgreDB();
  $db->Begin();
  try {
    $sql = "select pm.id,
                   pm.trnx_no_own
              from est.payments as pm
         left join est.receipts rp on pm.id = rp.payment_id
             where pm.status = 'I'";

    $db->Query($sql, []);
    $trnx_no_own = $db->FetchAll();

    $dv = new \LWMIS\Est\Payment();
    foreach ($trnx_no_own as $item) {
      $dv_ary['trnx_no_own'] = $item['trnx_no_own'];
      $dv->doubleVerifyPayment((object)$dv_ary);
    }

    (new \LWMIS\Common\GeneralFunctions())->jobSchedulerLog(__METHOD__, 'Double Verification is Executed Successfully.');

  } catch (\Exception $e) {
    (new \LWMIS\Common\GeneralFunctions())->jobSchedulerLog(__METHOD__, 'Double Verification is Executed with error: ' . $e);
    echo \LWMIS\Common\ErrorHandler::custom($e);
  } finally {
    $db->DBClose();
  }
}
