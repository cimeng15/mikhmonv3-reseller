<?php
/**
 * Mikhmon V3 - Admin Reseller Management
 */
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){echo "<script>window.location='./admin.php?id=login'</script>"; exit;}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/router_helpers.php';

// Get available router sessions from config
$available_sessions = [];
if (isset($data) && is_array($data)) {
    foreach ($data as $key => $val) {
        if ($key !== 'mikhmon' && is_array($val)) {
            $available_sessions[] = $key;
        }
    }
}

$resellers = getAllResellers();
?>

<div class="mk-admin-page-head">
    <div><h1>Kelola reseller</h1><p>Atur akun, akses router, status, dan saldo reseller.</p></div>
</div>
<?php include __DIR__ . '/admin_tabs.php'; ?>

<?php if (!$showAddReseller): ?>
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default mk-admin-list-panel">
            <div class="panel-heading">
                <h4><i class="fa fa-users"></i> Daftar Reseller
                </h4>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mk-card-table" id="resellerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Telepon</th>
                                <th>Saldo</th>
                                <th>Router</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($resellers)): ?>
                            <tr><td colspan="8" class="text-center">Belum ada reseller</td></tr>
                        <?php else: ?>
                            <?php $no=1; foreach($resellers as $r): ?>
                            <tr id="row-<?=$r['id']?>">
                                <td data-label="No."><?=$no++?></td>
                                <td data-label="Username"><strong><?=htmlspecialchars($r['username'])?></strong></td>
                                <td data-label="Nama"><?=htmlspecialchars($r['name'])?></td>
                                <td data-label="Telepon"><?=htmlspecialchars($r['phone'])?></td>
                                <td data-label="Saldo" class="text-right"><strong>Rp <?=number_format($r['balance'], 0, ',', '.')?></strong></td>
                                <td data-label="Router"><small><?=htmlspecialchars($r['allowed_sessions'] ?: 'Semua')?></small></td>
                                <td data-label="Status" class="text-center">
                                    <?php if($r['status'] === 'active'): ?>
                                        <span class="label label-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="label label-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Aksi" class="mk-action-cell">
                                    <div class="mk-action-group">
                                        <button class="btn btn-success btn-xs btn-deposit" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" title="Tambah saldo">
                                            <i class="fa fa-plus-circle"></i> <span>Deposit</span>
                                        </button>
                                        <button class="btn btn-info btn-xs btn-edit" data-id="<?=$r['id']?>" title="Ubah data reseller">
                                            <i class="fa fa-pencil"></i> <span>Edit</span>
                                        </button>
                                        <?php if($r['status'] === 'active'): ?>
                                        <button class="btn btn-warning btn-xs btn-toggle" data-id="<?=$r['id']?>" title="Nonaktifkan reseller">
                                            <i class="fa fa-ban"></i> <span>Nonaktifkan</span>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-primary btn-xs btn-toggle" data-id="<?=$r['id']?>" title="Aktifkan reseller">
                                            <i class="fa fa-check-circle"></i> <span>Aktifkan</span>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-xs btn-delete" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" title="Hapus reseller">
                                            <i class="fa fa-trash"></i> <span>Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showAddReseller): ?>
<section class="mk-add-reseller-card" aria-labelledby="addResellerTitle">
    <div class="mk-add-reseller-intro">
        <span class="mk-add-reseller-icon"><i class="fa fa-user-plus"></i></span>
        <div>
            <span class="eyebrow">Akun baru</span>
            <h2 id="addResellerTitle">Tambah reseller</h2>
            <p>Buat akun, tentukan router yang dapat digunakan, lalu reseller bisa langsung masuk melalui portal khusus.</p>
        </div>
    </div>
    <form id="formAddReseller" class="mk-add-reseller-form">
        <div class="mk-form-grid">
            <div class="form-group">
                <label for="addUsername">Username <span class="text-danger">*</span></label>
                <input id="addUsername" type="text" name="username" class="form-control" required autocomplete="off" placeholder="Username login reseller">
                <small class="text-muted">Gunakan username yang singkat dan mudah dikenali.</small>
            </div>
            <div class="form-group">
                <label for="addPassword">Password <span class="text-danger">*</span></label>
                <input id="addPassword" type="password" name="password" class="form-control" required autocomplete="new-password" placeholder="Buat password aman">
                <small class="text-muted">Password hanya digunakan untuk portal reseller.</small>
            </div>
            <div class="form-group">
                <label for="addName">Nama lengkap <span class="text-danger">*</span></label>
                <input id="addName" type="text" name="name" class="form-control" required placeholder="Nama pemilik atau outlet">
            </div>
            <div class="form-group">
                <label for="addPhone">Nomor telepon</label>
                <input id="addPhone" type="tel" name="phone" class="form-control" inputmode="tel" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group mk-form-grid-wide">
                <label for="addSessions">Router yang diizinkan</label>
                <select id="addSessions" name="allowed_sessions[]" class="form-control mk-session-select" multiple size="4">
                    <?php foreach($available_sessions as $s): ?>
                    <option value="<?=htmlspecialchars($s)?>"><?=htmlspecialchars($s)?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Kosongkan bila reseller boleh memakai semua router. Gunakan Ctrl atau Command untuk memilih beberapa router.</small>
            </div>
        </div>
        <div class="mk-form-actions">
            <a class="btn btn-default" href="./admin.php?id=resellers"><i class="fa fa-arrow-left"></i> Kembali</a>
            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan Reseller</button>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- Modal Edit Reseller -->
