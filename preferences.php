<?php

// include("include/dbcon.php"); // Removed by salva TDR | 5.12.2022

include('setting/main-top-files.php'); // Added by salva TDR | 9.12.2022

//$lastid=$_SESSION["lastid"];

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <title>Preferences</title>

   <?php
   include("include/header-code.php"); // Added by salva TDR | 9.12.2022
   ?>

   <script>
      $(document).ready(function() {

         //year
         $('#chkyear').change(function() {
            if ($('#chkyear').is(':checked')) {
               year = "Yes";
            } else {
               year = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + year + '&field=year&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });

         //model
         $('#chkmodel').change(function() {
            if ($('#chkmodel').is(':checked')) {
               model = "Yes";
            } else {
               model = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + model + '&field=model&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });


         //reg

         $('#chkreg').change(function() {
            if ($('#chkreg').is(':checked')) {
               reg = "Yes";
            } else {
               reg = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + reg + '&field=reg&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });

         //color

         $('#chkcolor').change(function() {
            if ($('#chkcolor').is(':checked')) {
               color = "Yes";
            } else {
               color = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + color + '&field=colour&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });

         //hp

         $('#chkhp').change(function() {
            if ($('#chkhp').is(':checked')) {
               hp = "Yes";
            } else {
               hp = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + hp + '&field=hp&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });

         //miles
         $('#chkmiles').change(function() {
            if ($('#chkmiles').is(':checked')) {
               miles = "Yes";
            } else {
               miles = "No";
            }

            $.ajax({
               url: 'ajax.changepagepref.php',
               data: 'data=' + miles + '&field=miles&page=product',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });


         //order page

         $('#chkprintorderbtn').change(function() {
            if ($('#chkprintorderbtn').is(':checked')) {
               printbtn = "Yes";
            } else {
               printbtn = "No";
            }

            $.ajax({
               url: 'ajax.changeorderpref.php',
               data: 'data=' + printbtn + '&field=showprintbutton&page=orderlist',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });


         //order pdf button

         $('#chkpdforderbtn').change(function() {
            if ($('#chkpdforderbtn').is(':checked')) {
               pdfbtn = "Yes";
            } else {
               pdfbtn = "No";
            }

            $.ajax({
               url: 'ajax.changeorderpref.php',
               data: 'data=' + pdfbtn + '&field=showpdfbutton&page=orderlist',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });


         //attribute tab
         $('#chkattrtab').change(function() {

            if ($('#chkattrtab').is(':checked')) {
               attrtab = "Yes";
            } else {
               attrtab = "No";
            }

            $.ajax({
               url: 'ajax.changeorderpref.php',
               data: 'data=' + attrtab + '&field=showattributetab&page=productedit',
               type: 'post',
               success: function(msg) {
                  alert(msg)
               }
            })
         });

      })
   </script>
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
                  <h2>Preferences</h2>
                  <section class="card">
                     <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                           <li class="nav-item">
                              <a class="nav-link  active" id="pills-home-tab" data-toggle="pill" href="#pills-home" role="tab" aria-controls="pills-home" aria-selected="false">Product Page</a>
                           </li>

                           <li class="nav-item">
                              <a class="nav-link " id="pills-order-tab" data-toggle="pill" href="#pills-order" role="tab" aria-controls="pills-order" aria-selected="false">Order Page</a>
                           </li>

                        </ul>
                        <div class="tab-content" id="pills-tabContent">
                           <div class="tab-pane fade  active show" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">
                              <h4>Fields</h4>

                              <form method="post">
                                 <table class="table">
                                    <tr>
                                       <td>Model</td>
                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkmodel'>
                                             <label class='custom-control-label' for='chkmodel'>Show on Web</label>
                                          </div>
                                       </td>
                                    </tr>
                                    <tr>
                                       <td>Year</td>
                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkyear'>
                                             <label class='custom-control-label' for='chkyear'>Show on Web</label>
                                          </div>
                                       </td>
                                    </tr>
                                    <tr>
                                       <td>Colour</td>
                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkcolor'>
                                             <label class='custom-control-label' for='chkcolor'>Show on Web</label>
                                          </div>
                                       </td>

                                    </tr>


                                    <tr>



                                       <td>Reg</td>

                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkreg'>
                                             <label class='custom-control-label' for='chkreg'>Show on Web</label>
                                          </div>
                                       </td>

                                    </tr>


                                    <tr>



                                       <td>Miles</td>

                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkmiles'>
                                             <label class='custom-control-label' for='chkmiles'>Show on Web</label>
                                          </div>
                                       </td>

                                    </tr>


                                    <tr>



                                       <td>HP</td>

                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkhp'>
                                             <label class='custom-control-label' for='chkhp'>Show on Web</label>
                                          </div>
                                       </td>

                                    </tr>









                                 </table>

                                 <h4>Tabs</h4>


                                 <table class="table">







                                    <tr>



                                       <td>Attribute Tab</td>

                                       <td>
                                          <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                             <input type='checkbox' class='custom-control-input' id='chkattrtab'>
                                             <label class='custom-control-label' for='chkattrtab'>Show on Web</label>
                                          </div>
                                       </td>

                                    </tr>


                                 </table>

                              </form>



                           </div>


                           <div class="tab-pane fade" id="pills-order" role="tabpanel" aria-labelledby="pills-order-tab">

                              <h4>Order Detail Page</h4>
                              <table class="table">







                                 <tr>



                                    <td>Show Print Button</td>

                                    <td>
                                       <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                          <input type='checkbox' class='custom-control-input' id='chkprintorderbtn'>
                                          <label class='custom-control-label' for='chkprintorderbtn'>Show on Web</label>
                                       </div>
                                    </td>

                                 </tr>


                                 <tr>



                                    <td>Show PDF Button</td>

                                    <td>
                                       <div class='custom-control custom-switch' style='margin-left:10px;margin-top:10px'>
                                          <input type='checkbox' class='custom-control-input' id='chkpdforderbtn'>
                                          <label class='custom-control-label' for='chkpdforderbtn'>Show on Web</label>
                                       </div>
                                    </td>
                                 </tr>
                              </table>

                           </div>
                        </div>
                  </section>
               </div>
            </div>
         </section>
      </section>

      <footer class="site-footer">
         <div class="text-center">
            2018 &copy; FlatLab by VectorLab.
            <a href="#" class="go-top">
               <i class="fa fa-angle-up"></i>
            </a>
         </div>
      </footer>
      <!--footer end-->
   </section>

   <!-- js placed at the end of the document so the pages load faster -->
   <script src="js/bootstrap.bundle.min.js"></script>
   <script class="include" type="text/javascript" src="js/jquery.dcjqaccordion.2.7.js"></script>
   <script src="js/jquery.scrollTo.min.js"></script>
   <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
   <script src="js/respond.min.js"></script>
   <!--right slidebar-->
   <script src="js/slidebars.min.js"></script>
   <!--Form Validation-->
   <script src="js/bootstrap-validator.min.js" type="text/javascript"></script>
   <!--Form Wizard-->
   <script src="js/jquery.steps.min.js" type="text/javascript"></script>
   <script src="js/jquery.validate.min.js" type="text/javascript"></script>
   <!--common script for all pages-->
   <script src="js/common-scripts.js"></script>
   <!--script for this page-->
   <script src="js/jquery.stepy.js"></script>

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
</body>

</html>