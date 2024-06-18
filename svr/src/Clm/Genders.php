<?php

namespace LWMIS\Clm;

use LWMIS\Common\PostgreDB;

class Genders
{
  function getGenders($data): array
  {
    $allowed_schemes = $data->allowed_schemes ?? null;
    $where_clause = '';
    $params = [];

    if (!is_null($allowed_schemes)) {
      $params[] = $allowed_schemes;
      $where_clause .= 'AND $' . count($params) . ' = ANY(allowed_schemes)';
    }

    $db = new PostgreDB();
    try {
      $sql = "SELECT code,name,to_jsonb(allowed_schemes) as allowed_schemes,remarks
                FROM clm.genders
               WHERE TRUE $where_clause";
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['allowed_schemes'] = isset($r['allowed_schemes']) ? json_decode($r['allowed_schemes']) : null;
      }
      $reObj['rows'] = $rows;
    } catch (\Exception $e) {
      $reObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $reObj;
  }

  function changeAllowedSchemesClaimGender($data): array
  {
    $retObj['message'] = 'Not saved !!';
    $allowed_schemes = $data->allowed_schemes ?? null;
    $remarks = $data->remarks ?? null;
    $code = $data->code ?? null;

    $params[] = $remarks;
    $params[] = $code;

    $arr_allowed_schemes = '';
    if (gettype($allowed_schemes) === 'array' && (count($allowed_schemes) > 0)) {
      $arr_allowed_schemes .= 'ARRAY[';
      foreach ($allowed_schemes as &$cf) {
        if (isset($cf) && gettype($cf) === 'string') {
          $params[] = $cf;
          $arr_allowed_schemes .= '$' . count($params) . ', ';
        }
      }
      $arr_allowed_schemes = rtrim($arr_allowed_schemes, ', ') . ']';
    } else {
      $arr_allowed_schemes = 'NULL';
    }

    $db = new PostgreDB();
    $db->Begin();

    if (!is_null($code)){
      try {
        $sql = "UPDATE clm.genders
                   SET allowed_schemes=$arr_allowed_schemes,remarks = $1
                 WHERE code
                  LIKE $2
             RETURNING code";
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        if (isset($rows) && count($rows) > 0) {
          $retObj['rows'] = $rows;
          $retObj['message'] = 'Allowed schemes are updated successfully.';
        }
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retObj['message'] = 'Not saved !!' . $e->getMessage();
      }
    }
    $db->DBClose();
    return $retObj;
  }

}
