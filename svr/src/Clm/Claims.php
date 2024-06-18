<?php

namespace LWMIS\Clm;

use Exception;
use LWMIS\Common\ErrorHandler;
use LWMIS\Common\PostgreDB;
use LWMIS\Doc\Attachment;
use LWMIS\Master\User;

class Claims
{
  function saveClaimDetails(object $data): array
  {
    $e_aadhaar_no = $data->e_aadhaar_no ?? null;
    $scheme_code = $data->scheme_code ?? null;
    $claim_id = $data->id ?? null;
    $sm_cert_dt = $data->sm_cert_dt ?? null;

    $mark_lists = $data->mark_lists ?? null;
    $retVal = ['message' => 'Claim Registration Can\'t be saved.'];
    $ref_no = $data->ref_no ?? null;//<- generated during the claim form registration.

    //empDetails
    $e_name = $data->e_name ?? null;
    $firm_id = $data->firm_id ?? null;
    $e_designation = $data->e_designation ?? null;
    $e_marital_status = $data->e_marital_status ?? null;
    $e_dob = $data->e_dob ?? null;
    $e_gender = $data->e_gender ?? null;
    $e_mobile = $data->e_mobile ?? null;
    $e_phone = $data->e_phone ?? null;
    $e_email = $data->e_email ?? null;
    $e_salary_pm = $data->e_salary_pm ?? null;
    $e_id_no = $data->e_id_no ?? null;
    $e_uan_no = $data->e_uan_no ?? null;
    $e_working_from_date = $data->e_working_from_date ?? null;

    //empAddress
    $ad_house_no = $data->ad_house_no ?? null;
    $ad_street_name = $data->ad_street_name ?? null;
    $ad_area = $data->ad_area ?? null;
    $ad_district_id = $data->ad_district_id ?? null;
    $ad_panchayat_id = $data->ad_panchayat_id ?? null;
    $ad_taluk_id = $data->ad_taluk_id ?? null;
    $ad_landmark = $data->ad_landmark ?? null;
    $ad_postal_code = $data->ad_postal_code ?? null;
    $ad_latitude = $data->ad_latitude ?? null;
    $ad_longitude = $data->ad_longitude ?? null;

    // Sewing Machine Assistance
    $dp_name = $data->dp_name ?? null;
    $acc_type = ($scheme_code == 'NAT_D_AST' || $scheme_code == 'AC_D_AST') ? 'D' : 'E';

    //** emp & dependent bank details
    $acc_ifsc = $data->acc_ifsc ?? null;
    $acc_bank_acc_name = $data->acc_bank_acc_name ?? null;
    $acc_bank_acc_no = $data->acc_bank_acc_no ?? null;

    $dp_relationship = $data->dp_relationship ?? null;
    $dp_gender = $data->dp_gender ?? null;
    $dp_mobile = $data->dp_mobile ?? null;
    $dp_phone = $data->dp_phone ?? null;
    $dp_email = $data->dp_email ?? null;
    $dp_dob = $data->dp_dob ?? null;
    $dp_street_name = $data->dp_street_name ?? null;
    $dp_house_no = $data->dp_house_no ?? null;
    $dp_area = $data->dp_area ?? null;
    $dp_district_id = $data->dp_district_id ?? null;
    $dp_panchayat_id = $data->dp_panchayat_id ?? null;
    $dp_taluk_id = $data->dp_taluk_id ?? null;
    $dp_aadhaar = $data->dp_aadhaar ?? null;
    $dp_aadhaar_file = $data->dp_aadhaar_file ?? null;
    $dp_postal_code = $data->dp_postal_code ?? null;
    $dp_latitude = $data->dp_latitude ?? null;
    $dp_longitude = $data->dp_longitude ?? null;
    $dp_landmark = $data->dp_landmark ?? null;
    $sm_course_duration_in_mon = $data->smCourseDuration ?? null;

    // common for ei,es,qb
    $prev_class_studied = $data->prev_class_studied ?? null;
    $curr_class_studied = $data->curr_class_studied ?? null;
    $ei_district_id = $data->ei_district_id ?? null;
    $ei_edu_district_id = $data->ei_edu_district_id ?? null;
    $ei_es_mark_sheet_no = $data->ei_es_mark_sheet_no ?? null;

    // passed prev education (for es, ei, ba)
    $is_passed_prev_edu = $data->is_passed_prev_edu ?? null;
    $inst_type = null;
    $cert_issue_date = $data->cert_issue_date ?? null;
    $ei_es_mark_percent = $data->ei_es_mark_percent ?? null;

    switch ($scheme_code) {
      case 'SM_AST':
        $inst_type = 'T';
        break;
      case 'CT_AST':
      case 'BOOK_ALW':
        $inst_type = 'I';
        break;
      case 'ED_INC':
      case 'DIST_SA':
      case 'STATE_SA':
        $inst_type = 'S';
        break;
    }

    $inst_name = $data->inst_name ?? null;
    $inst_contact_no = $data->inst_contact_no ?? null;
    $inst_address = $data->inst_address ?? null;
    $sm_tailoring_inst_is_govt_recognised = $data->sm_tailoring_inst_is_govt_recognised ?? null;
    $sm_tailoring_inst_reg_no = $data->sm_tailoring_inst_reg_no ?? null;

    //computer Training Assistance
    $ct_course_duration_in_mon = $data->ct_course_duration_in_mon ?? null;
    $ct_inst_is_govt_recognised = $data->ct_inst_is_govt_recognised ?? null;
    $ct_course_completion_cert_no = $data->ct_course_completion_cert_no ?? null;

    // bonafide cert (for ba, qb, ds, ss)
    $bonafide_cert_no = $data->bonafide_cert_no ?? null;

    //entrance coaching exam
    $ec_10th_pass_date = $data->ec_10th_pass_date ?? null;
    $ec_10th_mark_sheet_no = $data->ec_10th_mark_sheet_no ?? null;

    //    death details
    $e_death_date = $data->e_death_date ?? null;
    $e_death_time = $data->e_death_time ?? null;
    $e_death_place = $data->e_death_place ?? null;
    $ad_accident_nature = $data->ad_accident_nature ?? null;
    $e_death_cert_no = $data->e_death_cert_no ?? null;
    $e_death_cert_file = $data->e_death_cert_file ?? null;
    $ad_fir_no = $data->ad_fir_no ?? null;
    $ad_fir_file = $data->ad_fir_file ?? null;
    $ad_postmortem_report_no = $data->ad_postmortem_report_no ?? null;
    $ad_postmortem_report_file = $data->ad_postmortem_report_file ?? null;

    $dp_legal_heir_cert_no = $data->dp_legal_heir_cert_no ?? null;
    $dp_legal_heir_cert_file = $data->dp_legal_heir_cert_file ?? null;

    //    mrgAssistance
    $ma_g_name = $data->ma_g_name ?? null;
    $ma_g_dob = $data->ma_g_dob ?? null;
    $ma_b_name = $data->ma_b_name ?? null;
    $ma_b_dob = $data->ma_b_dob ?? null;
    $ma_wedding_dt = $data->ma_wedding_dt ?? null;
    $ma_wedding_place = $data->ma_wedding_place ?? null;
    $ma_b_aadhaar = $data->ma_b_aadhaar ?? null;
    $ma_b_aadhaar_file = $data->ma_b_aadhaar_file ?? null;
    $ma_g_aadhaar = $data->ma_g_aadhaar ?? null;
    $ma_g_aadhaar_file = $data->ma_g_aadhaar_file ?? null;
    $ma_marriage_cert_no = $data->ma_marriage_cert_no ?? null;
    $ma_marriage_cert_file = $data->ma_marriage_cert_file ?? null;
    $ma_b_family_ration_card_no = $data->ma_b_family_ration_card_no ?? null;
    $ma_g_family_ration_card_no = $data->ma_g_family_ration_card_no ?? null;
    $ma_b_family_ration_card_file = $data->ma_b_family_ration_card_file ?? null;
    $ma_g_family_ration_card_file = $data->ma_g_family_ration_card_file ?? null;
    $mr_invitation = $data->ma_invitation ?? null;

    //  spectacle assistance
    $sa_prescription_dt = $data->sa_prescription_dt ?? null;
    $sa_purchase_dt = $data->sa_purchase_dt ?? null;

    $status = $data->status ?? null;
    //    $create_ts = ($status = 'S' ? 'now()' : null);

    //    *** For $qb_subjects ***
    $qb_subjects = $data->qb_subjects ?? null;

    if (!is_null($qb_subjects)) {
      $qbs = [];
      foreach ($qb_subjects as $arr) {
        $qbs[] = $arr->code;
        unset($arr->name);
      }
      $qb_subjects = null;
      $qb_subjects = $qbs;
    }

    $ad_panchayat_type = $data->ad_panchayat_type ?? null;
    $dp_panchayat_type = $data->dp_panchayat_type ?? null;
    $claim_amount = $data->claim_amount ?? null;

    if (!is_null($scheme_code) && is_null($claim_id)) {
      if ($scheme_code == 'MRG_AST') {
        $duplicate_claim_id = $this->duplicateMrgByDifferentEmployee($ma_b_aadhaar, $ma_g_aadhaar, $ma_b_family_ration_card_no, $ma_g_family_ration_card_no, $e_id_no, $ma_marriage_cert_no, $scheme_code, $e_aadhaar_no);
      } elseif ($scheme_code == 'ED_INC' || $scheme_code == 'Q_BANK') {
        $duplicate_claim_id = $this->curr_academic_year($scheme_code, $e_id_no, $e_aadhaar_no, $dp_aadhaar);

        if (!empty($duplicate_claim_id) && count($duplicate_claim_id) > 0) {
          return ['message' => 'Duplicate Claims are not Allowed!'];
        } else {
          $duplicate_value = $this->onlyOnceSchemeCheckup($e_id_no, $e_aadhaar_no, $scheme_code, $dp_aadhaar);
          if (!empty($duplicate_value) && count($duplicate_value) >= 2) {
            return ['message' => "Employee is not allowed to apply for a same scheme twice."];
          }
        }

      } elseif ($scheme_code == 'ED_SCH' || $scheme_code == 'BOOK_ALW' || $scheme_code == 'DIST_SA' || $scheme_code == 'STATE_SA') {
        $duplicate_claim_id = $this->curr_academic_year($scheme_code, $e_id_no, $e_aadhaar_no, $dp_aadhaar);
      } else {
        $duplicate_claim_id = $this->onlyOnceSchemeCheckup($e_id_no, $e_aadhaar_no, $scheme_code, $dp_aadhaar);
      }

      if (!empty($duplicate_claim_id) && count($duplicate_claim_id) > 0) {
        return ['message' => 'Duplicate Claims are not Allowed!'];
      }
    }

    //    $id = $data->user_id ?? null;
    $db = new PostgreDB();


//    if ($mark_lists != null) {
    if ($mark_lists != null && count($mark_lists) > 0) {
      // fix/update it with mark list id.

      $claim_mark_list = new MarkLists();
//          $oml = $claim_mark_list->getMarkLists($data);
//          if (count($oml['rows']) > 5) {
//            return ['message' => 'Duplicate Mark Lists are not saved successfully.'];
//          }
      $msg = $claim_mark_list->saveMarkList($db, $data);

      if ($msg['message'] != "Mark list details are Saved Successfully.") {
        $db->RollBack();
        return ['message' => 'Claim Registration Can\'t be saved.'];
      }

    }
//    }

    try {
      $db->Begin();
      if (is_null($claim_id)) {
        $sql = "INSERT INTO clm.claims (
                        e_name,
                        firm_id,
                        scheme_code,
                        e_designation,
                        e_dob,
                        e_gender,
                        e_marital_status,
                        e_mobile,
                        e_phone,
                        e_email,
                        e_salary_pm,
                        e_aadhaar_no,
                        e_id_no,
                        e_uan_no,
                        e_working_from_date,
                        status,
                        cre_ts,
                        claim_amount,
                        claim_reg_no)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,
                        (SELECT scheme_amt FROM clm.scheme_claim_amts WHERE scheme_code = $18),
                        (select clm.generate_clm_reg_no($19)))
                RETURNING id;";

        $db->Query($sql, [$e_name, $firm_id, $scheme_code, $e_designation, $e_dob, $e_gender, $e_marital_status, $e_mobile,
          $e_phone, $e_email, $e_salary_pm, $e_aadhaar_no, $e_id_no, $e_uan_no, $e_working_from_date, $status,
          'now()', $scheme_code, $scheme_code]);
      } else {

        $params = [$e_name, $firm_id, $scheme_code, $e_designation, $e_dob, $e_gender, $e_marital_status, $e_mobile, $e_phone, $e_email, $e_salary_pm, $e_aadhaar_no, $e_id_no, $ad_house_no, $ad_street_name, $ad_area, $ad_district_id, $ad_panchayat_id, $ad_taluk_id, $ad_landmark, $ad_postal_code, $ad_latitude, $ad_longitude, $acc_ifsc, $acc_bank_acc_no, $acc_bank_acc_name, $dp_name, $dp_relationship, $dp_gender, $dp_mobile, $dp_phone, $sm_course_duration_in_mon, $inst_type, $inst_name, $inst_contact_no, $inst_address, $sm_tailoring_inst_is_govt_recognised, $dp_aadhaar, $sm_tailoring_inst_reg_no, $ct_course_duration_in_mon, $ct_inst_is_govt_recognised, $ct_course_completion_cert_no, $curr_class_studied, $is_passed_prev_edu, $bonafide_cert_no, $ei_district_id, $ei_edu_district_id, $prev_class_studied, $ei_es_mark_sheet_no, $ec_10th_pass_date, //$ec_10th_mark_sheet_no,
          $e_death_date, $e_death_place, $ad_accident_nature, $e_death_cert_no, $ad_fir_no, $ad_postmortem_report_no, $dp_legal_heir_cert_no, $dp_dob, $dp_email, $dp_street_name, $dp_district_id, $dp_taluk_id, $ma_g_name, $ma_g_dob, $ma_b_name, $ma_b_dob, $ma_wedding_dt, $ma_wedding_place, $ma_b_aadhaar, $ma_g_aadhaar, $ma_marriage_cert_no, $ma_b_family_ration_card_no, $ma_g_family_ration_card_no, $sa_prescription_dt, $sa_purchase_dt, $acc_type,//E-Employee D-Dependent
          $status, 'now()', $dp_latitude, $dp_longitude, $dp_landmark, $dp_postal_code, $e_death_time, $dp_panchayat_type, $ad_panchayat_type, $cert_issue_date, $ref_no, $claim_amount, $ei_es_mark_percent, $sm_cert_dt, $dp_panchayat_id, $dp_house_no, $dp_area, $e_uan_no, $e_working_from_date, $claim_id];

//        var_dump('params', $params);
//        var_dump('params', $is_passed_prev_edu);

        $arr_qb_subjects = '';
        if (gettype($qb_subjects) === 'array' && (count($qb_subjects) > 0)) {
          $arr_qb_subjects .= 'ARRAY[';
          foreach ($qb_subjects as &$cf) {
            if (isset($cf) && gettype($cf) === 'string') {
              $params[] = $cf;
              $arr_qb_subjects .= '$' . count($params) . ', ';
            }
          }
          $arr_qb_subjects = rtrim($arr_qb_subjects, ', ') . ']';
        } else {
          $arr_qb_subjects = 'NULL';
        }

        $sql = "UPDATE clm.claims SET
                        e_name =$1,
                        firm_id =$2,
                        scheme_code =$3,
                        e_designation =$4,
                        e_dob =$5,
                        e_gender =$6,
                        e_marital_status =$7,
                        e_mobile =$8,
                        e_phone =$9,
                        e_email =$10,
                        e_salary_pm =$11,
                        e_aadhaar_no =$12,
                        e_id_no =$13,

                        ad_house_no = $14,
                        ad_street_name = $15,
                        ad_area = $16,
                        ad_district_id = $17,
                        ad_panchayat_id = $18,
                        ad_taluk_id = $19,
                        ad_landmark = $20,
                        ad_postal_code = $21,
                        ad_latitude = $22,
                        ad_longitude = $23,

                        acc_ifsc = $24,
                        acc_bank_acc_no = $25,
                        acc_bank_acc_name = $26,
                        dp_name=$27,
                        dp_relationship=$28,
                        dp_gender=$29,
                        dp_mobile=$30,
                        dp_phone=$31,
                        sm_course_duration_in_mon=$32,
                        inst_type=$33,
                        inst_name=$34,
                        inst_contact_no=$35,
                        inst_address=$36,
                        sm_tailoring_inst_is_govt_recognised=$37,
                        dp_aadhaar=$38,
                        sm_tailoring_inst_reg_no=$39,

                        ct_course_duration_in_mon= $40,
                        ct_inst_is_govt_recognised = $41,
                        ct_course_completion_cert_no =$42,

                        curr_class_studied = $43,
                        is_passed_prev_edu = $44,
                        bonafide_cert_no = $45,

                        ei_district_id = $46,
                        ei_edu_district_id = $47,

                        prev_class_studied = $48,
                        ei_es_mark_sheet_no = $49,

                        ec_10th_pass_date = $50,

                        e_death_date = $51,
                        e_death_place = $52,
                        ad_accident_nature = $53,
                        e_death_cert_no =$54,
                        ad_fir_no = $55,
                        ad_postmortem_report_no = $56,
                        dp_legal_heir_cert_no = $57,

                        dp_dob = $58,
                        dp_email = $59,

                        dp_street_name = $60,
                        dp_district_id = $61,
                        dp_taluk_id = $62,

                        ma_g_name = $63,
                        ma_g_dob = $64,
                        ma_b_name = $65,
                        ma_b_dob = $66,
                        ma_wedding_dt = $67,
                        ma_wedding_place = $68,
                        ma_b_aadhaar = $69,
                        ma_g_aadhaar = $70,
                        ma_marriage_cert_no = $71,
                        ma_b_family_ration_card_no = $72,
                        ma_g_family_ration_card_no = $73,

                        sa_prescription_dt = $74,
                        sa_purchase_dt = $75,

                        acc_type = $76,
                        status = $77,
                        cre_ts = $78,

                        dp_latitude =$79,
                        dp_longitude =$80,
                        dp_landmark =$81,
                        dp_postal_code =$82,

                        e_death_time = $83,
                        dp_panchayat_type = $84,
                        ad_panchayat_type = $85,

                        cert_issue_date = $86,
                        ref_no = $87,
                        claim_amount = $88,
                        ei_es_mark_percent = $89,
                        sm_cert_dt = $90,
                        dp_panchayat_id = $91,
                        dp_house_no = $92,
                        dp_area = $93,
                        e_uan_no = $94,
                        e_working_from_date = $95,
                        qb_subjects = $arr_qb_subjects
                WHERE id = $96
                RETURNING id";

        $db->Query($sql, $params);
      }

