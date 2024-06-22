<?php

namespace LWMIS\Clm;

use Exception;
use LWMIS\Common\PostgreDB;

class QuestionBankSubjects
{
  function getQBSubjects($data): array
  {
    $class = $data->subCtrl ?? null;
    $where_clause = '';
    $params = [];

    if (!is_null($class)) {
      $params[] = $class;
      $where_clause .= 'AND $' . count($params) . ' = a.class';
    }

    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = 'SELECT *
            FROM clm.q_bank_subjects AS a
            WHERE TRUE ' . $where_clause . '
            ORDER BY code;';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      /**
       * below loop id used to convert the is_active form 't ' to 'true' statement
       */
      foreach ($rows as &$r) {
        $r['is_active'] = ($r['is_active'] == 't');
      }
      $reObj['rows'] = $rows;
    } catch (Exception $e) {
      $db->RollBack();
      $reObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $reObj;
  }

  function saveQBSubjects($data): array
  {
    $code = $data->code ?? null;
    $class = $data->class ?? null;
    $name = $data->name ?? null;
    $remarks = $data->remarks ?? null;

    $params = [$code, $class, $name, $remarks];

    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = "INSERT INTO clm.q_bank_subjects (code, class, name, remarks)
                VALUES ($1,$2,$3,$4);";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
      $retObj = ['message' => 'New Subjects are Saved Successfully'];
      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

  function updateQBSubjects($data): array
  {
    $is_active = (isset($data->is_active) && $data->is_active === true) ? 't' : 'f';
//    $status = $data->status ?? null;
    $code = $data->code ?? null;

//    $codes = $code ? 't':'f';
//    var_dump('status ',$status);

//    $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
//    var_dump('status and code = ', $status, $code);

    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = "UPDATE clm.q_bank_subjects SET is_active = $1 WHERE code = $2;";
      $db->Query($sql, [$is_active, $code]);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
      $retObj = ['message' => '✔️ Updated Successfully.'];
      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

}
