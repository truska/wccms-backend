<?php


include("include/dbcon.php");

include("logrecord.php");
//$lastid=$_SESSION["lastid"];

$username=$_SESSION["useremail"];

$sql="select * from cms_adminlogin where username='$username'";
$query=mysqli_query($conn,$sql);

$resgetid=mysqli_fetch_assoc($query);

$id=$resgetid["id"];

$msg=" <a href='https://microaid.witecanvas.com/wccms/resetpassword.php?id=$id>Click Here to Change Password</a>";

$to = "$username";
$subject = "Password Change Request";
$txt = "Hello world!";
$headers = "From: webmaster@example.com" ;


if(mail($to,$subject,$txt,$headers)){
    echo "ok";
}
/*

if(isset($_POST["submit"])){

        $oldpassword=$_POST["oldpassword"];
        $newpassword=$_POST["newpassword"];
        $retypepassword=$_POST["retypepassword"];


        $oldhash=md5($oldpassword);

        $email=$_SESSION["useremail"];
        $sqlgetpassword="SELECT * FROM `cms_adminlogin` where username='$email' and password='$oldhash'";
        $querygetpassword=mysqli_query($conn,$sqlgetpassword);
        $countuser=mysqli_num_rows($querygetpassword);
        
        if($countuser>0){

           if($newpassword==$retypepassword){

                $newhash=md5($newpassword);
                $sqlupdatepassword="update cms_adminlogin set password='$newhash' where username='$email'";
                $queryupdatepassword=mysqli_query($conn,$sqlupdatepassword);
                if($queryupdatepassword){
                    echo "<script>alert('Password Changed !')</script>";
                }
                else{
                    echo "Error ! ";
                }
           }
           else{

                echo "<script>alert('Password not matched!')</script>";
           }
        }
        else{

            echo "<script>alert('Invalid old Password !')</script>";
        }

        

        



}
*/


?>

<!DOCTYPE html>
<html lang="en">


<head>
   <?php include("include/header-code.php");?>

</head>

<body>

<section id="container" class="">
    <?php
    include("include/header.php");
    include("include/sidebar.php");
    ?>
    <!--main content start-->
    <section id="main-content">
        <section class="wrapper site-min-height">
            <!-- page start-->
            <div class="row">
                <div class="col-lg-12">

                   <center> <h2>Password Change Request Email Sent to <?php echo $username?></h2></center>
                    <section class="card">

                        <div class="card-body">

                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade  active show" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">




                                    <form method="post" style="display:none">

                                        <table  class="table">



                                            <tr >



                                                <td>Old Password</td>

                                                <td  colspan="3"> <input type="text" name="oldpassword" required class="form-control"></td>

                                            </tr>

                                            <tr >



                                            <td>New Password</td>

                                                <td  colspan="3"> <input type="text" name="newpassword" required class="form-control"></td>

                                            </tr>


                                            <tr >



                                                <td>Re-type Password</td>

                                                <td  colspan="3"> <input type="text" name="retypepassword" required class="form-control"></td>

                                            </tr>






                                           


                                            

                                           
                                           





                                            <tr>
                                                <td colspan=3 align="center"> <input type="submit" name="submit" value="Change Password" class="btn btn-success" style="width:20%"></td>

                                            </tr>




                                        </table>



                                    </form>



                                </div>

                            </div>
                    </section>


                </div>
            </div>

        </section>
    </section>

  

<!-- js placed at the end of the document so the pages load faster -->

 <?php include("include/footer-code.php");?>



<script>

    //step wizard

    $(function() {
        $('#default').stepy({
            backLabel: 'Previous',
            block: true,
            nextLabel: 'Next',
            titleClick: true,
            titleTarget: '.stepy-tab'
        });
    });
</script>

<script type="text/javascript">
    $(document).ready(function () {
        var form = $("#wizard-validation-form");
        form.validate({
            errorPlacement: function errorPlacement(error, element) {
                element.after(error);
            }
        });
        form.children("div").steps({
            headerTag: "h3",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            onStepChanging: function (event, currentIndex, newIndex) {
                form.validate().settings.ignore = ":disabled,:hidden";
                return form.valid();
            },
            onFinishing: function (event, currentIndex) {
                form.validate().settings.ignore = ":disabled";
                return form.valid();
            },
            onFinished: function (event, currentIndex) {
                alert("Submitted!");
            }
        }).validate({
            errorPlacement: function errorPlacement(error, element) {
                element.after(error);
            },
            rules: {
                confirm: {
                    equalTo: "#password"
                }
            }
        });
    });


</script>



</body>


</html>
