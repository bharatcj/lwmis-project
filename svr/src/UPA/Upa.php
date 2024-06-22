<?php

namespace LWMIS\UPA;

use LWMIS\Common\PostgreDB;

class Upa
{
  function saveUpa($data): array
  {
    $firm_id = $data->firm_id ?? null;
    $employer_name = $data->employer_name ?? null;
    $payment_year = $data->payment_year ?? null;
    $no_of_employees = $data->no_of_employees ?? null;
    $employee_contrib = $data->employee_contrib ?? null;
    $employer_contrib = $data->employer_contrib ?? null;
    $att_doc_id = $data->att_doc_id ?? null;
    $amount = $data->amount ?? null;

    $retVal = ['message' => "UPA Registration Can't be Saved"];

    $db = new PostgreDB();
    $db->Begin();
    try {
      if ($firm_id) {
        $sql = "select upa.gen_upa_reg_no($1) as upa_reg_no;";
        $db->Query($sql, array($firm_id));
        $upa_reg_no = $db->FetchAll()[0]['upa_reg_no'];

        $sql = "INSERT INTO upa.unpaid_accumulation(firm_id,att_doc_id,employer_name,
                                    payment_year, no_of_employees, employee_contrib,
                                    employer_contrib, amount, upa_reg_no)
                VALUES ($1,$2,$3,
                        $4,$5,$6,
                        $7,$8,$9)
                RETURNING id";

        $db->Query($sql, [$firm_id, $att_doc_id, $employer_name,
          $payment_year, $no_of_employees, $employee_contrib,
          $employer_contrib, $amount, $upa_reg_no]);
        $rows = $db->FetchAll();
        $retVal['rows'] = $rows;

        if (count($rows) > 0) {
          $retVal['id'] = intval($rows[0]['id']);
          $retVal['message'] = "UPA payments are made successfully";
        }

        $db->Commit();
      }
    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function saveUpaDoc($data): array
  {
    $retObj['message'] = ['Not Saved!:'];
    $firm_id = $data->firm_id ?? null;

    if ($firm_id) {
      $db = new PostgreDB();
      $db->Begin();
      try {
        $athmt = new \LWMIS\Doc\Attachment();
        $attachment_val = $athmt->saveAttachment($data,$db);
        $retObj['message'] = $attachment_val['message'];
        $retObj['id'] = $attachment_val['rows'][0]['id'];
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retObj;
    }
    return $retObj;
  }

  /*  function saveClaimAttachments($data): array
    {
      $retVal = [];
      $retVal['message'] = ['Claim & Firm Details are required !'];
      $claim_id = $data->claim_id ?? null;
      $firm_id = $data->firm_id ?? null;
      $db = new \LWMIS\Common\PostgreDB();

      if ($claim_id && $firm_id){
        //I'm getting firm_id is becz the document behave weirdly when creating new establishment.
        try {
          $db->Begin();
          $attc = new \LWMIS\Doc\Attachment();
          $retVal = $attc->saveAttachment($db, $data);
          $db->Commit();
        } catch (\Exception $e) {
          $db->RollBack();
          $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
      }
      return $retVal;
    }*/

  function getUpas($data): array
  {
    $db = new PostgreDB();
    $db->Begin();
    $params = [];
    try {
      /*$sql = 'SELECT id ,firm_id, employer_name, payment_year, no_of_employees, employee_contrib, employer_contrib,
       amount, cre_ts FROM upa.payments;';*/

      $sql = "select id, firm_id, upa_reg_no, att_doc_id, employer_name, payment_year,
                    no_of_employees, employee_contrib, employer_contrib, amount, cre_ts
                from upa.unpaid_accumulation";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      $retObj = $rows;
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

}