      $rows = $db->FetchAll();
      $db->Commit();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      if (count($rows) > 0) {
        $retVal['id'] = $rows[0]['id'];
        $retVal['message'] = "Claim Details are Updated Successfully.";
      }
    } catch (Exception $e) {
      $db->RollBack();
//      $retVal['message'] = $e->getMessage();
      $retVal['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retVal;
  }

  private function duplicateMrgByDifferentEmployee($ma_b_aadhaar, $ma_g_aadhaar, $ma_b_family_ration_card_no, $ma_g_family_ration_card_no, $e_id_no, $ma_marriage_cert_no, $scheme_code, $e_aadhaar_no): ?array
  {
    $db = new PostgreDB();
    $db->Begin();
    $rows = null;

    try {
      $sql = "SELECT id as claim_id
                FROM clm.claims
               WHERE (e_id_no = '$e_id_no'
                  OR e_aadhaar_no = '$e_aadhaar_no'
                  OR ma_marriage_cert_no = '$ma_marriage_cert_no'
                  OR ma_b_aadhaar = '$ma_b_aadhaar'
                  OR ma_g_aadhaar = '$ma_g_aadhaar'
                  OR ma_b_family_ration_card_no = '$ma_b_family_ration_card_no'
                  OR ma_g_family_ration_card_no = '$ma_g_family_ration_card_no')
                  AND scheme_code = '$scheme_code'";

      $db->Query($sql);
      $rows = $db->FetchAll();
    } catch (Exception $e) {
//      $rows['message'] = $e->getMessage();
//      print_r($e->getMessage());
    }
    $db->DBClose();
    return $rows;
  }

  private function curr_academic_year($scheme_code, $e_id_no, $e_aadhaar_no, $dp_aadhaar): array
  {
    //    $prev_academic_year_start = date("Y") - 1;
    $curr_academic_year_start = date("Y");
    $curr_academic_year = date("$curr_academic_year_start-05-31");

    $db = new PostgreDB();
    $rows = null;

    try {
      $sql = "SELECT id as claim_id
                FROM clm.claims
               WHERE (e_id_no = '$e_id_no' OR
                     e_aadhaar_no = '$e_aadhaar_no' OR
                     dp_aadhaar = '$dp_aadhaar')
                    AND
                    (cre_ts BETWEEN '$curr_academic_year' AND now()
                    AND
                    scheme_code = '$scheme_code') ";
      //      var_dump($sql);
      $db->Query($sql);
      $rows = $db->FetchAll();
      //      var_dump($rows);
    } catch (Exception $e) {
      $rows['message'] = $e->getMessage();
      //      print_r($e->getMessage());
    }
    $db->DBClose();
    return $rows;
  }

  private function onlyOnceSchemeCheckUp($e_id_no, $e_aadhaar_no, $scheme_code, $dp_aadhaar): ?array
  {
    /**
     * This function check whether the beneficiary is already got benefits or not.
     */

    $db = new PostgreDB();
    $rows = null;
    try {
      $sql = "SELECT id as claim_id
                FROM clm.claims
               WHERE scheme_code = '$scheme_code'
                 AND (e_id_no = '$e_id_no'
                  OR e_aadhaar_no = '$e_aadhaar_no'
                  OR dp_aadhaar = '$dp_aadhaar')";
      $db->Query($sql);
      $rows = $db->FetchAll();
    } catch (Exception $e) {
      //      $rows['message'] = $e->getMessage();
      //      print_r($e->getMessage());
    }
    $db->DBClose();
    return $rows;
  }

//  private function yearlyOnceSchemeCheckUp($scheme_code, $e_id_no, $e_aadhaar_no, $dp_aadhaar): ?array
//  {
//    $db = new PostgreDB();
//    $rows = null;
//
//    try {
//      $sql = "SELECT id as claim_id
//                FROM clm.claims
//               WHERE (e_id_no = '$e_id_no' OR
//                     e_aadhaar_no = '$e_aadhaar_no' OR
//                     dp_aadhaar = '$dp_aadhaar')
//                    AND
//                    cre_ts BETWEEN now() - INTERVAL '1 year' AND now()
//                    AND
//                    scheme_code = '$scheme_code' ";
//      var_dump($sql);
//      $db->Query($sql);
//      $rows = $db->FetchAll();
//    } catch (Exception $e) {
////      $rows['message'] = $e->getMessage();
////      print_r($e->getMessage());
//    }
//    $db->DBClose();
//    return $rows;
//  }

  public function updateClaimStatus(object $data): array
  {
    $id = $data->id ?? null;
    $status = $data->status ?? null;
    $retVal = ['message' => 'Claim Registration Can\'t be saved.'];

    $db = new PostgreDB();

    try {
      $db->Begin();
      $sql = "UPDATE clm.claims
                 SET status = $2
               WHERE id = $1
           RETURNING id";

      $db->Query($sql, [$id, $status]);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        $retVal['message'] = "Claim Details are Updated Successfully.";
      }
      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retVal;
  }

//  private function twoTimeAcademicYear($scheme_code, $e_id_no, $e_aadhaar_no, $dp_aadhaar): array
//  {
//    $curr_academic_year_start = date("Y") - 1;
//    $curr_academic_year = date("$curr_academic_year_start-05-31");
//    $db = new PostgreDB();
//    $rows = null;
//
//    try {
//      $sql = "SELECT id as claim_id
//                FROM clm.claims
//               WHERE (e_id_no = '$e_id_no' OR
//                     e_aadhaar_no = '$e_aadhaar_no' OR
//                     dp_aadhaar = '$dp_aadhaar')
//                    AND
//                    cre_ts BETWEEN '$curr_academic_year' AND now()
//                    AND
//                    scheme_code = '$scheme_code' ";
//      $db->Query($sql);
//      $rows = $db->FetchAll();
//    } catch (Exception $e) {
//      $rows['message'] = $e->getMessage();
////      print_r($e->getMessage());
//    }
//    $db->DBClose();
//    return $rows;
//  }

  function getEducations($data): array
  {
    $allowed_schemes = $data->allowed_schemes ?? null;
    $curr_class_studied = $data->curr_class_studied ?? null;
    $params = [];

    $where_clause = '';
    if (!is_null($curr_class_studied)) {
      $params[] = $curr_class_studied;
      $where_clause .= 'AND seca.edu_code = $' . count($params);
    }

    $db = new PostgreDB();
    try {
      $sql = "SELECT code,name,order_wise,seca.scheme_amt
                FROM clm.edus AS a
           LEFT JOIN clm.scheme_edu_claim_amts seca on a.code = seca.edu_code
                 AND seca.scheme_code = '$allowed_schemes'
               WHERE '$allowed_schemes'= ANY(a.allowed_schemes)
               $where_clause
            ORDER BY order_wise;";

      $db->Query($sql, $params);

      $rows = $db->FetchAll();

      foreach ($rows as $row) {
        $row['scheme_amt'] = intval($row['scheme_amt']);
      }

      $retObj['rows'] = $rows;
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    return $retObj;
  }

  public function getClaimTables($data): array
  {
    /**
     * userType is used to get value for specific user id / single user id
     */
    //    var_dump($data);
    $firm_id = $data->firm_id ?? null;
    $id = $data->claim_id ?? null;
    $type = $data->type ?? null;
    $status = $data->status ?? null;
    //    $designationCode = $data->designationCode ?? null;
    $where_clause = '';
    $params = [];
    $limit_offset = "";
    $limit_offset_as = '';
    $limit = $data->limit ?? null;
    $offset = $limit * ($data->offset ?? 0);

    if (!is_null($firm_id)) {
      $params[] = $firm_id;
      $where_clause .= ' AND c.firm_id = $' . count($params);
    }

    if (!is_null($id)) {
      $params[] = $id;
      $where_clause .= ' AND c.id = $' . count($params);
    }

    if (isset($data->reg_year) && ($data->reg_year) > 0) {
      $reg_year = $data->reg_year;
      $params[] = $reg_year;
      $where_clause .= ' AND EXTRACT(YEAR FROM c.cre_ts) = $' . count($params);
    }

    if (isset($data->scheme_code) && strlen($data->scheme_code) > 0) {
      $scheme_code = $data->scheme_code;
      $params[] = $scheme_code;
      $where_clause .= ' AND c.scheme_code = $' . count($params);
    }

    if (isset($data->search_text) && strlen($data->search_text) > 0) {
      $search_text = '%' . $data->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= " AND (c.e_name ilike $param_cnt or f.name ilike $param_cnt or c.claim_reg_no ilike $param_cnt)";
    }

    if (is_string($status) && $status != 'TOT-CLAIMS') {
      if ($status == 'AP-PEN') {//AP-PEN - Applicant's Pending Claims
        $where_clause .= " AND c.status = 'S' OR c.status = 'REC' OR c.status = 'VSO'
        OR c.status= 'VSP' OR c.status= 'VAO' OR c.status = 'VFA' OR c.status = 'CLR'";
      } elseif ($status == 'REC') {
        $where_clause .= " AND (c.status = 'REC' OR c.status = 'S') ";
      } elseif ($status != 'C') {
        $params[] = $status;
        $where_clause .= ' AND c.status = $' . count($params);
      }
    }

    if (is_string($status) && $status == 'TOT-CLAIMS') {
      $where_clause .= " AND (c.status = 'S' OR c.status='REC' OR c.status='VSO' OR c.status='VSP'
              OR c.status='VAO' OR c.status='VFA')";
    }

    if (isset($data->limit) && $data->limit) {
      $params[] = $limit;
      $limit_offset .= ' LIMIT $' . count($params);
      $limit_offset_as .= ' $' . count($params) . ' AS limit,';

      $params[] = $offset;
      $limit_offset .= ' OFFSET $' . count($params);
      $limit_offset_as .= ' $' . count($params) . ' AS offset';
    }

//        var_dump($data);
    $db = new PostgreDB();
    try {
      $sql = "SELECT c.id,
       c.firm_id,
       c.scheme_code,
       c.e_name,
       c.claim_amount,
       c.status,
       s.name AS scheme_name,
       c.acc_bank_acc_name,
       c.acc_bank_acc_no,
       c.cre_ts,
       c.o_designation_code_to,
       c.acc_ifsc,
       c.claim_reg_no,
       b_branch.name as branch_name,
       bnk.name as bank_name,
       (CASE WHEN c.status ='D'THEN 'Draft.'
             WHEN c.status ='S' THEN 'Submitted.'
             WHEN c.status ='REC' THEN 'Received By Section.'
             WHEN c.status ='VSO' THEN 'Verified By Section.'
             WHEN c.status ='VSP' THEN 'Verified By Superintendent.'
             WHEN c.status ='VAO' THEN 'Verified By Accounts Officer.'
             WHEN c.status ='VFA' THEN 'Verified By Financial Advisor.'
             WHEN c.status ='CLI' THEN 'Clarification Issued.'
             WHEN c.status ='CLR' THEN 'Clarification Responded.'
             WHEN c.status = 'R' THEN 'Rejected.'
             WHEN c.status ='A' THEN 'Approved.' END)AS status_name,
       c.claim_amount,
       c.remarks,
       f.name AS firm_name,
       f.lwb_reg_no FROM clm.claims AS c

                             INNER JOIN clm.schemes AS s ON c.scheme_code = s.code

                             LEFT JOIN est.firms AS f ON (f.id = c.firm_id)

                             LEFT JOIN mas.bank_branches AS b_branch ON (b_branch.ifsc = c.acc_ifsc)

                             LEFT JOIN mas.banks as bnk ON (bnk.code = b_branch.bank_code)
        ";

      if ($status == 'C') {
        $sql .= " WHERE TRUE AND c.id IN (select claim_id from clm.disbursement_det)";
      } else {
        $sql .= " WHERE TRUE AND c.id NOT IN (select claim_id from clm.disbursement_det)";
      }

      $sql .= "$where_clause
               ORDER BY c.cre_ts desc
               $limit_offset";

//      var_dump($sql);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

//      var_dump($sql);
//      var_dump($params);

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['firm_id'] = intval($r['firm_id']);
        $r['claim_amount'] = intval($r['claim_amount']);
      }

      $retObj['rows'] = $rows;

      //** Get Total Rows **
      if (!is_null($limit) && count($rows) == $limit) {

        $sql = "SELECT count(*) AS cnt,$limit_offset_as
                FROM clm.claims as c
                INNER JOIN clm.schemes AS s ON c.scheme_code = s.code
                LEFT JOIN est.firms AS f ON (f.id = c.firm_id)
                LEFT JOIN mas.bank_branches AS b_branch ON (b_branch.ifsc = c.acc_ifsc)
                LEFT JOIN mas.banks as bnk ON (bnk.code = b_branch.bank_code)";

        if ($status == 'C') {
          $sql .= " WHERE TRUE AND c.id IN (select claim_id from clm.disbursement_det)";
        } else {
          $sql .= " WHERE TRUE AND c.id NOT IN (select claim_id from clm.disbursement_det)";
        }

        $sql .= $where_clause;

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
    return $retObj;
  }

  public function getClaimTable($data): array
  {
    //    var_dump('DATA====',$data);
    $claim_id = $data->id ?? null;
    $db = new PostgreDB();
    $db->Begin();
    $where_clause = '';
    $params = [];

    if (!is_null($claim_id)) {
      $params[] = $claim_id;
      $where_clause = 'AND c.id = $' . count($params);
    }

    try {
      $sql = "SELECT c.id,
       c.firm_id,
       c.scheme_code,
       s.name as scheme_name,
       c.claim_reg_no,
       c.e_name,
       c.e_designation,
       c.e_dob,
       c.e_gender,
       gn.name as e_gender_name,
       c.e_marital_status,
       c.e_mobile,
       c.e_phone,
       c.e_email,
       c.e_salary_pm,
       c.e_aadhaar_no,
       c.e_id_no,
       c.e_salary_slip,
       c.e_uan_no,
       c.e_working_from_date,
       c.ad_house_no,
       c.ad_street_name,
       c.ad_area,
       c.ad_district_id,
       adi.name as ad_district_name,
       c.ad_panchayat_id,
       apt.name as ad_panchayat_name,
       c.ad_taluk_id,
       adtlk.name as ad_taluk_name,
       c.ad_landmark,
       c.ad_postal_code,
       c.ad_latitude,
       c.ad_longitude,
       c.acc_ifsc,
       c.acc_bank_acc_no,
       c.acc_bank_acc_name,
       c.sm_course_duration_in_mon,
       c.sm_tailoring_inst_reg_no,
       c.sm_tailoring_inst_is_govt_recognised,
       c.sm_cert_dt,
       c.ei_district_id,
       edi.name as ei_district_name,
       c.ei_edu_district_id,
       edi.name as ei_edu_district_name,
       c.ei_es_mark_percent,
       c.ei_es_mark_sheet_no,
       c.ct_course_duration_in_mon,
       c.ct_inst_is_govt_recognised,
       c.ct_course_completion_cert_no,
       c.cert_issue_date,
--       to_jsonb(c.qb_subjects) as qb_subjects,
       qb.qbag as qb_subjects,
--       qb.qbag as qb_subject_values,
       c.ec_10th_mark_sheet_no,
       c.ec_10th_pass_date,
       c.edu_code_prev,
       c.is_passed_prev_edu,
       c.edu_code_curr,
       rel.name as relationship_name,
       c.inst_type,
       c.inst_name,
       c.inst_address,
       c.inst_contact_no,
       c.prev_class_studied,
       c.curr_class_studied,
       c.bonafide_cert_no,
       c.bonafide_cert_dt,
       c.ma_g_name,
       c.ma_g_dob,
       c.ma_b_name,
       c.ma_b_dob,
       c.ma_wedding_dt,
       c.ma_wedding_place,
       c.ma_g_aadhaar,
       c.ma_b_aadhaar,
       c.ma_g_family_ration_card_no,
       c.ma_b_family_ration_card_no,
       c.ma_marriage_cert_dt,
       c.ma_marriage_cert_no,
       c.sa_prescription_dt,
       c.sa_purchase_dt,
       c.ad_accident_nature,
       c.ad_fir_no,
       c.ad_postmortem_report_no,
       c.e_death_date,
       c.e_death_time,
       c.e_death_place,
       c.e_death_cert_no,
       c.e_death_cert_dt,
       c.dp_name,
       c.dp_relationship,
       rel.name as dp_relationship_name,
       c.dp_gender,
       dgn.name as dp_gender_name,
       c.dp_address,
       c.dp_dob,
       c.dp_house_no,
       c.dp_street_name,
       c.dp_area,
       c.dp_district_id,
       ddi.name as dp_district_name,
       c.dp_taluk_id,
       dtlk.name as dp_taluk_name,
       c.dp_panchayat_id,
       dpt.name as dp_panchayat_name,
       c.dp_landmark,
       c.dp_latitude,
       c.dp_longitude,
       c.dp_postal_code,
       c.dp_email,
       c.dp_mobile,
       c.dp_phone,
       c.dp_legal_heir_cert_no,
       c.dp_aadhaar,
       c.acc_ifsc,
       c.acc_bank_acc_name,
       c.acc_bank_acc_no,
       c.cre_ts,
       c.claim_amount,
       s.name as scheme_name,
       f.name as firm_name,
       c.o_designation_code_to,
       c.o_user_id,
       c.ad_panchayat_type,
       c.dp_panchayat_type,
       --mlx.x as mark_lists,
       c.ref_no,
       c.status,
       (CASE WHEN c.status ='D'THEN 'Draft'
             WHEN c.status ='S' THEN 'Submitted For Verification'
             WHEN c.status ='VSO' THEN 'Verified By Section'
             WHEN c.status ='VSP' THEN 'Verified By Superindent'
             WHEN c.status ='VFA' THEN 'Verified By Financial Advisor'
             WHEN c.status ='A' THEN 'Approved' END) AS status_name,
       c.remarks,
        bnk.name as bank_name,
        b_branch.name as branch_name,
        clr.clr_ver
        FROM clm.claims AS c
        --INNER JOIN mas.designations AS md
            --ON md.code = c.o_designation_code_to
        INNER JOIN clm.schemes AS s
            ON c.scheme_code = s.code
        INNER JOIN est.firms AS f
            ON f.id = c.firm_id
        LEFT JOIN mas.bank_branches AS b_branch ON (b_branch.ifsc = c.acc_ifsc)
        LEFT JOIN mas.banks as bnk ON (bnk.code = b_branch.bank_code)
        LEFT JOIN clm.relationships as rel ON (c.dp_relationship = rel.code)
        LEFT JOIN clm.genders as gn ON (c.e_gender = gn.code)
        LEFT JOIN clm.genders as dgn ON (c.dp_gender = dgn.code)
        --LEFT JOIN clm.sdat_certs as cert ON (c.id = cert.claim_id)

        LEFT JOIN mas.districts as edi ON (edi.id =  c.ei_district_id)
        LEFT JOIN mas.districts as adi ON (adi.id =  c.ad_district_id)
        LEFT JOIN mas.districts as ddi ON (ddi.id =  c.dp_district_id)

        LEFT JOIN mas.taluks as adtlk ON (adtlk.id = c.ad_taluk_id)
        LEFT JOIN mas.taluks as dtlk ON (dtlk.id = c.dp_taluk_id)

        LEFT JOIN mas.panchayats as apt ON (apt.id = c.ad_panchayat_id)
        LEFT JOIN mas.panchayats as dpt ON (dpt.id = c.dp_panchayat_id)

        LEFT OUTER JOIN LATERAL (
            SELECT sum(CASE WHEN scn.status = 'V' THEN 1 ELSE 0 END) AS clr_ver
            FROM mas.screen_status as scn
            WHERE verification_id
            IN (SELECT id FROM mas.verifications as vs WHERE vs.claim_id = c.id))
        as clr ON TRUE

        LEFT OUTER JOIN LATERAL (
            SELECT to_jsonb(array_agg(x1)) as x
            FROM (SELECT ml.mark_list_no,
                         ml.attachment_id
                    FROM clm.mark_lists ml
                   WHERE ml.claim_id = c.id
                 ) as x1
        ) as mlx ON (true)

        LEFT OUTER JOIN LATERAL (
            SELECT TO_JSONB(ARRAY_AGG(sq)) AS qbag
            FROM (SELECT q.code, q.name
                  FROM clm.q_bank_subjects q
                  WHERE q.code::varchar = ANY (c.qb_subjects)
                  ) AS sq
        ) AS qb ON TRUE

        WHERE TRUE $where_clause
        ORDER BY c.id;";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      //      var_dump($sql);

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['firm_id'] = intval($r['firm_id']);
        $r['clr_ver'] = intval($r['clr_ver']);
        $r['ad_district_id'] = $r['ad_district_id'] == 0 ? null : intval($r['ad_district_id']);
        $r['ad_panchayat_id'] = $r['ad_panchayat_id'] == 0 ? null : intval($r['ad_panchayat_id']);
        $r['ad_taluk_id'] = $r['ad_taluk_id'] == 0 ? null : intval($r['ad_taluk_id']);
        $r['dp_district_id'] = $r['dp_district_id'] == 0 ? null : intval($r['dp_district_id']);
        $r['dp_panchayat_id'] = $r['dp_panchayat_id'] == 0 ? null : intval($r['dp_panchayat_id']);
        $r['dp_taluk_id'] = $r['dp_taluk_id'] == 0 ? null : intval($r['dp_taluk_id']);
        $r['mark_lists'] = isset($r['mark_lists']) ? json_decode($r['mark_lists']) : null;
        $r['qb_subjects'] = isset($r['qb_subjects']) ? json_decode($r['qb_subjects']) : null;
        $r['ei_district_id'] = $r['ei_district_id'] == 0 ? null : intval($r['ei_district_id']);
        $r['ei_edu_district_id'] = $r['ei_edu_district_id'] == 0 ? null : intval($r['ei_edu_district_id']);

        if ($r['is_passed_prev_edu'] == 't') {
          $r['is_passed_prev_edu'] = true;
        } else if ($r['is_passed_prev_edu'] == 'f') {
          $r['is_passed_prev_edu'] = false;
        }

      }

      if (count($rows) > 0) {
        $retObj['rows'] = $rows[0];
      }
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

//  function getTotalClaims($filter): array
//  {
//    $retObj = [];
//    $params = [];
//
//    $db = new PostgreDB();
//    try {
//      $sql = "SELECT * ,
//       (a.clm_un_rec +
//        a.clr_issued +
//        a.clr_responded)AS tt_pen_clm
//        FROM (
//        SELECT EXTRACT(YEAR FROM cre_ts)                    AS reg_year,
//            SUM(CASE
//               WHEN status = 'REC' OR
//                    status = 'VSO' OR
//                    status = 'VSP' OR
//                    status = 'VAO' OR
//                    status = 'VFA' THEN 1
//               ELSE 0 END)                             AS clm_un_rec,
//            SUM(CASE WHEN status = 'R' THEN 1 ELSE 0 END)   AS rejected,
//            SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END)   AS approved,
//            SUM(CASE WHEN status = 'CLI' THEN 1 ELSE 0 END) AS clr_issued,
//            SUM(CASE WHEN status = 'CLR' THEN 1 ELSE 0 END) AS clr_responded
//        FROM clm.claims AS c
//        WHERE c.id NOT IN (SELECT claim_id FROM clm.disbursement_det)
//        GROUP BY reg_year
//        ORDER BY reg_year DESC)AS a;";
//
//      //write user_id/user  specific submitted count to know how many claims are submitted by themself.
//
//      $db->Query($sql, $params);
//      $rows = $db->FetchAll();
//      foreach ($rows as &$r) {
//        $r['regyear'] = isset($r['regyear']) ? intval($r['regyear']) : 0;
//        $r['under_pro'] = isset($r['under_pro']) ? intval($r['under_pro']) : 0;
//        $r['rej'] = isset($r['rej']) ? intval($r['rej']) : 0;
//        $r['not_issue'] = isset($r['not_issue']) ? intval($r['not_issue']) : 0;
//        $r['act'] = isset($r['act']) ? intval($r['act']) : 0;
//        $r['not_res'] = isset($r['not_res']) ? intval($r['not_res']) : 0;
//        $r['yet_verf'] = isset($r['yet_verf']) ? intval($r['yet_verf']) : 0;
//      }
//      $retObj = $rows;
//    } catch (Exception $e) {
//      $db->RollBack();
//      $retObj['message'] = ErrorHandler::custom($e);
//    }
//    $db->DBClose();
//    return $retObj;
//  }

  function getTotalClaims($filter, $payload): array
  {
    $retObj = [];
    $params = [];
//    $designation_code = $filter->designation_code ?? null;
    $user_id = (isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null;

//    if ($designation_code == null) {
    $user = (new User())->getUsers((object)['id' => $user_id]);
    $designation_code = $user['rows'][0]['designation_code'];
//      $designation_code = (new User())->getUsers($filter)['rows'][0]['designation_code'] ?? null;
//    }

    $status = (string)$this->getStatusByDesignation($designation_code);

    $db = new PostgreDB();
    try {
      $sql = "SELECT scm.code,
                     scm.name,
                     SUM(CASE
                             WHEN
                                  c.status = 'S' OR
                                  c.status = 'REC' OR
                                  c.status = 'VSO' OR
                                  c.status = 'VSP' OR
                                  c.status = 'VAO' OR
                                  c.status = 'VFA' OR
                                  c.status = 'CLI' THEN 1
                             ELSE 0 END)                                   AS pend_claims,
              ";

      if ($designation_code != 'AAO') {
        $sql .= "SUM(CASE WHEN (c.status = $status) THEN 1 ELSE 0 END)     AS rec_claims,";
      }

      $sql .= "SUM(CASE WHEN c.status = 'A' THEN 1 ELSE 0 END)             AS approved,
               SUM(CASE WHEN c.status = 'R' THEN 1 ELSE 0 END)             AS rejected
               FROM clm.schemes AS scm
                 LEFT JOIN clm.claims AS c ON scm.code = c.scheme_code
               --WHERE c.id NOT IN (SELECT claim_id FROM clm.disbursement_det) <- this where clause will ignore the disb claim.
               GROUP BY scm.code,scm.name";

//      var_dump($sql);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['clm_rec'] = isset($r['clm_rec']) ? intval($r['clm_rec']) : 0;
        $r['pend_claims'] = isset($r['pend_claims']) ? intval($r['pend_claims']) : 0;
        $r['rec_claims'] = isset($r['rec_claims']) ? intval($r['rec_claims']) : 0;
        $r['approved'] = isset($r['approved']) ? intval($r['approved']) : 0;
        $r['rejected'] = isset($r['rejected']) ? intval($r['rejected']) : 0;
        $r['clr_pending'] = isset($r['clr_pending']) ? intval($r['clr_pending']) : 0;
        $r['disb_claims'] = isset($r['disb_claims']) ? intval($r['disb_claims']) : 0;
      }

      $retObj = $rows;
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function getStatusByDesignation($designation): string|null
  {
    return match ($designation) {
      'SO' => "'REC' OR c.status = 'CLR' OR c.status = 'S'",
      'SP' => "'VSO'",
      'AO' => "'VSP'",
      'FA' => "'VAO'",
      'SECR' => "'VFA'",
      default => null,
    };
  }


//  function delete($data)
//  {
//    $retObj = [];
//    $id = isset($data->id)?$data->id:null;
//
//    $db = new \LWMIS\Common\PostgreDB();
//    if (!is_null($id)) {
//      try {
//        $db->Begin();
//
//        $sql = 'DELETE FROM mas.bank_branches WHERE id = $1 RETURNING bank_id';
//        $db->Query($sql, [$id]);
//        $rows = $db->FetchAll();
//        foreach($rows as &$r) {
//          $r['bank_id'] = intval($r['bank_id']);
//        }
//
//        if (count($rows) > 0) {
//          $bank_id = $rows[0]['bank_id'];
//          $sql = 'DELETE FROM mas.banks AS a WHERE id = $1 AND NOT EXISTS (SELECT * FROM mas.bank_branches AS b WHERE b.bank_id = $1 AND b.bank_id = a.id)';
//          $db->Query($sql, [$bank_id]);
//        }
//
//        $db->Commit();
//        $retObj['message'] = 'Bank/Branch deleted successfully.';
//      } catch(\Exception $e) {
//        $db->RollBack();
//        $retObj['message'] = 'Delete not allowed. Error: '.$e->getMessage();
//      }
//      $db->DBClose();
//    }
//    return $retObj;
//  }

  function saveClaimAttachments($data): array
  {
    $retVal = [];
    $retVal['message'] = ['Claim & Firm Details are required !'];
    $claim_id = $data->claim_id ?? null;
    $firm_id = $data->firm_id ?? null;
    $db = new PostgreDB();

    if ($claim_id && $firm_id) {
      //I'm getting firm_id is becz the document behave weirdly when creating new establishment.
      try {
        $file = new Attachment();
        $retVal = $file->saveAttachment($data,$db);
      } catch (Exception $e) {
        $retVal['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retVal;
    }
    return $retVal;
  }

  public function deleteClaimDetails($data): array
  {
    $retVal = [];
    $claim_id = $data->claim_id ?? null;

    $db = new PostgreDB();
    if ($claim_id) {
      try {
        $db->Begin();
        $sql = "SELECT id,storage_name
                FROM doc.attachments
                WHERE claim_id = $1";
        $db->Query($sql, [$claim_id]);
        $attachments = $db->FetchAll();

        foreach ($attachments as $at) {
          if (unlink($at["storage_name"])) {
            $sql = "DELETE
                    FROM doc.attachments
                    WHERE id = $1;";
            $db->Query($sql, [$at["id"]]);
          } else {
            throw new Exception("Can't delete some files");
          }
        }

        $sql = "DELETE
                FROM clm.claims
                WHERE id = $1
                RETURNING id";
        $db->Query($sql, [$claim_id]);
        $rows = $db->FetchAll();

        if (count($rows) > 0) {
          $retVal['message'] = 'Deleted Successfully !';
          $db->Commit();
        }
      } catch (Exception $e) {
        $db->RollBack();
        $retVal['message'] = ErrorHandler::custom($e);
      }
    }
    $db->DBClose();
    return $retVal;
  }

  function getClaimTitle($filter): array
  {
    $retObj = ['rows' => [], 'message' => null];

    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = 'SELECT code,name
              FROM clm.schemes
              where is_active = true
              ORDER BY name';

      $db->Query($sql);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;

    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retObj;
  }

  function getWinningPositions($data): array
  {

    $scheme_code = $data->scheme_code ?? null;
    $winning_position = $data->winning_position ?? null;

    $where_clause = '';

    if (!is_null($scheme_code)) {
      $where_clause .= " AND scheme_code = '$scheme_code'";
    }

    if (!is_null($winning_position)) {
      $where_clause .= " AND winning_position = '$winning_position'";
    }

    $db = new PostgreDB();

    try {
      $sql = "select wp.scheme_code,
                     wp.winning_position,
                     case
                         when wp.winning_position = '1'
                             then '1st position'
                         when wp.winning_position = '2'
                             then '2nd position'
                         when wp.winning_position = '3'
                             then '3rd position'
                         end
                     winning_name,
                     wp.scheme_amt
                from clm.scheme_winning_position_claim_amts as wp
               where true $where_clause;";

      $db->Query($sql);

      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;

    } catch (Exception $e) {
      $retObj['message'] = $e->getMessage();
    }

    $db->DBClose();
    return $retObj;
  }
}
