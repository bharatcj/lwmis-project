<?php

namespace LWMIS\Clm;

use LWMIS\Common\ErrorHandler;
use LWMIS\Common\PostgreDB;

class Disbursements
{
  function saveDisbursements($data): array
  {
    $disb_claim = $data->disb_claim ?? null;
    $acc_id = $data->acc_id ?? null;
    $ref_message = $data->ref_message ?? null;

    $retObj = ['message' => 'Disbursement aren\'t ❌ saved.'];

    $db = new PostgreDB();
    $db->Begin();

    try {
      $tot_amt = 0;
      foreach ($disb_claim as $val) {
        $tot_amt = $tot_amt + $val->claim_amount;
      }

      $sql = "insert into clm.disbursements (acc_id, tot_amt, status, ref_message)
                values ($1,$2,$3,$4) returning id";

      $db->Query($sql, [$acc_id, $tot_amt, 'A', $ref_message]);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        $disb_id = intval($rows[0]['id']);
        foreach ($disb_claim as $val) {
          $sql = "insert into clm.disbursement_det(disbursement_id, claim_id, amt)
                    values ($1,$2,$3)";
          $db->Query($sql, [$disb_id, $val->id, $val->claim_amount]);
          $rows = $db->FetchAll();
        }
      }
      $retObj['rows'] = $rows;

      $db->Commit();
      $retObj = ['message' => '👍...Disbursements Are Saved.'];

    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retObj;
  }

  function getBankDisbursalHistory(): array
  {
    $db = new PostgreDB();
    $params = [];
    try {
      $sql = "select d.id,
                     d.bill_no,
                     d.bill_dt,
                     d.acc_id,
                     c.name  as board_bank,
                     c.ac_no as board_ac,
                     c.ifsc,
                     d.tot_amt
              from clm.disbursements as d
                       left join (select lbc.id, b.name, lbc.ac_no, bb.address, bb.ifsc
                                  from mas.lwb_bank_acc as lbc
                                           inner join mas.bank_branches bb on bb.ifsc = lbc.ifsc
                                           inner join mas.banks b on b.code = bb.bank_code) as c
                                 ON c.id = d.acc_id
              order by d.bill_dt desc ";
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }
//      $retObj[] = $rows;
      $retObj['rows'] = $rows;
    } catch (\Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function getClaimDisbursalHistory($data): array
  {
    $id = $data->disbursement_id ?? null;
    $db = new PostgreDB();
    $db->Begin();
    try {
      $sql = "SELECT cc.claim_reg_no,
                     cc.e_name,
                     cc.acc_bank_acc_name,
                     cc.acc_bank_acc_no,
                     cc.acc_ifsc,
                     dd.amt
                FROM clm.disbursement_det as dd
           LEFT JOIN clm.claims as cc ON dd.claim_id = cc.id
               WHERE dd.disbursement_id = $1";

      $db->Query($sql, [$id]);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function getDisbursedClaims($disb_id): array
  {
    $db = new PostgreDB();
    $db->Begin();
    $disb_claims = [];
    try {
      $sql = "
    select c.id,
           c.e_name,
           c.claim_reg_no,
           c.claim_amount,
           d.acc_id as disb_acc_id,
           c.acc_bank_acc_name,
           c.acc_bank_acc_no,
           c.acc_ifsc,
           b_branch.name as branch_name,
           bnk.name as bank_name,
           d.ref_message
      from clm.claims as c
right join (select dd.*,
                   ds.*
              from clm.disbursement_det as dd
              join clm.disbursements as ds on ds.id = dd.disbursement_id) as d
        on d.claim_id = c.id
 LEFT JOIN mas.bank_branches AS b_branch
        ON (b_branch.ifsc = c.acc_ifsc)
 LEFT JOIN mas.banks as bnk
        ON (bnk.code = b_branch.bank_code)
     where d.disbursement_id = $1;
      ";

      $db->Query($sql, [$disb_id]);
      $disb_claims = $db->FetchAll();

      foreach ($disb_claims as &$disb_claim) {
        $disb_claim['claim_amount'] = $disb_claim['claim_amount'] == null ? 0 : $disb_claim['claim_amount'];
      }
    } catch (\Exception $e) {
      $db->RollBack();
      ErrorHandler::custom($e);
    }
    return $disb_claims;
  }

}
