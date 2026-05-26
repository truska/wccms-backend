<?php 
/*
// New Dynamic code linking from cms_dashbaord
//Get records from Database
	
		$sqldashboard = "SELECT * FROM `cms_dashboard` WHERE `showonweb = 'Yes' AND `archived` = '0' ORDER BY `sort` "
		$querydashboard = mysqli_query($conn,$sqldashboard);
		$countdashboard = mysqli_num_rows($querydashboard);
	

	// loop and pass variable to get the count for each line

	
		if($countdashboard > 0 ) {
			while($rowdashboard = mysqli_fetch_assoc($querydashboard)) {

				
				// Loop the variious ststs using sql code in d/b and pass fwd to dashboard
				// are move this to dashboard.php or add as a function
				echo "<p>".$rowdashboard."</p>" ;
				$sqlcounter1="select `id` from `cms_users` ";
				$querycount1=mysqli_query($conn,$sqlcount1);
				$count1=mysqli_num_rows($querycount1);
			}
		}

// End New code
*/
//count users
$sqlcountusers="select `id` from `cms_users` ";
$querycountusers=mysqli_query($conn,$sqlcountusers);
$countusers=mysqli_num_rows($querycountusers);

//count orders
$sqlcountorders="select `id` from `order_checkoutdetail` ";
$querycountorders=mysqli_query($conn,$sqlcountorders);
$countorders=mysqli_num_rows($querycountorders);


//count products master
$sqlcountproducts="select `id` from products WHERE `producttype` = 'Main' AND `showonweb` = 'Yes' ";
$querycountproducts=mysqli_query($conn,$sqlcountproducts);
$countproducts=mysqli_num_rows($querycountproducts);

//count associated stock
$sqlcountproducts1="select `id` from products WHERE `producttype` = 'Associate' AND `showonweb` = 'Yes' ";
$querycountproducts1=mysqli_query($conn,$sqlcountproducts1);
$countproducts1=mysqli_num_rows($querycountproducts1);


//count sales
$sqlcountsales="SELECT SUM(orderamount) as total FROM order_checkoutdetail "; 
$querycountsales=mysqli_query($conn,$sqlcountsales);
while ($rowcountsales = mysqli_fetch_assoc($querycountsales)) {
	$countsales = $rowcountsales['total'];
}

?>