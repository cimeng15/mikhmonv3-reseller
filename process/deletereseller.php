<?php
/*
 *  Process: Delete Reseller
 *  Called from:  admin.php?id=reseller-delete&reseller=<id>
 */
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('./include/resellers.php');

$rid = $_GET['reseller'];
reseller_delete($rid);
echo "<script>window.location='./admin.php?id=resellers'</script>";
