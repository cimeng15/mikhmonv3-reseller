<?php
/**
 * Mikhmon V3 - Admin Reseller Management
 */
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){echo "<script>window.location='./admin.php?id=login'</script>"; exit;}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/models.php';

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

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-users"></i> Reseller Management
                    <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#modalAddReseller">
                        <i class="fa fa-plus"></i> Tambah Reseller
                    </button>
                </h4>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="resellerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Telepon</th>
                                <th>Saldo</th>
                                <th>Diskon</th>
                                <th>Sessions</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($resellers)): ?>
                            <tr><td colspan="9" class="text-center">Belum ada reseller</td></tr>
                        <?php else: ?>
                            <?php $no=1; foreach($resellers as $r): ?>
                            <tr id="row-<?=$r['id']?>">
                                <td><?=$no++?></td>
                                <td><strong><?=htmlspecialchars($r['username'])?></strong></td>
                                <td><?=htmlspecialchars($r['name'])?></td>
                                <td><?=htmlspecialchars($r['phone'])?></td>
                                <td class="text-right"><strong>Rp <?=number_format($r['balance'], 0, ',', '.')?></strong></td>
                                <td class="text-center"><?=$r['discount']?>%</td>
                                <td><small><?=htmlspecialchars($r['allowed_sessions'] ?: 'Semua')?></small></td>
                                <td class="text-center">
                                    <?php if($r['status'] === 'active'): ?>
                                        <span class="label label-success">Active</span>
                                    <?php else: ?>
                                        <span class="label label-danger">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-xs">
                                        <button class="btn btn-info btn-deposit" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" title="Deposit">
                                            <i class="fa fa-money"></i>
                                        </button>
                                        <button class="btn btn-warning btn-edit" data-id="<?=$r['id']?>" title="Edit">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <button class="btn btn-default btn-toggle" data-id="<?=$r['id']?>" title="Toggle Status">
                                            <i class="fa fa-power-off"></i>
                                        </button>
                                        <button class="btn btn-danger btn-delete" data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['name'])?>" title="Hapus">
                                            <i class="fa fa-trash"></i>
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

<!-- Modal Add Reseller -->
<div class="modal fade" id="modalAddReseller" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-user-plus"></i> Tambah Reseller</h4>
            </div>
            <form id="formAddReseller">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="username login reseller">
                    </div>
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>Diskon (%)</label>
                        <input type="number" name="discount" class="form-control" value="0" min="0" max="100" step="0.5">
                    </div>
                    <div class="form-group">
                        <label>Allowed Sessions (Router)</label>
                        <select name="allowed_sessions[]" class="form-control" multiple size="4">
                            <?php foreach($available_sessions as $s): ?>
                            <option value="<?=htmlspecialchars($s)?>"><?=htmlspecialchars($s)?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Kosongkan = akses semua router. Ctrl+Click untuk pilih banyak.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Reseller -->
<div class="modal fade" id="modalEditReseller" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
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
                        <label>Diskon (%)</label>
                        <input type="number" name="discount" id="editDiscount" class="form-control" min="0" max="100" step="0.5">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Allowed Sessions (Router)</label>
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
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update</button>
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
                <button type="button" class="close" data-dismiss="modal">&times;</button>
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
            $('#editDiscount').val(d.discount);
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