<div class="modal fade" id="modalEditReseller" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><i class="fa fa-times" aria-hidden="true"></i></button>
                <h4 class="modal-title"><i class="fa fa-pencil"></i> Edit Reseller</h4>
            </div>
            <form id="formEditReseller">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="editUsername" class="form-control" disabled>
                    </div>
                    <div class="form-group">
                        <label>Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Aktif</option>
                            <option value="disabled">Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Router yang Diizinkan</label>
                        <select name="allowed_sessions[]" id="editSessions" class="form-control" multiple size="4">
                            <?php foreach($available_sessions as $s): ?>
                            <option value="<?=htmlspecialchars($s)?>"><?=htmlspecialchars($s)?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Kosongkan = akses semua router.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Deposit -->
<div class="modal fade" id="modalDeposit" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><i class="fa fa-times" aria-hidden="true"></i></button>
                <h4 class="modal-title"><i class="fa fa-money"></i> Tambah Deposit</h4>
            </div>
            <form id="formDeposit">
                <input type="hidden" name="reseller_id" id="depositResellerId">
                <div class="modal-body">
                    <p>Reseller: <strong id="depositResellerName"></strong></p>
                    <div class="form-group">
                        <label>Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required min="1000" step="1000" placeholder="50000">
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="description" class="form-control" placeholder="Transfer BCA / Cash">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var processUrl = './reseller/process/admin_process.php';

// Add Reseller
$('#formAddReseller').on('submit', function(e) {
    e.preventDefault();
    $.post(processUrl, $(this).serialize() + '&action=add_reseller', function(res) {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
});

// Edit Button
$('.btn-edit').on('click', function() {
    var id = $(this).data('id');
    $.get(processUrl, {action: 'get_reseller', id: id}, function(res) {
        if (res.success) {
            var d = res.data;
            $('#editId').val(d.id);
            $('#editUsername').val(d.username);
            $('#editName').val(d.name);
            $('#editPhone').val(d.phone);
            $('#editStatus').val(d.status);
            var sessions = d.allowed_sessions ? d.allowed_sessions.split(',') : [];
            $('#editSessions').val(sessions);
            $('#modalEditReseller').modal('show');
        }
    }, 'json');
});

// Edit Submit
$('#formEditReseller').on('submit', function(e) {
    e.preventDefault();
    $.post(processUrl, $(this).serialize() + '&action=edit_reseller', function(res) {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
});

// Delete
$('.btn-delete').on('click', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    if (confirm('Hapus reseller "' + name + '"? Semua data transaksi juga akan dihapus!')) {
        $.post(processUrl, {action: 'delete_reseller', id: id}, function(res) {
            if (res.success) {
                $('#row-' + id).fadeOut();
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json');
    }
});

// Toggle Status
$('.btn-toggle').on('click', function() {
    var id = $(this).data('id');
    $.post(processUrl, {action: 'toggle_reseller', id: id}, function(res) {
        if (res.success) {
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
});

// Deposit
$('.btn-deposit').on('click', function() {
    $('#depositResellerId').val($(this).data('id'));
    $('#depositResellerName').text($(this).data('name'));
    $('#modalDeposit').modal('show');
});

$('#formDeposit').on('submit', function(e) {
    e.preventDefault();
    $.post(processUrl, $(this).serialize() + '&action=add_deposit', function(res) {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
});
</script>
