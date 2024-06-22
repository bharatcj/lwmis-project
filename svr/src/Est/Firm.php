<?php

namespace LWMIS\Est;

use Exception;
use LWMIS\Common\ErrorHandler;
use LWMIS\Common\PostgreDB;
use LWMIS\Doc\Attachment;
use LWMIS\Master\User;
use SplFileInfo;
use function count;
use function is_null;

class Firm
{
  function saveRegistrationEst($data): array
  {
    $retVal = ['message' => 'Establishment Registration cannot be saved.'];

    $user_id = isset($data->user_id) ? $data->user_id : null;
    $firm_name = isset($data->firm_name) ? $data->firm_name : null;
    $employer_name = isset($data->employer_name) ? $data->employer_name : null;
    $address = isset($data->address) ? $data->address : null;
    $district_id = isset($data->district_id) ? $data->district_id : null;
    $taluk_id = isset($data->taluk_id) ? $data->taluk_id : null;
    $panchayat_id = isset($data->panchayat_id) ? $data->panchayat_id : null;
    $locality = isset($data->locality) ? $data->locality : null;
    $postal_code = isset($data->postal_code) ? $data->postal_code : null;
    $landmark = isset($data->landmark) ? $data->landmark : null;
    $est_mobile = isset($data->est_mobile) ? $data->est_mobile : null;
    $est_phone = isset($data->est_phone) ? $data->est_phone : null;
    $est_email = isset($data->est_email) ? $data->est_email : null;
    $est_fax = isset($data->est_fax) ? $data->est_fax : null;
    $contact_name = isset($data->contact_name) ? $data->contact_name : null;
    $contact_designation = isset($data->contact_designation) ? $data->contact_designation : null;
    $contact_mobile = isset($data->contact_mobile) ? $data->contact_mobile : null;
    $contact_phone = isset($data->contact_phone) ? $data->contact_phone : null;
    $contact_email = isset($data->contact_email) ? $data->contact_email : null;
    $pe_male = isset($data->pe_male) ? $data->pe_male : null;
    $pe_female = isset($data->pe_female) ? $data->pe_female : null;
    $pe_trans = isset($data->pe_trans) ? $data->pe_trans : null;
    $pe_disabled = isset($data->pe_disabled) ? $data->pe_disabled : null;
    $ce_male = isset($data->ce_male) ? $data->ce_male : null;
    $ce_female = isset($data->ce_female) ? $data->ce_female : null;
    $ce_trans = isset($data->ce_trans) ? $data->ce_trans : null;
    $ce_disabled = isset($data->ce_disabled) ? $data->ce_disabled : null;
    $firm_reg_no = isset($data->firm_reg_no) ? $data->firm_reg_no : null;
    $firm_req_dt = isset($data->firm_req_dt) ? $data->firm_req_dt : null;
    $esic_no = isset($data->esic_no) ? $data->esic_no : null;
    $epfo_no = isset($data->epfo_no) ? $data->epfo_no : null;
    $cin_no = isset($data->cin_no) ? $data->cin_no : null;
    $tan_no = isset($data->tan_no) ? $data->tan_no : null;
    $gstin_no = isset($data->gstin_no) ? $data->gstin_no : null;
    $tneb_no = isset($data->tneb_no) ? $data->tneb_no : null;
//    $is_legacy_reg = (isset($data->is_legacy_reg) && $data->is_legacy_reg == 'Y') ? 't' : 'f';
//    $is_reg_confirmed = (isset($data->is_reg_confirmed) && $data->is_reg_confirmed == 'Y') ? 't' : 'f';
    $is_reg_confirmed = (isset($data->is_reg_confirmed) && $data->is_reg_confirmed) ? 't' : 'f';
    $is_legacy_reg = (isset($data->is_legacy_reg) && $data->is_legacy_reg == 'Y') ? 't' : 'f';
    $status = isset($data->status) ? $data->status : null;
    $remarks = isset($data->remarks) ? $data->remarks : null;
    $latitude_no = isset($data->latitude_no) ? $data->latitude_no : null;
    $longitude_no = isset($data->longitude_no) ? $data->longitude_no : null;
    $est_starts_from = $data->est_starts_from ?? null;
    $old_est_reg_no = $data->old_est_reg_no ?? null;

    $firm_id = $data->id ?? null;

    if ($status == 'S') {
      $is_reg_confirmed = 't';//<- To confirm the old_reg_no is avail in the old database & is not taken by someone.
    }

    $params = array();

    $db = new PostgreDB();
    try {
      $db->Begin();
      if (is_null($firm_id)) {
        if ($is_legacy_reg == 't') {
//          todo in update also
          $sql = "INSERT INTO est.firms(user_id,is_legacy_reg,is_reg_confirmed,old_est_reg_no,cre_ts)
                       VALUES ($1, $2, $3, $4, $5)
                    RETURNING id";
          $db->Query($sql, [$user_id, $is_legacy_reg, $is_reg_confirmed, $old_est_reg_no, 'now()']);
        } else {
          $sql = 'INSERT INTO est.firms(user_id, is_legacy_reg, name, firm_reg_no,
                                     est_fax, est_phone, est_email, est_mobile, est_starts_from, cre_ts)
                       VALUES ( $1, $2, $3, $4, $5, $6, $7, $8, $9, now())
                    RETURNING id';
          $db->Query($sql, [$user_id, $is_legacy_reg, $firm_name, $firm_reg_no, $est_fax, $est_phone, $est_email, $est_mobile, $est_starts_from]);
        }

        $rows = $db->FetchAll();

        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
        }

        if (count($rows) > 0) {
          $firm_id = $rows[0]['id'];
          $retVal['id'] = intval($rows[0]['id']);
          $retVal['message'] = "saved successfully.";
        }
      } else {
//        var_dump('is reg confirmed',$is_reg_confirmed);
        $sql = 'UPDATE est.firms
                   SET user_id = $1, remarks = $2, name = $3, employer_name = $4, address = $5, district_id = $6, taluk_id = $7, panchayat_id = $8,
                       locality = $9, postal_code = $10, landmark = $11, est_phone = $12, est_email = $13, est_fax = $14, contact_name = $15,
                       contact_designation = $16, contact_mobile = $17,contact_phone = $18, contact_email = $19, pe_male = $20, pe_female = $21,
                       pe_trans = $22, pe_disabled = $23, ce_male = $24, ce_female = $25, ce_trans = $26, ce_disabled = $27,firm_reg_no = $28,
                       firm_req_dt = $29, esic_no = $30, epfo_no = $31, cin_no = $32, tan_no = $33, gstin_no = $34, is_legacy_reg = $35,
                       is_reg_confirmed = $36, est_mobile = $37, status = $38, latitude_no=$39, longitude_no=$40,
                       est_starts_from = $41, old_est_reg_no = $42
                 WHERE id = $43
             RETURNING id';
        $db->Query($sql, [$user_id, $remarks, $firm_name, $employer_name, $address, $district_id, $taluk_id, $panchayat_id, $locality, $postal_code, $landmark, $est_phone, $est_email, $est_fax, $contact_name, $contact_designation, $contact_mobile, $contact_phone, $contact_email, $pe_male, $pe_female, $pe_trans, $pe_disabled, $ce_male, $ce_female, $ce_trans, $ce_disabled, $firm_reg_no, $firm_req_dt, $esic_no, $epfo_no, $cin_no, $tan_no, $gstin_no, $is_legacy_reg, $is_reg_confirmed, $est_mobile, $status, $latitude_no, $longitude_no, $est_starts_from, $old_est_reg_no, $firm_id]);

        $rows = $db->FetchAll();

        if (count($rows) > 0) {
          $firm_id = $rows[0]['id'];
          $retVal['id'] = intval($rows[0]['id']);
          $retVal['message'] = "update successfully.";
        }

      }

      if (!is_null($firm_id) && !is_null($est_mobile)) {
        $this->insertFirmEstAct($db, $firm_id, $data);
        $this->insertFirmEstBussinesNature($db, $firm_id, $data);
      }

