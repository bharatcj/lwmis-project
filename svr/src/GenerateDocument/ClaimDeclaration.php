<?php

namespace LWMIS\GenerateDocument;

use LWMIS\Clm\Claims;
use LWMIS\Common\PostgreDB;
use LWMIS\Common\RandomString;

class ClaimDeclaration extends ExtendTCPDF
{
  public function Generate($filter): void
  {
    $claimTable = new Claims();
    $p = (array)$claimTable->getClaimTable($filter)['rows'];

    $randomString = new RandomString();
    $ref_no = $randomString->genAlphaNumeric(8);

    $status = true;
    $status = $this->saveRef_no($p["id"], $ref_no);

//    $this->Line(15, 29, $this->getPageWidth() - 15, 29);
//    todo:unset the table data of <tr> <td> tags
    if ($status) {
      $html = <<<EOL
    <div>
        <p>Scheme Name : {$p['scheme_name']} </p>
        <p>Reference No : $ref_no </p>
    </div>
    EOL;

      $html .= '
        <table style="margin: 0.5%;">
          <tr>
            <td style="text-align: center;font-size: large;background-color: darkgrey">Employee Details</td>
          </tr>
        </table>
        ';
//    $this->SetFont('times', '', 9);
      $this->SetFont('freesans', '', 9);
//    $this->writeHTML($html, true, false, true, false, '');
//.addBorder td, .addBorder th { border: 1px solid black; padding: 5px; }

      $html .= '
        <style>
        .row{
            text-align:center;
            background-color: lightgray;
        }

        td{
            border: 1px solid #c9c4c4;
        }

        th{
            border: 1px solid black;
        }

        .slno{
            text-align: right;
            width: 8%;
        }

        .c2{
            width: 46%;
        }

        .c3{
            width: 46%;
        }

        </style>
      ';

      $html .= '
          <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno" >1.</td>
              <td class="c2">Employee Name</td>
              <td class="c3">' . $p['e_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Employee Designation</td>
              <td class="c3">' . $p['e_designation'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Date of Birth</td>
              <td class="c3">' . date_format(date_create($p['e_dob']), 'd-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Gender</td>
              <td class="c3">' . $p['e_gender_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Maritial Status</td>
              <td class="c3">' . $p['e_marital_status'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Mobile Number</td>
              <td class="c3">' . $p['e_mobile'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Landline Number</td>
              <td class="c3">' . $p['e_phone'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Email Address</td>
              <td class="c3">' . $p['e_email'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Monthly Salaary</td>
              <td class="c3">' . $p['e_salary_pm'] . '</td>
            </tr>
          </table>
       ';

//    ** Employee Address Details **
      $html .= '
            <table>
            <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Employee Address Details</th>
            </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Flat/House No</td>
              <td class="c3">' . $p['ad_house_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Street Name</td>
              <td class="c3">' . $p['ad_street_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Locality/Area</td>
              <td class="c3">' . $p['ad_area'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">District</td>
              <td class="c3">' . $p['ad_district_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Taluk</td>
              <td class="c3">' . $p['ad_taluk_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">panchayat</td>
              <td class="c3">' . $p['ad_panchayat_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Landmark</td>
              <td class="c3">' . $p['ad_landmark'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Pincode</td>
              <td class="c3">' . $p['ad_postal_code'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Latitude</td>
              <td class="c3">' . $p['ad_latitude'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Longitude</td>
              <td class="c3">' . $p['ad_longitude'] . '</td>
            </tr>
            </table>
            ';

// ** Bank Details of the Employee **

      if ($p['scheme_code'] == 'NAT_D_AST' || $p['scheme_code'] == 'AC_D_AST') {
        $html .= '
        <table>
            <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Bank Details of The Dependent.</th>
            </tr>
        </table>
        ';
      } else {
        $html .= '
        <table>
            <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Bank Details of The Employee.</th>
            </tr>
        </table>
            ';
      }

      $html .= '
            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Name of the Account Holder</td>
              <td class="c3">' . $p['acc_bank_acc_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Account Number</td>
              <td class="c3">' . $p['acc_bank_acc_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Bank IFSC code</td>
              <td class="c3">' . $p['acc_ifsc'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the bank</td>
              <td class="c3">' . $p['bank_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Branch of the Bank</td>
              <td class="c3">' . $p['branch_name'] . '</td>
            </tr>
            </table>
            ';


//    ** Education Details of Student **
// <mat-card *ngIf="value.scheme_code=='ED_SCH'">
      if ($p['scheme_code'] === 'ED_SCH') {
        $html .= '
            <table>
            <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Education Details of Student</th>
            </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Dependent Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the Institution</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact Number of the Institution</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Address of the institution</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Class/Course studied during the Previous academic year</td>
              <td class="c3">' . $p['prev_class_studied'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Class/Course Studying during the current academic year.</td>
              <td class="c3">' . $p['curr_class_studied'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Whether the student was passed in last year examination.</td>
              <td class="c3">' . $p['is_passed_prev_edu'] . '</td>
            </tr>
            </table>
            ';
      }


//    ** Education incentive **
//      <mat-card *ngIf="value.scheme_code=='ED_INC'">


//      <tr>
//        <td class="slno">7.</td>
//        <td class="c2">Date of Passing 10th Standard.</td>
//        <td class="c3">' . date_format(date_create($p['ec_10th_pass_date']), 'd-m-Y') . '</td>
//      </tr>
//      <tr>
//        <td class="slno">8.</td>
//        <td class="c2">Date of Passing 12th Standard.</td>
//        <td class="c3">' . date_format(date_create($p['ec_12th_pass_date']),'d-m-Y') . '</td>
//      </tr>

      if ($p['scheme_code'] === 'ED_INC') {
        $html .= '
                 <table>
                    <tr>
                      <th style="text-align: center;font-size: large;background-color: lightgray">Educational Incentive</th>
                    </tr>
                 </table>

                 <table style="border: 1px solid black;border-collapse: collapse;">
                 <tr>
                    <td class="slno">1.</td>
                    <td class="c2">Student Name</td>
                    <td class="c3">' . $p['dp_name'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">2.</td>
                    <td class="c2">Relationship to the Dependent</td>
                    <td class="c3">' . $p['dp_relationship'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">3.</td>
                    <td class="c2">Student Gender</td>
                    <td class="c3">' . $p['dp_gender_name'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">4.</td>
                    <td class="c2">Class/Course studying during the current academic year</td>
                    <td class="c3">' . $p['curr_class_studied'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">5.</td>
                    <td class="c2">Name of the School</td>
                    <td class="c3">' . $p['inst_name'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">6.</td>
                    <td class="c2">Contact Number of the School</td>
                    <td class="c3">' . $p['inst_contact_no'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">7.</td>
                    <td class="c2">School District</td>
                    <td class="c3">' . $p['inst_name'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">8.</td>
                    <td class="c2">Educational District of School</td>
                    <td class="c3">' . $p['dp_aadhaar'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">9.</td>
                    <td class="c2">Address Of the School</td>
                    <td class="c3">' . $p['inst_address'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">10.</td>
                    <td class="c2">Aadhaar Number of Student</td>
                    <td class="c3">' . $p['dp_aadhaar'] . '</td>
                  </tr>
                  <!--    todo:add ngif to check which marklist is applicable-->
                  <tr>
                    <td class="slno">11.</td>
                    <td class="c2">Percentage of marks Obtained</td>
                    <td class="c3">' . $p['ei_es_mark_percent'] . '</td>
                  </tr>
                  <tr>
                    <td class="slno">12.</td>
                    <td class="c2">10th Mark List Number</td>
                    <td class="c3">' . $p['ec_10th_mark_sheet_no'] . '</td>
                  </tr>
                  </table>
                  ';
      }


//    ** Educational Entrance Exam Coaching Assistance **
      if ($p['scheme_code'] === 'EX_AST') {
        $html .= '
            <table>
            <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Educational Incentive</th>
            </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '}}</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '}}</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Student Gender</td>
              <td class="c3">' . $p['dp_gender'] . '}}</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the Coaching institute</td>
              <td class="c3">' . $p['inst_name'] . '}}</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Date of passing 10th standard</td>
              <td class="c3">' . date_format(date_create($p['ec_10th_pass_date']),'d-m-Y') . '}}</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Aadhaar Number of Student</td>
              <td class="c3">' . $p['dp_aadhaar'] . '}}</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">10th Mark List Number</td>
              <td class="c3">' . $p['ec_10th_mark_sheet_no'] . '}}</td>
            </tr>
            </table>';

      }

