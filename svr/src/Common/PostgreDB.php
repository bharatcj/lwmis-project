<?php
namespace LWMIS\Common;

class PostgreDB
{
  var $host;
  var $username;
  var $password;
  var $port;
  var $dbname;
  var $error = null;
  var $dbconnect;
  var $query;
  var $result;
  // var $oid = null;
  // var $oid_res;
  var $persistent;

  function __construct()
  {
    //$DB = "", $Host = "localhost", $PgPort = 5432, $User = "Anonymous", $pass = "Anonymous", $persist = 1
    if (extension_loaded("pgsql")) {
      $this->host = MyConfig::pgHost; // $Host;
      $this->port = MyConfig::pgPort; // $PgPort;
      $this->dbname = MyConfig::pgDbName; // $DB;
      $this->username = MyConfig::pgUser; // $User;
      $this->password = MyConfig::pgPassword; // $pass;
      $this->persistent = MyConfig::pgPersistent; // $persist;
      $this->Connect();
    } else {
      echo "Web application server (apache) should be configured for PostgresSQL. Extension pgsql not found.";
      die();
    };
  }

  function Connect(): void
  {
    $connect = "host=" . $this->host . " port=" . $this->port . " dbname=" . $this->dbname . " user=" . $this->username;
    if (!empty($this->password))
      $connect .= " password=" . $this->password;
    //if ($this->persistent)
    $this->dbconnect = pg_pconnect($connect);
    //else
    //  $this->dbconnect = pg_connect($connect);
    if (!$this->dbconnect)
      $this->error = "cannot connect to database " . $this->dbname;
  }

  function Query($sql, $params = array())
  {
    // $this->result = pg_query_params($this->dbconnect, $sql, $params);
    pg_send_query_params($this->dbconnect, $sql, $params);
    $this->result = pg_get_result($this->dbconnect);
//      @bala pg_get_result() return true or false based on connection result.
    $this->error = pg_result_error($this->result);
    if ($this->error !== "") {
      // $state_code = pg_result_error_field($this->result, PGSQL_DIAG_SQLSTATE);
      // echo $state_code;
      throw new \Exception($this->error);
    }
    return $this->result;

    // $this->result = pg_query_params($this->dbconnect, $sql, $params);
    // if ($this->result === false) {
    //   pg_send_query_params($this->dbconnect, $sql, $params);
    //   $res1 = pg_get_result($this->dbconnect);
    //   $this->error = pg_result_error($res1);
    //   echo $this->error;
    //   if (isset($this->error)) {
    //     throw new \Exception($this->error);
    //   }
    // }
    // return $this->result;
  }

  function ExecQuery($sql)
  {
    // $this->query = new Query ($sql, $this->dbconnect);
    // $this->result = $this->query->Execute();
    // $this->error = $this->query->Error();
    // $this->query->Free();
    $this->result = pg_query($this->dbconnect, $sql);
    $this->error = pg_result_error($this->result);
    return $this->result;
  }

  function FetchAll($assoc = PGSQL_ASSOC): array
  {
    if (!$this->error) {
      $arr = pg_fetch_all($this->result, $assoc);
      return (!$arr) ? [] : $arr;
    } else {
      throw new \Exception($this->error);
      // echo "An error occured, $this->error";
      // return null;
    }
  }

  function pgGetNotify($result_type = PGSQL_ASSOC)
  {
    return pg_get_notify($this->dbconnect, $result_type);
  }

  // function FetchResult(&$row, $assoc = PGSQL_ASSOC) // PGSQL_BOTH
  // {
  //   if (!$this->error)
  //   {
  //     @$arr = pg_fetch_array($this->result, $row, $assoc);
  //     return $arr;
  //   }
  //   else
  //   {
  //     echo "An error occured, $this->error";
  //     return null;
  //   }
  // }

  function NumRows()
  {
    if ($this->result && !$this->error)
      return pg_num_rows($this->result);
    else
      return -1;
  }

  function Error()
  {
    $this->error = pg_last_error($this->dbconnect);
    return $this->error;
  }

