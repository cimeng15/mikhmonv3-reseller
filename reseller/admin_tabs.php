<?php
$currentAdminSection = $_GET['id'] ?? 'resellers';
$showAddReseller = $currentAdminSection === 'resellers' && (($_GET['view'] ?? '') === 'add');
?>
<nav class="mk-admin-tabs" aria-label="Menu reseller">
    <a class="<?=$currentAdminSection === 'resellers' && !$showAddReseller ? 'active' : ''?>" href="./admin.php?id=resellers">
        <i class="fa fa-list"></i><span>Daftar Reseller</span>
    </a>
    <a class="<?=$showAddReseller ? 'active' : ''?>" href="./admin.php?id=resellers&amp;view=add">
        <i class="fa fa-user-plus"></i><span>Tambah Reseller</span>
    </a>
    <a class="<?=$currentAdminSection === 'reseller-deposits' ? 'active' : ''?>" href="./admin.php?id=reseller-deposits">
        <i class="fa fa-credit-card"></i><span>Deposit</span>
    </a>
</nav>