//    ** Emp Accident Death Details **
//          <mat-card *ngIf="value.scheme_code=='AC_D_AST'">

      if ($p['scheme_code'] === 'AC_D_AST') {
        $html .= '
            <table>
              <tr>
                  <th style="text-align: center;font-size: large;background-color: lightgray">Employese Accidental Death Details</th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Employee Death Place</td>
              <td class="c3">' . $p['e_death_place'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Nature of Accident</td>
              <td class="c3">' . $p['ad_accident_nature'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Employees Death Date</td>
              <td class="c3">' . date_format(date_create($p['e_death_date']), 'd-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Employees Death Time</td>
              <td class="c3">' . $p['e_death_time'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Employee Death Certificare Number</td>
              <td class="c3">' . $p['e_death_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">First Investigation Report</td>
              <td class="c3">' . $p['ad_fir_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Postmortem Report Number</td>
              <td class="c3">' . $p['ad_postmortem_report_no'] . '</td>
            </tr>
            </table>
            ';
      }

      if ($p['scheme_code'] === 'NAT_D_AST') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Employee\'s Death Details </th>
              </tr>
            </table>
            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Employee Death Place</td>
              <td class="c3">' . $p['e_death_place'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Employees Death Time</td>
              <td class="c3">' . $p['e_death_time'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Employees Death Date</td>
              <td class="c3">' . date_format(date_create($p['e_death_date']),'d-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Employee Death Certificare Number</td>
              <td class="c3">' . $p['e_death_cert_no'] . '</td>
            </tr>
            </table>
            ';
      }
