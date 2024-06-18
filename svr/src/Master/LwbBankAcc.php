<?php

namespace LWMIS\Master;

class LwbBankAcc
{
  function saveLwbBankAcc($data): array
  {
    $retVal = ['message' => 'Bank Details cannot be saved.'];
    $acc_bank_acc_name = $data->acc_bank_acc_name ?? null;
    $acc_bank_acc_no = $data->acc_bank_acc_no ?? null;
    $acc_ifsc = $data->acc_ifsc ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    $db->Begin();
    try {
      if (!(is_null($acc_ifsc) && is_null($acc_bank_acc_no) && is_null($acc_bank_acc_name))) {

        $sql = "INSERT INTO mas.lwb_bank_acc (ac_no,ac_name,ifsc)
                VALUES ($1,$2,$3)
                RETURNING id";

        $db->Query($sql, [$acc_bank_acc_no, $acc_bank_acc_name, $acc_ifsc]);
        $rows = $db->FetchAll();

        $db->Commit();
        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
        }
        if (count($rows) > 0) {
          $retVal['id'] = $rows[0]['id'];
          $retVal['message'] = "Bank Details are saved successfully.";
        }

      } else {
        throw new \Exception("Required Data is not found!");
      }
    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retVal;
  }

  function getLwbBankAcc($data): array
  {
    $data = null;
    $params = [];

    $db = new \LWMIS\Common\PostgreDB();

    $db->Begin();
    try {
      $sql = "SELECT a.id,a.ac_name,a.ac_no,a.ifsc
                FROM mas.lwb_bank_acc as a;";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
    } catch (\Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);;
    }
    $db->DBClose();
    return $retObj;
  }

}
