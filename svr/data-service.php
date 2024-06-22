<?php
echo "here";
require_once __DIR__ . '/vendor/autoload.php';
\LWMIS\Common\ErrorHandler::defineErrorLevel();
date_default_timezone_set('Asia/Kolkata');

if (\LWMIS\Common\MyConfig::IS_TESTING) {
//own
  define('PG_RP_KEY_ID', 'rzp_test_IT1L6GcJ0413py');
  define('PG_RP_KEY_SECRET', 'DyAspUq8E6fRs2smHE2uKSBa');
//  lwb's
//  define('PG_RP_KEY_ID', 'rzp_test_77tIjetYPlWNFd');
//  define('PG_RP_KEY_SECRET', 'udEvICFQbYjrVwd1Rt3Psvzg');

//  lwb's live key
//  define('PG_RP_KEY_ID', 'rzp_live_rf7afHoC9aWnvp');
//  define('PG_RP_KEY_SECRET', '3u3QnUyIXae7kY4vDrZJoUz7');

} else {
  define('PG_RP_KEY_ID', 'rzp_live_KSoY5WhDvrCDLt');
  define('PG_RP_KEY_SECRET', 'QPBl03zhSognvILqnsh2rRMV');

//  define('PG_RP_KEY_ID', 'rzp_live_rf7afHoC9aWnvp');
//  define('PG_RP_KEY_SECRET', '3u3QnUyIXae7kY4vDrZJoUz7');
}

const PG_RP_CURRENCY = 'INR';//<- using cont inside conditional operator is not recommended because unlike runtime initialization (define) const will be initialized at compile time
const ENC_KEY = '3pBf*qCgNz@';

ini_set('memory_limit', '1024M'); // or you could use 1G for 1GB m/y limit
//ini_set('xdebug.auto_trace', 1);
$jwt = new \LWMIS\Common\JWT();
$gn = new \LWMIS\Common\GeneralFunctions();
if ($gn->addCORHeaders()) {
  exit();
}
$payload = array();
try {
  $payload = (array)$jwt->validateToken();
  switch (true) {
    case (isset($payload['id']) && $payload['id'] > 0):
      if (!(isset($_GET['logout']) && $_GET['logout'])) {
        // TODO:: Check if user is already logged in[ 'id' => ['id'] ]

        //          if (!isset($payload['user_type'])) {
        //            throw new \Exception('authentication data tampered.');
        //          }

//        if ($payload['user_type'] === 'registered_customer') {
//          $registeredCustomer = new LWMIS\Master\User();
//          $registeredCustomer->checkSessionToken((object)$payload);
//        } else {
        $user = new \LWMIS\Master\User();
        $user->checkUserSessionToken((object)$payload);
//        }

        $newToken = $jwt->generateToken((object)$payload);
//        var_dump($newToken);
        header("X-TOKEN: {$newToken}", true);
      }
      break;
    default:
      throw new \Exception("authentication data tampered.");
  }
} catch (\Exception $e) {
  switch (true) {
    // Requires no token
    case (isset($_GET['generateSecretKey']) && $_GET['generateSecretKey']):
      // User login related
    case (isset($_GET['login']) && $_GET['login']):
    case (isset($_GET['logout']) && $_GET['logout']):
    case (isset($_GET['checkUserAndGenerateOTP']) && $_GET['checkUserAndGenerateOTP']):
    case (isset($_GET['resendUserOTP']) && $_GET['resendUserOTP']):
    case (isset($_GET['validateUserOTP']) && $_GET['validateUserOTP']):
    case (isset($_GET['saveUserPassword']) && $_GET['saveUserPassword']):
    case (isset($_GET['regUser']) && $_GET['regUser']):
    case (isset($_GET['getFirmsEst']) && $_GET['getFirmsEst']):
    case (isset($_GET['getArrearsToBePaid']) && $_GET['getArrearsToBePaid']):
    case (isset($_GET['getActs']) && $_GET['getActs']):
    case (isset($_GET['makePaymentStatusPdf']) && $_GET['makePaymentStatusPdf'])://<- for public payment receipt
    case (isset($_GET['init_CF_Payment']) && $_GET['init_CF_Payment']):
    case (isset($_GET['capturePayment']) && $_GET['capturePayment']):
    case (isset($_GET['getFirmEst']) && $_GET['getFirmEst'])://<- For Payment.
    case (isset($_GET['changePassword']) && $_GET['changePassword']):
      /***** Temporary to Delete ******/
    case (isset($_GET['verifyPayment']) && $_GET['verifyPayment']):
    case (isset($_GET['initiateOtherPayment']) && $_GET['initiateOtherPayment']):
      break;

    // Default
    default:
      echo \LWMIS\Common\ErrorHandler::custom($e);
      // HTTP/1.1 401 Unauthorized
      header($_SERVER['SERVER_PROTOCOL'] . " 401 Unauthorized", true, 401);
      // die();
      exit();
  }
}

$gn->addGeneralHeaders();

