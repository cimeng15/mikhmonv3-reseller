<?php
/*
 *  Process: Toggle Reseller status (active ↔ disabled)
 *  Called from:  admin.php?id=reseller-toggle&reseller=<id>
 */
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('./include/resellers.php');

$rid = $_GET['reseller'];
reseller_toggle_status($rid);
echo "<script>window.location='./admin.php?id=resellers'</script>";
