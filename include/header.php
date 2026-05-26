<!-- START header -->
<?php
$user_role = $USER->getUserRole();
$cmsBase = "/wccms";

?>
<header class="header white-bg">

    <div class="sidebar-toggle-box">
        <i class="fa fa-bars"></i>
    </div>
    <!--logo start-->
    <a href="<?php echo htmlspecialchars($cmsBase, ENT_QUOTES); ?>/dashboard.php" class="logo">W<span>it</span>eCanvas</a>
	
        <h3 class="d-flex justify-content-center" style="padding-top:10px;"><?php echo $prefs["prefCompanyName"]; ?></h3>

    <div class="top-nav d-flex justify-content-end" style="margin-top:-45px; ">
        <!--search & user info start-->
        <ul class="nav text-right pull-right  top-menu">

            <!-- user login dropdown start-->
            <li class="dropdown">
                <a data-bs-toggle="dropdown" class="dropdown-toggle" href="#">
                    <?php
                    if ($user['image'] == "") {
                        echo '<img class="logged-img-cms" alt="" src="'.htmlspecialchars($cmsBase, ENT_QUOTES).'/img/avatar1_small.jpg" style="max-width:50px;">';
                    } else {
                        echo '<img class="logged-img-cms" alt="" src="/filestore/images/wccms/'.htmlspecialchars($user['image'], ENT_QUOTES).'" style="max-width:50px;">';
                    }
                    ?>
                    <span class="username">Logged in as : <b><?php echo $user['firstname'] . " " . $user['surname']; ?></b></span>

                </a>
                <ul class="dropdown-menu extended logout dropdown-menu-right">
                    <div class="log-arrow-up"></div>

                    <li><a href="<?php echo htmlspecialchars($cmsBase, ENT_QUOTES); ?>/logout.php"><i class="fa fa-key"></i> Log Out</a></li>
                </ul>

            </li>

            <!-- user login dropdown end -->
        </ul>
        <!--search & user info end-->
    </div>
</header>

<!-- END header -->