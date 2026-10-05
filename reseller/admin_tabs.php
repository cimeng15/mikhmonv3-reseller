<?php
$currentAdminSection = $_GET['id'] ?? 'resellers';
?>
<nav class="mk-admin-tabs" aria-label="Menu reseller">
    <a class="<?=$currentAdminSection === 'resellers' ? 'active' : ''?>" href="./admin.php?id=resellers">
        <i class="fa fa-list"></i><span>Daftar Reseller</span>
    </a>
    <a class="<?=$currentAdminSection === 'reseller-deposits' ? 'active' : ''?>" href="./admin.php?id=reseller-deposits">
        <i class="fa fa-credit-card"></i><span>Saldo dan Transaksi</span>
    </a>
</nav>
