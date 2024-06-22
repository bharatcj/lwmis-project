<?php

namespace LWMIS\Clm;

use Exception;
use LWMIS\Common\PostgreDB;

class Relationships
{
  function getRelationships($data): array
  {
    $allowed_schemes = $data->allowed_schemes ?? null;
    $where_clause = '';
    $params = [];

    if (!is_null($allowed_schemes)) {
      $params[] = $allowed_schemes;
      $where_clause .= 'AND $' . count($params) . '= ANY(a.allowed_schemes)';
    } else {
      $retObj['message'] = ['Claim Code is Not found'];
      return $retObj;
    }

    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = 'SELECT a.code,a.name,a.allowed_schemes
                FROM clm.relationships as a
                WHERE TRUE ' . $where_clause . ';';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

  function deleteRelationship($data): array
  {
    $code = $data->code ?? null;
    $db = new PostgreDB();
    if (!is_null($code))
      try {
        $db->Begin();

        $sql = 'DELETE FROM clm.relationships
                WHERE code = $1 AND
                    NOT EXISTS(
                        SELECT *
                        FROM clm.claims AS b
                        WHERE b.dp_relationship = $1
                    )
                RETURNING code;';

        $db->Query($sql, [$code]);
        $rows = $db->FetchAll();
        $retObj['rows'] = $rows;
        $retObj['message'] = 'Deleted 🗑️ Successfully';
        $db->Commit();
      } catch (Exception $e) {
        $retObj['message'] = 'Delete not allowed. 💀 Error: ' . $e->getMessage();
        $db->RollBack();
      }
    $db->DBClose();
    return $retObj;
  }

  function saveRelationship($data): array
  {
    $code = $data->rel_code ?? null;
    $name = $data->rel_name ?? null;
    $allowed_schemes = $data->rel_schemes ?? null;
    $remarks = $data->rel_remarks ?? null;

    $value = $this->getRelationship($data);
//    var_dump($value);
    $duplicate = false;
    foreach ($value as $v) {
      foreach ($v as $ve) {
//        print_r($ve['code']);
        if ($ve['code'] == $code) $duplicate = true;
      }
    }

    $params[] = $code;
    $params[] = $name;
    $params[] = $remarks;

    $arr_allowed_schemes = '';

//    var_dump($allowed_schemes);

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

    try {
      $db->Begin();

      if ($duplicate) {
        $sql = "UPDATE clm.relationships
                   SET code=$1,name = $2,remarks = $3,allowed_schemes=$arr_allowed_schemes
                 WHERE code
                  LIKE '$code'";
//        var_dump($sql);
      } else {
/*        $sql = "INSERT INTO clm.relationships (code,name,remarks,allowed_schemes)
                VALUES ($1,$2,$3,$arr_allowed_schemes)
                RETURNING code;";*/

        $sql = "INSERT INTO clm.relationships (code,name,allowed_schemes,remarks)
                VALUES ($1,$2,$arr_allowed_schemes,$3)
                RETURNING code;";
      }
//      var_dump($sql);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;
      $retObj['message'] = 'Relationship is Saved. 💾';
      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = '🚫 Not saved !!' . $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

  function getRelationship($data): array
  {
    $params = [];
    $db = new PostgreDB();

    /**    note : using TO_JSON is also applicable for this scenario
     *    to convert it into single scalar type to easily accessible
     *    array / objects.
     *
     *
     * The json data type stores an exact copy of the input text,
     * which processing functions must reparse on each execution;
     * while jsonb data is stored in a decomposed binary format that makes
     * it slightly slower to input due to added conversion overhead,
     * ut significantly faster to process, since no reparsing is needed.
     */
    try {
      $sql = 'SELECT a.code,
                a.name,
                TO_JSONB(a.allowed_schemes) AS allowed_schemes,
                COALESCE(e.is_del,TRUE) AS is_del
                FROM clm.relationships AS a
                    LEFT JOIN LATERAL (
                        SELECT FALSE AS is_del
                        FROM clm.claims AS b
                        WHERE b.dp_relationship = a.code
                        LIMIT 1
                    ) AS e ON (TRUE)';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['allowed_schemes'] = isset($r['allowed_schemes']) ? json_decode($r['allowed_schemes']) : null;
        $r['is_del'] = ($r['is_del'] == 't');
      }
//      var_dump('allowed_schemes = ',$rows);
      $retObj['rows'] = $rows;
    } catch (Exception $e) {
      $retObj['message'] = $e->getMessage();
    }
    return $retObj;
  }
}