//    ** Natural & accidental death's dependent details **
      if ($p['scheme_code'] == 'NAT_D_AST' || $p['scheme_code'] == 'AC_D_AST') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Dependent Details </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
              <tr>
                <td class="slno">1.</td>
                <td class="c2">Name of the Dependent</td>
                <td class="c3">' . $p['dp_name'] . '}</td>
              </tr>
              <tr>
                <td class="slno">2.</td>
                <td class="c2">Dependent Relationship With Deceased Employee</td>
                <td class="c3">' . $p['dp_relationship_name'] . '</td>
              </tr>
              <tr>
                <td class="slno">3.</td>
                <td class="c2">Dependent Date of Birth</td>
                <td class="c3">' . date_format(date_create($p['dp_dob']),'d-m-Y') . '</td>
              </tr>
              <tr>
                <td class="slno">4.</td>
                <td class="c2">Gender</td>
                <td class="c3">' . $p['dp_gender_name'] . '</td>
              </tr>
              <tr>
                <td class="slno">5.</td>
                <td class="c2">Mobile Number</td>
                <td class="c3">' . $p['dp_mobile'] . '</td>
              </tr>
              <tr>
                <td class="slno">6.</td>
                <td class="c2">Phone Number</td>
                <td class="c3">' . $p['dp_phone'] . '</td>
              </tr>
              <tr>
                <td class="slno">7.</td>
                <td class="c2">Email Address of the Dependent</td>
                <td class="c3">' . $p['dp_email'] . '</td>
              </tr>
              <tr>
                <td class="slno">8.</td>
                <td class="c2">Legal heir Certificate Number</td>
                <td class="c3">' . $p['dp_legal_heir_cert_no'] . '</td>
              </tr>
              <tr>
                <td class="slno">9.</td>
                <td class="c2">Aadhaar Number</td>
                <td class="c3">' . $p['dp_aadhaar'] . '</td>
              </tr>
            </table>
            ';
      }

