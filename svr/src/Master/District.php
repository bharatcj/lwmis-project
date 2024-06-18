<?php

namespace LWMIS\Master;

class District
{
  function getDistricts($filter): array
    {
        $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
      $limit = $filter->limit ?? null;
      $offset = $limit * ($filter->offset ?? 0);

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
            $sql = 'SELECT a.id,a.name as district_name,a.code as district_code,
                           COALESCE(b.is_del, true)AS is_del
                      from mas.districts as a
                           LEFT OUTER JOIN LATERAL(
                                    SELECT false AS is_del
                                      FROM est.firms AS b
                                     WHERE b.district_id = a.id
                                     LIMIT 1
                           )AS b ON TRUE
                     where true ' . $where_clause . '
                  ORDER BY a.id
                    ' . $limit_offset;

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

    function deleteDistrict($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        if (!is_null($id)) {
            $db = new \LWMIS\Common\PostgreDB();
            $sql = 'DELETE FROM mas.districts WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "District deleted successfully.";
            $db->DBClose();
        }
        return $retVal;
    }

    function saveDistrict($data)
    {
        $retVal = ['message' => 'User cannot be saved.'];
        $name = isset($data->district_name) ? $data->district_name : null;
        $code = isset($data->district_code) ? strtoupper($data->district_code) : null;
        $id = isset($data->id) ? $data->id : null;

        $params = array();
        $params[] = $name;
        $params[] = $code;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO mas.districts ( name, code)
                VALUES ($1, $2)
                RETURNING id';
                $db->Query($sql, $params);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id'] = $rows[0]['id'];
                    $retVal['message'] = "District saved successfully.";
                }
            } else {
                $params[] = $id;
                $sql = 'UPDATE mas.districts
                SET name = $1, code = $2
                WHERE id = $3';
                $db->Query($sql, $params);
                $retVal['message'] = "District update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

    function isMasterDistrictExist($data)
  {
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();

    if(isset($data->district) && is_string($data->district)){
        $district = $data->district;
        $params[] = $district;
        $where_clause = ' AND (name like $'.count($params).' OR code like $'.count($params).')';
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM mas.districts WHERE TRUE' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }
}
?>
