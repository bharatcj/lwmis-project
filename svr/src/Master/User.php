<?php

namespace LWMIS\Master;

use Exception;
use LWMIS\Common\Encryption;
use LWMIS\Common\ErrorHandler;
use Throwable;
use function is_null;

class User
{
  /**
   * @throws /Exception
   */

  function login($data, &$payload): string
  {
    $retObj = [];
    $db = new \LWMIS\Common\PostgreDB();
    $gn = new \LWMIS\Common\GeneralFunctions();
    $encryption = new Encryption();
    try {
      $db->Begin();

      if (!(isset($payload) && isset($payload['secret_key']))) {
        throw new Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];
      $email = $data->email ?? null;
      $password = $data->pwd ?? null;
      $pwd = $encryption->decrypt($password, $secret_key);
//      $pwd = password_hash($pwd, PASSWORD_BCRYPT);//hashing to verify
      $email = $encryption->decrypt($email, $secret_key);
      $db = new \LWMIS\Common\PostgreDB();

      $params = [];
      $params[] = $email;

      $sql = 'SELECT id, pwd, COALESCE(lockout_on, \'1978-02-28\'::TIMESTAMPTZ) + INTERVAL \'10 MINUTES\' > CURRENT_TIMESTAMP AS is_lockedout,
                    EXTRACT(MINUTES FROM lockout_on + INTERVAL \'11 MINUTES\' - CURRENT_TIMESTAMP) AS lockout_time,
                    COALESCE(failure_attempt, 0) AS failure_attempt, designation_code
                FROM mas.users
               WHERE is_active = true AND (mobile_no = $1 OR email = $1);';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
//      var_dump($rows);
      if (count($rows) <= 0) {
        throw new Exception('Invalid Login Credentials');
      }

      foreach ($rows as &$r) {
        $r['id'] = \intval($r['id']);
        $r['is_lockedout'] = ($r['is_lockedout'] == 't');
        $r['lockout_time'] = isset($r['lockout_time']) ? \intval($r['lockout_time']) : 0;
        $r['failure_attempt'] = isset($r['failure_attempt']) ? \intval($r['failure_attempt']) : 0;
      }
      $ret_data = $rows[0];

      if ($ret_data['is_lockedout']) {
        $retObj['lockout_time'] = $ret_data['lockout_time'];
        throw new Exception('Lockout');
      }
      $user_id = $ret_data['id'];
      $designation_code = $ret_data['designation_code'];
      if (password_verify($pwd, $ret_data['pwd'])) {
        // Get details before updating last login
        $users = $this->getUsers((object)['id' => $user_id, 'include_system_user' => true, 'designation_code' => $designation_code], $payload);
        $retObj = (isset($users) && count($users['rows']) > 0) ? ($users['rows'][0]) : [];
        // Update session token
        $session_token = $gn->generateSecretKey();
        $sql = 'UPDATE mas.users
                   SET last_login = CURRENT_TIMESTAMP,
                       session_token = $2,
                       failure_attempt = 0,
                       lockout_on = NULL
                 WHERE id = $1';
        $db->Query($sql, [$user_id, $session_token]);

        // Log User Action
        $userAction = new \LWMIS\LOG\UserAction();
        $userAction->save($db, (object)[
          'user_id' => $user_id,
          'action_code' => 'U_LOGIN'
        ]);

        // send token to client and for future session verifications ($payload already exist)
        $payload['id'] = $user_id;
        $payload['session_token'] = $session_token;
//        $payload['user_type'] = 'LWMIS_official';
//        if (isset($retObj['ward_no'])) $payload['ward_no'] = $retObj['ward_no'];
//        if (isset($retObj['zone_no']) && !isset($retObj['ward_no'])) $payload['zone_no'] = $retObj['zone_no'];
//        if (isset($retObj['se_id']) && !isset($retObj['ward_no']) && !isset($retObj['zone_no'])) $payload['se_id'] = $retObj['se_id'];

        // remove secret_key from $payload
        unset($payload['secret_key']);

        $retObj['message'] = "User authenticated";
      } else {
        $failure_attempt = $ret_data['failure_attempt'];
        if ($failure_attempt < 4) {
          $sql = 'UPDATE mas.users
                     SET failure_attempt = COALESCE(failure_attempt, 0) + 1
                   WHERE id = $1
               RETURNING failure_attempt';
          $db->Query($sql, [$user_id]);
          $f_rows = $db->FetchAll();
          foreach ($f_rows as &$r) {
            $r['failure_attempt'] = intval($r['failure_attempt']);
          }

          if (count($f_rows) > 0) {
            $db_failure_attempt = $f_rows[0]['failure_attempt'];

            // Log User Action
            $userAction = new \LWMIS\LOG\UserAction();
            $userAction->save($db, (object)[
              'user_id' => $user_id,
              'action_code' => 'U_NLOGIN',
              'note' => ('Failure attempt: ' . $db_failure_attempt)
            ]);
          }
          $retObj['message'] = "Invalid Login Credentials. Attempt " . ($failure_attempt + 1) . "/5.";
        } else {
          $sql = 'UPDATE mas.users
                     SET failure_attempt = COALESCE(failure_attempt, 0) + 1,
                         lockout_on = CURRENT_TIMESTAMP
                   WHERE id = $1';
          $db->Query($sql, [$user_id]);
          $retObj['lockout_time'] = 10;
          $retObj['message'] = 'Lockout';

          // Log User Action
          $userAction = new \LWMIS\LOG\UserAction();
          $userAction->save($db, (object)[
            'user_id' => $user_id,
            'action_code' => 'U_NLOGIN',
            'note' => ('Failure attempt: maximum reached. Lockout initiated.')
          ]);
        }
      }

      // Finished
      $db->Commit();
    } catch (Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();
    return $encryption->encrypt(json_encode($retObj), $secret_key);
  }

  function getUsers($filter): array
  {
    $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
    $limit = $filter->limit ?? null;
    $offset = $limit * ($filter->offset ?? 0);

    $where_clause = "";
    $params = [];
    $params[] = $limit;
    $params[] = $offset;

    if (isset($filter->id) && $filter->id > 0) {
      $id = $filter->id;
      $params[] = $id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if (isset($filter->designation_code) && $filter->designation_code) {
      $designation_code = $filter->designation_code;
      $params[] = $designation_code;
      $where_clause .= ' AND a.designation_code = $' . count($params);
    }

//    else {//<- this will make the getFirmsEst to not work
//      $designation_code = "APPS";
//      $params[] = $designation_code;
//      $where_clause .= ' AND a.designation_code != $' . count($params);
//    }

    if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

      $search_text = '%' . $filter->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (
                              UPPER(a.name) like UPPER(' . $param_cnt . ') OR
                              UPPER(a.mobile_no) like UPPER(' . $param_cnt . ') OR
                              UPPER(COALESCE(a.email, \'\')) like UPPER(' . $param_cnt . ') OR
                              UPPER(b.name) like UPPER(' . $param_cnt . ')
                             )';
    }

    if (!(isset($filter->include_inactive) && $filter->include_inactive === true)) {
      $where_clause .= ' AND a.is_active = true';
    }

    if (!(isset($filter->include_system_user) && $filter->include_system_user === true)) {
      $where_clause .= ' AND a.is_system = false';
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      // get actual data
      $sql = 'SELECT a.id, a.name, a.mobile_no, a.email, a.is_active, a.last_login,
                     a.designation_code, b.name AS designation_name
                FROM mas.users AS a
                     INNER JOIN mas.designations AS b ON (b.code = a.designation_code)
               WHERE true ' . $where_clause . '
               ORDER BY b.name, a.name
               LIMIT $1 OFFSET $2';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['is_active'] = ($r['is_active'] == 't');
      }
      $retObj['rows'] = $rows;

      // get total rows
      if (!is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                  FROM mas.users AS a
                      INNER JOIN mas.designations AS b ON (b.code = a.designation_code)
                WHERE true' . $where_clause;
        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();
        foreach ($tot_rows as &$r) {
          $r['cnt'] = intval($r['cnt']);
        }

        $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
      } else {
        $retObj['tot_rows'] = ((!is_null($offset)) ? $offset : 0) + \count($rows);
      }
    } catch (Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }
    $db->DBClose();
    // var_dump($retObj);
    return $retObj;
  }

  function save($data)
  {
    $retVal = ['message' => 'User cannot be saved.'];
    $email = (isset($data->email) && trim($data->email) != '') ? $data->email : null;
    $name = isset($data->name) ? $data->name : null;
    $designation_code = isset($data->designation_code) ? $data->designation_code : null;
    $mobile_no = isset($data->mobile_no) ? $data->mobile_no : null;
    $id = isset($data->id) ? $data->id : null;

    $params = array();
    $params[] = $email;
    $params[] = $name;
    $params[] = $designation_code;
    $params[] = $mobile_no;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (is_null($id)) {
//        $pwd = isset($data->pwd) ? $data->pwd : '12345678';
        $pwd = $data->pwd ?? null;
        $pwd = password_hash($pwd, PASSWORD_BCRYPT);
        $params[] = &$pwd;
        $sql = 'INSERT INTO mas.users (email, name, designation_code, mobile_no, pwd)
                VALUES ($1, $2, $3, $4, $5 )
                RETURNING id';
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
        }
        if (count($rows) > 0) {
          $retVal['id'] = $rows[0]['id'];
          $retVal['message'] = "User saved successfully.";
        }
      } else {
        $params[] = $id;
        $sql = 'UPDATE mas.users
             SET email = $1, name = $2, designation_code = $3, mobile_no = $4
           WHERE id = $5';
        $db->Query($sql, $params);
        $retVal['message'] = "User saved successfully.";
      }
    } catch (Exception $e) {
      $retVal['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function logout($data, &$payload): array
  {
    $retObj = [];
    $db = new \LWMIS\Common\PostgreDB();
    $encryption = new Encryption();
    try {
      $db->Begin();

      if (!(isset($payload) && isset($payload['secret_key']))) {
        throw new Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];

      // Key(encrypted) fields
      $session_id = $data->session_id ?? null;
      $id = (!is_null($session_id)) ? intval($encryption->decrypt($session_id, $secret_key)) : null;

      if (!is_null($id) && $id > 0) {
        $sql = 'UPDATE mas.users
                   SET last_login = NULL,
                       session_token = NULL
                 WHERE id = $1';
        $db->Query($sql, [$id]);

        // Log User Action
        $userAction = new \LWMIS\LOG\UserAction();
        $userAction->save($db, (object)[
          'user_id' => $id,
          'action_code' => 'U_LOGOUT'
        ]);

        // Remove secret_key from $payload
        unset($payload['secret_key']);

        if (isset($payload['user_type'])) {
          unset($payload['user_type']);
        }

        if (isset($payload['id'])) {
          unset($payload['id']);
        }

        $retObj['message'] = "User logout successful.";
      }

      // Finished
      $db->Commit();
    } catch (Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  /**
   * @throws Exception
   */
  function checkUserSessionToken($data)
  {
    $retObj = ['error_message' => null];
    $id = isset($data->id) ? $data->id : null;
    $session_token = isset($data->session_token) ? $data->session_token : null;
    $params = [];
    $params[] = $id;
    $params[] = $session_token;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (is_null($id) or is_null($session_token)) {
        $retObj['error_message'] = 'Token is not valid / Expired.';
      } else {
        $sql = 'SELECT a.id, a.designation_code
                  FROM mas.users AS a
                       INNER JOIN mas.designations AS b ON (b.code = a.designation_code)
                 WHERE a.id = $1 and COALESCE(a.session_token, \'\') = $2';
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        foreach ($rows as &$r) {
          $r['id'] = intval($r['id']);
          // $r['is_restrict_by_static_ip'] = ($r['is_restrict_by_static_ip'] == 't');
        }

        if (count($rows) <= 0) {
          $retObj['error_message'] = 'Your current session terminated, since you created new session with new login.';
        } else {
          $ud = $rows[0];
          // if ($ud['is_restrict_by_static_ip'] == true) {
          //   $gn = new \LWMIS\Common\GeneralFunctions();
          //   $client_ip = $gn->getIPAddress();

          //   $where_clause = '';
          //   $params = [];
          //   $params[] = $client_ip;

          //   if (isset($ud['zone_no'])) {
          //     $zone_no = $ud['zone_no'];
          //     $params[] = $zone_no;
          //     $where_clause .= ' AND a.zone_no = $' . count($params);
          //   } else {
          //     $where_clause .= ' AND a.zone_no IS NULL';
          //   }

          //   if (isset($ud['ward_no'])) {
          //     $ward_no = $ud['ward_no'];
          //     $params[] = $ward_no;
          //     $where_clause .= ' AND a.ward_no = $' . count($params);
          //   } else {
          //     $where_clause .= ' AND a.ward_no IS NULL';
          //   }

          //   $sql = 'SELECT 1 FROM mas.static_ips AS a WHERE a.ip = $1::INET' . $where_clause;
          //   $db->Query($sql, $params);
          //   $rows = $db->FetchAll();

          //   if (count($rows) <= 0) {
          //     $retObj['error_message'] = 'Access from Static IP (' . $client_ip . ') is not allowed.' . PHP_EOL . 'Contact your SAO / Technical Team for assistance.';
          //   }
          // }
        }
      }
    } catch (Throwable $th) {
      $retObj['error_message'] = $th->getMessage();
    }
    $db->DBClose();

    if (!is_null($retObj['error_message'])) {
      throw new Exception($retObj['error_message']);
    }

    return;
  }

  function changeMobileNo($data, &$payload): array
  {
    $retObj = ['message' => 'Invalid User / Mobile No. Unavailable.'];

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!(isset($payload) && isset($payload['id']))) {
        throw new \Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];
      $id = isset($payload['id']) ? $payload['id'] : null;

      // Key(encrypted) fields
      $mobile_no = isset($data->mobile_no) ? $data->mobile_no : null;
      // $id = isset($data->id)?$data->id:null;

      // decrypt
      $encryption = new Encryption();
      if (!is_null($mobile_no)) $mobile_no = $encryption->decrypt($mobile_no, $secret_key);
      // if (!is_null($id)) $id = $encryption->decrypt($id, $secret_key);

      if (is_null($id) || is_null($mobile_no)) {
        throw new \Exception('Insufficient data. User cannot be saved.');
      }

      // Other fields
      $old_mobile_no = isset($data->old_mobile_no) ? $data->old_mobile_no : null;

      $sql = 'UPDATE mas.users SET mobile_no = $1 WHERE id = $2';
      $db->Query($sql, [$mobile_no, $id]);

      // Log User Action
      if (!is_null($old_mobile_no)) {
        $userAction = new \LWMIS\LOG\UserAction();
        $userAction->save($db, (object)[
          'user_id' => $id,
          'action_code' => 'U_CNG_MNO',
          'note' => ('Old Mobile No.: ' . $old_mobile_no)
        ]);
      }

      // Remove secret_key from $payload
      unset($payload['secret_key']);

      // Finished
      $db->Commit();

      $retObj['message'] = 'Mobile No. changed successfully.';
    } catch (\Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  function changeeMailID($data, &$payload): array
  {
    $retObj = ['message' => 'Invalid User / eMail ID Unavailable.'];

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!(isset($payload['id']))) {
        throw new \Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];
      $id = isset($payload['id']) ? $payload['id'] : null;

      // Key(encrypted) fields
      $email = $data->email ?? null;
      // $id = isset($data->id)?$data->id:null;

      // decrypt
      $encryption = new Encryption();
      if (!is_null($email)) $email = $encryption->decrypt($email, $secret_key);
      // if (!is_null($id)) $id = $encryption->decrypt($id, $secret_key);

      if (is_null($id)) {
        throw new \Exception('Insufficient data. User cannot be saved.');
      }

      // Other fields
      $old_email = isset($data->old_email) ? $data->old_email : null;

      $sql = 'UPDATE mas.users
                 SET email = $1
               WHERE id = $2';
      $db->Query($sql, [$email, $id]);

      // Log User Action
      if (!is_null($old_email)) {
        $userAction = new \LWMIS\LOG\UserAction();
        $userAction->save($db, (object)[
          'user_id' => $id,
          'action_code' => 'U_CNG_EID',
          'note' => (!is_null($old_email) ? ('Old eMail ID: ' . $old_email) : null)
        ]);
      }

      // Remove secret_key from $payload
      unset($payload['secret_key']);

      // Finished
      $db->Commit();

      $retObj['message'] = 'eMail ID changed successfully.';
    } catch (\Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  function isMobileNoExist($data, &$payload)
  {
    $mobile_no = isset($data->mobile_no) ? $data->mobile_no : null;
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();
    $params[] = $mobile_no;

    if (!is_null($id)) {
      $params[] = $id;
      $where_clause = 'AND id != $' . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT mobile_no FROM mas.users WHERE is_active = true AND mobile_no = $1 ' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function isEmailExist($data, &$payload)
  {
    $email = isset($data->email) ? $data->email : null;
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();
    $params[] = $email;

    if (!is_null($id)) {
      $params[] = $id;
      $where_clause = 'AND id != $' . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT email FROM mas.users WHERE is_active = true AND email IS NOT NULL AND email = $1 ' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function toggleStatus($data)
  {
    $ret_val = ['message' => 'User status cannot be changed.'];
    $id = isset($data->id) ? $data->id : null;
    $is_active = isset($data->is_active) ? $data->is_active : false;
    $is_active = $is_active ? 't' : 'f';

    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id)) {
      try {
        $sql = "UPDATE mas.users SET is_active = $1 WHERE id = $2";
        $db->query($sql, [$is_active, $id]);
        $ret_val['message'] = 'User status changed successfully.';
      } catch (Exception $e) {
        $ret_val['message'] = $e->getMessage();
      }
    }
    $db->DBClose();
    return $ret_val;
  }

  /**
   * @throws Exception
   */
  function getUserMenus($payload): array
  {
    $rows = [];
    $id = (isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null;
    //$id = isset($filter->id) ? $filter->id : null;
    if (!is_null($id)) {
      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'WITH RECURSIVE a AS (
        SELECT r.code, r.name, r.ref_screen_code, r.router_link, TO_JSONB(r.related_router_links) AS related_router_links, r.order_num, r.icon_name
          FROM mas.screens AS r
            INNER JOIN mas.designation_screens AS dr ON (dr.screen_code  = r.code)
            INNER JOIN mas.users AS u ON (u.designation_code = dr.designation_code)
        WHERE u.id = $1
        UNION
        SELECT r.code, r.name, r.ref_screen_code, r.router_link, TO_JSONB(r.related_router_links) AS related_router_links, r.order_num, r.icon_name
          FROM mas.screens AS r
              INNER JOIN a ON (a.ref_screen_code = r.code)
      )
      SELECT * FROM a ORDER BY order_num ASC;';
//      var_dump($sql);
//      var_dump($id);
      $db->Query($sql, [$id]);
      $rows = $db->FetchAll();
      $db->DBClose();

      foreach ($rows as &$r) {
        $r['order_num'] = intval($r['order_num']);
        $r['related_router_links'] = isset($r['related_router_links']) ? json_decode($r['related_router_links']) : null;
      }
    }

    return $rows;
  }

  function regUser($data, $payload): array
  {
    $retObj = ['message' => 'Invalid customer details.'];

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();
      if (!(isset($payload) && isset($payload['secret_key']))) {
        throw new Exception('Token is not valid / Expired.');
      }

//      $secret_key = 'user'; //$payload['secret_key'];
      $secret_key = $payload['secret_key'];

      // Key(encrypted) fields
      $mobile_no = $data->mobile_no ?? null;
      $email = (isset($data->email) && strlen(trim($data->email)) > 0) ? $data->email : null;
      //      var_dump(strlen(trim($data->email)));
      //      trim() without any condition ie trim("bala","bl")
      $name = $data->name ?? null;
      $gender = $data->gender ?? null;
      $pwd = $data->password ?? null;
      $designation_code = 'APPS';

//      var_dump($pwd);
//      print_r($pwd,'\n');
//      decrypt

      $encryption = new Encryption();
      //      if (!is_null($c_id)) $c_id = $encryption->decrypt($c_id, $secret_key);
      if (!is_null($mobile_no)) $mobile_no = $encryption->decrypt($mobile_no, $secret_key);
      if (!is_null($email)) $email = $encryption->decrypt($email, $secret_key);
      if (!is_null($pwd)) {
        $pwd = $encryption->decrypt($pwd, $secret_key);
        $pwd = isset($pwd) ? password_hash($pwd, PASSWORD_BCRYPT) : null;
      }

      $params = array();
      $params[] = $mobile_no;
      $params[] = $email;
      $params[] = $pwd;
      $params[] = $name;
      $params[] = $gender;
      $params[] = $designation_code;

      $sql = 'INSERT INTO mas.users (mobile_no, email,pwd,name,gender,designation_code)
              VALUES ($1, $2, $3, $4, $5 ,$6)
              RETURNING id';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      if (count($rows) > 0) {
        $retObj['id'] = $rows[0]['id'];
        $retObj['message'] = 'Registered Successfully.';
      } else {
        throw new Exception('Property cannot be added. Insufficient data.');
      }

      // Finished
      $db->Commit();
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
      $db->RollBack();
    }
    $db->DBClose();
    return $retObj;
  }

  /**
   * @throws Exception
   */
  function checkSessionToken($data)
  {
    $id = isset($data->id) ? $data->id : null;
    $session_token = isset($data->session_token) ? $data->session_token : null;
    $params = [];
    $params[] = $id;
    $params[] = $session_token;

    if (is_null($id) or is_null($session_token)) {
      throw new Exception('Token is not valid / Expired.');
    }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT id FROM mas.users WHERE id = $1 AND COALESCE(session_token, \'\') = $2';
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();

    if (count($rows) <= 0) {
      throw new Exception('Your current session terminated, since you created new session with new login.');
    }

  }

  function checkAndGenerateOTP($data, &$payload): array
  {
    $retObj = [];
//    var_dump($data);
    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!(isset($payload) && isset($payload['secret_key']))) {
        throw new Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];

      // Key(encrypted) fields
      $mobile_no = $data->mobile_no ?? null;
      $email = $data->email ?? null;
//      $emp_no = $data->emp_no ?? null;

      // decrypt
      $encryption = new Encryption();
      if (!is_null($mobile_no)) $mobile_no = $encryption->decrypt($mobile_no, $secret_key);
      if (!is_null($email)) $email = $encryption->decrypt($email, $secret_key);
//      if (!is_null($emp_no)) $emp_no = $encryption->decrypt($emp_no, $secret_key);

//      if ((is_null($mobile_no) && is_null($email) && is_null($emp_no))) {
      if ((is_null($mobile_no) && is_null($email))) {
        throw new Exception('Invalid User');
      }

      $opt_request_obj = [];
      switch (true) {
        case (!is_null($mobile_no)):
          $opt_request_obj = ['mobile_no' => $mobile_no];
          break;
        case (!is_null($email)):
          $opt_request_obj = ['email' => $email];
          break;
//        case (!is_null($emp_no)):
//          $opt_request_obj = [ 'emp_no' => $emp_no ];
//          break;
      }

      $otp_status = $this->getUserOTPStatus($db, (object)$opt_request_obj);
//      var_dump('otp status==',$otp_status);
//      var_dump($otp_status['allow_otp_generation']);
      $payload['id'] = $id = $otp_status['id'];
      $mobile_no = $otp_status['mobile_no'];
      $retObj['masked_mobile_no'] = 'XXXXXX' . substr($mobile_no, 6);

      if ($otp_status['is_lockedout'] == true) {
        $retObj['lockout_time'] = $otp_status['lockout_time'];
        throw new Exception('Lockout');
      }

      if (!$otp_status['allow_otp_generation']) {
        throw new \Exception('New OTP cannot be generated when previous active OTP exist. Please try after 10 minutes.');
      }

//      $sms = new \LWMIS\Common\SMS();
//      $sms->sendOTPForUserForgotPassword($db, $mobile_no, $payload);

      (new \LWMIS\Common\SMS_Message())->sendOTPForUserForgotPassword($db, $mobile_no, $payload);

      $sql = 'UPDATE mas.users
                 SET otp_generated_on = CURRENT_TIMESTAMP,
                     otp_failure_attempt = NULL,
                     lockout_on = NULL,
                     is_otp_resent = NULL
               WHERE id = $1';
      $db->Query($sql, [$id]);

      // Log User Action
      $userAction = new \LWMIS\LOG\UserAction();
      $userAction->save($db, (object)[
        'user_id' => $id,
        'action_code' => 'U_FG_PWD'
      ]);

      // Remove secret_key from $payload
      unset($payload['secret_key']);

      // Finished
      $db->Commit();
//      $retObj['message'] = 'OTP Generated.';
//      $retObj['message'] = $encryption->encrypt('OTP Generated.',$secret_key);
      $retObj['msg'] = $encryption->encrypt('OTP Generated.', $secret_key);
    } catch (Throwable $th) {
      $db->RollBack();
      $retObj['msg'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  private function getUserOTPStatus($db, $data)
  {
    $retObj = [];
    $mobile_no = $data->mobile_no ?? null;
    $email = $data->email ?? null;
    $emp_no = $data->emp_no ?? null;

    $where_clause = '';
    $params = [];

    switch (true) {
      case (!is_null($mobile_no)):
        $params[] = $mobile_no;
        $where_clause .= ' AND c.mobile_no = $' . count($params);
        break;
      case (!is_null($email)):
        $params[] = $email;
        $where_clause .= ' AND c.email IS NOT NULL AND email = $' . count($params);
        break;
      case (!is_null($emp_no)):
        $params[] = $emp_no;
        $where_clause .= ' AND c.emp_no = $' . count($params);
        break;
    }

    if (isset($data->id)) {
      $id = $data->id;
      $params[] = $id;
      $where_clause .= ' AND c.id = $' . count($params);
    }

    if (count($params) <= 0 || $where_clause == '') {
      throw new Exception('Invalid User');
    }

    $sql = 'SELECT c.id,
                   c.mobile_no,
                   COALESCE(c.lockout_on, \'1978-02-28\'::TIMESTAMPTZ) + INTERVAL \'10 minutes\' > CURRENT_TIMESTAMP AS is_lockedout,
                   EXTRACT(\'minutes\' FROM c.lockout_on + INTERVAL \'11 minutes\' - CURRENT_TIMESTAMP) AS lockout_time,
                   COALESCE(c.otp_failure_attempt, 0) AS otp_failure_attempt,
                   (COALESCE(c.otp_generated_on, \'1978-02-28\'::DATE) + interval \'10 MINUTES\' < CURRENT_TIMESTAMP) AS allow_otp_generation,
                   (COALESCE(c.otp_generated_on, \'1978-02-28\'::DATE) + interval \'2 MINUTES\' < CURRENT_TIMESTAMP) AS allow_otp_resend,
                   COALESCE(is_otp_resent, FALSE) AS is_otp_resent
              FROM mas.users AS c
             WHERE c.is_active = true' . $where_clause;

//    var_dump('allow OTP Generation=',$sql);
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    foreach ($rows as &$r) {
      $r['id'] = intval($r['id']);
      $r['is_lockedout'] = ($r['is_lockedout'] == 't');
      $r['lockout_time'] = intval($r['lockout_time']);
      $r['otp_failure_attempt'] = intval($r['otp_failure_attempt']);
      $r['allow_otp_generation'] = ($r['allow_otp_generation'] == 't');
      $r['allow_otp_resend'] = ($r['allow_otp_resend'] == 't');
      $r['is_otp_resent'] = ($r['is_otp_resent'] == 't');
    }

    if (count($rows) > 0) {
      $retObj = $rows[0];
    } else {
      throw new Exception('Invalid User');
    }

    return $retObj;
  }

  function deleteOTPGeneratedOn($data, &$payload): array
  {
//    $retObj = ['message' => 'Token is not valid / Expired.'];
//    var_dump('data=', $data);
//    var_dump('pl=', $payload);

    $id = isset($payload['id']) ? $payload['id'] : null;
    if (!is_null($id)) {
      $db = new \LWMIS\Common\PostgreDB();
      $sql = 'UPDATE mas.users
                 SET otp_generated_on = NULL,
                     otp_failure_attempt = NULL,
                     lockout_on = NULL,
                     is_otp_resent = NULL
               WHERE id = $1';
      $db->Query($sql, [$id]);
      $db->DBClose();
      $retObj['message'] = 'Cleared OTP Generated On flag';
    }

    return $retObj;
  }

  function resendOTP($data, &$payload): array
  {
    // $id = isset($data->id)?$data->id:null;
    $id = $payload['id'] ?? null;
    $otp = $payload['otp'] ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();
      if (is_null($otp) || is_null($id)) {
        throw new \Exception('Token is not valid / Expired.');
      }

      $otp_status = $this->getUserOTPStatus($db, (object)['id' => $id]);
      $mobile_no = $otp_status['mobile_no'];

      if ($otp_status['is_lockedout'] == true) {
        $retObj['lockout_time'] = $otp_status['lockout_time'];
        throw new \Exception('Lockout');
      }

      if ($otp_status['allow_otp_generation']) {
        throw new \Exception('Resend OTP cannot be done since already generated OTP expired. Please generate new OTP.');
      }

      if (!$otp_status['allow_otp_resend']) {
        throw new \Exception('Resend OTP cannot be done. Please try after 2 minutes.');
      }

      if ($otp_status['is_otp_resent']) {
        throw new \Exception('Resend OTP can be exercised only once. Already OTP resent.');
      }

//      $sms = new \LWMIS\Common\SMS();
//      $sms->resendOTPForUserForgotPassword($db, $mobile_no, $otp);

      (new \LWMIS\Common\SMS_Message())->resendOTPForUserForgotPassword($db, $mobile_no, $otp);

      $sql = 'UPDATE mas.users SET is_otp_resent = TRUE WHERE id = $1';
      $db->Query($sql, [$id]);

      $db->Commit();
      $retObj['message'] = 'OTP resent.';
    } catch (\Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  function changePassword($data, &$payload): array
  {
    $enc_data = $data->enc_data ?? null;
    $retObj = ['message' => 'Incorrect current password / Invalid user.'];

    $db = new \LWMIS\Common\PostgreDB();

    try {

      if (!(isset($payload['secret_key']))) {
        throw new Exception('Token is not valid / Expired.');
      }

      $data = (new Encryption())->decrypt($enc_data, $payload['secret_key']);
      $data = json_decode($data);

      $db->Begin();

      $id = $data->id ?? null;
      $pwd = $data->pwd ?? null;
      $oldPwd = $data->oldPwd ?? null;

      if (is_null($id) || is_null($oldPwd) || is_null($pwd)) {
        throw new Exception('Invalid User / Password Unavailable.');
      }

      if (!(new \LWMIS\Common\GeneralFunctions())->checkPasswordPattern($pwd)) {
        throw new Exception('New Password should contain at least 1 uppercase, 1 lowercase, 1 digit and 1 special character');
      }

      // $oldPwd = $encryption->decrypt($oldPwd, $secret_key);
      $sql = 'SELECT pwd FROM mas.users WHERE id = $1';
      $db->Query($sql, [$id]);
      $rows = $db->FetchAll();
      if (count($rows) > 0) {
        $db_pwd = $rows[0]['pwd'];
        $verified = password_verify($oldPwd, $db_pwd);
        if ($verified) {
          // $pwd = $encryption->decrypt($pwd, $secret_key);
          $pwd = password_hash($pwd, PASSWORD_BCRYPT);
          $sql = 'UPDATE mas.users
                     SET pwd = $1
                   WHERE id = $2';
          $db->Query($sql, [$pwd, $id]);

          // Log User Action
          ( new \LWMIS\LOG\UserAction())->save($db, (object)['user_id' => $id, 'action_code' => 'U_CNG_PWD']);

          // Remove secret_key from $payload
          unset($payload['secret_key']);

          $retObj['message'] = 'Password changed successfully.';
        }
      }

      // Finished
      $db->Commit();
    } catch (Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  function savePassword($data, &$payload): array
  {
    $retObj = [];
    $db = new \LWMIS\Common\PostgreDB();

    try {
      $db->Begin();

      if (!(isset($payload) && isset($payload['secret_key']))) {
        throw new \Exception('Token is not valid / Expired.');
      }
      $secret_key = $payload['secret_key'];
      $id = isset($payload['id']) ? $payload['id'] : null;

      if (!(isset($payload['is_otp_validated']) && $payload['is_otp_validated'] === true)) {
        // OTP not yet validated.
        $retObj = $this->checkOTPFailureAttempts($data, $payload);
        throw new \Exception($retObj['message']);
      }

      // Key(encrypted) fields
      $pwd = isset($data->pwd) ? $data->pwd : null;

      // decrypt
      $encryption = new Encryption();
      if (!is_null($pwd)) $pwd = $encryption->decrypt($pwd, $secret_key);

      // Other fields
      //

      if (is_null($id) or is_null($pwd)) {
        throw new Exception('Invalid User / Password Unavailable.');
      }

      $pwd = password_hash($pwd, PASSWORD_BCRYPT);
      $sql = 'UPDATE mas.users
                 SET pwd = $1
               WHERE id = $2';
      $db->Query($sql, [$pwd, $id]);

      // Log User Action
      $userAction = new \LWMIS\LOG\UserAction();
      $userAction->save($db, (object)[
        'user_id' => $id,
        'action_code' => 'U_CNG_PWD',
        'note' => 'Forgot Password OTP Validated.'
      ]);

      // Remove secret_key & is_otp_validated from $payload
      unset($payload['secret_key']);
      unset($payload['is_otp_validated']);

      // Finished
      $db->Commit();
      $retObj['message'] = 'User password saved successfully.';
    } catch (\Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  function checkOTPFailureAttempts($data, &$payload): array
  {
    $retObj = [];
    // $id = isset($data->id)?$data->id:null;
    $id = isset($payload['id']) ? $payload['id'] : null;

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (is_null($id)) {
        throw new Exception('Invalid Customer');
      }

      $otp_status = $this->getCustomerOTPStatus($db, (object)['id' => $id]);

      if ($otp_status['is_lockedout'] == true) {
        $retObj['lockout_time'] = $otp_status['lockout_time'];
        throw new Exception('Lockout');
      }

      if ($otp_status['otp_failure_attempt'] < 4) {
        $sql = 'UPDATE mas.users
                   SET otp_failure_attempt = COALESCE(otp_failure_attempt, 0) + 1
                 WHERE id = $1
             RETURNING otp_failure_attempt';
        $db->Query($sql, [$id]);
        $rows = $db->FetchAll();
        foreach ($rows as &$r) {
          $r['otp_failure_attempt'] = intval($r['otp_failure_attempt']);
        }
        $retObj['message'] = 'Invalid OTP. Attempt ' . ($rows[0]['otp_failure_attempt']) . ' of 5.';
      } else {
        $sql = 'UPDATE mas.users
                   SET lockout_on = CURRENT_TIMESTAMP,
                       otp_failure_attempt = COALESCE(otp_failure_attempt, 0) + 1
                 WHERE id = $1';
        $db->Query($sql, [$id]);
        $retObj['lockout_time'] = 10;
        $retObj['message'] = 'Lockout';
      }
      $db->Commit();
    } catch (Throwable $th) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($th);
    }
    $db->DBClose();

    return $retObj;
  }

  private function getCustomerOTPStatus($db, $data)
  {
    $retObj = [];
    $where_clause = '';
    $params = [];

    if (isset($data->mobile_no_email)) {
      $mobile_no_email = $data->mobile_no_email;
      $params[] = $mobile_no_email;
      $param_cnt = '$' . count($params);
      $where_clause .= ' AND (c.mobile_no = ' . $param_cnt . ' OR (c.email IS NOT NULL AND c.email = ' . $param_cnt . '))';
    }

    if (isset($data->id)) {
      $id = $data->id;
      $params[] = $id;
      $where_clause .= ' AND c.id = $' . count($params);
    }

    if (count($params) <= 0 || $where_clause == '') {
      throw new Exception('Registered Mobile No. / eMail ID Invalid');
    }

    $sql = 'SELECT c.id, c.mobile_no, COALESCE(c.lockout_on, \'1978-02-28\'::TIMESTAMPTZ) + INTERVAL \'10 minutes\' > CURRENT_TIMESTAMP AS is_lockedout,
                     EXTRACT(\'minutes\' FROM c.lockout_on + INTERVAL \'11 minutes\' - CURRENT_TIMESTAMP) AS lockout_time,
                     COALESCE(c.otp_failure_attempt, 0) AS otp_failure_attempt,
                     (COALESCE(c.otp_generated_on, \'1978-02-28\'::DATE) + interval \'10 MINUTES\' < CURRENT_TIMESTAMP) AS allow_otp_generation,
                     (COALESCE(c.otp_generated_on, \'1978-02-28\'::DATE) + interval \'2 MINUTES\' < CURRENT_TIMESTAMP) AS allow_otp_resend,
                     COALESCE(is_otp_resent, FALSE) AS is_otp_resent
                FROM mas.users AS c
               WHERE c.is_active = true' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    foreach ($rows as &$r) {
      $r['id'] = intval($r['id']);
      $r['is_lockedout'] = ($r['is_lockedout'] == 't');
      $r['lockout_time'] = intval($r['lockout_time']);
      $r['otp_failure_attempt'] = intval($r['otp_failure_attempt']);
      $r['allow_otp_generation'] = ($r['allow_otp_generation'] == 't');
      $r['allow_otp_resend'] = ($r['allow_otp_resend'] == 't');
      $r['is_otp_resent'] = ($r['is_otp_resent'] == 't');
    }

    if (count($rows) > 0) {
      $retObj = $rows[0];
    } else {
      throw new Exception('Registered Mobile No. / eMail ID Invalid');
    }

    return $retObj;
  }

  /**
   * @throws Exception
   */
  function getDesignation($user_id, $db): string|null
  {
    $sql = 'select designation_code,name
              from mas.users
             where id = $1';
    $db->Query($sql, [$user_id]);
    return $db->FetchAll()[0]['designation_code'] ?? null;
  }


}