  function Begin()
  {
    pg_query($this->dbconnect, "begin");
    // $this->oid = pg_lo_create($this->dbconnect);
    // $this->result = $this->Open();
    // $this->oid_res = $this->result;
  }

  // function Create()
  // {
  //   $this->oid = pg_lo_create($this->dbconnect);
  // }

  // function Open($mode = "rw")
  // {
  //   $this->result = pg_lo_open($this->dbconnect, $this->oid, $mode);
  //   return $this->result;
  // }

  // function Write($data)
  // {
  //   if (!$this->oid || $this->error)
  //     echo "$this->error<br>\n";
  //   else
  //     $this->error=pg_lo_write ($this->result, $data);
  // }

  // function Read()
  // {
  //   if (!$this->oid)
  //     echo "$this->error<br>\n";
  //   else
  //     $this->result = pg_lo_read_all($this->result, $data);
  //   return $this->result;
  // }

  // function Unlink()
  // {
  //   pg_lo_unlink($this->dbconnect, $this->oid);
  // }

  // function LastOID()
  // {
  //   $this->oid = pg_last_oid($this->result);
  //   return $this->oid;
  // }

  // function Close()
  // {
  //   if (!$this->oid)
  //     echo "$this->error<br>\n";
  //   else
  //   {
  //     $this->result = pg_result_status($this->result);
  //     $this->error = pg_lo_close($this->oid);
  //   }
  // }

  function Options()
  {
    return pg_options($this->dbconnect);
  }

  function Status()
  {
    return pg_connection_status($this->dbconnect);
  }

  // function StartTransactionReadWrite()
  // {
  //   pg_query($this->dbconnect, 'START TRANSACTION ISOLATION LEVEL REPEATABLE READ;');
  // }

  // function Savepoint($a = 'a')
  // {
  //   pg_query($this->dbconnect, "SAVEPOINT ".$a);
  // }

  function RollBack(): void
  {
    // if (!$this->oid)
    //   echo "$this->error<br>\n";
    // else
    pg_query($this->dbconnect, "Rollback");
  }

  function Commit(): void
  {
    // if (!$this->oid) {
    //   echo "$this->error<br>\n";
    // }
    // else
    pg_query($this->dbconnect, "Commit");
  }

  function DBClose(): void
  {
    // Following to be used only in case of large data being handled
    // if (isset($this->result)) {
    //   \pg_free_result($this->result);
    // }

    // if (!$this->persistent)

    pg_close($this->dbconnect);
  }

  // General functions
  function checkString($val, $defaultVal = null)
  {
    return (isset($val) && strlen($val) > 0) ? $val : $defaultVal;
  }

  function checkInt($val, $defaultVal = null, $allow_zero = false)
  {
    if ($allow_zero)
      return (isset($val) && intval($val) >= 0) ? intVal($val) : $defaultVal;
    else
      return (isset($val) && intval($val) > 0) ? intval($val) : $defaultVal;
  }

  public function checkDec($val, $defaultVal = null, $allow_zero = true, $allow_negative = false)
  {
    if ($allow_negative) {
      if ($allow_zero)
        return (isset($val)) ? floatval($val) : $defaultVal;
      else
        return (isset($val) && floatval($val) != 0) ? floatval($val) : $defaultVal;
    } else {
      if ($allow_zero)
        return (isset($val) && floatval($val) >= 0) ? floatval($val) : $defaultVal;
      else
        return (isset($val) && floatval($val) > 0) ? floatval($val) : $defaultVal;
    }
  }

  // Returns string (formatted)
  public function checkDBDate($val, $defaultVal = null, $retFormat = 'Y-m-d', $from_time_zone = 'UTC', $to_time_zone = 'Asia/Kolkata')
  {
    if ($val == "" or $val == null)
      return $defaultVal;

    $dt = \DateTime::createFromFormat('d/m/Y', $val, new \DateTimeZone($from_time_zone));
    // $dt = new Datetime($val, new DateTimeZone($from_time_zone));
    $dt->setTimezone(new \DateTimeZone($to_time_zone));
    return $dt->format($retFormat);
  }
}


