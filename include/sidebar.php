<!-- START sidebar -->
<!-- TruskaCMS ver 4.1.0 -->

<?php


//Get User Level

// We got the user role from the current user_role var from header.php
// * The header.php is always added before sidebar.php () *
$userlevel = $user_role['level'];
$userid = $user["id"];?>

<aside>
	<div id='sidebar' class='nav-collapse'>
		<ul class='sidebar-menu' id='nav-accordion'>
			<?php
			$MENU = new Menu($userlevel);
			$menu = $MENU->getMenu();

			if ($menu) {
				foreach ($menu as $option) {
					$submenu = $MENU->getSubMenu($option['section']);

					$icon = ($option["icon"] != '0') ? $MENU->getIcon($option["icon"]) : 'fa fa-list-alt';

					if (count($submenu) > 0) {
						$collapseId = "collapse-section-" . $option['id'];
						echo "<li id=\"menu-{$option['id']}\" class=\"menu-i\">";
							echo "<a href=\"#{$collapseId}\" data-bs-toggle=\"collapse\" role=\"button\" aria-expanded=\"false\" aria-controls=\"{$collapseId}\">";
								echo "<i class='{$icon}'></i><span>{$option["title"]}</span>";
							echo "</a>";

							echo "<ul class='collapse sub-menu' id='{$collapseId}'>";
								foreach ($submenu as $suboption) {
									echo "<li id=\"submenu-{$suboption['id']}\" class=\"submenu-i\">";
										$href = "/wccms/" . ltrim($suboption["url"], "/") . "?frm=" . $suboption["form"] . $suboption["var1"];
						echo "<a href='{$href}' target='{$suboption["target"]}'>";
											echo $suboption["title"];
										echo "</a>";
									echo "</li>";
								}
							echo "</ul>";
						echo "</li>";
					} else {
						echo "<li id=\"menu-{$option['id']}\" class=\"menu-i\">";
							$href = "/wccms/" . ltrim($option["url"], "/");
						echo "<a href='{$href}'>";
								echo "<i class='{$icon}'></i><span>{$option["title"]}</span>";
							echo "</a>";
						echo "</li>";
					}
				}
			}

			// TruskaAdmin Menu
			// Add TruskaAdmin menu if user email domain is @truska.com
			
			if (isset($user["username"]) && substr($user["username"], -11) === "@truska.com") {
				echo "<li class='menu-i'>";
					echo "<a href='#truskaAdminMenu' data-bs-toggle='collapse' role='button' aria-expanded='false' aria-controls='truskaAdminMenu'>";
						echo "<i class='fa fa-tools'></i><span>Truska Admin</span>";
					echo "</a>";
					echo "<ul class='collapse sub-menu' id='truskaAdminMenu'>";						echo "<li class='submenu-i'><a href='/wccms/admin-database-fields.php'>Database Admin</a></li>";						echo "<li class='submenu-i'><a href='/wccms/recordBulkUpdatev1.php'>Bulk Record Updater</a></li>";
					echo "</ul>";
				echo "</li>";
			}
				echo "<li class='menu-j'>";
					echo "<a href='/wccms/logout.php'>";
						echo "<i class='fa fa-sign-out-alt'></i><span>Log Out</span>";
					echo "</a>";
				echo "</li>";
			?>

		</ul>

		<?php
		echo "<div class='sidebarinfo'>";
			echo "<br>Site: " . getSiteName($prefs);
			echo "<br>User: " . $user["firstname"] . " " . $user["surname"];
			echo "<br>Username: " . $user["username"];
			echo "<br>Role: " . $user["userrole"];
			echo "<br>ID: " . $userid;
			echo "<br>CMS Ver.: " . $prefs["prefCMSVer"];
			echo "<br>User IP: " . $_SERVER['REMOTE_ADDR'];
			echo "<hr>";
			if ($prefs['prefCMSManual']) {
				echo "<a href='/wccms/docs/{$prefs['prefCMSManual']}' target='_blank'><i class='far fa-file-pdf'></i>&nbsp;&nbsp;Manual</a><br>";
			}
			if ($prefs['prefDropboxRequestURL']) {
				echo "<a href='{$prefs['prefDropboxRequestURL']}' target='_blank'><i class='fab fa-dropbox'></i>&nbsp;&nbsp;DropBox File Request Link</a><br>";
			}
			echo "<hr>";
			echo "<img src='/wccms/img/wite-canvas-logo-sq-no-tag-150.jpg' style='max-width:60px' alt='wITeCanvas CMS'><br>";
			echo "&copy; copyright<br>w&nbsp;<span style='color:#8AC340;'>IT</span>&nbsp;ecanvas.com 2020 - " . date("Y") . "<br>";
			echo "Ver: 3.0.0";
		echo "</div>";
		echo "<hr>";
		?>
	</div>
</aside>

<!-- END sidebar -->