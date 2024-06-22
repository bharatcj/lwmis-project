<?php
  namespace LWMIS\Master;

  class BankBranch {
    function getBankBranches($filter): array
    {
      $retObj = [];
      $limit = isset($filter->limit)?$filter->limit:null;
      $offset = $limit * (isset($filter->offset)?$filter->offset:0);

      $where_clause = '';
      $params = [];
      $params[] = $limit;
      $params[] = $offset;

      if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
        $search_text = '%'.$filter->search_text.'%';
        $params[] = $search_text;
        $param_cnt = '$'.count($params);
        $where_clause .= ' AND (
                                UPPER(b.code) like UPPER('.$param_cnt.') OR
                                UPPER(b.name) like UPPER('.$param_cnt.') OR
                                UPPER(a.ifsc) like UPPER('.$param_cnt.') OR
                                UPPER(a.name) like UPPER('.$param_cnt.') OR
                                UPPER(a.address) like UPPER('.$param_cnt.')
                               )';
      }

      if (isset($filter->search_bank_text) && strlen($filter->search_bank_text) > 0) {
        $search_bank_text = '%'.$filter->search_bank_text.'%';
        $params[] = $search_bank_text;
        $param_cnt = '$'.count($params);
        $where_clause .= ' AND (
                                UPPER(b.code) like UPPER('.$param_cnt.') OR
                                UPPER(b.name) like UPPER('.$param_cnt.')
                               )';
      }

      if (isset($filter->search_branch_text) && strlen($filter->search_branch_text) > 0) {
        $search_branch_text = '%'.$filter->search_branch_text.'%';
        $params[] = $search_branch_text;
        $param_cnt = '$'.count($params);
        $where_clause .= ' AND (
                                UPPER(a.ifsc) like UPPER('.$param_cnt.') OR
                                UPPER(a.name) like UPPER('.$param_cnt.')
                               )';
      }

      if (!(isset($filter->include_inactive) && $filter->include_inactive === true)) {
        $where_clause .= ' AND a.is_active = true';
      }

      $db = new \LWMIS\Common\PostgreDB();
      try {
        // get actual data
        $sql = 'SELECT a.ifsc, a.name, a.address, a.is_active, a.remarks, COALESCE(d.is_del, true) AS is_del,
                       a.bank_code, b.name AS bank_name
                  FROM mas.bank_branches AS a
                       INNER JOIN mas.banks AS b ON (b.code = a.bank_code)
                       LEFT OUTER JOIN LATERAL (
                        SELECT false AS is_del
                          FROM est.receipts AS a1
                         WHERE a1.ifsc = a.ifsc
                          LIMIT 1
                      ) AS d ON (true)
                WHERE true'.$where_clause.'
                ORDER BY b.name, a.ifsc, a.name
                LIMIT $1 OFFSET $2';
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        foreach($rows as &$r) {
          $r['is_active'] = ($r['is_active'] == 't');
          $r['is_del'] = ($r['is_del'] == 't');
        }

        if (isset($limit) && count($rows) == $limit) {
          // get total rows
          $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    FROM mas.bank_branches AS a
                         INNER JOIN mas.banks AS b ON (b.code = a.bank_code)
                         LEFT OUTER JOIN LATERAL (
                          SELECT true AS is_del
                            FROM est.receipts AS a1
                           WHERE a1.ifsc = a.ifsc
                           LIMIT 1
                         ) AS d ON (true)
                   WHERE true'.$where_clause;
          $db->Query($sql, $params);
          $tot_rows = $db->FetchAll()[0]['cnt'];
          $tot_rows = intval($tot_rows);
        } else {
          $tot_rows = ((!\is_null($offset))?$offset:0) + \count($rows);
        }

        $retObj['rows'] = $rows;
        $retObj['tot_rows'] = $tot_rows;
      } catch(\Exception $e) {
        $retObj['rows'] = [];
        $retObj['tot_rows'] = 0;
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();

      return $retObj;
    }

    function getBankBranchUsingAPI($filter)
    {
      $retObj = [];
      $ifs_code = isset($filter->code)?$filter->code:null;

      if (!\is_null($ifs_code)) {
        try {
          $rz_ifsc = new \LWMIS\Common\RazorPayIFSC();
          $retObj = $rz_ifsc->lookupIFSC($ifs_code);
          $retObj['message'] = 'Bank/Brach details successfully received using API.';
        } catch(\Razorpay\IFSC\Exception\ServerError $e)
        {
          $retObj['message'] = $e->getMessage();
        } catch(\Razorpay\IFSC\Exception\InvalidCode $e)
        {
          $retObj['message'] = $e->getMessage();
        } catch(\Exception $e)
        {
          $retObj['message'] = $e->getMessage();
        }
      } else {
        $retObj['message'] = 'Invalid IFSC';
      }

      return $retObj;
    }

    private function insertUpdateBank($db, $data)
    {
      $retObj[] = [];
      $code = isset($data->code)?$data->code:null;
      $name = isset($data->name)?$data->name:null;
      $id = isset($data->id)?$data->id:null;

      $params = [];
      $params[] = $code;
      $params[] = $name;

      if (\is_null($id))
      {
        $sql = 'INSERT INTO mas.banks (code, name)
                VALUES (TRIM(UPPER($1)), TRIM($2))
                    ON CONFLICT (code)
                    DO UPDATE SET name = EXCLUDED.name
                RETURNING code';
        $db->Query($sql, $params);
      } else {
        $params[] = $id;
        $sql = 'UPDATE mas.banks SET code = TRIM(UPPER($1)), name = TRIM($2) WHERE id = $3 RETURNING code';
        $db->Query($sql, $params);
      }
      $rows = $db->FetchAll();

      $retObj['code'] = (count($rows) > 0)?$rows[0]['code']:null;
      $retObj['message'] = 'Bank saved successfully.';

      return $retObj;
    }

    function saveBank($data)
    {
      $retObj = [];

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        $retObj = $this->insertUpdateBank($db, $data);

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();

      return $retObj;
    }

    function saveBankBranch($data)
    {
      $retObj = [];
      $bank_code = isset($data->bank_code)?$data->bank_code:null;
      $bank_name = isset($data->bank_name)?$data->bank_name:null;
      $code = isset($data->code)?$data->code:null;
      $name = isset($data->name)?$data->name:null;
      $address = isset($data->address)?$data->address:null;
      $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
      $remarks = isset($data->remarks)?$data->remarks:null;
      $ifsc = isset($data->ifsc)?$data->ifsc:null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (!\is_null($bank_code)) {
          $bankData = $this->insertUpdateBank($db, (object)['code' => $bank_code, 'name' => $bank_name]);
          $retObj['bank_code'] = $bank_code = isset($bankData) && isset($bankData['code'])?$bankData['code']:null;
        }

        if (!\is_null($bank_code)) {
          $params = [];
          $params[] = $bank_code;
          $params[] = $code;
          $params[] = $name;
          $params[] = $address;
          $params[] = $is_active;
          $params[] = $remarks;

          if (is_null($ifsc))
          {
            $sql = 'INSERT INTO mas.bank_branches (bank_code, ifsc, name, address, is_active, remarks)
                    VALUES ($1, $2, $3, $4, $5, $6) RETURNING ifsc';
            $db->Query($sql, $params);
          } else {
            $params[] = $ifsc;
            $sql = 'UPDATE mas.bank_branches
                      SET bank_code = $1, ifsc = $2, name = $3, address = $4, is_active = $5, remarks = $6
                    WHERE ifsc = $7 RETURNING ifsc';
            $db->Query($sql, $params);
          }
          $rows = $db->FetchAll();
          foreach($rows as &$r) {
            $r['ifsc'] = intval($r['ifsc']);
          }
          $retObj['id'] = (count($rows) > 0)?$rows[0]['ifsc']:null;
          $retObj['message'] = 'Bank/Branch saved successfully.';
        }

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();

      return $retObj;
    }

    function delete($data)
    {
      $retObj = [];
      $id = isset($data->id)?$data->id:null;

      $db = new \LWMIS\Common\PostgreDB();
      if (!is_null($id)) {
        try {
          $db->Begin();

          $sql = 'DELETE FROM mas.bank_branches WHERE id = $1 RETURNING bank_id';
          $db->Query($sql, [$id]);
          $rows = $db->FetchAll();
          foreach($rows as &$r) {
            $r['bank_id'] = intval($r['bank_id']);
          }

          if (count($rows) > 0) {
            $bank_id = $rows[0]['bank_id'];
            $sql = 'DELETE FROM mas.banks AS a WHERE id = $1 AND NOT EXISTS (SELECT * FROM mas.bank_branches AS b WHERE b.bank_id = $1 AND b.bank_id = a.id)';
            $db->Query($sql, [$bank_id]);
          }

          $db->Commit();
          $retObj['message'] = 'Bank/Branch deleted successfully.';
        } catch(\Exception $e) {
          $db->RollBack();
          $retObj['message'] = 'Delete not allowed. Error: '.$e->getMessage();
        }
        $db->DBClose();
      }
      return $retObj;
    }

    function isBankCodeExist($data)
    {
      $where_clause = '';
      $code = isset($data->code)?$data->code:null;

      $params = [];
      $params[] = $code;

      if (isset($data->id))
      {
        $id = $data->id;
        $params[] = $id;
        $where_clause .= ' AND id != $'.count($params);
      }

      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'SELECT *
                FROM mas.banks
               WHERE UPPER(code) = TRIM(UPPER($1))'.$where_clause;
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      $db->DBClose();
      return (count($rows) > 0);
    }

    function isBankNameExist($data)
    {
      $where_clause = '';
      $name = isset($data->name)?$data->name:null;

      $params = [];
      $params[] = $name;

      if (isset($data->id))
      {
        $id = $data->id;
        $params[] = $id;
        $where_clause .= ' AND id != $'.count($params);
      }

      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'SELECT *
                FROM mas.banks
               WHERE UPPER(name) = UPPER($1)'.$where_clause;
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      $db->DBClose();
      return (count($rows) > 0);
    }

    function isBankBranchCodeExist($data): bool
    {
//      $where_clause = '';
//      $code = isset($data->code)?$data->code:null;
      $code = isset($data->ifsc)?$data->ifsc:null;

      $params = [];
      $params[] = $code;

//      if (isset($data->ifsc))
//      {
//        $ifsc = $data->ifsc;
//        $params[] = $ifsc;
//        $where_clause .= ' AND ifsc != $'.count($params);
//      }

      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'SELECT *
                FROM mas.bank_branches
               WHERE UPPER(ifsc) = UPPER($1)';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      $db->DBClose();
      return (count($rows) > 0);
    }

    function searchBank($filter): array
    {
      $name = isset($filter->name)?$filter->name:null;

      $params = [];
      $params[] = '%'.$name.'%';

      $sql = 'SELECT a.id, a.code, a.name
                FROM mas.banks As a
               WHERE (
                       upper(a.code) like upper($1) OR
                       upper(a.name) like upper($1)
                     )
               ORDER BY a.name
               LIMIT 50';

      $db = new \LWMIS\Common\PostgreDB();
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      $db->DBClose();
      return $rows;
    }

    function searchBankBranch($filter) {
      $code = isset($filter->code)?$filter->code:null;

      $params = [];
      $params[] = '%'.$code.'%';

      $sql = 'SELECT a.id, a.bank_id, b.code AS bank_code, b.name AS bank_name,
                     a.code, a.name, a.address
                FROM mas.bank_branches AS a
                     INNER JOIN mas.banks As b ON (b.id = a.bank_id)
               WHERE a.is_active = true AND (a.code ILIKE $1)
               ORDER BY a.code
               LIMIT 50';

      $db = new \LWMIS\Common\PostgreDB();
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['bank_id'] = intval($r['bank_id']);
      }

      $db->DBClose();
      return $rows;
    }

    function toggleStatus($data) {
      $retObj = ['message' => 'Invalid Bank Branch.'];
      $ifsc = isset($data->ifsc)?$data->ifsc:null;
      $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
      $user_id = isset($data->user_id)?$data->user_id:null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();

        if (!is_null($ifsc)) {

          $sql = "UPDATE mas.bank_branches SET is_active = $1 WHERE ifsc = $2";
          $db->query($sql, [$is_active, $ifsc]);

          $bank_branchAction = new \LWMIS\LOG\BankBranchAction();
          if ($is_active === 't') {
            $bank_branchAction->save($db, (object)[
              'bank_branch_id' => $ifsc,
              'action_code' => 'BRANCH_ACTIVATED',
              'user_id' => $user_id
            ]);
          } else {
            $bank_branchAction->save($db, (object)[
              'bank_branch_id' => $ifsc,
              'action_code' => 'BRANCH_DEACTIVATED',
              'user_id' => $user_id
            ]);
          }

          $retObj['message'] = 'Bank Branch status changed successfully.';
        }

        $db->Commit();
      } catch(\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retObj;
    }

  }
?>
