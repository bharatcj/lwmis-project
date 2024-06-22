<?php

namespace LWMIS\Clm;

use LWMIS\Common\PostgreDB;

class Sdat_Certs
{
  /*  function saveSDATCerts($data): array
    {
      $claim_id = $data->claim_id ?? null;
      $comp_level = $data->comp_level ?? null;
      $sport_name = $data->sport_name ?? null;
      $sport_type = $data->sport_type ?? null;
      $winning_position = $data->winning_position ?? null;

      $db = new PostgreDB();
      $db->Begin();

      try {
        $sql = "INSERT INTO clm.sdat_certs (claim_id,comp_level, sport_name, sport_type, winning_position)
                VALUES ($claim_id,$comp_level,$sport_name,$sport_type,$winning_position);";
        $db->Query($sql);
        $rows = $db->FetchAll();
        $reObj['rows'] = $rows;
      } catch (\Exception $e) {
        $db->RollBack();
        $reObj['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $reObj;
    }*/

//  function saveSdatCerts($data): array
//  {
//    $retVal = [];
//    $claim_id = $data->id ?? null;
//    $scheme_code = $data->scheme_code ?? null;
//    $comp_level = $scheme_code == 'STATE_SA' ? 'S' : 'D';
//    $sport_name = $data->sport_name ?? null;
//    $sport_type = $data->sport_type ?? null;
//    $winning_position = $data->winning_position ?? null;
//    $sdat_cert_no = $data->sdat_cert_no ?? null;
//    $remarks = $data->remarks ?? null;
//    $attachment_id = $data->attachment_id ?? null;
//
//    $db = new PostgreDB();
//    $db->Begin();
//
//    try {
//      $sql = "INSERT INTO clm.sdat_certs (claim_id, attachment_id, comp_level, sport_name, sport_type, sdat_cert_no, winning_position, remarks)
//                   VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
//                RETURNING id;";
//
//      $db->Query($sql, [$claim_id, $attachment_id, $comp_level, $sport_name, $sport_type, $sdat_cert_no, $winning_position, $remarks]);
//      $rows = $db->FetchAll();
//
//      foreach ($rows as &$r) {
//        $r['id'] = intval($r['id']);
//      }
//
//      if (count($rows) > 0) {
//        $retVal['id'] = $rows[0]['id'];
//        $retVal['message'] = 'Certificate Details are Saved Successfully';
//      }
//
//      $db->Commit();
//    } catch (\Exception $e) {
//      $db->RollBack();
//      $retVal['message'] = '🚫 Not saved !!' . $e->getMessage();
//    }
//    $db->DBClose();
//    return $retVal;
//  }

  public function saveSdatCertDetails($data): array
  {
    $retVal = [];
    $retVal['message'] = 'Certificate Details are Saved Successfully';

    $claim_id = $data->claim_id ?? null;
    $sdat_cert_details = $data->sdat_cert_details ?? null;
    $scheme_code = $data->scheme_code ?? null;
    $comp_level = $scheme_code == 'STATE_SA' ? 'S' : 'D';

    $db = new PostgreDB();

    if (!is_null($claim_id)) {
      try {
        $db->Begin();
        foreach ($sdat_cert_details as $scd) {
          $cert_id = $scd->cert_id ?? null;
          $sport_name = $scd->sport_name ?? null;
          $sport_type = $scd->sport_type ?? null;
          $winning_position = $scd->winning_position ?? null;
          $sdat_cert_no = $scd->sdat_cert_no ?? null;
          $remarks = $scd->remarks ?? null;
          $attachment_id = $scd->attachment_id ?? null;

          if (is_null($cert_id)) {
            $sql = "INSERT INTO clm.sdat_certs (claim_id, attachment_id, comp_level, sport_name, sport_type, sdat_cert_no, winning_position, remarks)
                         VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
                      RETURNING id;";

            $db->Query($sql, [$claim_id, $attachment_id, $comp_level, $sport_name, $sport_type, $sdat_cert_no, $winning_position, $remarks]);
          } else {
            $sql = "update clm.sdat_certs
                       set claim_id = $2,
                           attachment_id = $3,
                           comp_level = $4,
                           sport_name = $5,
                           sport_type = $6,
                           sdat_cert_no = $7,
                           winning_position  = $8,
                           remarks = $9
                     where id = $1";

            $db->Query($sql, [$cert_id, $claim_id, $attachment_id, $comp_level, $sport_name, $sport_type, $sdat_cert_no, $winning_position, $remarks]);
          }

        }
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retVal['message'] = $e->getMessage();
      }
    } else {
      $retVal['message'] = 'Required: Claim id';
    }

    $db->DBClose();
    return $retVal;
  }

  function deleteSdatCerts($data): array
  {//todo:remove this api & use deleteAttachment (with code) instead of this
    $retVal = [];
    $claim_id = $data->id ?? null;

    $scheme_code = $data->scheme_code ?? null;
    if ($scheme_code == 'STATE_SA') {
      $comp_level = 'S';
    } else {
      $comp_level = 'D';
    }

    $cert_id = $data->cert_id ?? null;

    $sport_name = $data->sport_name ?? null;
    $sport_type = $data->sport_type ?? null;
    $winning_position = $data->winning_position ?? null;
    $remarks = $data->remarks ?? null;

    $storage_name = $data->storage_name ?? null;
    $attachment_id = $data->attachment_id ?? null;

    $db = new PostgreDB();

    try {

      if ($storage_name == null || $attachment_id == null) {
        throw new \Exception('Storage name and attachment ID cannot be null');
      }

      $msg = (new \LWMIS\Doc\Attachment)->deleteAttachment($data)['message'];

      if ($msg != 'Deleted successfully.') {
        throw new \Exception($msg);
      }

      $db->Begin();

      $sql = "delete from clm.sdat_certs
                    where id = $1;";

      $db->Query($sql, [$cert_id]);

      $retVal['message'] = 'SDAT certificate details are deleted successfully!';
      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = '🚫 Not saved !!' . $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function getSdatCerts($filter): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    $scheme_code = $filter->scheme_code ?? null;
    $claim_id = $filter->claim_id ?? null;

    $where_clause = "";
    $where_clause1 = "";
    $params = [];

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

//    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
//      $search_text = '%' . $filter->search_text . '%';
//      $params[] = $search_text;
//      $param_cnt = '$' . count($params);
//      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
//    }

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
                     c.claim_id,
                     sdc.id,-- <- certificate details id
                     sdc.sport_name,
                     sdc.sport_type,
                     sdc.winning_position,
                     sdc.sdat_cert_no,
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
               LEFT JOIN clm.sdat_certs as sdc ON sdc.attachment_id = c.id
            WHERE true
              AND a.code = 'SDAT_CERT'
              AND c.file_name is NOT NULL $where_clause
         ORDER BY a.name";

//      var_dump($sql);
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

}
