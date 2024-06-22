<?php

namespace LWMIS\Est;

use LWMIS\Common\ErrorHandler;
use LWMIS\Common\PostgreDB;

class Employee_history
{
  /*    function getEmployeeHistory($filter)
      {
          $retObj = ['rows' => [], 'message' => null];
          $limit = isset($filter->limit) ? $filter->limit : null;
          $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

          $where_clause = "";
          $params = [];
          $params[] = $limit;
          $params[] = $offset;

          if (isset($filter->id) && $filter->id > 0) {
              $firm_id = $filter->id;
              $params[] = $firm_id;
              $where_clause .= ' AND a.id = $' . count($params);
          }

          $db = new \LWMIS\Common\PostgreDB();
          try {
              // get actual data
              $sql = 'SELECT a.id, a.firm_id ,a.YEAR,a.pe_male,a.pe_female,a.pe_trans,a.pe_disabled,a.ce_male,a.ce_female,a.ce_trans,a.ce_disabled,
                             a.tot_employees,a.pe_total,a.ce_total,a.employee_cntrbtn,a.employer_cntrbtn,a.total_cntrbtn,
                             (a.tot_employees*a.employee_cntrbtn)AS employee_amt,(a.tot_employees*a.employer_cntrbtn)AS employer_amt,
                             (a.tot_employees*a.total_cntrbtn)AS total_amt,a.paid_amt ,a.bal_amt,a.is_had_eh,a.is_has_pay,
                             a.payable_id, a.tot_paid_amt
                        FROM (SELECT a.id AS firm_id,a.YEAR, b.id,
                                  (CASE WHEN b.id IS NULL THEN FALSE ELSE TRUE END )AS is_had_eh,
                                  (CASE WHEN b.pe_male IS NULL THEN a.pe_male ELSE b.pe_male END )pe_male,
                                  (CASE WHEN b.pe_female IS NULL THEN a.pe_female ELSE b.pe_female END )pe_female,
                                  (CASE WHEN b.pe_trans IS NULL THEN a.pe_trans ELSE b.pe_trans END )pe_trans,
                                  (CASE WHEN b.pe_disabled IS NULL THEN a.pe_disabled ELSE b.pe_disabled END )pe_disabled,
                                  (CASE WHEN b.ce_male IS NULL THEN a.ce_male ELSE b.ce_male END )ce_male,
                                  (CASE WHEN b.ce_female IS NULL THEN a.ce_female ELSE b.ce_female END )ce_female,
                                  (CASE WHEN b.ce_trans IS NULL THEN a.ce_trans ELSE b.ce_trans END )ce_trans,
                                  (CASE WHEN b.ce_disabled IS NULL THEN a.ce_disabled ELSE b.ce_disabled END )ce_disabled,
                                  (CASE WHEN b.ce_disabled IS NULL THEN a.pe_total+a.ce_total ELSE b.tot_employees  END )tot_employees,
                                  (CASE WHEN b.ce_disabled IS NULL THEN a.pe_male+a.pe_male +a.pe_trans ELSE b.pe_male+b.pe_female +b.pe_trans END )pe_total,
                                  (CASE WHEN b.ce_disabled IS NULL THEN a.ce_total ELSE b.ce_male+b.ce_female +b.ce_trans END )ce_total,
                                  (CASE WHEN c.employee_cntrbtn IS NULL THEN d.employee_cntrbtn ELSE c.employee_cntrbtn END )AS employee_cntrbtn,
                                  (CASE WHEN c.employer_cntrbtn IS NULL THEN d.employer_cntrbtn ELSE c.employer_cntrbtn END )AS employer_cntrbtn,
                                  (CASE WHEN c.total_cntrbtn IS NULL THEN d.total_cntrbtn ELSE c.total_cntrbtn END )AS total_cntrbtn,
                                  (CASE WHEN COALESCE(e.bal_amt, 0) =0 THEN FALSE ELSE TRUE END )AS is_has_pay, e.paid_amt,
                                     e.id AS payable_id,sum(COALESCE(f.tot_amt,0))tot_paid_amt ,e.bal_amt
                                FROM (SELECT EXTRACT(YEAR from generate_series((CASE WHEN a.is_legacy_reg=true THEN a.cre_ts ELSE a.lwb_req_dt END)::date,now()::date,\'1 year\')::date)AS YEAR,a.id,
                                             a.pe_male ,a.pe_female ,a.pe_trans ,a.pe_disabled,
                                             a.ce_male ,a.ce_female ,a.ce_trans ,a.ce_disabled,
                                             (a.pe_male+a.pe_female +a.pe_trans)AS pe_total,
                                             (a.ce_male +a.ce_female +a.ce_trans)AS ce_total
                                        FROM est.firms AS a
                                     )AS a
                                     LEFT OUTER JOIN est.employee_history AS b ON (a.id =b.firm_id AND a.YEAR = b.YEAR)
                                     LEFT JOIN LATERAL (
                                               SELECT * FROM mas.contributions AS c WHERE a.YEAR >= c.from_year AND a.YEAR <=c.to_year)AS c ON TRUE
                                     LEFT JOIN LATERAL (
                                               SELECT * FROM mas.contributions AS d WHERE a.YEAR >= d.from_year AND d.to_year IS NULL )AS d ON TRUE
                                     LEFT OUTER JOIN est.payables AS e ON (e.firm_id =a.id AND e.YEAR = a.year)
                                     LEFT JOIN LATERAL(
                                               SELECT f.id, f.tot_amt
                                               FROM est.payment_against_payables AS f
                                               LEFT OUTER JOIN est.payments AS g ON (g.id = f.payment_id)
                                               LEFT OUTER JOIN est.receipts AS h ON (h.trnx_ref_no = g.trnx_no_own)
                                               WHERE f.payable_id = e.id AND h.status!=\'C\'
                                     )AS f ON TRUE
                               WHERE TRUE '.$where_clause.'
                            GROUP BY a.id,a.YEAR,b.id,a.pe_male,a.pe_female,a.pe_trans,a.pe_disabled,a.ce_male,a.ce_female,a.ce_trans,
                                     a.ce_disabled,a.pe_total,a.ce_total,
                                     c.employee_cntrbtn,d.employee_cntrbtn,c.employer_cntrbtn,
                                     d.employer_cntrbtn,c.total_cntrbtn, d.total_cntrbtn,
                                     e.paid_amt,e.id)AS a
                       ORDER BY a.YEAR DESC
                       LIMIT $1 OFFSET $2;';
  //            var_dump($sql);
              $db->Query($sql, $params);
              $rows = $db->FetchAll();
              foreach ($rows as &$r) {
                  $r['id']                = isset($r['id']) ? intval($r['id']) : null;
                  $r['payable_id']        = isset($r['payable_id']) ? intval($r['payable_id']) : null;
                  $r['firm_id']           = isset($r['firm_id']) ? intval($r['firm_id']) : null;
                  $r['year']              = intval($r['year']);
                  $r['pe_male']           = intval($r['pe_male']);
                  $r['pe_female']         = intval($r['pe_female']);
                  $r['pe_trans']          = intval($r['pe_trans']);
                  $r['pe_disabled']       = intval($r['pe_disabled']);
                  $r['ce_male']           = intval($r['ce_male']);
                  $r['ce_female']         = intval($r['ce_female']);
                  $r['ce_trans']          = intval($r['ce_trans']);
                  $r['ce_disabled']       = intval($r['ce_disabled']);
                  $r['tot_employees']     = intval($r['tot_employees']);
                  $r['pe_total']          = intval($r['pe_total']);
                  $r['ce_total']          = intval($r['ce_total']);
                  $r['employee_cntrbtn']  = intval($r['employee_cntrbtn']);
                  $r['employer_cntrbtn']  = intval($r['employer_cntrbtn']);
                  $r['total_cntrbtn']     = intval($r['total_cntrbtn']);
                  $r['employee_amt']      = intval($r['employee_amt']);
                  $r['employer_amt']      = intval($r['employer_amt']);
                  $r['total_amt']         = intval($r['total_amt']);
                  $r['paid_amt']          = intval($r['paid_amt']);
                  $r['tot_paid_amt']      = intval($r['tot_paid_amt']);
                  $r['bal_amt']           = intval($r['bal_amt']);
                  $r['is_had_eh']         = ($r['is_had_eh']=='t');
                  $r['is_has_pay']        = ($r['is_has_pay']=='t');
              }
              $retObj['rows'] = $rows;

              // get total rows
              if (!\is_null($limit) && count($rows) == $limit) {
                  $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                      from est.employee_history as a
                      where true ' . $where_clause;
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
      }*/

//  function getEmployeeHistory($filter): array
//  {
//    $retObj = ['rows' => [], 'message' => null];
//    $receipt_no = $filter->receipt_no ?? null;
//    $firm_id = $filter->id ?? null;
//    $limit = $filter->limit ?? null;
//    $offset = $limit * ($filter->offset ?? 0);
//
//    $where_clause = "";
//    $params = [];
//    $params[] = $limit;
//    $params[] = $offset;
//
//    if (!is_null($firm_id)) {
//      $selected_year = $filter->selected_year ?? null;
//      $params[] = $firm_id;
//      $where_clause .= ' AND fm.id = $' . count($params);
//
//      if (!is_null($selected_year)) {
//        $where_clause .= ' AND (';
//        foreach ($selected_year as $yr) {
//          $params[] = $yr;
//          $where_clause .= 'EXTRACT(YEAR from ct.year) = $' . count($params) . ' OR ';
//        }
//        $where_clause = rtrim($where_clause, " OR");
//        $where_clause .= ')';
//      }
//
//    }
//
//    if (!is_null($receipt_no) && is_null($firm_id)) {
//      $params[] = $receipt_no;
//      $where_clause .= ' AND rp.receipt_no = $' . count($params);
//    }
//
//    $db = new \LWMIS\Common\PostgreDB();
//
//    try {
//      /*$sql = "SELECT
//              EXTRACT(YEAR from ct.year)               AS year,
//              (pb.paid_amt > 0)                        AS is_paid,
//              COALESCE(eh.pe_male, fm.pe_male)         AS pe_male,
//              COALESCE(eh.pe_female, fm.pe_female)     AS pe_female,
//              COALESCE(eh.pe_trans, fm.pe_trans)       AS pe_trans,
//              COALESCE(eh.pe_disabled, fm.pe_disabled) AS pe_disabled,
//              COALESCE(eh.ce_male, fm.ce_male)         AS ce_male,
//              COALESCE(eh.ce_female, fm.ce_female)     AS ce_female,
//              COALESCE(eh.ce_trans, fm.ce_trans)       AS ce_trans,
//              COALESCE(eh.ce_disabled, fm.ce_disabled) AS ce_disabled,
//
//              COALESCE(eh.pe_male, fm.pe_male) +
//              COALESCE(eh.pe_female, fm.pe_female) +
//              COALESCE(eh.pe_trans, fm.pe_trans) +
//              COALESCE(eh.ce_male, fm.ce_male) +
//              COALESCE(eh.ce_female, fm.ce_female) +
//              COALESCE(eh.ce_trans, fm.ce_trans)       AS tt_emp,
//
//              (COALESCE(eh.pe_male, fm.pe_male) +
//              COALESCE(eh.pe_female, fm.pe_female) +
//              COALESCE(eh.pe_trans, fm.pe_trans) +
//              COALESCE(eh.ce_male, fm.ce_male) +
//              COALESCE(eh.ce_female, fm.ce_female) +
//              COALESCE(eh.ce_trans, fm.ce_trans))
//                  * ct.total_cntrbtn                   AS tt_amt,
//
//              ct.employee_cntrbtn                      AS empe_ctb,
//              ct.employer_cntrbtn                      AS empr_ctb,
//              ct.total_cntrbtn                         AS tt_ctb,
//              pb.paid_amt                              AS paid_amt,
//              rp.payment_mode,
//              rp.trnx_dt,
//              rp.receipt_no,
//              rp.status                                AS rpt_status
//              FROM est.firms AS fm
//                  INNER JOIN LATERAL (
//                  SELECT s.year::DATE,
//                         a.employee_cntrbtn,
//                         a.employer_cntrbtn,
//                         a.total_cntrbtn
//                  FROM GENERATE_SERIES(fm.cre_ts::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
//                  INNER JOIN mas.contributions a
//                      ON (s.year BETWEEN a.from_year AND COALESCE(a.to_year::date, CURRENT_DATE::date))
//                  ) AS ct ON TRUE
//                  LEFT JOIN est.employee_history AS eh ON (fm.id = eh.firm_id AND eh.year = extract(YEAR from ct.year))
//                  LEFT JOIN est.payables AS pb ON (pb.firm_id = fm.id AND pb.year(select receipt_no from est.receipts where pm.firm_id = receipts.firm_id) = extract(YEAR from ct.year))
//                  LEFT JOIN est.payments as pm on pb.firm_id = pm.firm_id
//                  LEFT JOIN est.receipts as rp on pm.id = rp.payment_id
//              WHERE TRUE $where_clause
//              GROUP BY
//                  ct.year,
//                  is_paid,
//                  fm.pe_male, fm.pe_female, fm.pe_trans, fm.pe_disabled, fm.ce_male, fm.ce_female, fm.ce_trans, fm.ce_disabled,
//                  eh.pe_male, eh.pe_female, eh.pe_trans, eh.pe_disabled, eh.ce_male, eh.ce_female, eh.ce_trans, eh.ce_disabled,
//                  ct.employer_cntrbtn, ct.employee_cntrbtn, ct.total_cntrbtn, pm.trnx_amount,
//                  rp.payment_mode,rp.receipt_no,rp.trnx_dt,rp.status,
//                  pb.paid_amt
//              ORDER BY ct.year
//              LIMIT $1 OFFSET $2;";*/
//
//      $sql = "SELECT EXTRACT(YEAR from ct.year)               AS year,
//                            --     (pb.paid_amt > 0)                        AS is_paid,
//                                (pm.status = 'SUCCESS') as is_paid,
//                     COALESCE(eh.pe_male, fm.pe_male)         AS pe_male,
//                     COALESCE(eh.pe_female, fm.pe_female)     AS pe_female,
//                     COALESCE(eh.pe_trans, fm.pe_trans)       AS pe_trans,
//                     COALESCE(eh.pe_disabled, fm.pe_disabled) AS pe_disabled,
//                     COALESCE(eh.ce_male, fm.ce_male)         AS ce_male,
//                     COALESCE(eh.ce_female, fm.ce_female)     AS ce_female,
//                     COALESCE(eh.ce_trans, fm.ce_trans)       AS ce_trans,
//                     COALESCE(eh.ce_disabled, fm.ce_disabled) AS ce_disabled,
//
//                     COALESCE(eh.pe_male, fm.pe_male) +
//                     COALESCE(eh.pe_female, fm.pe_female) +
//                     COALESCE(eh.pe_trans, fm.pe_trans) +
//                     COALESCE(eh.ce_male, fm.ce_male) +
//                     COALESCE(eh.ce_female, fm.ce_female) +
//                     COALESCE(eh.ce_trans, fm.ce_trans)       AS tt_emp,
//
//                     (COALESCE(eh.pe_male, fm.pe_male) +
//                      COALESCE(eh.pe_female, fm.pe_female) +
//                      COALESCE(eh.pe_trans, fm.pe_trans) +
//                      COALESCE(eh.ce_male, fm.ce_male) +
//                      COALESCE(eh.ce_female, fm.ce_female) +
//                      COALESCE(eh.ce_trans, fm.ce_trans))
//                         * ct.total_cntrbtn                   AS tt_amt,
//
//                     ct.employee_cntrbtn                      AS empe_ctb,
//                     ct.employer_cntrbtn                      AS empr_ctb,
//                     ct.total_cntrbtn                         AS tt_ctb,
//                     pb.paid_amt                              AS paid_amt,
//                     rp.payment_mode,
//                     rp.trnx_dt,
//                     rp.receipt_no,
//                     rp.status                                AS rpt_status
//              FROM est.firms AS fm
//                       INNER JOIN LATERAL (
//                  SELECT s.year::DATE,
//                         a.employee_cntrbtn,
//                         a.employer_cntrbtn,
//                         a.total_cntrbtn
//                  FROM GENERATE_SERIES(fm.cre_ts::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
//                           INNER JOIN mas.contributions a
//                                      ON (s.year BETWEEN a.from_year AND COALESCE(a.to_year::date, CURRENT_DATE::date))
//                  ) AS ct ON TRUE
//                       LEFT JOIN est.employee_history AS eh ON (fm.id = eh.firm_id AND eh.year = extract(YEAR from ct.year))
//                       LEFT JOIN est.payables AS pb ON (pb.firm_id = fm.id AND pb.year = extract(YEAR from ct.year))
//                       LEFT JOIN LATERAL (
//                  select *
//                  from est.payments
//                  where
//              --         payments.status = 'SUCCESS' AND
//              id IN (select payment_id
//                     from est.payment_against_payables
//                     where payable_id = pb.id)
//                  ) as pm ON true
//                       LEFT JOIN est.receipts as rp on pm.id = rp.payment_id
//              where true $where_clause
//              ORDER BY ct.year
//              LIMIT $1 OFFSET $2;";
//      $db->Query($sql, $params);
//      $rows = $db->FetchAll();
//
////      var_dump('sql',$sql);
//      foreach ($rows as &$r) {
//        $r['is_paid'] = ($r['is_paid'] == 't');
//        $r['year'] = intval($r['year']);
//        $r['pe_male'] = intval($r['pe_male']);
//        $r['pe_female'] = intval($r['pe_female']);
//        $r['pe_trans'] = intval($r['pe_trans']);
//        $r['pe_disabled'] = intval($r['pe_disabled']);
//        $r['ce_male'] = intval($r['ce_male']);
//        $r['ce_female'] = intval($r['ce_female']);
//        $r['ce_trans'] = intval($r['ce_trans']);
//        $r['ce_disabled'] = intval($r['ce_disabled']);
//        $r['empe_ctb'] = intval($r['empe_ctb']);
//        $r['empr_ctb'] = intval($r['empr_ctb']);
//        $r['tt_ctb'] = intval($r['tt_ctb']);
//        $r['tt_emp'] = intval($r['tt_emp']);
//        $r['tt_amt'] = intval($r['tt_amt']);
//        $r['paid_amt'] = intval($r['paid_amt']);
//      }
//      $retObj['rows'] = $rows;
//    } catch (\Exception $e) {
//      $retObj['message'] = $e->getMessage();
//    }
//
//    $db->DBClose();
//    return $retObj;
//
//  }

