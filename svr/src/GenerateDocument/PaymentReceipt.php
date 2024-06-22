<?php

namespace LWMIS\GenerateDocument;

use LWMIS\Common\IndianCurrencyWord;

//class PaymentReceipt extends \TCPDF
class PaymentReceipt extends ExtendTCPDF
{
  private $html = '';

  public function __construct()
  {
    parent::__construct('A5');
  }


  public function Generate($filter): void
  {
//    if ($filter->receipt_id && $filter->payment_id) {
    if ($filter->receipt_id) {
      $receipt = new \LWMIS\Est\Receipt();
//      $p = (array)$receipt->getReceipt($filter)[0];
      $p = $receipt->getReceiptDetail($filter)['rows'][0];

      global $html;

      $html = '
        <div>
        <table>
          <tr>
            <td style="text-align: left;width:40%"></td>
            <td style="text-align: center;font-size: large;width: 20%;text-decoration:underline">RECEIPT</td>
            <td style="text-align: right;width: 40%"></td>
          </tr>
        </table>
        </div>';

      $html .= '
        <style>
          .addBorder td, .addBorder th { border: 0.01px solid black; padding: 0; }
        </style>
      ';

      $year_wise_payments = [];
      $filter->payment_id = $p['payment_id'];//to getYearWisePayment

//      $paid_for_code = $p['paid_for_code'];`
      $eh = new \LWMIS\Est\Employee_history();
      if ($p['paid_for_code'] == 'CF') {
        $year_wise_payments = $eh->getYearWisePayment($filter);
      } else {
        $year_wise_payments = $eh->getYearWisePaymentForOtherPayments($filter);
      }

      $html .= '
      <table>
        <tr>
          <td><span style="font-weight: bold;">Receipt No.</span></td>
          <td><span>: ' . $p['receipt_no'] . '</span></td>
          <td><span style="font-weight: bold;">Receipt Date</span></td>
          <td><span>: ' . date_format(date_create($p['receipt_dt']), 'd-m-Y') . '</span></td>
        </tr>
        <tr>
          <td><span style="font-weight: bold;">Register ID No.</span></td>
          <td colspan="3"><span>: ' . $p['lwb_reg_no'] . '</span></td>
        </tr>
        <tr>
          <td><span style="font-weight: bold;">Received From</span></td>
          <td colspan="3" >: <span>' . $p['firm_address'] . '<br>
            ' . $p['landmark'] . ',<br>
            ' . $p['taluk_name'] . ',<br>
            ' . $p['panchayat_name'] . '<br>
            ' . $p['district_name'] . ' - ' . $p['postal_code'] . '<br></span></td>
          <td style="width:25%;"></td>
        </tr>
      </table>';

      if ($p['paid_for_code'] == 'CF') {

        $html .= '
    <div style="margin-top: 5%;width=100%;height=150%">
      <table  cellspacing="0" class="addBorder" style="text-align: center;margin-top: 5%;width=100%;">
        <thead>
          <tr>
            <th rowspan="2" style="font-weight: bold;">S.No.</th>
            <th rowspan="2" style="font-weight: bold;">Year</th>
            <th rowspan="2" style="font-weight: bold;font-size: 10px">No. of <br> Employees <br> <span style="font-weight: bolder">(E)</span></th>
            <th colspan="3" style="font-weight: bold;">Contribution</th>
            <th rowspan="2" style="font-weight: bold;">Amount <br> (E * C)</th>
          </tr>
          <tr>
            <th style="font-weight: bold;">Employee</th>
            <th style="font-weight: bold;">Employer</th>
            <th style="font-weight: bold;">Total <br> <span style="font-weight: bolder">(C)</span></th>
          </tr>
        </thead>
        <tbody>';

        $i = 0;
        $total_amount = 0;
        foreach ($year_wise_payments as $pmt) {
          $i = $i + 1;
          $total_amount = $total_amount + $pmt['total_amt'];
          $html .= '
        <tr>
          <td>' . $i . '</td>
          <td>' . $pmt['year'] . '</td>
          <td>' . $pmt['tot_employees'] . '</td>
          <td>' . $pmt['employee_cntrbtn'] . '</td>
          <td>' . $pmt['employer_cntrbtn'] . '</td>
          <td>' . $pmt['total_cntrbtn'] . '</td>
          <td>' . $pmt['total_amt'] . '</td>
        </tr>
        ';
        }

      } else {

        $html .= '
    <div style="margin-top: 5%;width=100%;height=150%">
      <table  cellspacing="0" class="addBorder" style="text-align: center;margin-top: 5%;width=100%;">
        <thead>
          <tr>
            <th style="font-weight: bold;">S.No.</th>
            <th style="font-weight: bold;">Year</th>
            <th style="font-weight: bold;">No. of <br> Employees</th>
            <th style="font-weight: bold;">Payment <br> Made For</th>
            <th style="font-weight: bold;">Amount</th>
          </tr>
        </thead>
        <tbody>';

        $i = 0;
        $total_amount = 0;
        foreach ($year_wise_payments as $pmt) {
          $i = $i + 1;
          $total_amount = $total_amount + $pmt['amount'];
          $html .= '
        <tr>
          <td>' . $i . '</td>
          <td>' . $pmt['year'] . '</td>
          <td>' . $pmt['emp_count'] . '</td>
          <td>' . $pmt['paid_for_name'] . '</td>
          <td>' . $pmt['amount'] . '</td>
        </tr>
        ';
        }

      }

      if ($p['paid_for_code'] == 'CF') {
        $html .= '
      </tbody>
      <tfooter>
        <tr>
          <td colspan="6" style="font-weight: bold" >Total</td>
          <td style="font-weight: bold">Rs.' . number_format($total_amount, 2) . '</td>
        </tr>
      </table>';
      } else {
        $html .= '
      </tbody>
      <tfooter>
        <tr>
          <td colspan="4" style="font-weight: bold" >Total</td>
          <td style="font-weight: bold">Rs.' . number_format($total_amount, 2) . '</td>
        </tr>
      </table>';
      }

      $amt_to_word = new IndianCurrencyWord();

      $html .= '
      <table style="padding:5px">
        <tr>
          <td><span style="font-weight: bold;">Amount Paid:</span>
          <span>Rs. ' . number_format($p['amount'], 2) . '</span></td>
          <td><span style="font-weight: bold;">Amount (In Words):</span>
          <span>' . $amt_to_word->get_words((float)$p['amount']) . '</span><br></td>
        </tr>
      </table>
      ';

      if (isset($p['payment_mode']) && ($p['payment_mode'] == 'OLP')) {
        $html .= '
        <table style="padding:5px">
          <tr>
            <td><span style="font-weight: bold;">Transaction No.</span>
            <span>: ' . $p['trnx_ref_no'] . '</span></td>
            <td><span style="font-weight: bold;">Date.</span>
            <span>: ' . date_format(date_create($p['trnx_dt']), 'd-m-Y') . '</span></td>
          </tr>';
      }

      if (isset($p['payment_mode']) && ($p['payment_mode'] == 'DD')) {
        $html .= '
        <table style="padding:5px">
          <tr>
            <td><span style="font-weight: bold;">Demand Draft</span>
            <span>: ' . $p['chq_no'] . '</span></td>

            <td><span style="font-weight: bold;">Dated.</span>
            <span>: ' . $p['chq_dt'] . '</span></td>
          </tr>';
      }

      if (isset($p['payment_mode']) && ($p['payment_mode'] == 'CHQ')) {
        $html .= '
        <table style="padding:5px">
          <tr>
            <td><span style="font-weight: bold;">Cheque No.</span>
            <span>: ' . $p['chq_no'] . '</span></td>

            <td><span style="font-weight: bold;">Dated.</span>
            <span>: ' . $p['chq_dt'] . '</span></td>
          </tr>
        ';
      }

      $html .= '
      <tr>
        <td><span style="font-weight: bold;">Ref No.:</span> <span>' . $p['trnx_no_gw'] . '</span>
        </td>
      </tr>
      ';

      $html .= '
      <tr>
        <td style="width:100%"><span style="font-weight: bold;">Drawn On</span>
        <span style="font-size:14px">: ' . $p['firm_name'] . '</span></td>
      </tr>
      </table>
      </div>';

      $html = '<table style="border-style: ridge;"><tr><td width="100%">' . $html . '</td></tr></table>';
      $this->writeHTML($html, true, false, true, false, '');
      // reset pointer to the last page
      echo $this->Output('example_003.pdf', 'I');
    } else {
      echo 'receipt_id & payment id is required';
    }
  }


}