      if (!is_null($firm_id) && !is_null($pe_male)) {
        $this->saveEmployee_history($db, $firm_id, $data);
      }

      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retVal;
  }

  function saveOldFirm($data): array
  {
    $old_firm_id = $data->old_est_reg_no ?? null;
    $firm_id = $data->id ?? null;

    $params = [];
    $where_clause = '';

    if ($old_firm_id != null) {
      $params[] = $old_firm_id;
      $where_clause = 'and fm.old_est_reg_no = $' . count($params);
    }

    if ($firm_id != null) {
      $params[] = $firm_id;
      $where_clause .= ' and fm.id != $' . count($params);
    }

    $db = new PostgreDB();

    try {
      $sql = "select fm.*
                from est.firms as fm
               where true $where_clause";

      $db->Query($sql, $params);
      $old_rows = $db->FetchAll();

      $retVal['is_already_taken'] = (count($old_rows) > 0);

      $sql = "select oe.abcode,
                     oe.lname,
                     oe.email,
                     oe.mobile,
                     oe.tel,
                     oe.empydesignation
                from old.gmfam as oe
               where oe.abcode = $1";

      $db->Query($sql, [$old_firm_id]);
      $rows = $db->FetchAll();

      $retVal['rows'] = $rows;

    } catch (Exception $e) {
      $retVal['message'] = ErrorHandler::custom($e);
    }

    return $retVal;

  }

  private function insertFirmEstAct($db, $firm_id, $data)
  {
    $retVal = ['message' => 'Firm Establishment cannot be saved.'];
    $license_no = isset($data->license_no) ? $data->license_no : null;
    $act_id = isset($data->act_id) ? $data->act_id : null;
    $remarks = isset($data->remarks) ? $data->remarks : null;
    $other_act_name = isset($data->other_act_name) ? $data->other_act_name : null;
    $id = isset($data->id) ? $data->id : null;

    $sql = 'SELECT * FROM est.est_acts WHERE firm_id = $1';
    $db->Query($sql, [$firm_id]);
    $rows = $db->FetchAll();
    if (!count($rows) > 0) {

      $sql = 'INSERT INTO est.est_acts(
                         firm_id,
                         act_id,
                         other_act_name,
                         license_no,
                         remarks )
              VALUES ( $1, $2, $3, $4, $5 )
              RETURNING id';

      $db->Query($sql, [$firm_id, $act_id, $other_act_name, $license_no, $remarks]);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }
      if (count($rows) > 0) {
        $retVal['id'] = $rows[0]['id'];
        $retVal['message'] = "saved successfully.";
      }
    } else {
      $sql = 'UPDATE est.est_acts
                 SET act_id = $1, other_act_name = $2,license_no = $3, remarks = $4
               WHERE firm_id = $5';
      $db->Query($sql, [$act_id, $other_act_name, $license_no, $remarks, $firm_id]);
      $retVal['message'] = "saved successfully.";
    }

    return $retVal;
  }

  private function insertFirmEstBussinesNature($db, $firm_id, $data): array
  {
    $retVal = ['message' => 'Firm Establishment cannot be saved.'];
    $remarks = isset($data->remarks) ? $data->remarks : null;
//    $business_nature_id = isset($data->business_nature_id->id) ? $data->business_nature_id->id : $data->business_nature_id;
    $business_nature_id = $data->business_nature_id ?? null;
    $id = isset($data->id) ? $data->id : null;
    $act_id = isset($data->act_id) ? $data->act_id : null;

    if (!is_null($business_nature_id) && is_string($data->business_nature_id)) {
      $business_nature_id = $data->business_nature_id;
      $sql = 'SELECT * FROM mas.business_natures WHERE name = $1';
      $db->Query($sql, [$business_nature_id]);
      $rows = $db->FetchAll();

      if (!count($rows) > 0) {

        $sql = 'INSERT INTO mas.business_natures
                        ( act_id, name )
                VALUES ( $1, $2 )
                RETURNING id';
        $db->Query($sql, [$act_id, $business_nature_id]);
        $rows = $db->FetchAll();

        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
        }
        if (count($rows) > 0) {
          $business_nature_id = $rows[0]['id'];
        }
      }
    }
    $sql = 'SELECT * FROM est.est_business_natures WHERE firm_id = $1';
    $db->Query($sql, [$firm_id]);
    $rows = $db->FetchAll();
    if (!count($rows) > 0) {

      $sql = 'INSERT INTO est.est_business_natures
                    ( firm_id, business_nature_id, remarks )
            VALUES ( $1, $2, $3 )
            RETURNING id';
      $db->Query($sql, [$firm_id, $business_nature_id, $remarks]);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }
      if (count($rows) > 0) {
        $retVal['id'] = $rows[0]['id'];
        $retVal['message'] = "saved successfully.";
      }
    } else {
      $sql = 'UPDATE est.est_business_natures
                            SET business_nature_id = $1 ,remarks = $2
                    WHERE firm_id = $3';
      $db->Query($sql, [$business_nature_id, $remarks, $firm_id]);
      $retVal['message'] = "saved successfully.";
    }
    return $retVal;
  }

  private function saveEmployee_history($db, $firm_id, $data)
  {
    $retVal = ['message' => 'Employee History cannot be saved.'];
    $id = isset($data->id) ? $data->id : null;
    $pe_male = isset($data->pe_male) ? $data->pe_male : null;
    $pe_female = isset($data->pe_female) ? $data->pe_female : null;
    $pe_trans = isset($data->pe_trans) ? $data->pe_trans : null;
    $pe_disabled = isset($data->pe_disabled) ? $data->pe_disabled : null;
    $ce_male = isset($data->ce_male) ? $data->ce_male : null;
    $ce_female = isset($data->ce_female) ? $data->ce_female : null;
    $ce_trans = isset($data->ce_trans) ? $data->ce_trans : null;
    $ce_disabled = isset($data->ce_disabled) ? $data->ce_disabled : null;
    $tot_employees = isset($data->tot_employees) ? $data->tot_employees : null;
    $remarks = isset($data->remarks) ? $data->remarks : null;
    $year = date('Y');

    $sql = 'SELECT * FROM est.employee_history WHERE firm_id = $1 AND year = $2';
    $db->Query($sql, [$firm_id, $year]);
    $rows = $db->FetchAll();

    if (!count($rows) > 0) {

      $sql = 'INSERT INTO est.employee_history ( firm_id, year, pe_male, pe_female, pe_trans, pe_disabled, ce_male, ce_female, ce_trans, ce_disabled, tot_employees, remarks )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
            RETURNING id';
      $db->Query($sql, [$firm_id, $year, $pe_male, $pe_female, $pe_trans, $pe_disabled, $ce_male, $ce_female, $ce_trans, $ce_disabled, $tot_employees, $remarks]);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }
      if (count($rows) > 0) {
        $retVal['id'] = $rows[0]['id'];
        $retVal['message'] = "Employee History saved successfully.";
      }
    } else {
      $sql = 'UPDATE est.employee_history
                       SET pe_male =$3, pe_female =$4, pe_trans =$5, pe_disabled =$6, ce_male =$7, ce_female =$8, ce_trans =$9, ce_disabled =$10, tot_employees =$11, remarks=$12
                     WHERE firm_id =$1 AND year =$2';
      $db->Query($sql, [$firm_id, $year, $pe_male, $pe_female, $pe_trans, $pe_disabled, $ce_male, $ce_female, $ce_trans, $ce_disabled, $tot_employees, $remarks]);
      $retVal['message'] = "Employee History update successfully.";
    }
    return $retVal;
  }

  function saveFirmAttachment($data): array
  {
    // var_dump($data);
    $retVal = ['message' => 'Attachment cannot be saved.'];
    $firm_id = isset($data->firm_id) ? $data->firm_id : null;
    $doc_type = isset($data->doc_type) ? $data->doc_type : null;
    $esic_no = isset($data->esic_no) ? $data->esic_no : null;
    $epfo_no = isset($data->epfo_no) ? $data->epfo_no : null;
    $firm_reg_no = isset($data->firm_reg_no) ? $data->firm_reg_no : null;
    $firm_req_dt = isset($data->firm_req_dt) ? $data->firm_req_dt : null;

    // $id = isset($data->id)? $data->id : null;
    $db = new PostgreDB();
    try {
      $db->Begin();
      $attc = new Attachment();
      $attc->saveAttachment($data,$db);

      if ($doc_type == 'EPFO_DOC') {
        $sql = 'UPDATE est.firms SET epfo_no = $1 WHERE id = $2 RETURNING id';
        $db->Query($sql, [$epfo_no, $firm_id]);
      }
      if ($doc_type == 'ESIC_DOC') {
        $sql = 'UPDATE est.firms SET esic_no = $1 WHERE id = $2 RETURNING id';
        $db->Query($sql, [$esic_no, $firm_id]);
      }
      if ($doc_type == 'FIRM_DOC') {
        $sql = 'UPDATE est.firms SET firm_reg_no = $1,firm_req_dt=$2 WHERE id = $3 RETURNING id';
        $db->Query($sql, [$firm_reg_no, $firm_req_dt, $firm_id]);
      }
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      if (count($rows) > 0) {
        $retVal['id'] = $rows[0]['id'];
        $retVal['message'] = "Attachment saved successfully.";
      }

//      var_dump("sql vd ========${sql}");

      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

//  function saveFirmEst($data): array
//  {
//    $retVal = ['message' => 'Firm Establishment cannot be saved.'];
//
//    $user_id = isset($data->user_id) ? $data->user_id : null;
//    $is_legacy_reg = (isset($data->is_legacy_reg) && $data->is_legacy_reg == 'Y') ? 't' : 'f';
//    $firm_name = isset($data->firm_name) ? $data->firm_name : null;
//    $firm_reg_no = isset($data->firm_reg_no) ? $data->firm_reg_no : null;
//    $id = isset($data->id) ? $data->id : null;
//
//    $db = new PostgreDB();
//    try {
//      if (is_null($id)) {
//        $db->Begin();
//        $sql = 'INSERT
//                INTO est.firms ( user_id, is_legacy_reg, name, firm_reg_no, cre_ts )
//                VALUES ( $1, $2, $3, $4, now())
//                RETURNING id';
//        $db->Query($sql, [$user_id, $is_legacy_reg, $firm_name, $firm_reg_no]);
//        $rows = $db->FetchAll();
//
//        foreach ($rows as &$r) {
//          $r['id'] = intval($r['id']);
//        }
//        if (count($rows) > 0) {
//          $firm_id = $rows[0]['id'];
//
//          $retVal['id'] = $rows[0]['id'];
//          $retVal['message'] = "saved successfully.";
//        }
//      } else {
//        $sql = 'UPDATE est.firms
//                SET is_legacy_reg = $1, name = $2, firm_reg_no = $3
//                WHERE id = $4
//                RETURNING id';
//
//        $db->Query($sql, [$is_legacy_reg, $firm_name, $firm_reg_no, $id]);
//
//        $firm_id = $db->FetchAll()[0]['id'];
//        $retVal['message'] = "update successfully.";
//      }
//      $this->insertFirmEstAct($db, $firm_id, $data);
//      $this->insertFirmEstBussinesNature($db, $firm_id, $data);
//      $db->Commit();
//    } catch (Exception $e) {
//      $db->RollBack();
//      $retVal['message'] = ErrorHandler::custom($e);
//    }
//    $db->DBClose();
//    return $retVal;
//  }

  function getFirmsEst($filter, $payload): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    $limit = $filter->limit ?? null;
    $offset = $limit * ($filter->offset ?? 0);
    $user_id = (isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null;
    $o_designation_code = $filter->o_designation_code ?? null;
    $status = $filter->status ?? null;

    $where_clause = "";
    $params = [];
    $params[] = $limit;
    $params[] = $offset;

//    if (isset($filter->id) && $filter->id > 0) {
//      $id = $filter->id;
//      $params[] = $id;
//      $where_clause .= ' AND a.user_id = $' . count($params);
//    }

    if (!is_null($status)) {
      if ($status == 'REC&S') {
        $where_clause .= " AND (a.status = 'REC' OR a.status = 'S')";
      } else if ($status == 'REC&S&CLI') {
        $where_clause .= " AND (a.status = 'REC' OR a.status = 'S' OR a.status = 'CLR')";
      } else {
        $params[] = $status;
        $where_clause .= " AND a.status = $" . count($params);
      }
    }

//    if (is_string($o_designation_code)) {
//      $params[] = $o_designation_code;
//      $where_clause .= ' AND o_designation_code = $' . count($params);
//    }

    if (isset($filter->search_text) && is_string($filter->search_text)) {
      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (
                                UPPER(a.name) like UPPER(' . $param_cnt . ') OR
                                UPPER(a.lwb_reg_no) like UPPER(' . $param_cnt . ') OR
                                a.firm_reg_no ilike ' . $param_cnt . '
                                )';
    }

//    if (isset($filter->status) && is_string($filter->status)) {
//      $status = $filter->status;
////       if($status!='REC'){
////           $params[] = $status;
////           $where_clause .= ' AND a.status = $' . count($params).' AND a.o_designation_code is null';
////       }
//      if ($status == 'REC') {
//        $where_clause .= ' AND (a.status=\'REC\' OR a.status=\'VSECR\'  OR a.status=\'CLD\')';
//      } else if ($status == 'TOT-PEN-EST') {
//        $where_clause .= ' AND (a.status=\'S\' OR a.status=\'REC\' OR a.status=\'CLR\' OR a.status=\'CLD\'OR a.status=\'VSECR\')';
//      } else {
//        $params[] = $status;
//        $where_clause .= ' AND a.status = $' . count($params);
//      }
//    }

    if (isset($filter->regyear) && $filter->regyear > 0) {
      $regyear = $filter->regyear;
      $params[] = $regyear;
      $where_clause .= ' AND EXTRACT(YEAR FROM a.cre_ts) = $' . count($params);
    }

    $db = new PostgreDB();
    try {
//      $designation_code = (new User())->getDesignation($user_id, $db);
//      var_dump($user_id);

      $user = (new User())->getUsers((object)['id' => $user_id]);
      $designation_code = $user['rows'][0]['designation_code'];
      $user_name = $user['rows'][0]['name'];

      if (!is_null($user_id)) {
        if ($designation_code == 'APPS') {
          $params[] = $user_id;
          $where_clause .= ' AND a.user_id = $' . count($params);
        }
      }

      $sql = "SELECT a.id,a.name as firm_name,a.status,EXTRACT(YEAR FROM a.cre_ts)as regyear,
                            a.user_id, a.district_id,a.taluk_id,a.is_legacy_reg, a.is_reg_confirmed,
                            a.o_designation_code ,a.o_user_id,a.lwb_reg_no,a.firm_reg_no,
                            --(CASE WHEN a.o_designation_code IS NULL THEN TRUE ELSE FALSE END )AS is_del,
                            (CASE WHEN a.status = 'D' THEN 'Draft.'
                                  WHEN a.status = 'S' THEN 'Submitted for Verification.'
                                  WHEN a.status = 'REC' THEN 'Received.'
                                  WHEN a.status = 'CLR' THEN 'Clarification Responded.'
                                  WHEN a.status = 'CLI' THEN 'Clarification Issued.'
                                  WHEN a.status = 'VSO' THEN 'Verified By Section.'
                                  WHEN a.status = 'A' THEN 'Active.'
                                  WHEN a.status = 'R' THEN 'Rejected' ELSE NULL END) status_name,
                            (CASE WHEN b.business_nature_id IS NOT NULL THEN c.name ELSE b.other_business_nature_name END )AS business_nature_name,
                            (CASE WHEN d.act_id IS NOT NULL THEN e.name ELSE d.other_act_name END )AS act_name
                   FROM est.firms AS a
                   LEFT OUTER JOIN est.est_business_natures AS b ON ( b.firm_id = a.id )
                   LEFT OUTER JOIN mas.business_natures AS c ON ( c.id = b.business_nature_id )
                   LEFT OUTER JOIN est.est_acts AS d ON ( d.firm_id = a.id )
                   LEFT OUTER JOIN mas.acts AS e ON ( e.id = d.act_id )
                  WHERE true $where_clause
                  ORDER BY a.o_designation_code,a.name
                  LIMIT $1 OFFSET $2";

      $db->Query($sql, $params);
//      var_dump($sql);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['user_id'] = intval($r['user_id']);
        $r['district_id'] = intval($r['district_id']);
        $r['taluk_id'] = intval($r['taluk_id']);
        // $r['panchayat_id']      = intval($r['panchayat_id']);
        // $r['pe_male']           = intval($r['pe_male']);
        // $r['pe_female']         = intval($r['pe_female']);
        // $r['pe_trans']          = intval($r['pe_trans']);
        // $r['pe_disabled']       = intval($r['pe_disabled']);
        // $r['ce_male']           = intval($r['ce_male']);
        // $r['ce_female']         = intval($r['ce_female']);
        // $r['ce_trans']          = intval($r['ce_trans']);
        // $r['ce_disabled']       = intval($r['ce_disabled']);
        $r['regyear'] = intval($r['regyear']);
        $r['is_legacy_reg'] = ($r['is_legacy_reg'] == 't');
        $r['is_reg_confirmed'] = ($r['is_reg_confirmed'] == 't');
//        $r['is_del'] = ($r['is_del'] == 't');
      }
      $retObj['rows'] = $rows;

      // get total rows
      if (!is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                FROM est.firms AS a
                WHERE true' . $where_clause;
        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();
        foreach ($tot_rows as &$r) {
          $r['cnt'] = intval($r['cnt']);
        }
        $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
      } else {
        $retObj['tot_rows'] = ((!is_null($offset)) ? $offset : 0) + count($rows);
      }

    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    // var_dump($retObj);
    return $retObj;
  }

  function getFirmEst($filter)
  {
    $retObj = [];
    $where_clause = "";
    $params = [];

    if (isset($filter->id) && $filter->id > 0) {
      $id = $filter->id;
      $params[] = $id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      // get actual data
      /*      $sql = 'SELECT a.id,a.name AS firm_name,a.firm_reg_no ,a.user_id, a.is_legacy_reg ,a.address, a.district_id, a.taluk_id, a.panchayat_id,
                             a.locality, a.postal_code, a.landmark, a.est_phone, a.est_email, a.est_fax, a.contact_name, a.contact_designation,a.is_reg_confirmed,
                             a.contact_mobile, a.contact_phone, a.contact_email,a.employer_name,  a.pe_male, a.pe_female, a.pe_trans, a.pe_disabled,
                             a.ce_male, a.ce_female, a.ce_trans, a.ce_disabled, a.esic_no, a.epfo_no, a.firm_req_dt,a.est_mobile, a.est_phone, a.status,
                             (COALESCE(a.pe_male,0) + COALESCE(a.pe_female,0) + COALESCE(a.pe_trans,0) + COALESCE(a.ce_male,0) + COALESCE(a.ce_female,0)
                             + COALESCE(a.ce_trans,0))AS tot_emp,a.tneb_no,a.o_user_id, a.o_designation_code, a.lwb_reg_no,a.latitude_no ,a.longitude_no,
                             b.act_id, b.license_no ,b.other_act_name, b.license_no,
                             c.is_active AS is_act_active ,
                             d.business_nature_id ,d.other_business_nature_name,
                             e.is_active AS is_business_nature_active,
                                 f.type AS panchayat_type,
                                 (CASE WHEN f.type = \'T\' THEN \'Town Panchayat\'
                                       WHEN f.type = \'V\' THEN \'Village Panchayat\'
                                       WHEN f.type = \'M\' THEN \'Municipality\'
                                       ELSE NULL
                                  END
                                 )AS panchayat_type_name,
                                 g.name AS o_designation_name,
                                 h.name AS district_name,
                                 h.code AS district_code,
                                 i.name AS taluk_name,
                             (CASE WHEN b.act_id IS NULL THEN b.other_act_name
                                    WHEN b.act_id IS NOT NULL THEN c.name END )AS act_name,
                             (CASE WHEN d.business_nature_id IS NULL THEN d.other_business_nature_name
                                    WHEN d.business_nature_id IS NOT NULL THEN e.name END )AS business_nature_name,
                                 TO_JSON(ARRAY_AGG(to_jsonb(e))),
                                 sum(l.clr_ver)clr_ver,
                                 sum(l.clrf)clrf,
                                 sum(l.est_clrf )est_clrf,
                                 sum(l.est_addr_clrf)est_addr_clrf,
                                 sum(l.est_cont_clrf)est_cont_clrf,
                                 sum(l.est_mp_clrf )est_mp_clrf,
                                 sum( l.est_doc_clrf)est_doc_clrf,
                                 m.email AS user_email,
                                 count(n)as tneb_bill_count
                            FROM est.firms AS a
                                 LEFT OUTER JOIN est.est_acts AS b ON (a.id = b.firm_id)
                                 LEFT OUTER JOIN mas.acts AS c ON (b.act_id = c.id)
                                 LEFT OUTER JOIN est.est_business_natures AS d ON (d.firm_id = a.id)
                                 LEFT OUTER JOIN mas.business_natures AS e ON (e.id = d.business_nature_id)
                                 LEFT OUTER JOIN mas.panchayats AS f ON (a.panchayat_id = f.id )
                                 LEFT OUTER JOIN mas.designations AS g ON (a.o_designation_code = g.code)
                                 LEFT OUTER JOIN mas.districts AS h ON (a.district_id = h.id)
                                 LEFT OUTER JOIN mas.taluks AS i ON (a.taluk_id = i.id)
                                 LEFT OUTER JOIN LATERAL(
                                                 SELECT sum(CASE WHEN k.res_status=3 THEN 1 ELSE 0 END )AS clr_ver ,k.firm_id,k.action_code,k.res_status,
                                                        sum(CASE WHEN k.res_status=1 THEN 1 ELSE 0 END )AS clrf ,
                                                        sum(CASE WHEN k.action_code=\'EST_CLRF\' AND k.res_status=1 THEN 1 ELSE 0 END ) est_clrf,
                                        sum(CASE WHEN k.action_code=\'EST_ADDR_CLRF\' AND k.res_status=1 THEN 1 ELSE 0 END )est_addr_clrf,
                                        sum(CASE WHEN k.action_code=\'EST_CONT_CLRF\' AND k.res_status=1 THEN 1 ELSE 0 END )est_cont_clrf,
                                        sum(CASE WHEN k.action_code=\'EST_MP_CLRF\' AND k.res_status=1 THEN 1 ELSE 0 END )est_mp_clrf,
                                        sum(CASE WHEN k.action_code=\'EST_DOC_CLRF\' AND k.res_status=1 THEN 1 ELSE 0 END )est_doc_clrf
                                                 FROM (SELECT DISTINCT(j.action_code)as action_code
                                                       FROM est.clarifications AS j
                                                          WHERE j.firm_id=a.id)AS j
                                                                LEFT OUTER JOIN LATERAL(
                                                                                SELECT k.id ,k.action_code ,k.res_status,k.firm_id
                                                                                  FROM est.clarifications AS k
                                                                                 WHERE j.action_code = k.action_code
                                                                                   AND k.firm_id = a.id
                                                                              ORDER BY k.id desc LIMIT 1
                                                         )AS k ON TRUE
                                                         GROUP BY k.firm_id,k.action_code,k.res_status)
                                     AS l
                                     ON (l.firm_id =a.id)
                                 LEFT OUTER JOIN mas.users AS m ON (m.id = a.user_id)
                                 LEFT OUTER JOIN est.tneb_bills AS n ON (n.firm_id = a.id)
                           WHERE true' . $where_clause . '
                        GROUP BY a.id,a.name ,a.firm_reg_no ,a.user_id, a.is_legacy_reg ,
                                 b.act_id, b.license_no ,b.other_act_name,
                                 c.is_active  ,c.name,
                                 d.business_nature_id ,d.other_business_nature_name ,d.id ,
                                 e.is_active ,e.name,
                                 f.type,
                                 g.name,
                                 h.name,h.code,
                                 i.name,
                                 m.email
                           ORDER BY a.name';*/

      $sql = "
      SELECT a.id,
             a.name AS firm_name,
             a.firm_reg_no,
             a.old_est_reg_no,
             a.user_id,
             a.is_legacy_reg,
             a.address,
             a.district_id,
             a.taluk_id,
             a.panchayat_id,
             a.locality,
             a.postal_code,
             a.landmark,
             a.est_phone,
             a.est_email,
             a.est_fax,
             a.est_starts_from,
             a.contact_name,
             a.contact_designation,
             a.is_reg_confirmed,
             a.contact_mobile,
             a.contact_phone,
             a.contact_email,
             a.employer_name,
             a.pe_male,
             a.pe_female,
             a.pe_trans,
             a.pe_disabled,
             a.ce_male,
             a.ce_female,
             a.ce_trans,
             a.ce_disabled,
             a.esic_no,
             a.epfo_no,
             a.firm_req_dt,
             a.est_mobile,
             a.est_phone,
             a.status,
             (COALESCE(a.pe_male,0)+
              COALESCE(a.pe_female,0)+
              COALESCE(a.pe_trans,0)+
              COALESCE(a.ce_male,0)+
              COALESCE(a.ce_female,0)+
              COALESCE(a.ce_trans,0))AS tot_emp,
             a.o_user_id,
             a.o_designation_code,
             a.lwb_reg_no,
             a.latitude_no,
             a.longitude_no,
             b.act_id,
             b.license_no,
             b.other_act_name,
             b.license_no,
             c.is_active AS is_act_active,
             d.business_nature_id,
             d.other_business_nature_name,
             e.is_active AS is_business_nature_active,
             f.type AS panchayat_type,
             --(CASE WHEN f.type='T'THEN 'Town Panchayat'WHEN f.type='V'THEN 'Village Panchayat'WHEN f.type='M'THEN 'Municipality'ELSE NULL END)AS panchayat_type_name,
             (CASE WHEN f.type='T'THEN 'Town Panchayat'WHEN f.type='V'THEN 'Village Panchayat'WHEN f.type='M'THEN 'Municipality'ELSE NULL END)AS panchayat_name,
             g.name AS o_designation_name,
             h.name AS district_name,
             h.code AS district_code,
             i.name AS taluk_name,
             (CASE WHEN b.act_id IS NULL THEN b.other_act_name WHEN b.act_id IS NOT NULL THEN c.name END)AS act_name,
             (CASE WHEN d.business_nature_id IS NULL THEN d.other_business_nature_name WHEN d.business_nature_id IS NOT NULL THEN e.name END)AS business_nature_name,
             TO_JSON(ARRAY_AGG(to_jsonb(e))),
             sum(l.clr_ver) as clr_ver,
             sum(l.clrf) as clrf,
             sum(l.est_clrf)as est_clrf,
             sum(l.est_addr_clrf)as est_addr_clrf,
             sum(l.est_cont_clrf)as est_cont_clrf,
             sum(l.est_mp_clrf)as est_mp_clrf,
             sum(l.est_doc_clrf)as est_doc_clrf,
             m.email as user_email,
             count(n)as tneb_bill_count
             FROM est.firms AS a
             LEFT OUTER JOIN est.est_acts AS b ON(a.id=b.firm_id)
             LEFT OUTER JOIN mas.acts AS c ON(b.act_id=c.id)
             LEFT OUTER JOIN est.est_business_natures AS d ON(d.firm_id=a.id)
             LEFT OUTER JOIN mas.business_natures AS e ON(e.id=d.business_nature_id)
             LEFT OUTER JOIN mas.panchayats AS f ON(a.panchayat_id=f.id)
             LEFT OUTER JOIN mas.designations AS g ON(a.o_designation_code=g.code)
             LEFT OUTER JOIN mas.districts AS h ON(a.district_id=h.id)
             LEFT OUTER JOIN mas.taluks AS i ON(a.taluk_id=i.id)
             LEFT OUTER JOIN LATERAL(SELECT sum(CASE WHEN k.res_status=3 THEN 1 ELSE 0 END)AS clr_ver,
             		k.firm_id,k.action_code,k.res_status,
             		sum(CASE WHEN k.res_status=1 THEN 1 ELSE 0 END)AS clrf,
             		sum(CASE WHEN k.action_code='EST_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_clrf,
             		sum(CASE WHEN k.action_code='EST_ADDR_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_addr_clrf,
             		sum(CASE WHEN k.action_code='EST_CONT_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_cont_clrf,
             		sum(CASE WHEN k.action_code='EST_MP_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_mp_clrf,
             		sum(CASE WHEN k.action_code='EST_DOC_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_doc_clrf
             FROM mas.actions AS j
             LEFT OUTER JOIN LATERAL(SELECT k.id,
             			k.action_code,
             			k.res_status,
             			k.firm_id
             			FROM est.clarifications AS k
             			WHERE j.code=k.action_code AND k.firm_id=a.id
             			ORDER BY k.id desc LIMIT 1
             		)AS k ON TRUE
             	GROUP BY k.firm_id ,k.action_code ,k.res_status
             					)AS l ON(l.firm_id=a.id)
             LEFT OUTER JOIN mas.users AS m ON(m.id=a.user_id)
             LEFT OUTER JOIN est.tneb_bills AS n ON(n.firm_id=a.id)
--             WHERE true AND a.id=$1 GROUP BY a.id,
             WHERE true $where_clause GROUP BY a.id,
             a.name,
             a.firm_reg_no,
             a.user_id,
             a.is_legacy_reg,
             b.act_id,
             b.license_no,
             b.other_act_name,
             c.is_active,
             c.name,
             d.business_nature_id,
             d.other_business_nature_name,
             d.id,
             e.is_active,
             e.name,
             f.type,
             g.name,
             h.name,
             h.code,
             i.name,
             m.email ORDER BY a.name;";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['user_id'] = intval($r['user_id']);
        $r['act_id'] = isset($r['act_id']) ? intval($r['act_id']) : null;
        $r['business_nature_id'] = isset($r['business_nature_id']) ? intval($r['business_nature_id']) : null;
        $r['district_id'] = isset($r['district_id']) ? intval($r['district_id']) : null;
        $r['taluk_id'] = isset($r['taluk_id']) ? intval($r['taluk_id']) : null;
        $r['panchayat_id'] = isset($r['panchayat_id']) ? intval($r['panchayat_id']) : null;
        $r['tot_emp'] = isset($r['tot_emp']) ? intval($r['tot_emp']) : null;
        $r['clr_ver'] = isset($r['clr_ver']) ? intval($r['clr_ver']) : null;
        $r['clrf'] = isset($r['clrf']) ? intval($r['clrf']) : null;
        $r['pe_male'] = isset($r['pe_male']) ? intval($r['pe_male']) : null;
        $r['pe_female'] = isset($r['pe_female']) ? intval($r['pe_female']) : null;
        $r['pe_trans'] = isset($r['pe_trans']) ? intval($r['pe_trans']) : null;
        $r['pe_disabled'] = isset($r['pe_disabled']) ? intval($r['pe_disabled']) : null;
        $r['ce_male'] = isset($r['ce_male']) ? intval($r['ce_male']) : null;
        $r['ce_female'] = isset($r['ce_female']) ? intval($r['ce_female']) : null;
        $r['ce_trans'] = isset($r['ce_trans']) ? intval($r['ce_trans']) : null;
        $r['ce_disabled'] = isset($r['ce_disabled']) ? intval($r['ce_disabled']) : null;
        $r['tneb_bill_count'] = intval($r['tneb_bill_count']);
        $r['is_act_active'] = ($r['is_act_active'] == 't');
        $r['is_business_nature_active'] = ($r['is_business_nature_active'] == 't');
        $r['is_legacy_reg'] = ($r['is_legacy_reg'] == 't');
        $r['is_reg_confirmed'] = ($r['is_reg_confirmed'] == 't');
        $r['est_clrf'] = (intval($r['est_clrf']) != 0);
        $r['est_addr_clrf'] = (intval($r['est_addr_clrf']) != 0);
        $r['est_cont_clrf'] = (intval($r['est_cont_clrf']) != 0);
        $r['est_mp_clrf'] = (intval($r['est_mp_clrf']) != 0);
        $r['est_doc_clrf'] = (intval($r['est_doc_clrf']) != 0);
        $r['to_json'] = isset($r['to_json']) ? json_decode($r['to_json']) : null;
        $r['is_legacy_reg'] = ($r['is_legacy_reg'] == 't' ? 'Y' : 'N');
      }
      $retObj = $rows[0];
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    // var_dump($retObj);
    return $retObj;
  }

  function getFirmEstNew($filter)
  {
//    todo: simplify the below query & remove the getFirmEst API on both front & backend
    $retObj = [];
    $where_clause = "";
    $params = [];

    if (isset($filter->id) && $filter->id > 0) {
      $id = $filter->id;
      $params[] = $id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {
      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (UPPER(a.name) like UPPER(' . $param_cnt . '))';
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      // get actual data
      $sql = "
        SELECT a.id,
             a.name AS firm_name,
             a.firm_reg_no,
             a.user_id,
             a.is_legacy_reg,
             a.old_est_reg_no,
             a.address,
             a.district_id,
             a.taluk_id,
             a.panchayat_id,
             a.locality,
             a.postal_code,
             a.landmark,
             a.est_phone,
             a.est_email,
             a.est_fax,
             a.est_starts_from,
             a.contact_name,
             a.contact_designation,
             a.is_reg_confirmed,
             a.contact_mobile,
             a.contact_phone,
             a.contact_email,
             a.employer_name,
             a.pe_male,
             a.pe_female,
             a.pe_trans,
             a.pe_disabled,
             a.ce_male,
             a.ce_female,
             a.ce_trans,
             a.ce_disabled,
             a.esic_no,
             a.epfo_no,
             a.firm_req_dt,
             a.est_mobile,
             a.est_phone,
             a.status,
             (COALESCE(a.pe_male,0)+
              COALESCE(a.pe_female,0)+
              COALESCE(a.pe_trans,0)+
              COALESCE(a.ce_male,0)+
              COALESCE(a.ce_female,0)+
              COALESCE(a.ce_trans,0)) AS tot_emp,
             a.o_user_id,
             a.o_designation_code,
             a.lwb_reg_no,
             a.latitude_no,
             a.longitude_no,
             b.act_id,
             b.license_no,
             b.other_act_name,
             b.license_no,
             c.is_active AS is_act_active,
             d.business_nature_id,
             d.other_business_nature_name,
             e.is_active AS is_business_nature_active,
             f.type AS panchayat_type,
             --(CASE WHEN f.type='T'THEN 'Town Panchayat'WHEN f.type='V'THEN 'Village Panchayat'WHEN f.type='M'THEN 'Municipality'ELSE NULL END)AS panchayat_type_name,
             (CASE WHEN f.type='T'THEN 'Town Panchayat'WHEN f.type='V'THEN 'Village Panchayat'WHEN f.type='M'THEN 'Municipality'ELSE NULL END)AS panchayat_name,
             g.name AS o_designation_name,
             h.name AS district_name,
             h.code AS district_code,
             i.name AS taluk_name,
             (CASE WHEN b.act_id IS NULL THEN b.other_act_name WHEN b.act_id IS NOT NULL THEN c.name END)AS act_name,
             (CASE WHEN d.business_nature_id IS NULL THEN d.other_business_nature_name WHEN d.business_nature_id IS NOT NULL THEN e.name END)AS business_nature_name,
             TO_JSON(ARRAY_AGG(to_jsonb(e))),
             sum(l.clr_ver) as clr_ver,
             sum(l.clrf) as clrf,
             sum(l.est_clrf)as est_clrf,
             sum(l.est_addr_clrf)as est_addr_clrf,
             sum(l.est_cont_clrf)as est_cont_clrf,
             sum(l.est_mp_clrf)as est_mp_clrf,
             sum(l.est_doc_clrf)as est_doc_clrf,
             m.email as user_email,
             count(n)as tneb_bill_count
             FROM est.firms AS a
             LEFT OUTER JOIN est.est_acts AS b ON(a.id=b.firm_id)
             LEFT OUTER JOIN mas.acts AS c ON(b.act_id=c.id)
             LEFT OUTER JOIN est.est_business_natures AS d ON(d.firm_id=a.id)
             LEFT OUTER JOIN mas.business_natures AS e ON(e.id=d.business_nature_id)
             LEFT OUTER JOIN mas.panchayats AS f ON(a.panchayat_id=f.id)
             LEFT OUTER JOIN mas.designations AS g ON(a.o_designation_code=g.code)
             LEFT OUTER JOIN mas.districts AS h ON(a.district_id=h.id)
             LEFT OUTER JOIN mas.taluks AS i ON(a.taluk_id=i.id)
             LEFT OUTER JOIN LATERAL(SELECT sum(CASE WHEN k.res_status=3 THEN 1 ELSE 0 END)AS clr_ver,
             		k.firm_id,k.action_code,k.res_status,
             		sum(CASE WHEN k.res_status=1 THEN 1 ELSE 0 END)AS clrf,
             		sum(CASE WHEN k.action_code='EST_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_clrf,
             		sum(CASE WHEN k.action_code='EST_ADDR_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_addr_clrf,
             		sum(CASE WHEN k.action_code='EST_CONT_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_cont_clrf,
             		sum(CASE WHEN k.action_code='EST_MP_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_mp_clrf,
             		sum(CASE WHEN k.action_code='EST_DOC_CLRF'AND k.res_status=1 THEN 1 ELSE 0 END)est_doc_clrf
             FROM mas.actions AS j
             LEFT OUTER JOIN LATERAL(SELECT k.id,
             			k.action_code,
             			k.res_status,
             			k.firm_id
             			FROM est.clarifications AS k
             			WHERE j.code=k.action_code AND k.firm_id=a.id
             			ORDER BY k.id desc LIMIT 1
             		)AS k ON TRUE
             	GROUP BY k.firm_id ,k.action_code ,k.res_status
             					)AS l ON(l.firm_id=a.id)
             LEFT OUTER JOIN mas.users AS m ON(m.id=a.user_id)
             LEFT OUTER JOIN est.tneb_bills AS n ON(n.firm_id=a.id)
--             WHERE true AND a.id=$1 GROUP BY a.id,
             WHERE true $where_clause GROUP BY a.id,
             a.name,
             a.firm_reg_no,
             a.user_id,
             a.is_legacy_reg,
             b.act_id,
             b.license_no,
             b.other_act_name,
             c.is_active,
             c.name,
             d.business_nature_id,
             d.other_business_nature_name,
             d.id,
             e.is_active,
             e.name,
             f.type,
             g.name,
             h.name,
             h.code,
             i.name,
             m.email ORDER BY a.name;";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (isset($filter->id) && $filter->id > 0) {
        $sql = "select count(ss.status) filter ( where ss.status = 'V' )
                  from mas.screen_status as ss
            inner join mas.verifications v on (v.id = ss.verification_id and v.type = 'F')
                 where v.firm_id = $1";

        $db->Query($sql, [$id]);
        $clr_ver_count = $db->FetchAll()[0]['count'];
      }

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['user_id'] = intval($r['user_id']);
        $r['act_id'] = isset($r['act_id']) ? intval($r['act_id']) : null;
        $r['business_nature_id'] = isset($r['business_nature_id']) ? intval($r['business_nature_id']) : null;
        $r['district_id'] = isset($r['district_id']) ? intval($r['district_id']) : null;
        $r['taluk_id'] = isset($r['taluk_id']) ? intval($r['taluk_id']) : null;
        $r['panchayat_id'] = isset($r['panchayat_id']) ? intval($r['panchayat_id']) : null;
        $r['tot_emp'] = isset($r['tot_emp']) ? intval($r['tot_emp']) : null;

        $r['clr_ver'] = isset($clr_ver_count) ? intval($clr_ver_count) : 0;

        $r['clrf'] = isset($r['clrf']) ? intval($r['clrf']) : null;
        $r['pe_male'] = isset($r['pe_male']) ? intval($r['pe_male']) : null;
        $r['pe_female'] = isset($r['pe_female']) ? intval($r['pe_female']) : null;
        $r['pe_trans'] = isset($r['pe_trans']) ? intval($r['pe_trans']) : null;
        $r['pe_disabled'] = isset($r['pe_disabled']) ? intval($r['pe_disabled']) : null;
        $r['ce_male'] = isset($r['ce_male']) ? intval($r['ce_male']) : null;
        $r['ce_female'] = isset($r['ce_female']) ? intval($r['ce_female']) : null;
        $r['ce_trans'] = isset($r['ce_trans']) ? intval($r['ce_trans']) : null;
        $r['ce_disabled'] = isset($r['ce_disabled']) ? intval($r['ce_disabled']) : null;
        $r['tneb_bill_count'] = intval($r['tneb_bill_count']);
        $r['is_act_active'] = ($r['is_act_active'] == 't');
        $r['is_business_nature_active'] = ($r['is_business_nature_active'] == 't');
        $r['is_legacy_reg'] = ($r['is_legacy_reg'] == 't');
        $r['is_reg_confirmed'] = ($r['is_reg_confirmed'] == 't');
        $r['est_clrf'] = (intval($r['est_clrf']) != 0);
        $r['est_addr_clrf'] = (intval($r['est_addr_clrf']) != 0);
        $r['est_cont_clrf'] = (intval($r['est_cont_clrf']) != 0);
        $r['est_mp_clrf'] = (intval($r['est_mp_clrf']) != 0);
        $r['est_doc_clrf'] = (intval($r['est_doc_clrf']) != 0);
        $r['to_json'] = isset($r['to_json']) ? json_decode($r['to_json']) : null;
        $r['is_legacy_reg'] = ($r['is_legacy_reg'] == 't' ? 'Y' : 'N');
      }
      $retObj = $rows[0];
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function deleteFirmEst($data)
  {
    $retVal = [];
    $id = $data->id ?? null;

    $db = new PostgreDB();
    if (!is_null($id)) {
      try {
        $db->Begin();
        $sql = 'DELETE FROM est.est_acts WHERE firm_id = $1';
        $db->Query($sql, [$id]);

        $sql = 'DELETE FROM est.est_business_natures WHERE firm_id = $1';
        $db->Query($sql, [$id]);

        $sql = 'DELETE FROM est.employee_history WHERE firm_id = $1';
        $db->Query($sql, [$id]);

        $sql = 'SELECT a.* ,b.storage_name
                          FROM est.tneb_bills as a
                               LEFT OUTER JOIN doc.attachments as b ON(a.attachment_id = b.id)
                         WHERE a.firm_id = $1';
        $db->Query($sql, [$id]);
        $rows = $db->FetchAll();
        if (count($rows) > 0) {
          foreach ($rows as &$row) {
            $row = json_decode(json_encode($row));
            $this->deleteEstTnebBill($db, $row);
          }
        }
        $doc_type = 'TNEB_DOC';
        $sql = 'SELECT * FROM doc.attachments WHERE firm_id = $1 AND doc_type!=$2';
        $db->Query($sql, [$id, $doc_type]);
        $rows = $db->FetchAll();
        if (count($rows) > 0) {
          foreach ($rows as &$row) {
            $row = json_decode(json_encode($row));
            $this->deleteAttachment($db, $row);
          }
        }

        $sql = 'DELETE FROM est.firms WHERE id = $1';
        $db->Query($sql, [$id]);


        $retVal['message'] = "Firm Establishment deleted successfully.";
        $db->Commit();
      } catch (Exception $e) {
        $db->RollBack();
        $retVal['message'] = \LWMIS\Common\ErrorHandler::custom($e);
      }
    }
    $db->DBClose();
    return $retVal;
  }

  private function deleteEstTnebBill($db, $data)
  {
    $retVal = [];
    $id = isset($data->id) ? $data->id : null;
    $attachment_id = isset($data->attachment_id) ? $data->attachment_id : null;
    $file_name = isset($data->file_name) ? $data->file_name : null;
    $storage_name = isset($data->storage_name) ? $data->storage_name : null;

    if (!is_null($id) && !is_null($attachment_id)) {
      if (unlink($storage_name)) {

        $sql = 'DELETE FROM est.tneb_bills WHERE id = $1';
        $db->Query($sql, [$id]);
        $sql = 'DELETE FROM doc.attachments WHERE id = $1';
        $db->Query($sql, [$attachment_id]);

        $retVal['message'] = "TNEB Bill deleted successfully.";

      } else {

        $retVal['message'] = "TNEB Bill deletion failed.";
      }

    }
    return $retVal;
  }

  private function deleteAttachment($db, $data)
  {
    $retVal = [];
    $id = isset($data->id) ? $data->id : null;
    $attachment_id = isset($data->attachment_id) ? $data->attachment_id : $id;
    $storage_name = isset($data->storage_name) ? $data->storage_name : null;
    $code = isset($data->code) ? $data->code : null;
    $firm_id = isset($data->firm_id) ? $data->firm_id : null;

    if (unlink($storage_name)) {
      // $db = new \LWMIS\Common\PostgreDB();
      $sql = 'DELETE FROM doc.attachments WHERE id = $1';
      $db->Query($sql, [$attachment_id]);
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
      $retVal['message'] = "Deleted successfully.";
    }
    return $retVal;
  }

//  function getTotalEsts($filter): array
//  {
//    $retObj = [];
//
//    $where_clause = "";
//    $params = [];
//
//    $db = new PostgreDB();
//    try {
//      // get actual data
//      $sql = 'SELECT a.regyear,
//                    sum(a.est_un_yet_rec) AS est_un_yet_rec,
//                    sum(a.est_un_rec) AS est_un_rec,
//                    sum(a.rej)AS rej,
//                    sum(a.act)AS act,
//                    sum(a.not_issue)AS not_issue,
//                    sum(a.not_res)AS not_res,
//                    sum(a.yet_verf)AS yet_verf,
//                    (sum(a.est_un_yet_rec) + sum(a.est_un_rec) + sum(a.not_issue) + sum(a.not_res)) AS tot_pen_est
//                    FROM ( SELECT regyear,
//                                  sum(CASE WHEN a.status=\'S\' AND a.o_designation_code IS NULL THEN 1 ELSE 0 END )AS est_un_yet_rec,
//                                  sum(CASE WHEN a.status=\'REC\' OR a.status=\'V-SEC\' OR a.status=\'CLD\' THEN 1 ELSE 0 END )AS est_un_rec,
//                                  sum(CASE WHEN a.status=\'R\' THEN 1 ELSE 0 END )AS rej,
//                                  sum(CASE WHEN a.status=\'A\' THEN 1 ELSE 0 END )AS act,
//                                  sum(CASE WHEN a.status=\'CLR\' THEN 1 ELSE 0 END )AS not_issue,
//                                  sum(CASE WHEN a.status=\'CLD\' THEN 1 ELSE 0 END )AS not_res,
//                                  sum(CASE WHEN a.status=\'REC\' THEN 1 ELSE 0 END )AS yet_verf
//                             FROM ( SELECT EXTRACT(YEAR FROM a.cre_ts)AS regyear,a.status,a.id,a.o_user_id, a.o_designation_code
//                                      FROM est.firms AS a
//                                  GROUP BY a.cre_ts, a.status, a.o_user_id, a.o_designation_code,a.id
//                                  ) AS a
//                           GROUP BY a.regyear
//                           ORDER BY a.regyear DESC
//                           )AS a
//                  GROUP BY a.regyear;';
//
//      $db->Query($sql, $params);
//      $rows = $db->FetchAll();
//
//      foreach ($rows as &$r) {
//        $r['regyear'] = isset($r['regyear']) ? intval($r['regyear']) : 0;
//        $r['est_un_yet_rec'] = isset($r['est_un_yet_rec']) ? intval($r['est_un_yet_rec']) : 0;
//        $r['tot_pen_est'] = isset($r['tot_pen_est']) ? intval($r['tot_pen_est']) : 0;
//        $r['rej'] = isset($r['rej']) ? intval($r['rej']) : 0;
//        $r['not_issue'] = isset($r['not_issue']) ? intval($r['not_issue']) : 0;
//        $r['act'] = isset($r['act']) ? intval($r['act']) : 0;
//        $r['not_res'] = isset($r['not_res']) ? intval($r['not_res']) : 0;
//        $r['yet_verf'] = isset($r['yet_verf']) ? intval($r['yet_verf']) : 0;
//      }
//      $retObj = $rows;
//
//    } catch (Exception $e) {
//      $retObj['message'] = ErrorHandler::custom($e);
//    }
//    $db->DBClose();
//    return $retObj;
//  }

  function getTotalEstablishments($filter, $payload): array
  {
    $user_id = (isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null;

    $retObj = [];
    $db = new PostgreDB();
    try {
      $designation_code = (new \LWMIS\Master\User())->getDesignation($user_id, $db);

      if ($designation_code == 'SO' || $designation_code == 'SP') {

        if ($designation_code == 'SO') {
//          Received claims for SO 's are S, REC, CLR
//          Pending Claims for SO 's are CLI, VSO

          $rec_est = "count(*) filter ( where fm.status = 'CLR' ) +
                      count(*) filter ( where fm.status = 'S' ) +
                      count(*) filter ( where fm.status = 'REC' ) as rec_est,";

          $pend_est = "count(*) filter ( where fm.status = 'CLI' ) + count(*) filter ( where fm.status = 'VSO' ) as pen_est,";
        } else {
          //for sup
//          Received Claims for SP 's are VSO
//          Pending Claims for SP 's are CLI, REC & S( s is need to implement)

          $rec_est = "count(*) filter ( where fm.status = 'VSO' ) as rec_est,";
          $pend_est = "count(*) filter ( where fm.status = 'CLI' ) +
                       count(*) filter ( where fm.status = 'CLR' ) +
                       count(*) filter ( where fm.status = 'REC' ) +
                       count(*) filter ( where fm.status = 'S') as pen_est,";
        }

        $sql = "select extract(YEAR from fm.cre_ts)                                                           as cre_year,
                       $rec_est
                       $pend_est
                       count(*) filter ( where fm.status = 'A' )                                              as approved,
                       count(*) filter ( where fm.status = 'R' )                                              as rejected
                from est.firms as fm
                group by cre_year
                order by cre_year desc";

//        var_dump('sql',$sql);
      } else {
        $sql = "select extract(YEAR from fm.cre_ts)                                                                   as cre_year,
                       count(*) filter ( where fm.status = 'CLI' ) + count(*) filter ( where fm.status = 'CLR' )
                           + count(*) filter ( where fm.status = 'REC' ) +count(*) filter ( where fm.status = 'VSO' ) as pen_est,
                       count(*) filter ( where fm.status = 'A' )                                                      as approved,
                       count(*) filter ( where fm.status = 'R' )                                                      as rejected
                from est.firms as fm
                group by cre_year";
      }
//      var_dump($sql);
      $db->Query($sql, []);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['cre_year'] = isset($r['cre_year']) ? intval($r['cre_year']) : 0;
        $r['rec_est'] = isset($r['rec_est']) ? intval($r['rec_est']) : 0;
        $r['pen_est'] = isset($r['pen_est']) ? intval($r['pen_est']) : 0;
        $r['approved'] = isset($r['approved']) ? intval($r['approved']) : 0;
        $r['rejected'] = isset($r['rejected']) ? intval($r['rejected']) : 0;
      }

      $retObj = $rows;
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

//  function firmReject($data)
//  {
//    $retVal = [];
//    $id = isset($data->id) ? $data->id : null;
//    $status = isset($data->status) ? $data->status : null;
//
//    $db = new PostgreDB();
//    if (!is_null($id)) {
//      try {
//        $sql = 'UPDATE est.firms
//                   SET status=$1
//                 WHERE id = $2';
//        $db->Query($sql, [$status, $id]);
//
//        $retVal['message'] = "Firm Establishment Rejected.";
//        $db->DBClose();
//      } catch (Exception $e) {
//        $db->RollBack();
//        $retVal['message'] = $e->getMessage();
//      }
//    }
//    $db->DBClose();
//    return $retVal;
//  }

  function firmActiveEst($data): array
  {
    $retVal = [];
    $firm_id = $data->firm_id ?? null;
//    $status = $data->status ?? null;
    $status = 'A';
    $act_name = $data->act_name ?? null;
    $district_id = $data->district_id ?? null;//get district_id on stored procedure not from api.
    $year = date("Y");
    $lwb_reg_no = null;

//    todo: write postgresql stored procedure.
    $db = new PostgreDB();
    $db->Begin();

    try {
      if (is_null($firm_id)) {
        throw new Exception("Firm is required !");
      }

      if (is_null($act_name)) {
        throw new Exception("Act name is required !");
      }

      $sql = "select code from mas.districts where id = $1";
      $db->Query($sql, [$district_id]);
      $district_code = $db->FetchAll()[0]["code"];

      if ($status == 'A') {
        $reg = explode(" ", $act_name);
        $reg_no = $district_code . '/';

        for ($i = 0; $i < count($reg); $i++) {
          $reg_no .= substr(strtoupper($reg[$i]), 0, 3);
          if ($i < (count($reg) - 1)) {
            $reg_no .= '-';
          }
        }
        $lwb_reg_no = $reg_no . '/' . $year . '/';
      }

//        $sql = 'SELECT * FROM est.firms AS a WHERE a.lwb_reg_no IS NOT NULL';
      $sql = "select count(*)
                from est.firms
                where lwb_reg_no is not null
                  and
                extract(year from cre_ts) = extract(year from now())";

      $db->Query($sql, []);
      $firms_reg_this_year = $db->FetchAll()[0]['count'];
      $lwb_reg_no .= $firms_reg_this_year;

      $sql = 'UPDATE est.firms
                   SET status=$1, lwb_reg_no=$2, lwb_req_dt = now()
                 WHERE id = $3';
      $db->Query($sql, [$status, $lwb_reg_no, $firm_id]);

      $sql = "SELECT EXTRACT(YEAR from gsy.year) AS year,
                       COALESCE(eh.pe_male, fm.pe_male) +
                       COALESCE(eh.pe_female, fm.pe_female) +
                       COALESCE(eh.pe_trans, fm.pe_trans) +
                       COALESCE(eh.ce_male, fm.ce_male) +
                       COALESCE(eh.ce_female, fm.ce_female) +
                       COALESCE(eh.ce_trans, fm.ce_trans)  AS tt_emp,

                       (COALESCE(eh.pe_male, fm.pe_male) +
                        COALESCE(eh.pe_female, fm.pe_female) +
                        COALESCE(eh.pe_trans, fm.pe_trans) +
                        COALESCE(eh.ce_male, fm.ce_male) +
                        COALESCE(eh.ce_female, fm.ce_female) +
                        COALESCE(eh.ce_trans, fm.ce_trans)) *
                       gsy.total_cntrbtn                   AS tt_amt,

                       gsy.employee_cntrbtn                AS empe_ctb,
                       gsy.employer_cntrbtn                AS empr_ctb
                FROM est.firms as fm
                         inner join lateral (
                    SELECT s.year::DATE,
                           a.employee_cntrbtn,
                           a.employer_cntrbtn,
                           a.total_cntrbtn
                    --FROM GENERATE_SERIES((to_char(fm.lwb_req_dt,'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
                    --FROM GENERATE_SERIES((to_char('2020-08-28 11:45:57.520928 +00:00'::date,'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
                    FROM GENERATE_SERIES((to_char(fm.lwb_req_dt::date,'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
                             INNER JOIN mas.contributions a
                                        ON (s.year BETWEEN a.from_year AND COALESCE(a.to_year::date, CURRENT_DATE::date))
                             LEFT JOIN est.employee_history AS eh ON
                        (fm.id = eh.firm_id AND eh.year = extract(YEAR from s.year))
                    ) as gsy on true
                         LEFT JOIN est.employee_history AS eh ON
                    (fm.id = eh.firm_id AND eh.year = extract(YEAR from gsy.year))
                where fm.id = $1";

      $db->Query($sql, [$firm_id]);
      $rows = $db->FetchAll();

      foreach ($rows as $row) {
        $sql = "insert into est.payables(year,
                    firm_id,
                    tot_employees,
                    employee_cntrbtn,
                    employer_cntrbtn,
                    total_cntrbtn,
                    employee_amt,
                    employer_amt,
                    total_amt,
                    bal_amt)
                    values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)";

        $db->Query($sql, [$row['year'], $firm_id, $row['tt_emp'], $row['empe_ctb'], $row['empr_ctb'],
            ($row['empe_ctb'] + $row['empr_ctb']),
            ($row['tt_emp'] * $row['empe_ctb']),
            ($row['tt_emp'] * $row['empr_ctb']),
            $row['tt_amt'],
            $row['tt_amt']]
        );
      }

      $db->Commit();
      $retVal['message'] = "Firm Establishment Activated.";
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retVal;
  }

  function getUserIdByFirmId($db, $firm_id)
  {
    $sql = "SELECT user_id
            FROM est.firms
            WHERE id = $1";

    $db->Query($sql, [$firm_id]);
    return $db->FetchAll();
  }

//  function verifyQuickPayCusID($filter)
//  {
//    $retObj = ['message' => 'Invalid QuickPay Link / Link expired.'];
//    $enc_cus_id = $filter->enc_cus_id ?? null;
//    if (!is_null($enc_cus_id)) {
//      try {
//        $c_id = base_convert($enc_cus_id, 32, 10);
//        $customer = $this->getCustomer((object)['id' => $c_id]);
//        if (isset($customer['id'])) {
//          $retObj['message'] = 'Verified Quick Pay link successfully.';
//          $retObj['customer'] = $customer;
//        }
//      } catch (\Exception $e) {
//        // return default message;
//      }
//    }
//    return $retObj;
//  }

  function savePfDetails($file)
  {
    $retVal = [];
    $retVal['message'] = 'Only Post Method is allowed';
    $firm_id = $_FILES['firm_id']['name'];

    $db = new PostgreDB();
    $db->Begin();

    try {
      if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if ((new SplFileInfo($_FILES['file']['name']))->getExtension() != 'csv') {
          $retVal['message'] = 'Only a file with .csv extension is allowed.';
          return $retVal;
        }

        $target_path = "../uploads/temp_files/";

        if (!is_dir("../uploads/")) {
          mkdir("../uploads/", 0777);
        }

        if (!is_dir("../uploads/temp_files")) {
          mkdir("../uploads/temp_files/", 0777);
        }

        $target_path = $target_path . basename(time() . '.csv');
        $file_location = dirname(__DIR__, 3) . str_replace('../', '/', $target_path);

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
          if (($handle = fopen($file_location, "r")) !== FALSE) {
            $row = 1;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
              $num = count($data);

              if ($num != 7) {
                $retVal['message'] = 'Required all 7 fields';
                return $retVal;
              }

              if ($row > 1) {
//                $sql = "insert into est.pf_details (
//                            est_id,
//                            uan_no,
//                            emp_name,
//                            emp_contact_no,
//                            date_of_joining,
//                            gender,
//                            is_differently_able)
//                        values ($1,$2,$3,$4,$5,$6,$7)
//                        on conflict (uan_no)
//                        do
//                        update set est_id = $1
//                        ";
//                var_dump(strlen($data[4]));
//                $data[4] = (strlen($data[4]) == 0) ? null : $data[4];

//                var_dump($data[0]);

                if (strlen($data[4]) == 0) {
                  $data[4] = null;
                }

                $sql = "insert into est.pf_details (est_id,
                                                    uan_no,
                                                    emp_name,
                                                    emp_contact_no,
                                                    date_of_joining,
                                                    date_of_leaving,
                                                    gender,
                                                    is_differently_able)
                        values ($1,$2,$3,$4,$5,$6,$7,$8)
                        on conflict on constraint pf_details_est_id_uan_no_key
                        do update
                        set date_of_leaving = $6";
                $db->Query($sql, [$firm_id, ...$data]);

              }
              $row++;
            }
            fclose($handle);
          }
          $db->Commit();
          unlink($file_location);
          $retVal['message'] = 'Saved successfully';
        }

      }
    } catch (\Exception $e) {
      $retVal['message'] = 'Not Saved!Kindly Check the given details format !';
      $retVal['message'] .= ErrorHandler::custom($e);
      $db->RollBack();
    }
    $db->DBClose();
    return $retVal;
  }

  function getPermanentEmployeeCounts($data): array|string
  {
    $firm_id = $data->firm_id ?? null;
    $db = new PostgreDB();
    try {
      $sql = "select count(pf.gender) filter ( where gender = 'M' ) as male,
                     count(pf.gender) filter ( where gender = 'F' ) as female,
                     count(pf.gender) filter ( where gender = 'T' ) as trans,
                     count(pf.is_differently_able) filter ( where is_differently_able = true ) as diff_abled
                from est.pf_details pf
               where pf.date_of_leaving is not null
                 and pf.est_id = $1";

      $db->Query($sql, [$firm_id]);
      $rows = $db->FetchAll();
    } catch (\Exception $e) {
      $rows = ErrorHandler::custom($e);
    }
    return $rows;
  }

  function getUAN_Details($data): array
  {
    $retVal = [];
    $retVal['rows'] = 'Not Available in LWMIS Database';
    $uan_no = $data->uan_no ?? null;
    $db = new PostgreDB();

    try {
      $sql = "select uan_no,
                     f.name as firm_name,
                     emp_name,
                     emp_contact_no,
                     date_of_leaving,
                     date_of_joining,
                     gender,
                     is_differently_able
              from est.pf_details pf
              left join est.firms f on pf.est_id = f.id
              where pf.uan_no = $1";

      $db->Query($sql, [$uan_no]);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        $retVal['rows'] = $rows[0];
      }

    } catch (\Exception $e) {
      $retVal['message'] = ErrorHandler::custom($e);
    }
    return $retVal;
  }

  public function updateFirmStatus(object $data): array
  {
//    Note: Use firmActiveEst API for 'A' status otherwise firm_reg_no will not bbe created.
    $id = $data->id ?? null;
    $status = $data->status ?? null;
    $retVal = ['message' => 'Firm Registration Can\'t be saved.'];
    $db = new PostgreDB();

    try {
      $db->Begin();
      $sql = "UPDATE est.firms
                 SET status = $2
               WHERE id = $1
           RETURNING id";

      $db->Query($sql, [$id, $status]);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        $retVal['message'] = "Firm Details are Updated Successfully.";
      }
      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retVal;
  }

}