//    address details of dependent
//    *ngIf="value.scheme_code=='NAT_D_AST'">

      if ($p['scheme_code'] == 'NAT_D_AST' || $p['scheme_code'] == 'AC_D_AST') {
        $html .= '
            <table>
                <tr>
                    <th style="text-align: center;font-size: large;background-color: lightgray">Dependent Address Details </th>
                </tr>
            </table>
            <table style="border: 1px solid black;border-collapse: collapse;">
                <tr>
                    <td class="slno">1.</td>
                    <td class="c2">Flat/House No</td>
                    <td class="c3">' . $p['dp_house_no'] . '</td>
                </tr>
                <tr>
                    <td class="slno">2.</td>
                    <td class="c2">Street Name</td>
                    <td class="c3">' . $p['dp_street_name'] . '</td>
                </tr>
                <tr>
                    <td class="slno">3.</td>
                    <td class="c2">Locality/Area</td>
                    <td class="c3">' . $p['dp_area'] . '</td>
                </tr>
                <tr>
                    <td class="slno">4.</td>
                    <td class="c2">District</td>
                    <td class="c3">' . $p['dp_district_name'] . '</td>
                </tr>
                <tr>
                    <td class="slno">5.</td>
                    <td class="c2">Taluk</td>
                    <td class="c3">' . $p['dp_taluk_name'] . '</td>
                </tr>
                <tr>
                    <td class="slno">6.</td>
                    <td class="c2">Panchayat name</td>
                    <td class="c3">' . $p['dp_panchayat_name'] . '</td>
                </tr>
                <tr>
                    <td class="slno">7.</td>
                    <td class="c2">Landmark</td>
                    <td class="c3">' . $p['dp_landmark'] . '</td>
                </tr>
                <tr>
                    <td class="slno">8.</td>
                    <td class="c2">Pincode</td>
                    <td class="c3">' . $p['ad_postal_code'] . '</td>
                </tr>
                <tr>
                    <td class="slno">9.</td>
                    <td class="c2">Latitude</td>
                    <td class="c3">' . $p['dp_latitude'] . '</td>
                </tr>
                <tr>
                    <td class="slno">10.</td>
                    <td class="c2">Longitude</td>
                    <td class="c3">' . $p['dp_longitude'] . '</td>
                </tr>
            </table>
            ';
      }
//    student Details for sewing machine
//    *ngIf="value.scheme_code=='SM_AST'
      if ($p['scheme_code'] == 'SM_AST') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">Student Details </th>
              </tr>
            </table>
            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '}</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Employee</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Duration of Course in Months</td>
              <td class="c3">' . $p['sm_course_duration_in_mon'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Dependent Mobile Number</td>
              <td class="c3">' . $p['dp_mobile'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Dependent Phone Number</td>
              <td class="c3">' . $p['dp_phone'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Name of the tailoring institute</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Constact Number of the tailoring institute</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Address of the Tailoring institute</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Whether the Institute was recognized by the government</td>
              <td class="c3">' . $p['sm_tailoring_inst_is_govt_recognised'] . '</td>
            </tr>
            <tr>
              <td class="slno">11.</td>
              <td class="c2">Adhaar Number</td>
              <td class="c3">' . $p['e_aadhaar_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">13.</td>
              <td class="c2">Enter the tailoring training registration Number</td>
              <td class="c3">' . $p['sm_tailoring_inst_reg_no'] . '</td>
            </tr>
            </table>
            ';
      }

//    ** Book Allowance **
//    scheme_code=='BOOK_ALW'

      if ($p['scheme_code'] == 'BOOK_ALW') {
        $html .= '
           <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                Book Allowance Details
                </th>
              </tr>
           </table>

           <table style="border: 1px solid black;border-collapse: collapse;">
           <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '}</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Student Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the institute</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact No. of the institution</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">class/course studying during the current academic year.</td>
              <td class="c3">' . $p['curr_class_studied'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Address of The institution</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Bona-fide Certificate Number.</td>
              <td class="c3">' . $p['bonafide_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Whether the student was passed in the last year examination ?</td>
              <td class="c3">' . $p['is_passed_prev_edu'] . '</td>
            </tr>
            </table>
            ';
      }

