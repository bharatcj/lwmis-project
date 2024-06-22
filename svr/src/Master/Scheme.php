<?php

namespace LWMIS\Master;

class Scheme
{
  function getSchemes($filter): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    $limit = isset($filter->limit) ? $filter->limit : null;
    $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

    $where_clause = "";
    $limit_offset = "";
    $limit_offset_as = "";
    $params = [];

    if (isset($filter->limit) && $filter->limit) {
      $params[] = $limit;
      $params[] = $offset;
      $limit_offset .= ' LIMIT $1 OFFSET $2';
      $limit_offset_as .= ', $1 AS limit, $2 AS offset';
    }

    if (isset($filter->code) && is_string($filter->code)) {
      $code = $filter->code;
      $params[] = $code;
      $where_clause .= ' AND a.code = $' . count($params);
    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (
                                  UPPER(a.name) like UPPER(' . $param_cnt . ') OR
                                  UPPER(a.code) like UPPER(' . $param_cnt . ')
                                 )';
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      // get actual data
      $sql = 'SELECT a.code,a.name as scheme_name,a.code as scheme_code,a.description, a.eff_sd, a.eff_ed,
                           COALESCE(b.is_del, true)AS is_del, a.is_active, is_fixed_amt, is_amt_edu_dependent, is_winning_position_dependent
                      from clm.schemes as a
                           LEFT OUTER JOIN LATERAL(
                                    SELECT false AS is_del
                                      FROM clm.claims AS b
                                     WHERE b.scheme_code = a.code
                                     LIMIT 1
                           )AS b ON TRUE
                     where true ' . $where_clause . '
                  ORDER BY a.code
                    ' . $limit_offset;
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = isset($r['id']) ? intval($r['id']) : null;
        $r['is_del'] = ($r['is_del'] == 't');
        $r['is_active'] = ($r['is_active'] == 't');
        $r['is_fixed_amt'] = ($r['is_fixed_amt'] == 't');
        $r['is_amt_edu_dependent'] = ($r['is_amt_edu_dependent'] == 't');
        $r['is_winning_position_dependent'] = ($r['is_winning_position_dependent'] == 't');
      }
      $retObj['rows'] = $rows;

      // get total rows
      if (!\is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT count(*) AS cnt' . $limit_offset_as . '
                        FROM mas.Schemes AS a
                        WHERE true ' . $where_clause;
//                var_dump($sql);
        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();
        foreach ($tot_rows as &$r) {
          $r['cnt'] = intval($r['cnt']);
        }
        $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
      } else {
        $retObj['tot_rows'] = ((!\is_null($offset)) ? $offset : 0) + \count($rows);
      }
    } catch (\Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }

    $db->DBClose();

    return $retObj;
  }


  function deleteScheme($data)
  {
    $retVal = [];
    $code = isset($data->code) ? $data->code : null;

    $db = new \LWMIS\Common\PostgreDB();
    $db->Begin();
    try {
      if (!is_null($code)) {
        $log = new \LWMIS\LOG\SchemeAction();
        $log->delete($db, $data);
        $sql = 'DELETE FROM clm.schemes WHERE code = $1';
        $db->Query($sql, [$code]);
        $retVal['message'] = "Scheme deleted successfully.";
      }
      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function saveScheme($data)
  {
    $retVal = ['message' => 'User cannot be saved.'];
    $name = isset($data->scheme_name) ? $data->scheme_name : null;
    $code = isset($data->code) ? strtoupper($data->code) : null;
    $scheme_code = isset($data->scheme_code) ? strtoupper($data->scheme_code) : null;
    $user_id = isset($data->user_id) ? $data->user_id : null;
    $description = isset($data->description) ? $data->description : null;
    $eff_sd = isset($data->eff_sd) ? $data->eff_sd : null;
    $eff_ed = isset($data->eff_ed) ? $data->eff_ed : null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (is_null($code)) {

        $sql = 'INSERT INTO clm.schemes (code, name, description, eff_sd, eff_ed )
                VALUES ($1, $2, $3, $4, $5)
                RETURNING code';
        $db->Query($sql, [$scheme_code, $name, $description, $eff_sd, $eff_ed]);
        $rows = $db->FetchAll();
        foreach ($rows as &$r) {
          $r['code'] = intval($r['code']);
        }
        if (count($rows) > 0) {
          $schemeAction = new \LWMIS\LOG\SchemeAction();
          $schemeAction->save($db, (object)[
            'scheme_code' => $scheme_code,
            'action_code' => 'SCHM_CRE',
            'user_id' => $user_id
          ]);
          $retVal['code'] = $rows[0]['code'];
          $retVal['message'] = "Scheme saved successfully.";
        }
      } else {
        $sql = 'UPDATE clm.schemes
                           SET name = $1 ,description = $2, eff_sd = $3, eff_ed = $4
                         WHERE code = $5';
        $db->Query($sql, [$name, $description, $eff_sd, $eff_ed, $code]);
        $schemeAction = new \LWMIS\LOG\SchemeAction();
        $schemeAction->save($db, (object)[
          'scheme_code' => $code,
          'action_code' => 'SCHM_UPD',
          'user_id' => $user_id
        ]);
        $retVal['message'] = "Scheme update successfully.";
      }
    } catch (\Exception $e) {
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function isMasterSchemeExist($data)
  {
    $where_clause = "";
    $params = array();
    // if (!is_null($id)) {
    //   $params[] = $id;
    //   $where_clause = 'AND email != $' . count($params);
    // }

    if (isset($data->scheme_name) && is_String($data->scheme_name)) {
      $scheme = '%' . $data->scheme_name . '%';
      $params[] = $scheme;
      $where_clause = ' AND (name like $' . count($params) . ' OR code like UPPER($' . count($params) . '))';
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM clm.schemes WHERE TRUE ' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function toggleStatus($data)
  {
    $retObj = ['message' => 'Invalid Status.'];
    $code = isset($data->code) ? $data->code : null;
    $is_active = (isset($data->is_active) && $data->is_active === true) ? 't' : 'f';
    $user_id = isset($data->user_id) ? $data->user_id : null;
    // var_dump($data);

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!is_null($code)) {

        $sql = "UPDATE clm.schemes SET is_active = $1 WHERE code = $2";
        $db->query($sql, [$is_active, $code]);

        $actAction = new \LWMIS\LOG\ActAction();
        if ($is_active === 't') {
          $actAction->save($db, (object)[
            'scheme_code' => $code,
            'action_code' => 'BUSS_NAT_ACTIVATED',
            'user_id' => $user_id
          ]);
        } else {
          $actAction->save($db, (object)[
            'scheme_code' => $code,
            'action_code' => 'BUSS_NAT_DEACTIVATED',
            'user_id' => $user_id
          ]);
        }

        $retObj['message'] = 'Scheme status changed successfully.';
      }

      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }
}
