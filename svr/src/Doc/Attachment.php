<?php

namespace LWMIS\Doc;

use LWMIS\Common\ErrorHandler;

class Attachment
{
  function getTypesAttachments($filter): array
  {
//    var_dump('filter',$filter);
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    $limit = $filter->limit ?? null;
    $offset = $limit * ($filter->offset ?? 0);
    $scheme_code = $filter->scheme_code ?? null;
    $claim_id = $filter->claim_id ?? null;
    $type = $filter->type ?? null;
    $attachment_code = $filter->attachment_code ?? null;
    $where_clause = "";
    $where_clause1 = "";
    $params = [];
    $params[] = $limit;
    $params[] = $offset;

    if (isset($filter->id) && $filter->id > 0) {
      $id = $filter->id;
      $params[] = $id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if ($claim_id) {
      $params[] = $claim_id;
      $where_clause1 .= ' AND at.claim_id = $' . count($params);
    }

    if (isset($filter->firm_id) && strlen($filter->firm_id) > 0) {
      $firm_id = $filter->firm_id;
      $params[] = $firm_id;
      $where_clause1 .= ' AND at.firm_id = $' . count($params);
    }

    if (isset($filter->applicable_to) && strlen($filter->applicable_to) > 0) {
      $applicable_to = $filter->applicable_to;
      $params[] = $applicable_to;
      $where_clause .= ' AND $' . count($params) . '= any(a.applicable_to)';
    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
    }

    if ($scheme_code) {
      $params[] = $scheme_code;
      $where_clause .= ' AND $' . count($params) . ' = any(a.scheme_codes)';
    }

    if ($attachment_code) {
      $params[] = $attachment_code;
      $where_clause .= ' AND a.code = $' . count($params);
    }

    if ($type == 'T') {
      switch ($scheme_code) {
        case 'ED_INC':
          $where_clause .= "AND a.code != 'M_LIST'";
          break;
        case 'ED_SCH':
          $where_clause .= "AND a.code != 'M_LIST' AND a.code != 'D_AADHAAR'";
          break;
        case 'STATE_SA' || 'DIST_SA':
          $where_clause .= "AND a.code != 'SDAT_CERT'";
          $where_clause .= "AND a.code != 'D_AADHAAR'";
          break;
      }
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "SELECT a.code,
                           a.name as type_name,
                           a.description as type_description,
                           a.applicable_to,
                           a.scheme_codes,
                           a.is_mandatory,
                           a.remarks,
                           c.id AS attachment_id,
                           c.file_name,
                           c.storage_name,
                           c.firm_id,
                           (CASE WHEN a.is_mandatory = 't' THEN '*Required'
                            ELSE 'Optional'
                            END) AS req_det
                     FROM doc.types AS a
                     LEFT OUTER JOIN LATERAL(
                          SELECT *
                          FROM doc.attachments as at
                          WHERE at.doc_type = a.code $where_clause1
                          LIMIT 1
                         ) AS c
                        ON true
                     WHERE true $where_clause
                     ORDER BY a.name
                     LIMIT $1 OFFSET $2;";
//      var_dump($sql);
//      var_dump($params);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['attachment_id'] = intval($r['attachment_id']);
        $r['id'] = isset($r['id']) ? intval($r['id']) : null;
        $r['is_mandatory'] = ($r['is_mandatory'] == 't');
      }
      $retObj['rows'] = $rows;

      // get total rows
      if (!\is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                        FROM doc.types as a
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

  function deleteAttachment($data): array
  {
    $retVal = [];
    $id = $data->id ?? null;
    $attachment_id = $data->attachment_id ?? $id;
    $storage_name = $data->storage_name ?? null;
    $code = $data->code ?? null;
    $firm_id = $data->firm_id ?? null;

    $db = new \LWMIS\Common\PostgreDB();

    try {
      $db->Begin();
      if (unlink($storage_name)) {

        if ($code == 'SDAT_CERT') {
          $sql = 'DELETE FROM clm.sdat_certs WHERE attachment_id = $1';
          $db->Query($sql, [$attachment_id]);
        }

        if ($code == 'EPFO_DOC') {
          $sql = 'UPDATE est.firms SET epfo_no = null WHERE id = $1';
          $db->Query($sql, [$firm_id]);
        }

        if ($code == 'ESIC_DOC') {
          $sql = 'UPDATE est.firms SET esic_no = null WHERE id = $1';
          $db->Query($sql, [$firm_id]);
        }

        if ($code == 'FIRM_DOC') {
          $sql = 'UPDATE est.firms SET firm_reg_no = null,firm_req_dt=null WHERE id = $1';
          $db->Query($sql, [$firm_id]);
        }

        $sql = 'DELETE FROM doc.attachments WHERE id = $1';
        $db->Query($sql, [$attachment_id]);

        $retVal['message'] = "Deleted successfully.";
      } else {
        $retVal['message'] = "TNEB Bill deletion failed.";
      }
      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = $e->getMessage();
    }
    return $retVal;
  }

  function saveAttachment($data, $db = new \LWMIS\Common\PostgreDB()): array
  {
    $retVal = ['message' => 'Attachment cannot be saved.'];
    $claim_id = $data->claim_id ?? null;
    $firm_id = $data->firm_id ?? null;
    $receipt_id = $data->receipt_id ?? null;
    $doc_type = $data->doc_type ?? null;
    $file_name = $data->file_name ?? null;
    $file_type = $data->file_type ?? null;
    $file_size = $data->file_size ?? null;
    $remarks = $data->remarks ?? null;
    $storage_name = $data->storage_name ?? null;
    $params = array();

    $params[] = $claim_id;
    $params[] = $firm_id;
    $params[] = $receipt_id;
    $params[] = $doc_type;
    $params[] = $file_name;
    $params[] = $file_type;
    $params[] = $file_size;
    $params[] = $storage_name;
    $params[] = $remarks;

    if ($firm_id == null)
      return ['message' => 'Can not save attachment without firm details'];

    try {
      $sql = 'INSERT INTO doc.attachments (claim_id, firm_id, receipt_id,
                           doc_type, file_name, file_type,
                           file_size, storage_name, remarks, upload_ts)
                   VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, now())
                RETURNING id';//    var_dump($params);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      if (count($rows) > 0) {
        $retVal['message'] = "Attachment saved successfully.";
      }

      $retVal['rows'] = $rows;

    } catch (\Exception $e) {
      $retVal['message'] = ErrorHandler::custom($e);
    }
    return $retVal;
  }

