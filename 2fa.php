<?php
$pageType = '' ; // Used to prevent controller/formField.php from Loading on Preference editing page(s)
require_once (__DIR__ . '/setting/main-top-files.php');

if (!isset($_GET['u'])) {
   $toast[] = array(
      "message" => "No user information",
      "type" => "error",
      "redirect" => $baseURL."/wccms",
   );
}

if (isset($_POST['submit2fa'])) {
   $username = trim(mysqli_real_escape_string(DB::connection(), $_GET["u"]));
   $code = trim(mysqli_real_escape_string(DB::connection(), $_POST["2fa"]));

   $user = new CMSUser($username);
   $check2fa = $user->check2fa($username, $code);

   $logtable = "N/A";
   $action = "2fa CHECK" ;
   $sqlquery = "N/A";
   $notes = "User 2FA Checked" ;

   if ($check2fa['status'] == 200) {
      $resuser = $user->getUser();
      $fname = $resuser["firstname"];
      $lname = $resuser["surname"];
      $full = "$fname $lname";
      $_SESSION["useremail"] = $username;
      $_SESSION["user"] = $full;

      saveLogV2($username, $action, $sqlquery, $logtable, 'SUCCESS', $notes, $recordnumber);


      $user->delete2fa($username);

      $toast[] = array(
         "message" => $check2fa['msg'],
         "type" => "success",
         "redirect" => "$BASE_URL/wccms/dashboard.php",
      );
   } else {

      saveLogV2($username, $action, $sqlquery, $logtable, 'FAIL', $notes, $recordnumber);

      $toast[] = array(
         "message" => $check2fa['msg'],
         "type" => "error",
      );
   }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="description" content="2fa verification - wITeCanvas CMS">
   <meta name="author" content="wITeCanvas">
   <meta name="keyword" content="wITeCanva, cms, truska, digita">
   <link rel="shortcut icon" href="img/favicon.html">
   <meta name="robots" content="noindex, nofollow">

   <title>2fa verification - wITeCanvas CMS</title>

   <!-- Bootstrap core CSS -->
   <link href="css/bootstrap.min.css" rel="stylesheet">
   <link href="css/bootstrap-reset.css" rel="stylesheet">
   <!--external css-->
   <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
   <!-- Custom styles for this template -->
   <link href="css/style.css" rel="stylesheet">
   <link href="css/style-responsive.css" rel="stylesheet" />
   <script src="/wccms/js/Toast.js"></script>
</head>

<body class="login-body">
   <div class="container">
      <form class="form-signin" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?u=<?= $_GET['u']; ?>">
         <h2 class="form-signin-heading">2FA verification</h2>
         <div class="login-wrap">
            <div class="form-group">
               <label>2FA Code:</label>
               <input type="text" class="form-control" name="2fa" placeholder="~~~~~~" autofocus="">
            </div>
            <button class="btn btn-primary" type="submit" name="submit2fa">Confirm</button>
         </div>
      </form>
   </div>

   <!-- js placed at the end of the document so the pages load faster -->
   <script src="js/jquery.js"></script>
   <script src="js/bootstrap.bundle.min.js"></script>

   <script>
      $(document).ready(function () {
         <?php
         if (count($toast) > 0) {
            echo "const toast = new Toast('" . $toast[0]['message'] . "', '" . $toast[0]['type'] . "');";
            echo "toast.show();";
            if ($toast[0]['redirect']) {
               echo "setTimeout(() => { 
                  window.location.href = '{$toast[0]['redirect']}'; 
               }, 4000);";
            }
         }
         ?>
      });
   </script>
</body>
</html>