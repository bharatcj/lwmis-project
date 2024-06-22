<?php
  namespace LWMIS\Master;

  class Counter {
    function getCounters($filter) {
      $retObj = ['rows' => [], 'tot_rows' => 0];
      $limit = isset($filter->limit)?$filter->limit:null;
      $offset = $limit * (isset($filter->offset)?$filter->offset:0);
      $is_strict = (isset($filter->is_strict) && $filter->is_strict == true)?true:false;

      $where_clause = "";
      $params = [];
      $params[] = $limit;
      $params[] = $offset;

      if (isset($filter->id) && $filter->id > 0) {
        $id = $filter->id;
        $params[] = $id;
        $where_clause .= ' AND a.id = $'.count($params);
      }

      if (isset($filter->district_id) && $filter->district_id != 'ALL') {
        $district_id = $filter->district_id;
        $params[] = $district_id;
        $where_clause .= ' AND z.zone_no = $'.count($params);
      } else {
        if ($is_strict) {
          $where_clause .= ' AND a.zone_no IS NULL';
        }
      }

      if (isset($filter->ward_no) && $filter->ward_no != 'ALL') {
        $ward_no = $filter->ward_no;
        $params[] = $ward_no;
        $where_clause .= ' AND w.ward_no = $'.count($params);
      } else {
        if ($is_strict) {
          $where_clause .= ' AND a.ward_no IS NULL';
        }
      }

      if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
        $search_text = '%'.$filter->search_text.'%';
        $params[] = $search_text;
        $param_cnt = '$'.count($params);
        $where_clause .= ' AND (
                                UPPER(a.counter_no) like UPPER('.$param_cnt.') OR
                                UPPER(COALESCE(z.zone_name, \'\')) like UPPER('.$param_cnt.') OR
                                UPPER(COALESCE(w.ward_name, \'\')) like UPPER('.$param_cnt.')
                               )';
      }

      if (!(isset($filter->include_inactive) && $filter->include_inactive === true)) {
        $where_clause .= ' AND a.is_active = true';
      }

      $db = new \LWMIS\Common\PostgreDB();
      try {
        // get actual data
        $sql = 'SELECT a.id, a.seq_no, a.counter_no, a.is_active, a.counter_type,
                       a.c_user_id, c.name AS c_user_name, c.designation_code AS c_user_designation_code,
                       d.name AS c_user_designation_name, a.c_start_ts, a.remarks,
                       b.id as district_id,b.name as district_name,COALESCE(e.is_del,f.is_del, true) AS is_del
                  FROM mas.counters AS a
                       LEFT OUTER JOIN mas.districts AS b ON (b.id = a.district_id)
                       LEFT OUTER JOIN mas.users AS c ON (c.id = a.c_user_id)
                       LEFT OUTER JOIN mas.designations AS d ON (d.code = c.designation_code)
                       LEFT OUTER JOIN LATERAL (
                                SELECT false AS is_del
                                  FROM est.receipt_seq_nos AS e
                                 WHERE e.counter_id = a.id
                                 LIMIT 1
                       ) AS e ON (true)
                       LEFT OUTER JOIN LATERAL (
                                SELECT false AS is_del
                                  FROM est.receipts AS f
                                 WHERE f.counter_id = a.id
                                 LIMIT 1
                       ) AS f ON (true)
                 WHERE true' . $where_clause . '
                 ORDER BY b.id NULLS FIRST, a.seq_no
                 LIMIT $1 OFFSET $2';
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        foreach($rows as &$r) {
          $r['id'] = intval($r['id']);
          $r['seq_no'] = intval($r['seq_no']);
          $r['is_active'] = ($r['is_active'] == 't');
          $r['is_del'] = ($r['is_del'] == 't');
          $r['c_user_id'] = intval($r['c_user_id']);
        }
        $retObj['rows'] = $rows;

        // get total rows
        if (!\is_null($limit) && count($rows) == $limit) {
          $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    FROM mas.counters AS a
                       LEFT OUTER JOIN mas.users AS u ON (u.id = a.c_user_id)
                       LEFT OUTER JOIN mas.designations AS b ON (b.code = u.designation_code)
                  WHERE true'.$where_clause;
          $db->Query($sql, $params);
          $tot_rows = $db->FetchAll();
          foreach($tot_rows as &$r) {
            $r['cnt'] = intval($r['cnt']);
          }

          $retObj['tot_rows'] = (count($tot_rows) > 0)?$tot_rows[0]['cnt']:count($rows);
        } else {
          $retObj['tot_rows'] = ((!\is_null($offset))?$offset:0) + \count($rows);
        }
      } catch(\Exception $e) {
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();

      return $retObj;
    }

    function getAvailableCounters($filter) {
      $ward_no = isset($filter->ward_no)?$filter->ward_no:null;
      $zone_no = (isset($filter->zone_no) && is_null($ward_no))?$filter->zone_no:null;

      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'SELECT a.id, a.seq_no, a.counter_no, a.counter_type, a.remarks
                FROM mas.counters AS a
               WHERE a.is_active = true AND a.c_user_id IS NULL
                     AND COALESCE(a.zone_no, \'00\') = COALESCE($1, \'00\')
                     AND COALESCE(a.ward_no, \'000\') = COALESCE($2, \'000\')
               ORDER BY a.seq_no';
      $db->Query($sql, [$zone_no, $ward_no]);
      $rows = $db->FetchAll();
      foreach($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['seq_no'] = intval($r['seq_no']);
      }
      $db->DBClose();

      return $rows;
    }

    function save($data) {
      $retObj = [ 'message' => 'Counter cannot be saved.' ];
      $district_id = (isset($data->district_id) && $data->district_id!='ALL') ?$data->district_id:null;
      $users_id = isset($data->users_id)?$data->users_id:null;
      $user_id = isset($data->user_id)?$data->user_id:null;
      $id = isset($data->id)?$data->id:null;

      $params = array();
      $params[] = $district_id;
      $params[] = $users_id;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (is_null($id)) {
          $sql = 'INSERT INTO mas.counters (district_id, seq_no, counter_no, counter_type, c_user_id)
                  (SELECT $1::INT AS district_id, COUNT(*)+1 AS seq_no,
                          CASE WHEN $1 IS NULL THEN \'HO/\'
                               WHEN $1 IS NOT NULL THEN CONCAT(\'D\', $1, \'/\')
                               WHEN $1 IS NOT NULL THEN CONCAT(\'Z\', $1, \'/\') ELSE null
                          END || (COUNT(*)+1)::VARCHAR AS counter_no,
                          CASE WHEN $1::INT IS NULL THEN \'HOC\'
                               WHEN $1::INT IS NOT NULL THEN \'DOC\'
                               ELSE null
                          END AS counter_type,
                          $2::INT AS c_user_id
                     FROM mas.counters
                    WHERE district_id = $1)
                  RETURNING id';
          $db->Query($sql, $params);
          $rows = $db->FetchAll();
          foreach ($rows as &$r) {
            $r['id'] = intval($r['id']);
          }
          if (count($rows) > 0) {
            $retObj['id'] = $id = $rows[0]['id'];

            $counterAction = new \LWMIS\LOG\CounterAction();
            $counterAction->save($db, (object)[
              'counter_id' => $id,
              'action_code' => 'COUNTER_CREATED',
              'user_id' => $user_id
            ]);

            $retObj['message'] = "Counter saved successfully.";
          }
        } // else {
        //   $params[] = $id;
        //   $sql = 'UPDATE mas.counters SET zone_no = $1, zone_no = $2 WHERE id = $3';
        //   $db->Query($sql, $params);
        //   $retObj['message'] = "Counter saved successfully.";
        // }

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retObj;
    }

    function delete($data) {
      $retObj = [];
      $id = isset($data->id)?$data->id:null;

      if (!is_null($id)) {
        try{
            $db = new \LWMIS\Common\PostgreDB();

            $counterAction = new \LWMIS\LOG\CounterAction();
            $counterAction->delete($db, (object)[
              'counter_id' => $id
            ]);

            $sql = 'DELETE FROM mas.counters WHERE id = $1';
            $db->Query($sql, [$id]);
            $retObj['message'] = "Counter deleted successfully.";
          } catch(\Exception $e) {
            $db->RollBack();
            $retObj['message'] = $e->getMessage();
          }

        $db->DBClose();
      }
      return $retObj;
    }

    function toggleStatus($data) {
      $retObj = ['message' => 'Invalid Counter.'];
      $id = isset($data->id)?$data->id:null;
      $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
      $user_id = isset($data->user_id)?$data->user_id:null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (!is_null($id)) {

          $sql = "UPDATE mas.counters SET c_user_id = null WHERE id = $1";
          $db->query($sql, [$id]);

          $sql = "UPDATE mas.counters SET is_active = $1 WHERE id = $2";
          $db->query($sql, [$is_active, $id]);

          $counterAction = new \LWMIS\LOG\CounterAction();
          if ($is_active === 't') {
            $counterAction->save($db, (object)[
              'counter_id' => $id,
              'action_code' => 'COUNTER_ACTIVATED',
              'user_id' => $user_id
            ]);
          } else {
            $counterAction->save($db, (object)[
              'counter_id' => $id,
              'action_code' => 'COUNTER_DEACTIVATED',
              'user_id' => $user_id
            ]);
          }

          $retObj['message'] = 'Counter status changed successfully.';
        }

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retObj;
    }

    function assign($data) {
      $retObj = [ 'message' => 'Counter cannot be assigned.' ];
      $id = isset($data->id)?$data->id:null;
      $c_user_id = isset($data->c_user_id)?$data->c_user_id:null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (!is_null($id)) {
          $sql = 'UPDATE mas.counters SET c_user_id = $1, c_start_ts = CURRENT_TIMESTAMP WHERE id = $2';
          $db->Query($sql, [$c_user_id, $id]);

          $counterAction = new \LWMIS\LOG\CounterAction();
          $counterAction->save($db, (object)[
            'counter_id' => $id,
            'action_code' => 'COUNTER_ASSIGNED',
            'user_id' => $c_user_id
          ]);

          $retObj['message'] = "Counter assigned successfully.";
        }

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retObj;
    }

    function close($data) {
      $retObj = ['message' => 'Invalid User'];
      $user_id = isset($data->user_id)?$data->user_id:null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (!is_null($user_id)) {
          $sql = 'INSERT INTO bnc.counter_usages (counter_id, user_id, start_ts, end_ts, cr_cnt, cr_val, chq_cnt, chq_val, dd_cnt, dd_val, edc_cnt, edc_val, ecs_cnt, ecs_val)
                  (SELECT a.id AS counter_id, a.c_user_id AS user_id, a.c_start_ts AS start_ts, CURRENT_TIMESTAMP AS end_ts,
                         SUM(CASE WHEN b.payment_method_code = \'CASH\' THEN 1 ELSE 0 END) AS cr_cnt,
                         SUM(CASE WHEN b.payment_method_code = \'CASH\' THEN b.tot_receipt_amt ELSE 0 END) AS cr_val,
                         SUM(CASE WHEN b.payment_method_code = \'CHQ\' THEN 1 ELSE 0 END) AS chq_cnt,
                         SUM(CASE WHEN b.payment_method_code = \'CHQ\' THEN b.tot_receipt_amt ELSE 0 END) AS chq_val,
                         SUM(CASE WHEN b.payment_method_code = \'DD\' THEN 1 ELSE 0 END) AS dd_cnt,
                         SUM(CASE WHEN b.payment_method_code = \'DD\' THEN b.tot_receipt_amt ELSE 0 END) AS dd_val,
                         SUM(CASE WHEN b.payment_method_code = \'EDC\' THEN 1 ELSE 0 END) AS edc_cnt,
                         SUM(CASE WHEN b.payment_method_code = \'EDC\' THEN b.tot_receipt_amt ELSE 0 END) AS edc_val,
                         SUM(CASE WHEN b.payment_method_code = \'ECS\' THEN 1 ELSE 0 END) AS ecs_cnt,
                         SUM(CASE WHEN b.payment_method_code = \'ECS\' THEN b.tot_receipt_amt ELSE 0 END) AS ecs_val
                    FROM mas.counters AS a
                         LEFT OUTER JOIN bnc.receipts AS b ON (b.receipt_type = \'P\' AND b.counter_id = a.id AND b.user_id = a.c_user_id AND b.receipt_ts BETWEEN a.c_start_ts AND CURRENT_TIMESTAMP)
                   WHERE a.c_user_id = $1
                   GROUP BY a.id, a.c_user_id, a.c_start_ts)
                   RETURNING id, counter_id';
          $db->Query($sql, [$user_id]);
          $rows = $db->FetchAll();
          foreach($rows as &$r) {
            $r['id'] = intval($r['id']);
            $r['counter_id'] = intval($r['counter_id']);
          }

          if (count($rows) > 0) {
            $r = $rows[0];
            $retObj['id'] = $r['id'];
            $counter_id = $r['counter_id'];

            $sql = 'UPDATE mas.counters SET c_user_id = NULL, c_start_ts = NULL WHERE id = $1';
            $db->Query($sql, [$counter_id]);

            $counterAction = new \LWMIS\LOG\CounterAction();
            $counterAction->save($db, (object)[
              'counter_id' => $counter_id,
              'action_code' => 'COUNTER_CLOSED',
              'user_id' => $user_id
            ]);

            $retObj['message'] = "Counter closed successfully.";
          }
        }

        $db->Commit();
      } catch (\Throwable $th) {
        $db->RollBack();
        $retObj['message'] = $th->getMessage();
      }
      $db->DBClose();

      return $retObj;
    }
  }
