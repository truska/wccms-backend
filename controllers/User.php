<?php

/**
 * User class
 *
 * @version    1.0.0
 * @author     Salva TDR
 * 
 * @var array $user        User data
 * @var boolean $isGuest   Is guest
 * @var string $orderno    Order Number
 **/
class User
{
   protected $user;
   protected $isGuest;
   protected $orderno;
   protected $shippingRate;

   function __construct($userId = 1, $orderno)
   {
      $this->orderno = $orderno;

      if ($userId == 1) { // Guest
         $this->setUserAsGuest();
      } else {
         $this->setUser($userId);
      }
   }

   /**
    * Set user
    *
    * @param string $username  Username
    * @return void
    */
   private function setUser($userId)
   {
      $query = "SELECT * FROM `cms_users` 
      WHERE `id` = $userId";
      $user = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      if ($user) {
         $this->user = $user;
         $this->isGuest = false;
      } else {
         $this->setUserAsGuest();
      }
   }

   /**
    * Set user as guest
    *
    * @return void
    */
   private function setUserAsGuest()
   {
      $query = "SELECT * FROM `cms_users` 
      WHERE `id` = 1";
      $user = mysqli_fetch_array(DB::query($query), MYSQLI_ASSOC);

      $this->user = $user;
      $this->isGuest = true;
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

   public function getUsers() {
      $query = "SELECT * FROM `cms_users`
      WHERE `archived` = 0
      ORDER BY `id` DESC";
      $result = DB::query($query);
      $count = mysqli_num_rows($result);

      if ($count > 0) {
         $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
      } else {
         $users = [];
      }

      return $users;
   }

   /**
    * Get Order Number
    *
    * @return string  Order Number
    */
   public function getOrderNo()
   {
      return $this->orderno;
   }

   /**
    * Get isGuest
    *
    * @return boolean  Is guest
    */
   public function getIsGuest()
   {
      return $this->isGuest;
   }

   static function searchUserInput($word)
   {
      $query = "SELECT * FROM `cms_users`";
      if (!empty($word)) {
         $query .= " WHERE (`username` LIKE '%$word%' 
         OR `firstname` LIKE '%$word%'
         OR `surname` LIKE '%$word%')";
      }
      $query .= " ORDER BY `id` DESC";
      $result = mysqli_fetch_all(DB::query($query), MYSQLI_ASSOC);
      if (empty($result)) {
         return 'No data';
      } else {
         return $result;
      }
   }

   /**
    * Sign in
    *
    * @param string $username  Username
    * @param string $password  Password
    * @return array  Response
    */
   public function signIn($username, $password)
   {
      $response = [];

      $pass = md5($password); // encrypt password

      $query = "SELECT * FROM `cms_users` 
      WHERE `username` LIKE '$username'
      AND `password` LIKE '$pass'";
      $result = DB::query($query);
      $count = mysqli_num_rows($result);

      if ($count > 0) {
         $user = mysqli_fetch_array($result, MYSQLI_ASSOC);

         $this->user = $user;
         $this->isGuest = false;
         $response = [
            'status' => 'success',
            'code' => 200,
            'msg' => 'User logged in successfully',
            'user_data' => $user
         ];
      } else {
         $response = [
            'status' => 'error',
            'code' => 400,
            'msg' => 'Invalid username or password'
         ];
      }

      return $response;
   }

   /**
    * Update the user activation code
    *
    * @param string $time  Time
    * @return boolean  True if the update was successful, false otherwise
    */
   public function updateActivationCode($time)
   {
      $query = "UPDATE `useractivationcode` 
      SET `activationcode` = '$time' 
      WHERE `useremail` = '{$this->user['username']}'";
      $result = DB::query($query);

      if ($result) {
         return true;
      } else {
         return false;
      }
   }

