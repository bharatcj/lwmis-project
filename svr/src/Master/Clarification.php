<?php

namespace LWMIS\Master;

class Clarification
{
  function saveClarification($data): array
  {
//    var_dump($data);
    $retVal = ['message' => 'Clarification cannot be saved.'];
    $id = $data->id ?? null;
    $firm_id = $data->firm_id ?? null;
    $claim_id = $data->claim_id ?? null;
    $remarks = $data->remarks ?? null;
    $user_id = $data->user_id ?? null;
//    $o_designation_code = $data->o_designation_code ?? null;
    $from_designation_code = $data->from_designation_code ?? null;
    $to_designation_code = $data->to_designation_code ?? null;
    $status = $data->status ?? null;
    $action_code = $data->action_code ?? null;
    $email = $data->email ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();
//      var_dump($id);
//      if (is_null($id)) {
//        $sql = 'INSERT INTO mas.clarifications
//                (firm_id, claim_id, remarks, user_id, o_designation_code, ent_by, action_code, status, cre_ts)
//                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, NOW())
//                RETURNING id';

      $sql = "INSERT INTO mas.verifications
                (firm_id, claim_id, remarks, user_id, from_designation_code,to_designation_code,cre_by, action_code, status, cre_ts)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,NOW())
                RETURNING id";

      $db->Query($sql, [$firm_id, $claim_id, $remarks, $user_id, $from_designation_code, $to_designation_code, $email, $action_code, $status]);
      $rows = $db->FetchAll();
//      var_dump('count===', count($rows));

      if ((isset($rows) && count($rows) == 1) && !is_null($claim_id)) {
        if ($status == 'CLR') {
          $status = 'REC';
        }
        $sql = "UPDATE clm.claims
                   SET status = $2
                 WHERE id = $1;";
        $db->Query($sql, [$claim_id, $status]);
        $db->FetchAll();
      }

      $db->Commit();
//      var_dump($rows);
//        foreach ($rows as &$r) {
//          $r['id'] = intval($r['id']);
//        }
//      count('rows == =',$rows);

      if (count($rows) > 0) {
        $db->Commit();
        $retVal['message'] = "clarification saved successfully.";
      }
//      }

    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  public function getClarifications($filter): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
//    $limit = $filter->limit ?? null;
//    $offset = $limit * ($filter->offset ?? 0);

    $where_clause = "";
    $params = [];
//    $params[] = $limit;
//    $params[] = $offset;

    if (isset($filter->firm_id) && $filter->firm_id > 0) {
      $firm_id = $filter->firm_id;
      $params[] = $firm_id;
      $where_clause .= ' AND a.firm_id = $' . count($params);
    }

    if (isset($filter->claim_id) && $filter->claim_id > 0) {
      $claim_id = $filter->claim_id;
      $params[] = $claim_id;
      $where_clause .= ' AND a.claim_id = $' . count($params);
    }

//    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
//      $search_text = '%' . $filter->search_text . '%';
//      $params[] = $search_text;
//      $param_cnt = '$' . count($params);
//      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
//    }

    if (isset($filter->action_code) && strlen($filter->action_code) > 0) {
      $action_code = $filter->action_code;
      $params[] = $action_code;
      $where_clause .= ' AND a.action_code = $' . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      // get actual data
//      $sql = 'SELECT a.id,
//                     a.firm_id,
//                     a.claim_id,
//                     a.user_id,
//                     a.action_code,
//                     a.o_designation_code,
//                     a.status,
//                     a.cre_ts,
//                     a.ent_by,
//                     a.remarks,
//                     b.name AS designation_name
//                     FROM mas.clarifications AS a
//                        INNER JOIN mas.designations AS b ON ( a.o_designation_code = b.code )
//                     WHERE true ' . $where_clause . '
//                     ORDER BY a.id ASC
//                     LIMIT $1 OFFSET $2;';

      $sql = "SELECT a.id,
                     a.firm_id,
                     a.claim_id,
                     a.user_id,
                     a.action_code,
                     a.from_designation_code,
                     a.status,
                     a.cre_ts,
                     a.cre_by,
                     a.remarks,
                     b.name AS designation_name
              FROM mas.verifications AS a
                  INNER JOIN mas.designations AS b ON ( a.from_designation_code = b.code )
              WHERE true $where_clause
              ORDER BY a.id;";

      $db->Query($sql, $params);
//      var_dump($sql);
      $rows = $db->FetchAll();
//            var_dump($rows);
      foreach ($rows as &$r) {
        $r['id'] = isset($r['id']) ? intval($r['id']) : null;
        $r['firm_id'] = isset($r['firm_id']) ? intval($r['firm_id']) : null;
        $r['user_id'] = isset($r['user_id']) ? intval($r['user_id']) : null;
        $r['claim_id'] = isset($r['claim_id']) ? intval($r['claim_id']) : null;
      }

      $retObj['rows'] = $rows;

      /*      // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
              $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                          from est.clarifications as a
                          where true ' . $where_clause;
              $db->Query($sql, $params);
              $tot_rows = $db->FetchAll();
              foreach ($tot_rows as &$r) {
                $r['cnt'] = intval($r['cnt']);
              }

              $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
            } else {
              $retObj['tot_rows'] = ((!\is_null($offset)) ? $offset : 0) + \count($rows);
            }*/

    } catch (\Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retObj;
  }
}
