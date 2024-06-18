<?php

namespace LWMIS\Clm;

class Edu_District
{
    function getEdu_Districts($filter): array
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

        if (isset($filter->id) && $filter->id > 0) {
            $id = $filter->id;
            $params[] = $id;
            $where_clause .= ' AND a.id = $' . count($params);
        }

        if (isset($filter->district_id) && $filter->district_id > 0) {
            $district_id = $filter->district_id;
            $params[] = $district_id;
            $where_clause .= ' AND eda.district_id = $' . count($params);
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
/*            $sql = 'SELECT a.id,a.name as edu_district_name,a.code as edu_district_code,
                           COALESCE(b.is_del, true)AS is_del
                      from clm.edu_districts as a
                           LEFT OUTER JOIN LATERAL(
                                    SELECT false AS is_del
                                      FROM clm.claims AS b
                                     WHERE b.ei_district_id = a.id
                                     LIMIT 1
                           )AS b ON TRUE
                     where true ' . $where_clause . '
                  ORDER BY a.id
                    ' . $limit_offset;*/

            $sql = 'SELECT a.id,
                        dist.name                as dist_name,
                        dist.code                as dist_code,
                        a.name                   as edu_district_name,
                        a.code                   as edu_district_code,
                        COALESCE(b.is_del, true) AS is_del
                    from clm.edu_districts as a
                    LEFT OUTER JOIN LATERAL (
                    SELECT false AS is_del
                    FROM clm.claims AS b
                    WHERE b.ei_district_id = a.id
                    LIMIT 1
                    ) AS b ON TRUE
                         INNER JOIN clm.edu_district_asso eda ON
                            a.id = eda.edu_district_id
                         INNER JOIN mas.districts dist ON
                            dist.id = eda.district_id
                    WHERE true ' . $where_clause . '
                    ORDER BY dist.name
                     ' . $limit_offset;
//            var_dump($sql);
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id']     = isset($r['id']) ? intval($r['id']) : null;
                $r['is_del'] = ($r['is_del'] == 't');
            }
            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT count(*) AS cnt' . $limit_offset_as . '
                    FROM mas.districts AS a
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
            $sql = 'DELETE FROM clm.edu_districts WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Education District deleted successfully.";
            $db->DBClose();
        }
        return $retVal;
    }

    function save($data)
    {
        $retVal = ['message' => 'User cannot be saved.'];
        $name = isset($data->edu_district_name) ? $data->edu_district_name : null;
        $code = isset($data->edu_district_code) ? strtoupper($data->edu_district_code) : null;
        $id = isset($data->id) ? $data->id : null;

        $params = array();
        $params[] = $name;
        $params[] = $code;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO clm.edu_districts ( name, code)
                VALUES ($1 ,$2)
                RETURNING id';
                $db->Query($sql, $params);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id'] = $rows[0]['id'];
                    $retVal['message'] = "Education District saved successfully.";
                }
            } else {
                $params[] = $id;
                $sql = 'UPDATE clm.edu_districts
                SET name = $1, code = $2
                WHERE id = $3';
                $db->Query($sql, $params);
                $retVal['message'] = "Education District update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

    function isMasterEdu_DistrictExist($data)
  {
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();

    if(isset($data->edu_district_name) && is_string($data->edu_district_name)){
        $edu_district_name = $data->edu_district_name;
        $params[] = $edu_district_name;
        $where_clause = ' AND (name like $'.count($params).' OR code like $'.count($params).')';
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM clm.edu_districts WHERE TRUE' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }
}
?>