switch (true) {
  #region General
  case (isset($_GET['getServerTime']) && $_GET['getServerTime']):
    try {
      $dt = new \DateTime("now", new \DateTimeZone('Asia/Kolkata'));
      echo json_encode(['a' => $dt->format('Y-m-d H:i:sP')]);
    } catch (Exception $e) {
      \LWMIS\Common\ErrorHandler::custom($e);
    }
    break;

  case (isset($_GET['generateSecretKey']) && $_GET['generateSecretKey']):
    $secret_key = $gn->generateSecretKey();

//    if (isset($payload)) {
//      $payload['secret_key'] = $secret_key;
//    } else {
//      $payload = ['secret_key' => $secret_key];
//    }

    $payload = ['secret_key' => $secret_key];

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($secret_key); //[ 'secretKey' =>  ]
    break;

  case (isset($_GET['login']) && $_GET['login']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $data = new \LWMIS\Master\User();
    $response = $data->login($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($response);
    break;

  case (isset($_GET['logout']) && $_GET['logout']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    $response = $user->logout($request, $payload);

    // $newToken = $jwt->generateToken((object)$payload);
    // header("X-TOKEN: {$newToken}", true);
    header_remove("X-TOKEN");

    echo json_encode($response);
    break;

  #region
  case (isset($_GET['getUsers']) && $_GET['getUsers']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->getUsers($request));
    break;

  case (isset($_GET['getUserMenus']) && $_GET['getUserMenus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->getUserMenus($payload));
    break;

  case (isset($_GET['changeUserMobileNo']) && $_GET['changeUserMobileNo']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    $response = $user->changeMobileNo($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($response);
    break;

  case (isset($_GET['changeUsereMailID']) && $_GET['changeUsereMailID']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    $response = $user->changeeMailID($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($response);
    break;

  case (isset($_GET['getDesignations']) && $_GET['getDesignations']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $designation = new \LWMIS\Master\Designation();
    echo json_encode($designation->getDesignations($request));
    break;

  case (isset($_GET['getDesignationsRootWise']) && $_GET['getDesignationsRootWise']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $designation = new \LWMIS\Master\Designation();
    echo json_encode($designation->getDesignationsRootWise($request));
    break;

  case (isset($_GET['isUserMobileNoExist']) && $_GET['isUserMobileNoExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->isMobileNoExist($request, $payload));
    break;

  case (isset($_GET['isUserEmailExist']) && $_GET['isUserEmailExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->isEmailExist($request, $payload));
    break;

  case (isset($_GET['toggleUserStatus']) && $_GET['toggleUserStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->toggleStatus($request));
    break;

  case (isset($_GET['saveUser']) && $_GET['saveUser']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    echo json_encode($user->save($request));
    break;

  case (isset($_GET['getDistricts']) && $_GET['getDistricts']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\District();
    echo json_encode($user->getDistricts($request));
    break;

  case (isset($_GET['deleteDistrict']) && $_GET['deleteDistrict']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\District();
    echo json_encode($user->deleteDistrict($request));
    break;

  case (isset($_GET['saveDistrict']) && $_GET['saveDistrict']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\District();
    echo json_encode($user->saveDistrict($request));
    break;

  case (isset($_GET['isMasterDistrictExist']) && $_GET['isMasterDistrictExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\District();
    echo json_encode($user->isMasterDistrictExist($request));
    break;

  case (isset($_GET['getTaluks']) && $_GET['getTaluks']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Taluk();
    echo json_encode($user->getTaluks($request));
    break;

  case (isset($_GET['deleteTaluk']) && $_GET['deleteTaluk']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Taluk();
    echo json_encode($user->deleteTaluk($request));
    break;

  case (isset($_GET['saveTaluk']) && $_GET['saveTaluk']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Taluk();
    echo json_encode($user->saveTaluk($request));
    break;


  case (isset($_GET['getPanchayats']) && $_GET['getPanchayats']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Panchayat();
    echo json_encode($user->getPanchayats($request));
    break;

  case (isset($_GET['deletePanchayat']) && $_GET['deletePanchayat']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Panchayat();
    echo json_encode($user->deletePanchayat($request));
    break;

  case (isset($_GET['savePanchayat']) && $_GET['savePanchayat']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Panchayat();
    echo json_encode($user->savePanchayat($request));
    break;

  #region BankBranch Master
  case (isset($_GET['getBankBranches']) && $_GET['getBankBranches']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->getBankBranches($request));
    break;

  case (isset($_GET['getBankBranchUsingAPI']) && $_GET['getBankBranchUsingAPI']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->getBankBranchUsingAPI($request));
    break;

  case (isset($_GET['saveBank']) && $_GET['saveBank']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->saveBank($request));
    break;
  case (isset($_GET['saveBankBranch']) && $_GET['saveBankBranch']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->saveBankBranch($request));
    break;
  case (isset($_GET['deleteBankBranch']) && $_GET['deleteBankBranch']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->delete($request));
    break;
  case (isset($_GET['isBankCodeExist']) && $_GET['isBankCodeExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->isBankCodeExist($request));
    break;
  case (isset($_GET['isBankNameExist']) && $_GET['isBankNameExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->isBankNameExist($request));
    break;
  case (isset($_GET['isBankBranchCodeExist']) && $_GET['isBankBranchCodeExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->isBankBranchCodeExist($request));
    break;
  case (isset($_GET['searchBank']) && $_GET['searchBank']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->searchBank($request));
    break;
  case (isset($_GET['searchBankBranch']) && $_GET['searchBankBranch']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($bankBranch->searchBankBranch($request));
    break;

  case (isset($_GET['toggleBankBranchStatus']) && $_GET['toggleBankBranchStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $BankBranch = new \LWMIS\Master\BankBranch();
    echo json_encode($BankBranch->toggleStatus($request));
    break;
  #endregion BankBranch Master

  #region Counter Master
  case (isset($_GET['getCounters']) && $_GET['getCounters']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->getCounters($request));
    break;

  case (isset($_GET['getAvailableCounters']) && $_GET['getAvailableCounters']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->getAvailableCounters($request));
    break;

  case (isset($_GET['saveCounter']) && $_GET['saveCounter']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->save($request));
    break;

  case (isset($_GET['deleteCounter']) && $_GET['deleteCounter']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->delete($request));
    break;

  case (isset($_GET['toggleCounterStatus']) && $_GET['toggleCounterStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->toggleStatus($request));
    break;

  case (isset($_GET['assignCounter']) && $_GET['assignCounter']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->assign($request));
    break;

  case (isset($_GET['closeCounter']) && $_GET['closeCounter']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $counter = new \LWMIS\Master\Counter();
    echo json_encode($counter->close($request));
    break;
  #endregion Counter Master

  case (isset($_GET['regUser']) && $_GET['regUser']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Master\User();
    $response = $registeredUser->regUser($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);
    echo json_encode($response);
    break;

  case (isset($_GET['getActs']) && $_GET['getActs']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Master\Act();
    $response = $registeredUser->getActs($request);

    echo json_encode($response);
    break;

  case (isset($_GET['deleteAct']) && $_GET['deleteAct']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Act();
    echo json_encode($user->deleteAct($request));
    break;

  case (isset($_GET['saveAct']) && $_GET['saveAct']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Act();
    echo json_encode($user->saveAct($request));
    break;

  case (isset($_GET['isMasterActExist']) && $_GET['isMasterActExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Act();
    echo json_encode($user->isMasterActExist($request));
    break;

  case (isset($_GET['toggleActStatus']) && $_GET['toggleActStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\Act();
    echo json_encode($user->toggleStatus($request));
    break;

  case (isset($_GET['getBusinessNatures']) && $_GET['getBusinessNatures']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Master\BusinessNature();
    $response = $registeredUser->getBusinessNatures($request);

    echo json_encode($response);
    break;

  case (isset($_GET['deleteBusinessNature']) && $_GET['deleteBusinessNature']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\BusinessNature();
    echo json_encode($user->deleteBusinessNature($request));
    break;

  case (isset($_GET['saveBusinessNature']) && $_GET['saveBusinessNature']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\BusinessNature();
    echo json_encode($user->saveBusinessNature($request));
    break;

  case (isset($_GET['isMasterBusinessNatureExist']) && $_GET['isMasterBusinessNatureExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\BusinessNature();
    echo json_encode($user->isMasterBusinessNatureExist($request));
    break;

  case (isset($_GET['toggleBusinessNatureStatus']) && $_GET['toggleBusinessNatureStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\BusinessNature();
    echo json_encode($user->toggleStatus($request));
    break;

  case (isset($_GET['saveRegistrationEst']) && $_GET['saveRegistrationEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->saveRegistrationEst($request);

    echo json_encode($response);
    break;

  case (isset($_GET['saveOldFirm']) && $_GET['saveOldFirm']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->saveOldFirm($request);

    echo json_encode($response);
    break;

  case (isset($_GET['saveClaimDetails']) && $_GET['saveClaimDetails']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $claimDetails = new \LWMIS\Clm\Claims();
    $response = $claimDetails->saveClaimDetails($request);

    echo json_encode($response);
    break;

  case (isset($_GET['updateClaimStatus']) && $_GET['updateClaimStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $claimDetails = new \LWMIS\Clm\Claims();
    $response = $claimDetails->updateClaimStatus($request);

    echo json_encode($response);
    break;

  case (isset($_GET['updateFirmStatus']) && $_GET['updateFirmStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $claimDetails = new \LWMIS\Est\Firm();
    $response = $claimDetails->updateFirmStatus($request);

    echo json_encode($response);
    break;

  case (isset($_GET['deleteClaimDetails']) && $_GET['deleteClaimDetails']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $claimDetails = new \LWMIS\Clm\Claims();
    $response = $claimDetails->deleteClaimDetails($request);

    echo json_encode($response);
    break;

//  case (isset($_GET['saveFirmEst']) && $_GET['saveFirmEst']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $registeredUser = new \LWMIS\Est\Firm();
//    $response = $registeredUser->saveFirmEst($request);
//
//    echo json_encode($response);
//    break;

  case (isset($_GET['getFirmsEst']) && $_GET['getFirmsEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->getFirmsEst($request, $payload);
//    if ($request == null) {
//      $response = $registeredUser->getFirmsEst($payload);
//    } else {
//      $response = $registeredUser->getFirmsEst($request);
//    }
    echo json_encode($response);
    break;

  case (isset($_GET['deleteFirmEst']) && $_GET['deleteFirmEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->deleteFirmEst($request);

    echo json_encode($response);
    break;

  case (isset($_GET['getFirmEst']) && $_GET['getFirmEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->getFirmEst($request);

    echo json_encode($response);
    break;

  case (isset($_GET['getFirmEstNew']) && $_GET['getFirmEstNew']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $registeredUser = new \LWMIS\Est\Firm();
    $response = $registeredUser->getFirmEstNew($request, $payload);

    echo json_encode($response);
    break;

  case (isset($_GET['getClaimTable']) && $_GET['getClaimTable']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $object = new \LWMIS\Clm\Claims();
    $response = $object->getClaimTable($request);

    echo json_encode($response);
    break;

  case (isset($_GET['getClaimTables']) && $_GET['getClaimTables']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $object = new \LWMIS\Clm\Claims();
    $response = $object->getClaimTables($request);

    echo json_encode($response);
    break;

//  case (isset($_GET['receiveFirmEstSuggestion']) && $_GET['receiveFirmEstSuggestion']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $registeredUser = new \LWMIS\Est\Suggestion();
//    $response = $registeredUser->receiveFirmEstSuggestion($request);
//
//    echo json_encode($response);
//    break;

//  case (isset($_GET['receiveClaimSuggestion']) && $_GET['receiveClaimSuggestion']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $registeredUser = new \LWMIS\Clm\ClaimSuggestion();
//    $response = $registeredUser->receiveClaimSuggestion($request);
//
//    echo json_encode($response);
//    break;


//  case (isset($_GET['saveFirmAddresEst']) && $_GET['saveFirmAddresEst']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $registeredUser = new \LWMIS\Est\Firm();
//    $response = $registeredUser->saveFirmAddresEst($request);
//
//    echo json_encode($response);
//    break;

  case(isset($_GET['getClaimTitle']) && $_GET['getClaimTitle']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Clm\Claims();
    $response = $getClaims->getClaimTitle($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getTotalEstablishments']) && $_GET['getTotalEstablishments']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Firm();
    $response = $getClaims->getTotalEstablishments($request, $payload);

    echo json_encode($response);
    break;

  case(isset($_GET['getTypesDoc']) && $_GET['getTypesDoc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Type();
    $response = $getClaims->getTypes($request);

    echo json_encode($response);
    break;

  case(isset($_GET['saveTypeDoc']) && $_GET['saveTypeDoc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Type();
    $response = $getClaims->saveType($request);

    echo json_encode($response);
    break;

  case(isset($_GET['isMasterDocTypeExist']) && $_GET['isMasterDocTypeExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Type();
    $response = $getClaims->isMasterDocTypeExist($request);

    echo json_encode($response);
    break;

  case(isset($_GET['toggleDocTypeStatus']) && $_GET['toggleDocTypeStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Type();
    $response = $getClaims->toggleStatus($request);

    echo json_encode($response);
    break;

  case(isset($_GET['deleteType']) && $_GET['deleteType']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Type();
    $response = $getClaims->deleteType($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getTypesAttachments']) && $_GET['getTypesAttachments']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Attachment();
    $response = $getClaims->getTypesAttachments($request);

    echo json_encode($response);
    break;

  case(isset($_GET['clmReqDoc']) && $_GET['clmReqDoc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Attachment();
    $response = $getClaims->clmReqDoc($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getMultipleAttachments']) && $_GET['getMultipleAttachments']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Attachment();
    $response = $getClaims->getMultipleAttachments($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getSdatCerts']) && $_GET['getSdatCerts']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Clm\Sdat_Certs();
    $response = $getClaims->getSdatCerts($request);

    echo json_encode($response);
    break;

  case(isset($_GET['Upload']) && $_GET['Upload']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Common\Upload();
    $response = $getClaims->upload($request);

    echo json_encode($response);
    break;

  case(isset($_GET['savePfDetails']) && $_GET['savePfDetails']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = (new \LWMIS\Est\Firm())->savePfDetails($request);
    echo json_encode($response);
    break;


  case(isset($_GET['SaveEstClarification']) && $_GET['SaveEstClarification']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Clarification();
    $response = $getClaims->saveClarification($request);

    echo json_encode($response);
    break;

  case(isset($_GET['saveClarification']) && $_GET['saveClarification']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Master\Clarification();
    $response = $getClaims->saveClarification($request);

    echo json_encode($response);
    break;

//  case(isset($_GET['getClarifications']) && $_GET['getClarifications']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $getClaims = new \LWMIS\Master\Clarification();
//    $response = $getClaims->getClarifications($request);
//
//    echo json_encode($response);
//    break;

  case(isset($_GET['getVerifications']) && $_GET['getVerifications']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Master\Verification();
    $response = $getClaims->getVerifications($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getEstClarifications']) && $_GET['getEstClarifications']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Clarification();
    $response = $getClaims->getEstClarifications($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getEstClarification']) && $_GET['getEstClarification']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Clarification();
    $response = $getClaims->getEstClarification($request);

    echo json_encode($response);
    break;

  case(isset($_GET['deleteEstClarification']) && $_GET['deleteEstClarification']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Clarification();
    $response = $getClaims->deleteClarification($request);

    echo json_encode($response);
    break;

  case(isset($_GET['verEstClarification']) && $_GET['verEstClarification']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Clarification();
    $response = $getClaims->verClarification($request);

    echo json_encode($response);
    break;

  case(isset($_GET['forwardFirmEstSuggestion']) && $_GET['forwardFirmEstSuggestion']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Suggestion();
    $response = $getClaims->forwardFirmEst($request);

    echo json_encode($response);
    break;

//  case(isset($_GET['forwardClaimSuggestion']) && $_GET['forwardClaimSuggestion']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $getClaims = new \LWMIS\Clm\ClaimSuggestion();
//    $response = $getClaims->forwardClaimSuggestion($request);
//
//    echo json_encode($response);
//    break;


  case(isset($_GET['verify']) && $_GET['verify']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Master\Verification();
    $response = $getClaims->verify($request);

    echo json_encode($response);
    break;

  case(isset($_GET['backwardFirmEstSuggestion']) && $_GET['backwardFirmEstSuggestion']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Suggestion();
    $response = $getClaims->backwardFirmEst($request);

    echo json_encode($response);
    break;

  case(isset($_GET['backwardClaim']) && $_GET['backwardClaim']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Clm\ClaimSuggestion();
    $response = $getClaims->backwardClaim($request);

    echo json_encode($response);
    break;

//  case(isset($_GET['firmRejectEst']) && $_GET['firmRejectEst']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $getClaims = new \LWMIS\Est\Firm();
//    $response = $getClaims->firmReject($request);
//
//    echo json_encode($response);
//    break;

  case(isset($_GET['firmActiveEst']) && $_GET['firmActiveEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Firm();
    $response = $getClaims->firmActiveEst($request);

    echo json_encode($response);
    break;

  case(isset($_GET['addTnebBillEst']) && $_GET['addTnebBillEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\TnebBill();
    $response = $getClaims->saveTneb_bill($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getEstTnebBills']) && $_GET['getEstTnebBills']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\TnebBill();
    $response = $getClaims->getEstTnebBills($request);

    echo json_encode($response);
    break;

  case(isset($_GET['deleteEstTnebBill']) && $_GET['deleteEstTnebBill']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $tnebBill = new \LWMIS\Est\TnebBill();
    $response = $tnebBill->deleteEstTnebBill($request);

    echo json_encode($response);
    break;

  case(isset($_GET['deleteAttachmentDoc']) && $_GET['deleteAttachmentDoc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Doc\Attachment();
    $response = $getClaims->deleteAttachment($request);

    echo json_encode($response);
    break;

  case(isset($_GET['saveFirmAttachment']) && $_GET['saveFirmAttachment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Firm();
    $response = $getClaims->saveFirmAttachment($request);

    echo json_encode($response);
    break;

  case (isset($_GET['saveClaimAttachments']) && $_GET['saveClaimAttachments']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getAttachment = new \LWMIS\Clm\Claims();
    $response = $getAttachment->saveClaimAttachments($request);

    echo json_encode($response);
    break;

  case (isset($_GET['saveAttachment']) && $_GET['saveAttachment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = (new \LWMIS\Doc\Attachment())->saveAttachment($request);

    echo json_encode($response);
    break;


  case(isset($_GET['getArrearsToBePaid']) && $_GET['getArrearsToBePaid']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Employee_history();
    $response = $getClaims->getArrearsToBePaid($request);

    echo json_encode($response);
    break;

  case(isset($_GET['getPaymentHistory']) && $_GET['getPaymentHistory']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Employee_history();
    $response = $getClaims->getPaymentHistory($request);

    echo json_encode($response);
    break;

  case(isset($_GET['SaveEmployeeHistoryEst']) && $_GET['SaveEmployeeHistoryEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Employee_history();
    $response = $getClaims->SaveEmployeeHistory($request);
    echo json_encode($response);
    break;

  case(isset($_GET['makePayment']) && $_GET['makePayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Est\Payment();
    $response = $getClaims->makePayment($request);
    echo json_encode($response);
    break;

  case (isset($_GET['payoutAPI']) && $_GET['payoutAPI']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Common\SBIePay_Gateway();
    echo json_encode($payment->transactionPayoutAPI($request));
    break;

  case (isset($_GET['transactionMIS_API']) && $_GET['transactionMIS_API']):
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata);

    $payment = new \LWMIS\Common\SBIePay_Gateway();
    echo json_encode($payment->transactionMIS_API($request));
    break;

  case (isset($_GET['doubleVerifyPayment']) && $_GET['doubleVerifyPayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->doubleVerifyPayment($request));
    break;

  case (isset($_GET['getEducations']) && $_GET['getEducations']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $edu = new \LWMIS\Clm\Claims();
    $response = $edu->getEducations($request);
    echo json_encode($response);
    break;

  case (isset($_GET['init_CF_Payment']) && $_GET['init_CF_Payment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->init_CF_Payment($request));
    break;

  case (isset($_GET['initiateOtherPayment']) && $_GET['initiateOtherPayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->initiateOtherPayment($request));
    break;

  case (isset($_GET['getOtherPaymentCategories']) && $_GET['getOtherPaymentCategories']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Master\PaymentCategory();
    echo json_encode($payment->getOtherPaymentCategories($request));
    break;

  case (isset($_GET['capturePayment']) && $_GET['capturePayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->capture($request));
    break;

  case (isset($_GET['verifyPayment']) && $_GET['verifyPayment']):
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->verifyPayment($request));
    break;
  #endregion Payment


  case (isset($_GET['cancelledPayment']) && $_GET['cancelledPayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->cancelledPayment($request));
    break;

  case (isset($_GET['getPaymentsEst']) && $_GET['getPaymentsEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $payment = new \LWMIS\Est\Payment();
    echo json_encode($payment->getPaymentsEst($request));
    break;

  case (isset($_GET['makePaymentStatusPdf']) && $_GET['makePaymentStatusPdf']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $gd_olp_receipt = new \LWMIS\GenerateDocument\PaymentReceipt();
    $gd_olp_receipt->Generate($request);
    break;

  case (isset($_GET['ClaimDocumentPDF']) && $_GET['ClaimDocumentPDF']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $clm_doc = new \LWMIS\GenerateDocument\ClaimDeclaration();
    $clm_doc->Generate($request);
    break;

  case (isset($_GET['ClaimDisbursementGeneration']) && $_GET['ClaimDisbursementGeneration']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

//    $clm_doc = new \LWMIS\GenerateDocument\ClaimDisbursementGeneration($request);
//    $clm_doc->Generate();

    $clm_doc = new \LWMIS\GenerateDocument\ClaimDisbursement();
    $clm_doc->Generate($request);
    break;

  #getUploadedPdf
  case (isset($_GET['getUploadedPdf']) && $_GET['getUploadedPdf']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    if (isset($request->storage_name)) {
      $storage_name = $request->storage_name;
      if (file_exists($storage_name)) {
        $filelength = filesize($storage_name);
        header('Content-Type: application/pdf', true);
        header('Content-Disposition: inline; filename="file.pdf"');
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) or empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
          // the content length may vary if the server is using compression
          header('Content-Length: ' . $filelength);
        }
        readfile($storage_name);
        exit;
      } else {
        echo "FILE NOT FOUND..!";
      }
    }
    // For unsuccessful cases
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");
    break;

//  case (isset($_GET['getReceiptDetail']) && $_GET['getReceiptDetail']):
//    $postData = file_get_contents("php://input");
//    $request = json_decode($postData);
//
//    $gd_olp_receipt = new \LWMIS\Est\Receipt;
//    echo json_encode($gd_olp_receipt->getReceiptDetail($request));
//    break;

  case (isset($_GET['getGenders']) && $_GET['getGenders']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $getClaims = new \LWMIS\Clm\Genders();
    $response = $getClaims->getGenders($request);

    echo json_encode($response);
    break;

  case (isset($_GET['getQBSubjects']) && $_GET['getQBSubjects']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $obj = new \LWMIS\Clm\QuestionBankSubjects();
    $response = $obj->getQBSubjects($request);

    echo json_encode($response);
    break;

  case (isset($_GET['saveQBSubjects']) && $_GET['saveQBSubjects']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $obj = new \LWMIS\Clm\QuestionBankSubjects();
    $response = $obj->saveQBSubjects($request);

    echo json_encode($response);
    break;

  case (isset($_GET['UpdatePaymentReceipt']) && $_GET['UpdatePaymentReceipt']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $gd_olp_receipt = new \LWMIS\Est\Receipt;
    $response = $gd_olp_receipt->UpdatePayment($request);
    echo json_encode($response);
    break;

  case (isset($_GET['saveDdChqReceiptEst']) && $_GET['saveDdChqReceiptEst']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $receipt = new \LWMIS\Est\Receipt;
    echo json_encode($receipt->saveDdChq($request));
    break;

  case (isset($_GET['saveAlreadyPaid']) && $_GET['saveAlreadyPaid']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $pay = new \LWMIS\Est\Payment();
    echo json_encode(value: $pay->saveAlreadyPaid($request));
    break;

  #region Scheme
  case (isset($_GET['deleteScheme']) && $_GET['deleteScheme']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Scheme();
    echo json_encode($response->deleteScheme($request));
    break;

  case (isset($_GET['saveScheme']) && $_GET['saveScheme']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Scheme();
    echo json_encode($response->saveScheme($request));
    break;

  case (isset($_GET['isMasterSchemeExist']) && $_GET['isMasterSchemeExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Scheme();
    echo json_encode($response->isMasterSchemeExist($request));
    break;

  case (isset($_GET['getSchemes']) && $_GET['getSchemes']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Scheme();
    echo json_encode($response->getSchemes($request));
    break;

  case (isset($_GET['toggleSchemeStatus']) && $_GET['toggleSchemeStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Scheme();
    echo json_encode($response->toggleStatus($request));
    break;
  #endregion

  #region contribution
  case (isset($_GET['getContributions']) && $_GET['getContributions']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Contribution;
    echo json_encode($response->getContributions($request));
    break;

  case (isset($_GET['toggleContributionStatus']) && $_GET['toggleContributionStatus']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Contribution;
    echo json_encode($response->toggleStatus($request));
    break;

  case (isset($_GET['saveContribution']) && $_GET['saveContribution']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Contribution;
    echo json_encode($response->save($request));
    break;

  case (isset($_GET['isMasterContributionExist']) && $_GET['isMasterContributionExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Contribution;
    echo json_encode($response->isMasterContributionExist($request));
    break;

  case (isset($_GET['deleteContribution']) && $_GET['deleteContribution']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\Contribution;
    echo json_encode($response->delete($request));
    break;
  #endregion

  #region edu_district

  case (isset($_GET['getEdu_Districts']) && $_GET['getEdu_Districts']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Edu_District();
    echo json_encode($response->getEdu_Districts($request));
    break;

  case (isset($_GET['saveEdu_District']) && $_GET['saveEdu_District']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Edu_District;
    echo json_encode($response->save($request));
    break;

  case (isset($_GET['isMasterEdu_DistrictExist']) && $_GET['isMasterEdu_DistrictExist']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Edu_District;
    echo json_encode($response->isMasterEdu_DistrictExist($request));
    break;

  case (isset($_GET['deleteEdu_District']) && $_GET['deleteEdu_District']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Edu_District;
    echo json_encode($response->delete($request));
    break;

  #endregion
  case (isset($_GET['getTotalClaims']) && $_GET['getTotalClaims']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Claims;
    echo json_encode($response->getTotalClaims($request, $payload));
    break;

  case (isset($_GET['saveUpa']) && $_GET['saveUpa']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\UPA\Upa;
    echo json_encode($response->saveUpa($request));
    break;

  case (isset($_GET['getUpas']) && $_GET['getUpas']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\UPA\Upa;
    echo json_encode($response->getUpas($request));
    break;

  case (isset($_GET['getPaymentCount']) && $_GET['getPaymentCount']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Est\Payment();
    echo json_encode($response->getPaymentCount());
    break;

  case (isset($_GET['getPaymentList']) && $_GET['getPaymentList']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Est\Payment();
    echo json_encode($response->getPaymentList($request));
    break;

  case (isset($_GET['getPaymentListByPaidOnYear']) && $_GET['getPaymentListByPaidOnYear']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Est\Payment();
    echo json_encode($response->getPaymentListByPaidOnYear());
    break;

  case (isset($_GET['getPaymentListByPaidForYears']) && $_GET['getPaymentListByPaidForYears']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Est\Payment();
    echo json_encode($response->getPaymentListByPaidForYears($request));
    break;

  case (isset($_GET['updateQBSubjects']) && $_GET['updateQBSubjects']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\QuestionBankSubjects();
    echo json_encode($response->updateQBSubjects($request));
    break;

  case (isset($_GET['getRelationships']) && $_GET['getRelationships']):
//    note: getRelationships is differed from the get relationship
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $relations = new \LWMIS\Clm\Relationships();
    $response = $relations->getRelationships($request);

    echo json_encode($response);
    break;

  case (isset($_GET['getRelationship']) && $_GET['getRelationship']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Relationships();
    echo json_encode($response->getRelationship($request));
    break;

  case (isset($_GET['deleteRelationship']) && $_GET['deleteRelationship']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Relationships();
    echo json_encode($response->deleteRelationship($request));
    break;

  case (isset($_GET['saveRelationship']) && $_GET['saveRelationship']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Relationships();
    echo json_encode($response->saveRelationship($request));
    break;

  case (isset($_GET['saveUpaDoc']) && $_GET['saveUpaDoc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\UPA\Upa();
    echo json_encode($response->saveUpaDoc($request));
    break;

  case (isset($_GET['saveLwbBankAcc']) && $_GET['saveLwbBankAcc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\LwbBankAcc();
    echo json_encode($response->saveLwbBankAcc($request));
    break;

  case (isset($_GET['getLwbBankAcc']) && $_GET['getLwbBankAcc']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Master\LwbBankAcc();
    echo json_encode($response->getLwbBankAcc($request));
    break;

  case (isset($_GET['saveDisbursements']) && $_GET['saveDisbursements']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Disbursements();
    echo json_encode($response->saveDisbursements($request));
    break;

  case (isset($_GET['getBankDisbursalHistory']) && $_GET['getBankDisbursalHistory']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Disbursements();
    echo json_encode($response->getBankDisbursalHistory($request));
    break;

  case (isset($_GET['getClaimDisbursalHistory']) && $_GET['getClaimDisbursalHistory']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Disbursements();
    echo json_encode($response->getClaimDisbursalHistory($request));
    break;

  case (isset($_GET['saveSdatCertDetails']) && $_GET['saveSdatCertDetails']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Sdat_Certs();
    echo json_encode($response->saveSdatCertDetails($request));
    break;

  case (isset($_GET['deleteSdatCerts']) && $_GET['deleteSdatCerts']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Sdat_Certs();
    echo json_encode($response->deleteSdatCerts($request));
    break;

  case (isset($_GET['checkUserAndGenerateOTP']) && $_GET['checkUserAndGenerateOTP']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    $response = $user->checkAndGenerateOTP($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($response);
    break;

  case (isset($_GET['resendUserOTP']) && $_GET['resendUserOTP']):
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata);

    $user = new \LWMIS\Master\User();
    $response = $user->resendOTP($request, $payload);

    $newToken = $jwt->generateToken((object)$payload);
    header("X-TOKEN: {$newToken}", true);

    echo json_encode($response);
    break;

  case (isset($_GET['validateUserOTP']) && $_GET['validateUserOTP']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);
    $encryption = new \LWMIS\Common\Encryption();

//    $otp = $encryption->decrypt($request->otp, $payload['secret_key']);
    $otp = $encryption->decrypt($request->otp, ENC_KEY);

    $user = new \LWMIS\Master\User();
    if (isset($request->otp) && isset($payload['otp']) && $otp == $payload['otp']) {
      $payload['is_otp_validated'] = true;
      $user->deleteOTPGeneratedOn($request, $payload);
      unset($payload['otp']);

      $newToken = $jwt->generateToken((object)$payload);
      header("X-TOKEN: {$newToken}", true);

      $response = ['message' => 'OTP Validated.'];
    } else {
      $response = $user->checkOTPFailureAttempts($request, $payload);
    }
    $response['message'] = $encryption->encrypt($response['message'], ENC_KEY);
    echo json_encode($response);
    break;

  case (isset($_GET['saveUserPassword']) && $_GET['saveUserPassword']):
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata);

    $user = new \LWMIS\Master\User();
    $response = $user->savePassword($request, $payload);
    $newToken = $jwt->generateToken((object)$payload);

    header("X-TOKEN: {$newToken}", true);
    echo json_encode($response);
    break;

  case (isset($_GET['getWinningPositions']) && $_GET['getWinningPositions']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Claims();
    echo json_encode($response->getWinningPositions($request));
    break;

  case (isset($_GET['getMarkLists']) && $_GET['getMarkLists']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\MarkLists();
    echo json_encode($response->getMarkLists($request));
    break;

  case (isset($_GET['changeAllowedSchemesClaimGender']) && $_GET['changeAllowedSchemesClaimGender']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $response = new \LWMIS\Clm\Genders();
    echo json_encode($response->changeAllowedSchemesClaimGender($request));
    break;

  case (isset($_GET['changePassword']) && $_GET['changePassword']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $user = new \LWMIS\Master\User();
    $response = $user->changePassword($request, $payload);
    $newToken = $jwt->generateToken((object)$payload);

    header("X-TOKEN: {$newToken}", true);
    echo json_encode($response);
    break;

  case (isset($_GET['sendPaymentRemainders']) && $_GET['sendPaymentRemainders']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bulkSMS = new \LWMIS\Common\SMS_Message();
    echo json_encode($bulkSMS->sendPaymentRemainders($request));
    break;

  case (isset($_GET['getYearWisePayment']) && $_GET['getYearWisePayment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bulkSMS = new \LWMIS\Est\Employee_history();
    echo json_encode($bulkSMS->getYearWisePayment($request));
    break;

  case (isset($_GET['getYearWisePaymentForOtherPayments']) && $_GET['getYearWisePaymentForOtherPayments']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $bulkSMS = new \LWMIS\Est\Employee_history();
    echo json_encode($bulkSMS->getYearWisePaymentForOtherPayments($request));
    break;

  case (isset($_GET['is_pending_payment']) && $_GET['is_pending_payment']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $pmt = new \LWMIS\Est\Payment();
    echo json_encode($pmt->is_pending_payment($request));
    break;

  case (isset($_GET['getPermanentEmployeeCounts']) && $_GET['getPermanentEmployeeCounts']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $pmt = new \LWMIS\Est\Firm();
    echo json_encode($pmt->getPermanentEmployeeCounts($request));
    break;

  case (isset($_GET['getUAN_Details']) && $_GET['getUAN_Details']):
    $postData = file_get_contents("php://input");
    $request = json_decode($postData);

    $pmt = new \LWMIS\Est\Firm();
    echo json_encode($pmt->getUAN_Details($request));
    break;


  default:
    header($_SERVER['SERVER_PROTOCOL'] . "404 Not Found", false, 404); // HTTP/1.1 404 Not Found
    die();

}