  function getArrearsToBePaid($filter): array
  {
//    var_dump('filter',$filter);
    $retObj = ['rows' => [], 'message' => null];
    $receipt_no = $filter->receipt_no ?? null;
    $firm_id = $filter->id ?? null;
//    $limit = $filter->limit ?? null;
//    $offset = $limit * ($filter->offset ?? 0);

    $where_clause = "";

    $params = [];
//    $params[] = $limit;
//    $params[] = $offset;

    if (!is_null($firm_id)) {
      $selected_year = $filter->selected_year ?? null;

      if (is_null($selected_year) && !is_null($receipt_no)) {//->For saveAlreadyPaid()
        $selected_year = [];
        $selected_year [0] = ($filter->selected_payments)->year ?? null;//->For saveAlreadyPaid()
      }

      $params[] = $firm_id;//<-firm_id should be a 1st params always.
      $where_clause .= ' AND fm.id = $' . count($params);

      if (!is_null($selected_year)) {
        $where_clause .= ' AND (';
        foreach ($selected_year as $yr) {
          $params[] = $yr;
          $where_clause .= 'EXTRACT(YEAR from ct.year) = $' . count($params) . ' OR ';
        }
        $where_clause = rtrim($where_clause, " OR");
        $where_clause .= ')';
      }
    }
// Commented becz NA for any api
//    if (!is_null($receipt_no) && is_null($firm_id)) {
//    if (!is_null($receipt_no)) {
//      $params[] = $receipt_no;
//      $where_clause .= ' AND rp.receipt_no = $' . count($params);
//    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "SELECT EXTRACT(YEAR from ct.year)               AS year,
                     COALESCE(eh.pe_male, fm.pe_male)         AS pe_male,
                     COALESCE(eh.pe_female, fm.pe_female)     AS pe_female,
                     COALESCE(eh.pe_trans, fm.pe_trans)       AS pe_trans,
                     COALESCE(eh.pe_disabled, fm.pe_disabled) AS pe_disabled,
                     COALESCE(eh.ce_male, fm.ce_male)         AS ce_male,
                     COALESCE(eh.ce_female, fm.ce_female)     AS ce_female,
                     COALESCE(eh.ce_trans, fm.ce_trans)       AS ce_trans,
                     COALESCE(eh.ce_disabled, fm.ce_disabled) AS ce_disabled,

                     COALESCE(eh.pe_male, fm.pe_male) +
                     COALESCE(eh.pe_female, fm.pe_female) +
                     COALESCE(eh.pe_trans, fm.pe_trans) +
                     COALESCE(eh.ce_male, fm.ce_male) +
                     COALESCE(eh.ce_female, fm.ce_female) +
                     COALESCE(eh.ce_trans, fm.ce_trans)       AS tt_emp,

                     (COALESCE(eh.pe_male, fm.pe_male) +
                      COALESCE(eh.pe_female, fm.pe_female) +
                      COALESCE(eh.pe_trans, fm.pe_trans) +
                      COALESCE(eh.ce_male, fm.ce_male) +
                      COALESCE(eh.ce_female, fm.ce_female) +
                      COALESCE(eh.ce_trans, fm.ce_trans))
                       * ct.total_cntrbtn                     AS tt_amt,

                     ct.employee_cntrbtn                      AS empe_ctb,
                     ct.employer_cntrbtn                      AS empr_ctb,
                     ct.total_cntrbtn                         AS tt_ctb
            FROM est.firms AS fm
                     INNER JOIN LATERAL (
                SELECT s.year::DATE,
                       a.employee_cntrbtn,
                       a.employer_cntrbtn,
                       a.total_cntrbtn
                FROM GENERATE_SERIES((to_char(fm.lwb_req_dt,'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
                         INNER JOIN mas.contributions a
                                    ON (s.year BETWEEN a.from_year AND COALESCE(a.to_year::date, CURRENT_DATE::date))
                ) AS ct ON TRUE
                     LEFT JOIN est.employee_history AS eh ON
                (fm.id = eh.firm_id AND eh.year = extract(YEAR from ct.year))
            where TRUE $where_clause
              and EXTRACT(YEAR from ct.year) NOT IN
                  (select year
                   from est.payables as pb
                            inner join
                        (select payable_id, payment_id
                           from est.payment_against_payables) as pap
                             on (pap.payable_id = pb.id
                            AND pap.payment_id IN (
                                select pm.id
                                  from est.payments as pm
                                 where pm.id = pap.payment_id
                                   and pm.status in ('S','I')
                                )
                            )
                   where pb.firm_id = $1-- <-firm_id should be a 1st params always.
                   )
            order by ct.year;";

//            LIMIT $1 OFFSET $2";//<-firm_id should be a 1st params always.
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      $sql = "select yts.year as prev_years_to_select
                from est.firms as fm
                         inner join lateral (
                    select *
                    from generate_series(extract(YEAR from fm.est_starts_from), extract(YEAR from fm.cre_ts) - 1) as yt(year)
                    ) as yts on true
                where fm.id = $1
                except
                (select pb.year
                 from est.payables as pb
                 where pb.id in (select pap.payable_id
                                 from est.payment_against_payables as pap
                                 where pap.payment_id in (select pm.id
                                                          from est.payments as pm
                                                          where pm.firm_id = $1
                                                            and pm.status != 'F'
                                                            and pm.id not in (select op.payment_id
                                                                              from est.other_payments op))))
                order by prev_years_to_select";

      $db->Query($sql, [$firm_id]);
      $prev_years_to_select = $db->FetchAll();

      $retObj['prev_years_to_select'] = $prev_years_to_select;

      foreach ($rows as &$r) {
        $r['year'] = intval($r['year']);
        $r['pe_male'] = intval($r['pe_male']);
        $r['pe_female'] = intval($r['pe_female']);
        $r['pe_trans'] = intval($r['pe_trans']);
        $r['pe_disabled'] = intval($r['pe_disabled']);
        $r['ce_male'] = intval($r['ce_male']);
        $r['ce_female'] = intval($r['ce_female']);
        $r['ce_trans'] = intval($r['ce_trans']);
        $r['ce_disabled'] = intval($r['ce_disabled']);
        $r['empe_ctb'] = intval($r['empe_ctb']);
        $r['empr_ctb'] = intval($r['empr_ctb']);
        $r['tt_ctb'] = intval($r['tt_ctb']);
        $r['tt_emp'] = intval($r['tt_emp']);
        $r['tt_amt'] = intval($r['tt_amt']);
//        $r['paid_amt'] = intval($r['paid_amt']);
      }
//
      $curr_cf_year = '';
      $params = [];
      $params[] = $firm_id;
      $fetch_val = [];

      foreach ($rows as $index => $row) {
        $params[] = $row['year'];
        $curr_cf_year .= '$' . count($params) . ',';
      }


      if (count($params) > 1) {
        $curr_cf_year = rtrim($curr_cf_year, ',');
        $in_val = ' AND eh.year NOT IN (' . $curr_cf_year . ')';

        $sql = "select eh.year,
                     eh.pe_male,
                     eh.pe_female,
                     eh.pe_trans,
                     eh.pe_disabled,
                     eh.ce_male,
                     eh.ce_female,
                     eh.ce_trans,
                     eh.ce_disabled,

                     (eh.pe_male + eh.pe_female + eh.pe_trans + eh.pe_disabled
                         + eh.ce_male + eh.ce_female + eh.ce_trans + eh.ce_disabled)                   AS tt_emp,

                     (eh.pe_male + eh.pe_female + eh.pe_trans + eh.pe_disabled
                         + eh.ce_male + eh.ce_female + eh.ce_trans + eh.ce_disabled) * c.total_cntrbtn AS tt_amt,

                     c.employee_cntrbtn                                                                AS empe_ctb,
                     c.employer_cntrbtn                                                                AS empr_ctb,
                     c.total_cntrbtn                                                                   AS tt_ctb
              from est.employee_history as eh
                       join mas.contributions c
                            ON ((eh.year || '-01' || '-01')::date BETWEEN c.from_year AND COALESCE(c.to_year::date, CURRENT_DATE::date))
              where eh.firm_id = $1 {$in_val}";

        $db->Query($sql, $params);
        $fetch_val = $db->FetchAll();

        foreach ($fetch_val as &$r) {
          $r['year'] = intval($r['year']);
          $r['pe_male'] = intval($r['pe_male']);
          $r['pe_female'] = intval($r['pe_female']);
          $r['pe_trans'] = intval($r['pe_trans']);
          $r['pe_disabled'] = intval($r['pe_disabled']);
          $r['ce_male'] = intval($r['ce_male']);
          $r['ce_female'] = intval($r['ce_female']);
          $r['ce_trans'] = intval($r['ce_trans']);
          $r['ce_disabled'] = intval($r['ce_disabled']);
          $r['empe_ctb'] = intval($r['empe_ctb']);
          $r['empr_ctb'] = intval($r['empr_ctb']);
          $r['tt_ctb'] = intval($r['tt_ctb']);
          $r['tt_emp'] = intval($r['tt_emp']);
          $r['tt_amt'] = intval($r['tt_amt']);
//        $r['paid_amt'] = intval($r['paid_amt']);
        }
      }

      $retObj['rows'] = ($fetch_val + $rows);

    } catch (\Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    return $retObj;
  }

  function getPaymentHistory($filter): array
  {
//    var_dump($filter);
    $retObj = ['rows' => [], 'message' => null];
    $receipt_no = $filter->receipt_no ?? null;
    $firm_id = $filter->firm_id ?? null;
    $limit = $filter->limit ?? null;
    $offset = $limit * ($filter->offset ?? 0);

    $where_clause = "";
    $params = [];

    if (!is_null($firm_id)) {
      $params[] = $firm_id;
      $where_clause = ' AND pm.firm_id = $' . count($params);
    }

    if (!is_null($receipt_no) && is_null($firm_id)) {
      $params[] = $receipt_no;
      $where_clause .= ' AND rp.receipt_no = $' . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "select pm.id,
                     pm.clr_status,
                     fm.id as firm_id,
                     fm.name as firm_name,
                     rp.id as receipt_id,
                     pm.trnx_no_own,
                     coalesce(rp.receipt_no,'Not Applicable')     as receipt_no,
                     pm.trnx_date,
                     pm.trnx_amount,
                     pm.status as pm_status,
                     pm.is_already_paid,
                     pm.payment_mode,
                     pm.rzp_order_id,
                     coalesce(opmt.pmt_name, 'Contribution Fund') as type_name,
                     coalesce(opmt.type, 'CF')                    as type_code
                from est.payments as pm
           left join est.receipts rp on pm.id = rp.payment_id
           left join est.firms fm on fm.id = pm.firm_id
           LEFT JOIN (select op.type,
                             op.payment_id,
                             op.year,
                             coalesce(op.other_name, opc.name) as pmt_name
                        from est.other_payments op
                   LEFT JOIN (select code, name from mas.other_payment_category) as opc
                         on opc.code = op.type) as opmt on opmt.payment_id = pm.id
          where true $where_clause
            order by pm.trnx_date desc";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

//      foreach ($rows as &$r) {
//        $r['year'] = intval($r['year']);
//        $r['empe_ctb'] = intval($r['empe_ctb']);
//        $r['empr_ctb'] = intval($r['empr_ctb']);
//        $r['tt_amt'] = intval($r['tt_amt ']);
//      }

      $retObj['rows'] = $rows;
    } catch (\Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function deleteEstTnebBill($data)
  {
    $retVal = [];
    $id = isset($data->id) ? $data->id : null;
    $attachment_id = isset($data->attachment_id) ? $data->attachment_id : null;
    $file_name = isset($data->file_name) ? $data->file_name : null;
    $storage_name = isset($data->storage_name) ? $data->storage_name : null;

    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id) && !is_null($attachment_id)) {
      try {

        if (unlink($storage_name)) {
          $db->Begin();
          $sql = 'DELETE FROM est.tneb_bills WHERE id = $1';
          $db->Query($sql, [$id]);
          $sql = 'DELETE FROM doc.attachments WHERE id = $1';
          $db->Query($sql, [$attachment_id]);

          $retVal['message'] = "TNEB Bill deleted successfully.";

        } else {

          $retVal['message'] = "TNEB Bill deletion failed.";
        }

      } catch (\Exception $e) {
        $retVal['message'] = $e->getMessage();
      }
    }
    $db->DBClose();
    return $retVal;
  }

  /*    function SaveEmployeeHistory($data)
      {
          $retVal = ['message' => 'Employee History cannot be saved.'];
          $firm_id        = isset($data->firm_id)         ? $data->firm_id : null;
          $pe_male        = isset($data->pe_male)         ? $data->pe_male : null;
          $pe_female      = isset($data->pe_female)       ? $data->pe_female : null;
          $pe_trans       = isset($data->pe_trans)        ? $data->pe_trans : null;
          $pe_disabled    = isset($data->pe_disabled)     ? $data->pe_disabled : null;
          $ce_male        = isset($data->ce_male)         ? $data->ce_male : null;
          $ce_female      = isset($data->ce_female)       ? $data->ce_female : null;
          $ce_trans       = isset($data->ce_trans)        ? $data->ce_trans : null;
          $ce_disabled    = isset($data->ce_disabled)     ? $data->ce_disabled : null;
          $tot_employees  = isset($data->tot_employees)   ? $data->tot_employees : null;
          $remarks        = isset($data->remarks)         ? $data->remarks : null;
          $year           = isset($data->year)            ? $data->year : null;

          $db = new \LWMIS\Common\PostgreDB();
          $sql = 'SELECT * FROM est.employee_history WHERE firm_id = $1 AND year=$2';
          $db->Query($sql, [$firm_id, $year]);
          $rows = $db->FetchAll();

          try{
            $db->Begin();
            $sql = 'UPDATE est.firms
                      SET pe_male = $1, pe_female = $2,
                         pe_trans = $3, pe_disabled = $4, ce_male = $5, ce_female = $6, ce_trans = $7, ce_disabled = $8
                      WHERE id = $9 RETURNING id';
            $db->Query($sql, [$pe_male, $pe_female, $pe_trans, $pe_disabled, $ce_male, $ce_female, $ce_trans, $ce_disabled, $firm_id]);

              if (!count($rows)>0) {

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
              $pay = new \LWMIS\Est\Payable();
              $pay->SavePayable($db, $data);
              $db->Commit();
          } catch (\Exception $e) {
              $db->RollBack();
              $retVal['message'] = $e->getMessage();
          }
          $db->DBClose();
          return $retVal;
      }*/

  function SaveEmployeeHistory($data): array
  {
//    var_dump('save emp===',$data);
    $retVal = ['message' => 'Employee History cannot be saved.'];
    $firm_id = $data->firm_id ?? null;
    $pe_male = $data->pe_male ?? null;
    $pe_female = $data->pe_female ?? null;
    $pe_trans = $data->pe_trans ?? null;
    $pe_disabled = $data->pe_disabled ?? null;
    $ce_male = $data->ce_male ?? null;
    $ce_female = $data->ce_female ?? null;
    $ce_trans = $data->ce_trans ?? null;
    $ce_disabled = $data->ce_disabled ?? null;
//    $tot_employees = $data->tt_emp ?? null;
    $remarks = $data->remarks ?? null;
    $year = $data->year ?? null;

//    var_dump($data);
//    var_dump("firm_id===", $firm_id);
//    var_dump("pe_male", $pe_male);

    $db = new \LWMIS\Common\PostgreDB();

    try {
      if (!is_null($pe_male) && !is_null($pe_female) && !is_null($pe_trans)
        && !is_null($ce_male) && !is_null($ce_female) && !is_null($ce_trans)) {
        $tot_employees = $pe_male + $pe_female + $pe_trans +
          $ce_male + $ce_female + $ce_trans;
      } else {
        throw new \Exception("Can't Calculate tot_emp");
      }

      $sql = 'SELECT * FROM est.employee_history WHERE firm_id = $1 AND year=$2';
      $db->Query($sql, [$firm_id, $year]);
      $rows = $db->FetchAll();

//      var_dump("rows",$rows);
      /*$sql = 'UPDATE est.firms
                        SET pe_male = $1, pe_female = $2,
                           pe_trans = $3, pe_disabled = $4, ce_male = $5, ce_female = $6, ce_trans = $7, ce_disabled = $8
                        WHERE id = $9 RETURNING id';
      $db->Query($sql, [$pe_male, $pe_female, $pe_trans, $pe_disabled, $ce_male, $ce_female, $ce_trans, $ce_disabled, $firm_id]);*/


      if (!count($rows) > 0) {
        $db->Begin();

        $sql = 'INSERT INTO est.employee_history
                (
                    firm_id,
                    year,
                    pe_male,
                    pe_female,
                    pe_trans,
                    pe_disabled,
                    ce_male,
                    ce_female,
                    ce_trans,
                    ce_disabled,
                    tot_employees,
                    remarks )
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
                RETURNING id';

        $db->Query($sql, [$firm_id, $year, $pe_male, $pe_female, $pe_trans, $pe_disabled,
          $ce_male, $ce_female, $ce_trans, $ce_disabled, $tot_employees, $remarks]);
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
                SET pe_male       =$3,
                    pe_female     =$4,
                    pe_trans      =$5,
                    pe_disabled   =$6,
                    ce_male       =$7,
                    ce_female     =$8,
                    ce_trans      =$9,
                    ce_disabled   =$10,
                    tot_employees =$11,
                    remarks       =$12
                WHERE firm_id = $1
                  AND year = $2';

        $db->Query($sql, [$firm_id, $year, $pe_male, $pe_female, $pe_trans, $pe_disabled,
          $ce_male, $ce_female, $ce_trans, $ce_disabled, $tot_employees, $remarks]);
        $retVal['message'] = "Employee History update successfully.";
      }

      $pay = new \LWMIS\Est\Payable();
//      $pay->SavePayable($db, $data);
      $pay->savePayable($db, $data, $tot_employees);

      $db->Commit();

    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
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

  function getYearWisePayment($data): array|string
  {
    $payment_id = $data->payment_id ?? null;
    $db = new PostgreDB();

    try {
      $sql = "select pb.year,
                     pb.employee_cntrbtn,
                     pb.employer_cntrbtn,
                     pb.total_cntrbtn,
                     pb.tot_employees,
                     pb.employee_amt,
                     pb.employer_amt,
                     pb.total_amt,
                     pb.paid_amt
                from est.payables as pb
               where pb.id in (
              select pap.payable_id
                from est.payment_against_payables as pap
               where pap.payment_id = $1)";
      $db->Query($sql, [$payment_id]);
      return $db->FetchAll();
    } catch (\Exception $e) {
      return ErrorHandler::custom($e);
    }
  }

  function getYearWisePaymentForOtherPayments($data): array|string
  {
    $payment_id = $data->payment_id ?? null;
    $db = new PostgreDB();

    try {
      $sql = "select op.year,
                     op.emp_count,
                     coalesce(opc.name,op.other_name) as paid_for_name,
                     op.amount
                from est.other_payments as op
           left join mas.other_payment_category as opc on op.type = opc.code
               where op.payment_id = $1;";
      $db->Query($sql, [$payment_id]);
      return $db->FetchAll();
    } catch (\Exception $e) {
      return ErrorHandler::custom($e);
    }
  }

}