//    Free Question Bank
//    'Q_BANK'

      if ($p['scheme_code'] == 'Q_BANK') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                Free Supply of Question Bank.
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '}</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Students Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the institute</td>
              <td class="c3">' . $p['inst_name'] . '}</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact No. of the institution</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">class/course studying during the current academic year.</td>
              <td class="c3">' . $p['curr_class_studied'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td>Selected Subjects</td>
              <td>';

        foreach ($p['qb_subjects'] as $arr) {
//        echo $arr->name;
          $html .= "{$arr->name} <br>";
        }

        $html .= '
            </td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Address of The institution</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Bona-fide Certificate Number.</td>
              <td class="c3">' . $p['bonafide_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">11.</td>
              <td class="c2">Whether the student was passed in the last year examination ?</td>
              <td class="c3">' . $p['is_passed_prev_edu'] . '</td>
            </tr>
            </table>
            ';
      }

//    **Spectacle Assistance**
//    SPT_AST


      if ($p['scheme_code'] == 'SPT_AST') {

        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                Spectacle Assistance Details
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
              <tr>
                <td class="slno">1.</td>
                <td class="c2">Date of Doctor Presscription</td>
                <td class="c3">' . date_format(date_create($p['sa_prescription_dt']), 'd-m-Y') . '</td>
              </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Date of Purchase</td>
              <td class="c3">' . date_format(date_create($p['sa_purchase_dt']), 'd-m-Y') . '</td>
            </tr>
            </table>
    ';
      }

//    Marriage Assistance
//    MRG_AST

      if ($p['scheme_code'] == 'MRG_AST') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                Marriage Assistance Details
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Name of the Groom</td>
              <td class="c3">' . $p['ma_g_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Grooms Date of Birth</td>
              <td class="c3">' . date_format(date_create($p['ma_g_dob']),'d-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Name of Bride</td>
              <td class="c3">' . $p['ma_b_name'] . '/td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Bride Date of Birth</td>
              <td class="c3">' . date_format(date_create($p['ma_b_dob']),'d-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Marriage Assistance Sought For</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Marriage Date</td>
              <td class="c3">' . date_format(date_create($p['ma_wedding_dt']),'d-m-Y') . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Place of Marriage</td>
              <td class="c3">' . $p['ma_wedding_place'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Marriage Certificate Number</td>
              <td class="c3">' . $p['ma_marriage_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Brides Aadhaar Number</td>
              <td class="c3">' . $p['ma_b_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">11.</td>
              <td class="c2">Grooms Aadhaar Number</td>
              <td class="c3">' . $p['ma_g_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">13.</td>
              <td class="c2">Bribes Ration Card Number</td>
              <td class="c3">' . $p['ma_b_family_ration_card_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">14.</td>
              <td class="c2">Grooms Ration Card Number</td>
              <td class="c3">' . $p['ma_g_family_ration_card_no'] . '</td>
            </tr>
            </table>
            ';
      }

//   ** Computer Training Assistance **
//   CT_AST

      if ($p['scheme_code'] == 'CT_AST') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                Computer Training Assistance
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Students Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the Training Institute</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact No. of the Training Institution</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Address of The institution</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Whethe the institute was recognized by the government?</td>
              <td class="c3">' . $p['ct_inst_is_govt_recognised'] . '</td>
            </tr>
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Course Completion Certificate Number</td>
              <td class="c3">' . $p['ct_course_completion_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">9.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            </table>
            ';
      }

//    State Level Sports Scholarship
//    STATE_SA

      if ($p['scheme_code'] == 'STATE_SA') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                State Level Sports Scholarship
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Dependent Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the School</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact Number of the School</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Address of the School</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Class/Course studying during the Current academic year</td>
              <td class="c3">' . $p['curr_class_studied'] . '</td>
            </tr>
            <!--todo:sports sdat certificates-->
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Bona-fide Certificate Number.</td>
              <td class="c3">' . $p['bonafide_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">12.</td>
              <td class="c2">Mark list Number</td>
              <td class="c3">' . $p['ei_es_mark_sheet_no'] . '</td>
            </tr>
            </table>
            ';
      }