   /**
    * Check user status
    *
    * @return array  Response 
    */
   public function checkUserStatus()
   {
      $response = [];
      if ($this->user['accountstatus'] == 0) {
         $response = [
            'status' => 'error',
            'code' => 400,
            'msg' => 'Your account is not active. Please check your email to activate your account'
         ];
      } else {
         $response = [
            'status' => 'success',
            'code' => 200,
            'msg' => 'The user account is active',
            'user_data' => $this->user
         ];
      }

      return $response;
   }

   /**
    * Update the cart to the logged in user
    *
    * @param string $oldUser  Old user assigned to the cart
    * @return boolean  True if the cart was assigned to the logged in user, false otherwise
    */
   public function assignCartToTheLoggedInUser($oldUser)
   {
      $query = "UPDATE `cart`
      SET `userid` = '{$this->user['id']}',
      `orderno` = '{$this->orderno}',
      `usercookie` = '{$this->orderno}'
      WHERE `userid` = '$oldUser'
      AND (`usercookie` = '{$this->orderno}'
         OR `orderno` = '{$this->orderno}'
      )";
      $result = DB::query($query);

      if ($result) {
         return true;
      } else {
         return false;
      }
   }

   /**
    * Get user cart
    *
    * @param int $cartStatus  Cart status
    * @return array 
    */
   public function getCart($cartStatus = -1, $linkedproid = -1)
   {
      $response = [];

      if ($this->isGuest) {
         $query = "SELECT * FROM `cart` 
         WHERE `orderno` = '{$this->orderno}'
         AND `userid` = {$this->user['id']}";
      } else {
         $query = "SELECT * FROM `cart` 
         WHERE `orderno` = '{$this->orderno}' 
         AND ( `userid` = {$this->user['id']} 
            OR `userid` = 1
         )";
      }
      if ($cartStatus != -1) {
         $query .= " AND `cartstatus` = $cartStatus";
      }
      if ($linkedproid != -1) {
         $query .= " AND `linkedproid` = $linkedproid";
      }
      $result = DB::query($query);
      $count = mysqli_num_rows($result);

      if ($count > 0) {
         $cart = mysqli_fetch_all($result, MYSQLI_ASSOC);

         $response = [
            'status' => 'success',
            'code' => 200,
            'msg' => 'Cart retrieved successfully',
            'num_items' => $count,
            'cart' => $cart
         ];
      } else {
         $response = [
            'status' => 'no_data',
            'code' => 400,
            'msg' => 'Cart Empty',
            'num_items' => 0,
         ];
      }

      return $response;
   }

   public function getMyOrders($paid = false)
   {
      $response = [];

      $query = "SELECT * FROM `order_checkoutdetail` 
      WHERE `userid` = {$this->user['id']} 
      ORDER BY `created` DESC";
      // if ($paid) {
      //    $query .= " AND `paymentstatus` = 1";
      // }
      $result = DB::query($query);
      $count = mysqli_num_rows($result);

      if ($count > 0) {
         $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

         $response = [
            'status' => 'success',
            'code' => 200,
            'num_items' => $count,
            'orders' => $orders
         ];
      } else {
         $response = [
            'status' => 'no_data',
            'code' => 400,
            'msg' => 'No orders found',
            'num_items' => 0,
         ];
      }

      return $response;
   }

   public function checkIfVitalsFieldsAreCompleted()
   {
      $response = [];

      if ($this->user['billaddress1'] == '' || empty($this->user['billaddress1'])) {
         $response = [
            'status' => 'error',
            'code' => 400,
            'msg' => 'Please complete your address, you will be redirected to your account page'
         ];
      } elseif ($this->user['billtown'] == '' || empty($this->user['billtown'])) {
         $response = [
            'status' => 'error',
            'code' => 400,
            'msg' => 'Please complete your town, you will be redirected to your account page'
         ];
      } elseif ($this->user['billpostcode'] == '' || empty($this->user['billpostcode'])) {
         $response = [
            'status' => 'error',
            'code' => 400,
            'msg' => 'Please complete your postcode, you will be redirected to your account page'
         ];
      }else {
         $response = [
            'status' => 'success',
            'code' => 200,
            'msg' => 'Vitals fields completed'
         ];
      }

      return $response;
   }
}