  function toggleStatus($data)
  {
    $retObj = ['message' => 'Invalid Bank Branch.'];
    $id = isset($data->id) ? $data->id : null;
    $is_active = (isset($data->is_active) && $data->is_active === true) ? 't' : 'f';
    $user_id = isset($data->user_id) ? $data->user_id : null;
    // var_dump($data);

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!is_null($id)) {

        $sql = "UPDATE mas.acts SET is_active = $1 WHERE id = $2";
        $db->query($sql, [$is_active, $id]);

        $actAction = new \LWMIS\LOG\ActAction();
        if ($is_active === 't') {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'ACT_ACTIVATED',
            'user_id' => $user_id
          ]);
        } else {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'ACT_DEACTIVATED',
            'user_id' => $user_id
          ]);
        }

        $retObj['message'] = 'Act status changed successfully.';
      }

      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }

  function isMasterActExist($data)
  {
    $act = isset($data->act) ? $data->act : null;
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();
    $params[] = $act;

    // if (!is_null($id)) {
    //   $params[] = $id;
    //   $where_clause = 'AND email != $' . count($params);
    // }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM mas.acts WHERE TRUE AND name IS NOT NULL AND name = $1 ' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function getMultipleAttachments($filter): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    /* $limit = isset($filter->limit) ? $filter->limit : null;
     $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);*/
    $scheme_code = $filter->scheme_code ?? null;
    $claim_id = $filter->claim_id ?? null;

    $where_clause = "";
    $where_clause1 = "";
    $certificate = null;
    $params = [];

    if (isset($filter->id) && $filter->id > 0) {
      $id = $filter->id;
      $params[] = $id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if ($claim_id) {
//            $claim_id = $filter->claim_id;
      $params[] = $claim_id;
      $where_clause1 .= ' AND at.claim_id = $' . count($params);
    }

    if (isset($filter->firm_id) && strlen($filter->firm_id) > 0) {
      $firm_id = $filter->firm_id;
      $params[] = $firm_id;
      $where_clause1 .= ' AND at.firm_id = $' . count($params);
    }

    if (isset($filter->applicable_to) && strlen($filter->applicable_to) > 0) {
      $applicable_to = $filter->applicable_to;
      $params[] = $applicable_to;
      $where_clause .= ' AND $' . count($params) . '= any(a.applicable_to)';
    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
    }

    if ($scheme_code) {
      $params[] = $scheme_code;
      $where_clause .= ' AND $' . count($params) . ' = any(a.scheme_codes)';

      switch ($scheme_code) {
        case "STATE_SA":
          $certificate = 'SDAT_CERT';
          $params[] = $certificate;
          break;
        default:
          $certificate = null;
      }

      if (!is_null($certificate)) {
        $where_clause .= ' AND a.code = $' . count($params);
      }

    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "SELECT a.code,
                     a.name as type_name,
                     a.description as type_description,
                     a.applicable_to,
                     a.scheme_codes,
                     a.is_mandatory,
                     a.remarks,
                     c.id AS attachment_id,
                     c.file_name,
                     c.storage_name,
                     c.firm_id,
                     (CASE WHEN a.is_mandatory = 't' THEN '*Required'
                      ELSE 'Optional'
                      END) AS req_det
               FROM doc.types AS a
               LEFT OUTER JOIN LATERAL(
                    SELECT *
                    FROM doc.attachments as at
                    WHERE at.doc_type = a.code
                                $where_clause1
               )AS c
               ON true
            WHERE true
              AND c.file_name is NOT NULL $where_clause
         ORDER BY a.name";

//      var_dump('qry =', $sql);

//      AND EXISTS(SELECT 1 FROM doc.attachments WHERE doc_type = c.doc_type)
//      AND EXISTS(SELECT 1 from clm.sdat_certs WHERE attachment_id = c.id);


      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = isset($r['id']) ? intval($r['id']) : null;
        $r['is_mandatory'] = ($r['is_mandatory'] == 't');
      }
      $retObj['rows'] = $rows;

      // get total rows
      /*     if (!\is_null($limit) && count($rows) == $limit) {
             $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                         FROM doc.types as a
                         WHERE true ' . $where_clause;
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

  function clmReqDoc($data): array
  {
    $retObj = [];

    $applicable_to = $data->applicable_to ?? null;
    $scheme_code = $data->scheme_code ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (!is_null($applicable_to) && !is_null($scheme_code)) {
        $sql = "SELECT name
                FROM doc.types
                WHERE $1 = ANY (applicable_to)
                  AND $2 = ANY (scheme_codes)
                  AND code != 'CLM_VR_DOC'
                ORDER BY name";
        $db->Query($sql, [$applicable_to, $scheme_code]);
        $rows = $db->FetchAll();
        $retObj['rows'] = $rows;
      } else {
        $retObj['message'] = 'Check applicable_to & scheme_code';
      }
    } catch (\Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retObj;
  }

}
