<!-- START base page [20250710] -->
<!DOCTYPE html>

<?php
// Turn off error reporting
// error_reporting(0);
// Turn on error reporting
 error_reporting(1);

include('setting/main-top-files.php'); 

//Load any data

?>

<html lang="en">

<head>
    <?php
        include("include/header-code.php");
    ?>

</head>

<body>
    <section id="container" class="">
        <?php
        include("include/header.php");
        include("include/sidebar.php");
        ?>


        <section id="main-content">
            <section class="wrapper site-min-height">

                <!-- START page main body area -->
                <section class="card" style="width:100%;margin-left: -10px">
                    <div class="row">
                        <div class="card-body">
                            <!-- <div class="col-md-1 hidden-sm hidden-xs"></div>  -->
                            <div class="col-sm-12 col-md-10 col-lg-10" style="margin-top:20px;">
                                <h2>Top area / heading</h2>
                            </div>


                            <?php


                            // START FOOTER FIXED STUFF

                            include("include/footer-code.php");
                            include("include-tinymce.php");
                            ?>
                        </div>
                    </div>
                </section>
                <!-- END page main body area -->
            </section>
        </section>
    </section>

</body>

</html>

<!-- END base page -->