//    District Level Sports Assistance
//    DIST_SA
      if ($p['scheme_code'] == 'DIST_SA') {
        $html .= '
            <table>
              <tr>
                <th style="text-align: center;font-size: large;background-color: lightgray">
                    District Level Sports Assistance
                </th>
              </tr>
            </table>

            <table style="border: 1px solid black;border-collapse: collapse;">
            <tr>
              <td class="slno">1.</td>
              <td class="c2">Student Name</td>
              <td class="c3">' . $p['dp_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">2.</td>
              <td class="c2">Relationship to the Dependent</td>
              <td class="c3">' . $p['dp_relationship'] . '</td>
            </tr>
            <tr>
              <td class="slno">3.</td>
              <td class="c2">Dependent Gender</td>
              <td class="c3">' . $p['dp_gender'] . '</td>
            </tr>
            <tr>
              <td class="slno">4.</td>
              <td class="c2">Name of the School</td>
              <td class="c3">' . $p['inst_name'] . '</td>
            </tr>
            <tr>
              <td class="slno">5.</td>
              <td class="c2">Contact Number of the School</td>
              <td class="c3">' . $p['inst_contact_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">6.</td>
              <td class="c2">Address of the School</td>
              <td class="c3">' . $p['inst_address'] . '</td>
            </tr>
            <tr>
              <td class="slno">7.</td>
              <td class="c2">Class/Course studying during the Current academic year</td>
              <td class="c3">' . $p['curr_class_studied'] . '</td>
            </tr>
            <!--todo:sports sdat certificates-->
            <tr>
              <td class="slno">8.</td>
              <td class="c2">Bona-fide Certificate Number.</td>
              <td class="c3">' . $p['bonafide_cert_no'] . '</td>
            </tr>
            <tr>
              <td class="slno">10.</td>
              <td class="c2">Aadhaar Number</td>
              <td class="c3">' . $p['dp_aadhaar'] . '</td>
            </tr>
            <tr>
              <td class="slno">11.</td>
              <td class="c2">Mark list Number</td>
              <td class="c3">' . $p['ei_es_mark_sheet_no'] . '</td>
            </tr>
            </table>';
      }

      if ($p['scheme_code'] == 'NAT_D_AST' || $p['scheme_code'] == 'AC_D_AST') {
        $html .= <<<EOD
            <div>
            <h3>Employer Declaration</h3>
              <p>       I certify that the above employee is worked in our establishment & Labour Welfare Fund contribution
                 has been deducted and paid to the board.</p>
                 <br><br><br>
                 <span style="text-align: right"><p>Employer Signature</p></span>
            </div>

            <div>
            <h3>Dependent Declaration</h3>
              <p>       I certify that the above mentioned particulars are true and correct & I have not applied / received any
                  death assistance from other Government Department / Organisation.</p>
                <br><br><br>
                <span style="text-align: right"><p>Dependent Signature</p></span>
            </div>
          EOD;
      } else {
        $html .= <<<EOD
            <h3>Employee Declaration</h3>
              <p>       I certify that the above mentioned particulars are true and I have not applied for any other Scholarship / Assistance scheme from other Government Departments / Organisations.</p>
                <br><br><br>
                <span style="text-align: right"><p>Employee Signature</p></span>

            <h3>Employer Declaration</h3>
              <p>       I Certify that the above employee is working in our establishment and Labour Welfare Fund contribution has been deducted and paid to the Board.</p>
                 <br><br><br>
                 <span style="text-align: right"><p>Employer Signature</p></span>
            EOD;
      }

//    $html = '<table style="border-style: ridge;"><tr><td width="100%">' . $html . '</td></tr></table>';
      // $this->SetFont('times', '', 8);
//    $this->SetFont('freesans');
      $this->writeHTML($html, true, false, true, false, '');
    } else {
      print_r('CHECK REF_NO AND CLAIM ID');
    }
    $this->Output('supplier_invoice.this', 'D');

  }

  function saveRef_no($id, $ref_no): bool
  {
    $db = new PostgreDB();
    $db->Begin();

    try {
      $sql = "UPDATE clm.claims SET ref_no = '$ref_no' WHERE id=$id RETURNING id";
      $db->Query($sql);
      $row = $db->FetchAll();
      $db->Commit();
      if ($row[0]["id"] != $id) {
        return false;
      }
    } catch (\Exception $e) {
      $db->RollBack();
//      return $retVal['message'] = $e->getMessage();
      return false;
    }

    $db->DBClose();
    return true;
  }

}
