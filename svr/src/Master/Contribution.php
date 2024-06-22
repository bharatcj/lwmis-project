<?php

namespace LWMIS\Master;

class Contribution
{
    function getContributions($filter)
    {
        $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
        $limit = isset($filter->limit) ? $filter->limit : null;
        $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

        $where_clause = "";
        $params = [];
        $params[] = $limit;
        $params[] = $offset;

        if (isset($filter->id) && $filter->id > 0) {
            $id = $filter->id;
            $params[] = $id;
            $where_clause .= ' AND a.id = $' . count($params);
        }

        if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

            $search_text = '%' . $filter->search_text . '%';
            $params[] = $search_text;
            $param_cnt = '$' . count($params);
            $where_clause .= ' AND (
                                  UPPER(a.name) like UPPER(' . $param_cnt . ')
                                 )';
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get actual data
/*            $sql = 'SELECT a.*,COALESCE(b.is_del,true)AS is_del
                      FROM mas.contributions AS a
                      LEFT OUTER JOIN LATERAL (
                             SELECT false AS is_del
                               FROM est.payables AS b
                              WHERE TRUE AND ((a.from_year <=b.YEAR AND a.to_year >=b.year) OR (a.from_year <=b.YEAR AND a.to_year IS NULL ))
                               LIMIT 1
                         ) AS b ON (true)
                     where true ' . $where_clause . '
                     ORDER BY a.from_year
                     LIMIT $1 OFFSET $2;';*/

          $sql = "SELECT c.id,
                           c.employee_cntrbtn,
                           c.employer_cntrbtn,
                           c.total_cntrbtn,
                           c.from_year,
                           c.to_year,
                           coalesce(t.is_del,TRUE)as is_del
                    FROM mas.contributions as c
                        LEFT JOIN LATERAL (
                            SELECT DISTINCT false as is_del
                            FROM est.payables As p
                            WHERE (
                                p.year BETWEEN extract(YEAR FROM c.from_year)
                                    AND
                                coalesce(extract(YEAR FROM c.to_year),extract(YEAR FROM now()))
                            )
                        ) as t on TRUE
                        WHERE TRUE $where_clause
                        LIMIT $1 OFFSET $2";

//          var_dump($sql);
          $db->Query($sql, $params);
          $rows = $db->FetchAll();
          foreach ($rows as &$r) {
            $r['id'] = isset($r['id']) ? intval($r['id']) : null;
//                $r['from_year'] = isset($r['from_year']) ? intval($r['from_year']) : null;
            $r['employee_cntrbtn'] = isset($r['employee_cntrbtn']) ? floatval($r['employee_cntrbtn']) : null;
            $r['employer_cntrbtn'] = isset($r['employer_cntrbtn']) ? floatval($r['employer_cntrbtn']) : null;
            $r['total_cntrbtn'] = isset($r['total_cntrbtn']) ? floatval($r['total_cntrbtn']) : null;
            $r['is_del'] = ($r['is_del'] == 't');
//                $r['is_active'] = ($r['is_active'] == 't');
          }
          $retObj['rows'] = $rows;

          // get total rows
          if (!\is_null($limit) && count($rows) == $limit) {
            $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                        FROM mas.contributions as a
                        WHERE true ' . $where_clause;
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

    function delete($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        if (!is_null($id)) {
          $db = new \LWMIS\Common\PostgreDB();
            $sql = 'DELETE FROM mas.contributionss WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Contribution deleted successfully.";
            $db->DBClose();
          }
        return $retVal;
    }

  function save($data): array
  {
    $retVal = ['message' => 'Act cannot be saved.'];
    $from_year = $data->from_year ?? null;
    $employee_cntrbtn = $data->employee_cntrbtn ?? null;
    $employer_cntrbtn = $data->employer_cntrbtn ?? null;
    $remarks = $data->remarks ?? null;
    $total_cntrbtn = $data->total_cntrbtn ?? null;
    $to_year = $data->to_year ?? null;
    $id = $data->id ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (is_null($id)) {

        $sql = 'INSERT INTO mas.contributions ( from_year, employee_cntrbtn, employer_cntrbtn, total_cntrbtn, to_year )
                             VALUES ($1, $2, $3, $4, $5)
                          RETURNING id';
        $db->Query($sql, [$from_year, $employee_cntrbtn, $employer_cntrbtn, $total_cntrbtn, $to_year]);
        $rows = $db->FetchAll();
        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
        }
        if (count($rows) > 0) {
          $retVal['id'] = $rows[0]['id'];
          $retVal['message'] = "Contribution saved successfully.";
        }
      } else {
        $sql = 'UPDATE mas.contributions
                           SET from_year = $1, employee_cntrbtn = $2, employer_cntrbtn = $3, total_cntrbtn = $4, to_year =$5
                         WHERE id = $6';
                $db->Query($sql, [$from_year, $employee_cntrbtn, $employer_cntrbtn, $total_cntrbtn, $to_year ,$id]);
                $retVal['message'] = "Contribution update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

  function isMasterContributionExist($data): bool
  {
    $where_clause = "";
    $params = array();

    if(isset($data->from_year) && ($data->from_year) > 0){
        $from_year = $data->from_year;
        $params[] = $from_year;
        $where_clause .=' AND from_year = $'.count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
//    $sql = 'SELECT from_year FROM mas.contributions WHERE TRUE ' . $where_clause;
    $sql = "SELECT id FROM mas.contributions WHERE TRUE $where_clause BETWEEN from_year AND to_year";
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function toggleStatus($data) {
    $retObj = ['message' => 'Invalid Contribution.'];
    $id = isset($data->id)?$data->id:null;
    $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
    $user_id = isset($data->user_id)?$data->user_id:null;
    // var_dump($data);

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!is_null($id)) {

        $sql = "UPDATE mas.contributionss SET is_active = $1 WHERE id = $2";
        $db->query($sql, [$is_active, $id]);

        // $actAction = new \LWMIS\LOG\ActAction();
        // if ($is_active === 't') {
        //   $actAction->save($db, (object)[
        //     'act_id' => $id,
        //     'action_code' => 'ACT_ACTIVATED',
        //     'user_id' => $user_id
        //   ]);
        // } else {
        //   $actAction->save($db, (object)[
        //     'act_id' => $id,
        //     'action_code' => 'ACT_DEACTIVATED',
        //     'user_id' => $user_id
        //   ]);
        // }

        $retObj['message'] = 'Contribution status changed successfully.';
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
