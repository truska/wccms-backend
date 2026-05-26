<?php
/**
 * User class
 * 
 * Class to process user data
 *
 * @version    1.0.0
 * @author     Salva TDR
 * 
 * @var array $user  User data
 * @var array $userRole  User role
 **/
class CMSUser
{
   public $user;
   public $userRole;

   function __construct($username)
   {
      $user = cms_db_fetch_one(
         "SELECT * FROM cms_adminlogin WHERE username = :username",
         [':username' => $username]
      );

      if ($user) {
         $this->user = $user;
         $userRole = cms_db_fetch_one(
            "SELECT * FROM `cms_userrole` WHERE `name` = :name",
            [':name' => $user['userrole']]
         );

         if ($userRole) {
            $this->userRole = $userRole;
         }else {
            $this->userRole = null;
         }
      }else {
         $this->user = null;
      }
   }

   /**
    * Get user
    *
    * @return array  User data
    */
   public function getUser()
   {
      return $this->user;
   }

   /**
    * Get user role
    *
    * @return array  User role data
    */
   public function getUserRole()
   {
      // $query = "SELECT * FROM `cms_userrole` 
      // WHERE `name` = '" . $this->user['userrole'] . "'";
      // $userRole = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      // return $userRole;
      return $this->userRole;
   }

   /**
    * Sign in
    * 
    * @param string $username  Username
    * @param string $password  Password
    * @return boolean 
    */
   public function signIn($username, $password) {
      if ($this->user['username'] == $username && $this->user['password'] == $password) {
         return true;
      }else {
         return false;
      }
   }

   /**
    * Update password
    * 
    * @param string $pass  New password
    * @return array  Response
    */
   public function updatePassword($pass) {
      $pass = md5($pass);
      $response = [];

      try {
         $result = cms_db_execute(
            "UPDATE cms_adminlogin SET `password` = :password WHERE `username` = :username",
            [
               ':password' => $pass,
               ':username' => $this->user['username'],
            ]
         );
         if ($result) {
            $response['status'] = 200;
            $response['msg'] = 'Password changed successfully';
         }else {
            throw new Exception("Error Processing Request (new password)", 400);
         }
      } catch (\Exception $ex) {
         $response['status'] = $ex->getCode();
         $response['msg'] = $ex->getMessage();
      }

      return $response;
   }

   public function deleteResetCode($resetCode) {
      $response = [];

      try {
         $result = cms_db_execute(
            "DELETE FROM `recoverpassword` WHERE `emailcode` = :code",
            [':code' => $resetCode]
         );

         if ($result) {
            $response['status'] = 200;
            $response['msg'] = 'Reset code deleted successfully';
         }else {
            throw new Exception("Error Processing Request (delete reset code)", 400);
         }
      } catch (\Exception $e) {
         $response['status'] = $e->getCode();
         $response['msg'] = $e->getMessage();
      }

      return $response;
   }

   public function send2fa($from, $to, $code) {
      $response = [];

      try {
         $subject = "wITeCanvas 2FA Code";
         $message = "Your wITeCanvas CMS 2FA code is:<br><strong>";
         $message .= "$code" ;
         $message .= "</strong><br>Please enter this code where prompted.";
         $message .= "<br>Do not share this code with anyone.";
         $message .= "<br>Not expecting this? Contact Truska/digita Support.";
         $headers = "MIME-Version: 1.0" . "\r\n";
         $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
         $headers .= "From: " . $from;

         if (mail($to, $subject, $message, $headers, "-f $from")) {
            $response['status'] = 200;
            $response['msg'] = '2FA code sent successfully';
         }else {
            $response['status'] = 400;
            $response['msg'] = 'Error Processing Request (sending 2fa)';
         }  
      } catch (\Exception $e) {
         $response['status'] = $e->getCode();
         $response['msg'] = $e->getMessage();
      }

      return $response;
   }

   public function save2fa($email, $code) {
      // Create a valid date (15min)
      $date = date('Y-m-d H:i:s', strtotime('+15 minutes'));

      $response = [];

      try {
         // 1. Check if there any code for this email
         $count = (int) cms_db_fetch_column(
            "SELECT COUNT(*) FROM `cms_2fa` WHERE `email` = :email",
            [':email' => $email]
         );

         if ($count > 0) {
            // 2. Delete the old code
            cms_db_execute(
               "DELETE FROM `cms_2fa` WHERE `email` = :email",
               [':email' => $email]
            );
         }

         $result = cms_db_execute(
            "INSERT INTO `cms_2fa` (`email`, `code`, `valid_until`) VALUES (:email, :code, :valid_until)",
            [
               ':email' => $email,
               ':code' => $code,
               ':valid_until' => $date,
            ]
         );

         if ($result) {
            $response['status'] = 200;
            $response['msg'] = '2FA code saved successfully';
         }else {
            $response['status'] = 400;
            $response['msg'] = 'Error Processing Request (save 2fa code)';
         }
      } catch (\Exception $e) {
         $response['status'] = $e->getCode();
         $response['msg'] = $e->getMessage();
      }

      return $response;
   }

   public function delete2fa($email) {
      $response = [];

      try {
         $result = cms_db_execute(
            "DELETE FROM `cms_2fa` WHERE `email` = :email",
            [':email' => $email]
         );

         if ($result) {
            $response['status'] = 200;
            $response['msg'] = '2FA code deleted successfully';
         }else {
            $response['status'] = 400;
            $response['msg'] = 'Error Processing Request (delete 2fa code)';
         }
      } catch (\Exception $e) {
         $response['status'] = $e->getCode();
         $response['msg'] = $e->getMessage();
      }

      return $response;
   }

   public function check2fa($email, $code) {
      $response = [];

      try {
         $count = (int) cms_db_fetch_column(
            "SELECT COUNT(*) FROM `cms_2fa`
             WHERE `email` = :email
             AND `code` = :code
             AND `valid_until` > NOW()",
            [
               ':email' => $email,
               ':code' => $code,
            ]
         );

         if ($count > 0) {
            $response['status'] = 200;
            $response['msg'] = '2FA code successfully verified, you will be redirected to the dashboard in a few seconds';
         }else {
            $response['status'] = 400;
            $response['msg'] = 'Error Processing Request (check 2fa code)';
         }
      } catch (\Exception $e) {
         $response['status'] = $e->getCode();
         $response['msg'] = $e->getMessage();
      }

      return $response;
   }
}
?>
