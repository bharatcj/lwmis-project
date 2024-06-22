<?php

namespace LWMIS\Common;

class Upload
{
  function Upload($data): array
  {
    $result = array();
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
      //user ip address
      $ip = $_SERVER['REMOTE_ADDR'];

      //get the file
      $ori_fname = $_FILES['file']['name'];

      //get file extension
      $ext = pathinfo($ori_fname, PATHINFO_EXTENSION);

      $year = date("Y");
      $month = date('m');
      $date = date("d");

//      todo:Change file permission
      if (!is_dir("../uploads/" . $year)) {
        mkdir("../uploads/" . $year, 0777);
      }

      if (!is_dir('../uploads/' . $year . '/' . $month)) {
        mkdir('../uploads/' . $year . '/' . $month, 0777);
      }

      if (!is_dir('../uploads/' . $year . '/' . $month . '/' . $date)) {
        mkdir('../uploads/' . $year . '/' . $month . '/' . $date, 0777);
      }

      //target folder
      $target_path = "../uploads/" . $year . '/' . $month . '/' . $date . '/';

      $file = trim(str_replace(" ", "", $_FILES['file']['name']), '.');
      $user_email = isset($data->user_email) ? trim($data->user_email, '.') : null;

      //replace hash in the file name
      $actual_fname = trim(password_hash($file . $user_email, PASSWORD_BCRYPT), '.');
      //set target file path

      $target_path = $target_path . basename($actual_fname . '.pdf');

      if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        $result["status"] = 1;
//        $result["message"] = "Uploaded file successfully.";
        $result["message"] = "File uploaded successfully.";
        $result['file_name'] = $target_path;
      } else {
        $result["status"] = 0;
        $result["message"] = "File upload failed. Please try again.";
      }
    }
    return $result;
  }

//  function moveUploadFile($data)
//  {
//    if ($_SERVER['REQUEST_METHOD'] == "POST") {
//      //user ip address
//      $ip = $_SERVER['REMOTE_ADDR'];
//
//      //get the file
//      $ori_fname = $_FILES['file']['name'];
//
//      //get file extension
//      $ext = pathinfo($ori_fname, PATHINFO_EXTENSION);
//
//      $year = date("Y");
//      $month = date('m');
//
//      if (!is_dir($year)) {
//        mkdir("uploads/" . $year, 0777);
//      }
//      if (!is_dir($year . '-' . $month)) {
//        mkdir($year . '/' . $year . '-' . $month, 0777);
//      }
//      //target folder
//      $target_path = "../uploads/" . $year . '/' . $year . '-' . $month . '/';
//      // $target_path = "src/uploads/";
//
//      //replace special chars in the file name
//      $actual_fname = str_replace(" ", "", $_FILES['file']['name']);
//      //set target file path
//
//      $target_path = $target_path . basename($actual_fname);
//
//      $result = array();
//      if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
//        $result["status"] = 1;
//        $result["message"] = "Uploaded file successfully.";
//        $result['file_name'] = $actual_fname;
//      } else {
//
//        $result["status"] = 0;
//        $result["message"] = "File upload failed. Please try again.";
//      }
//    }
//
//    return $result;
//  }

//  function move_file($source_file, $to)
//  {
//    if (rename($source_file, $to . pathinfo($source_file, PATHINFO_BASENAME)))
//      return $to;
//    return null;
//  }

}


