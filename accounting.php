<?php
require 'config.php'; require 'layout.php'; require_login();
$role    = $_SESSION['user']['role'];
$userId  = (int)$_SESSION['user']['user_id'];
$canEdit = in_array($role, ['Admin','Faculty'], true);
$v = (($_POST['v'] ?? $_GET['v'] ?? 'coa') === 'journal') ? 'journal' : 'coa';
 
// Normal balance side for each account type ("Debit/Credit Nature" column).
function normal_balance($type) {
    return in_array($type, ['Asset', 'Expense'], true) ? 'Debit' : 'Credit';
}
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_account') {
            require_role(['Admin','Faculty']);
            $stmt = $pdo->prepare("INSERT INTO chart_of_accounts(account_code,account_name,account_type,description) VALUES(?,?,?,?)");
            $stmt->execute([trim($_POST['code']), trim($_POST['name']), $_POST['type'], trim($_POST['description'] ?? '')]);
            audit($pdo, 'Added chart of account');
            flash('ok', 'Account added.');
        } elseif ($action === 'delete_account') {
            require_role(['Admin','Faculty']);
            $id = (int)($_POST['account_id'] ?? 0);
            $used = $pdo->prepare("SELECT COUNT(*) FROM journal_lines WHERE account_id=?");
            $used->execute([$id]);
            if ($used->fetchColumn() > 0) {
                flash('warn', 'Cannot remove — account is used in journal entries.');
            } else {
                $pdo->prepare("DELETE FROM chart_of_accounts WHERE account_id=?")->execute([$id]);
                audit($pdo, 'Removed a chart-of-accounts entry');
                flash('ok', 'Account removed.');
            }
        } elseif ($action === 'journal') {
            $r = save_journal_entry(
                $pdo, $userId, $_POST['entry_date'] ?? date('Y-m-d'), trim($_POST['description'] ?? ''),
                $_POST['account_id'] ?? [], $_POST['debit'] ?? [], $_POST['credit'] ?? []
            );
            if ($r['ok']) audit($pdo, 'Recorded journal entry: ' . ($r['valid'] ? 'Valid' : 'Invalid'));
            flash($r['ok'] && $r['valid'] ? 'ok' : 'warn', $r['message']);
        }
    } catch (PDOException $e) {
        flash('warn', $e->getCode() === '23000' ? 'That account code already exists.' : 'Database error.');
    }
    header('Location: accounting.php?v=' . $v); exit;
}
 
$accounts = $pdo->query("SELECT * FROM chart_of_accounts ORDER BY account_code")->fetchAll();
 
if ($v === 'coa') {
    layout_start('coa', 'Chart of Accounts', 'All accounts in one organized table.');
?>

<div class="flex-between" style="margin-bottom:12px">
    <span class="muted small">
        <?=count($accounts)?> accounts · as of <?=h(date('F d, Y'))?>
    </span>

    <?php if ($canEdit): ?>
        <button class="btn brass" onclick="document.getElementById('accDlg').showModal()">
            + New account
        </button>
    <?php endif; ?>
</div>

<section class="block">

    <?php table_open(
        ['Code','Account Name','Type','Debit/Credit Nature','Description','Action'],
        [0,0,0,0,0,0]
    ); ?>

    <?php foreach ($accounts as $a): ?>

        <tr>
            <td class="num">
                <?=h($a['account_code'])?>
            </td>

            <td>
                <?=h($a['account_name'])?>
            </td>

            <td>
                <?=h($a['account_type'])?>
            </td>

            <td>
                <?=h(normal_balance($a['account_type']))?>
            </td>

            <td>
                <?=h($a['description'] ?? '')?>
            </td>

            <td style="width:110px">
                <?php if ($canEdit): ?>

                    <form method="post"
                          onsubmit="return confirm('Remove this account?')">

                        <input type="hidden"
                               name="csrf"
                               value="<?=h(csrf())?>">

                        <input type="hidden"
                               name="action"
                               value="delete_account">

                        <input type="hidden"
                               name="v"
                               value="coa">

                        <input type="hidden"
                               name="account_id"
                               value="<?=(int)$a['account_id']?>">

                        <button class="btn ghost small">
                            Remove
                        </button>

                    </form>

                <?php endif; ?>
            </td>
        </tr>

    <?php endforeach; ?>

    <?php table_close(6); ?>

</section>

<?php if ($canEdit): ?>

<dialog id="accDlg" class="modal">

    <h3>New Account</h3>

    <form method="post" class="form-grid cols-1">

        <input type="hidden"
               name="csrf"
               value="<?=h(csrf())?>">

        <input type="hidden"
               name="action"
               value="add_account">

        <input type="hidden"
               name="v"
               value="coa">

        <div>
            <label class="field-label">Account Code</label>
            <input
                name="code"
                required
                placeholder="e.g. 102">
        </div>

        <div>
            <label class="field-label">Account Name</label>
            <input
                name="name"
                required
                placeholder="e.g. Supplies">
        </div>

        <div>
            <label class="field-label">Type</label>

            <select name="type">
                <option>Asset</option>
                <option>Liability</option>
                <option>Equity</option>
                <option>Revenue</option>
                <option>Expense</option>
            </select>
        </div>

        <div>
            <label class="field-label">Description</label>

            <input
                name="description"
                placeholder="Optional">
        </div>

        <div class="actions">

            <button
                type="button"
                class="btn ghost"
                onclick="this.closest('dialog').close()">
                Cancel
            </button>

            <button class="btn brass">
                Add Account
            </button>

        </div>

    </form>

</dialog>

<?php endif;

    layout_end();
    exit;
}
 
// ---------- Journal Entries ----------
$entries = recent_journal_entries($pdo, 50, $role === 'Student' ? $userId : null);
$lines   = journal_lines_for($pdo, array_column($entries, 'entry_id'));
layout_start('journal', 'Journal Entries',
    'Record debit-and-credit transactions. The validation engine checks that every entry balances before it posts to the ledger.',
    'AI-assisted validation');
 
function je_line() { ?>
    <div class="je-line">
      <div><label class="field-label">Account</label>
        <input type="text" name="account_id[]" placeholder="Account code or name" autocomplete="off"></div>
      <div><label class="field-label">Debit</label><input type="number" min="0" step="0.01" name="debit[]" oninput="updateTotals()"></div>
      <div><label class="field-label">Credit</label><input type="number" min="0" step="0.01" name="credit[]" oninput="updateTotals()"></div>
      <div><button type="button" class="btn ghost small" onclick="removeLine(this)">Remove</button></div>
    </div>
<?php } ?>
<section class="block"><h2>New journal entry</h2>
<div class="card">
<form method="post">
  <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="journal"><input type="hidden" name="v" value="journal">
  <div class="form-grid" style="display:grid">
    <div><label class="field-label">Date</label><input type="date" name="entry_date" value="<?=date('Y-m-d')?>" required></div>
    <div><label class="field-label">Memo / description</label><input name="description" required placeholder="e.g. Paid utilities for the month"></div>
  </div>
  <div class="je-lines" id="jeLines" style="margin-top:12px"><?php je_line(); je_line(); ?></div>
  <button type="button" class="btn ghost small" style="margin-top:8px" onclick="addLine()">+ Add line</button>
  <div class="je-totals" id="jeTotals"></div>
  <div id="jeBanner"></div>
  <div style="margin-top:12px"><button type="submit" class="btn brass">Validate &amp; post entry</button></div>
</form></div></section>
 
<section class="block"><h2>All entries <span class="tag"><?=count($entries)?> shown</span></h2>
<?php if (!$entries): ?><div class="empty">No journal entries yet.</div><?php endif; ?>
<div class="card-stack">
<?php foreach ($entries as $e): ?>
  <div class="card">
    <div class="flex-between"><div><b><?=h($e['entry_date'])?></b> &middot; <?=h($e['description'])?> <span class="small muted">— <?=h($e['name'])?></span></div><?=pill($e['ai_validation_status'])?></div>
    <div style="margin-top:8px">
    <?php table_open(['Account','Debit','Credit'], [0,1,1]);
          foreach ($lines[$e['entry_id']] ?? [] as $l): ?>
      <tr><td><?=h($l['account_code'] . ' · ' . $l['account_name'])?></td><td class="num"><?=$l['debit_amount'] > 0 ? h(peso($l['debit_amount'])) : ''?></td><td class="num"><?=$l['credit_amount'] > 0 ? h(peso($l['credit_amount'])) : ''?></td></tr>
    <?php endforeach; table_close(3); ?>
    </div>
  </div>
<?php endforeach; ?>
</div></section>
<script>
function peso(n){ return '₱' + n.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function sumOf(name){ var t=0; document.querySelectorAll('input[name="'+name+'[]"]').forEach(function(i){ t+=parseFloat(i.value)||0; }); return t; }
function updateTotals(){
  var d=sumOf('debit'), c=sumOf('credit'), diff=Math.round((d-c)*100)/100;
  document.getElementById('jeTotals').innerHTML='<span>Total debits <b class="num">'+peso(d)+'</b></span><span>Total credits <b class="num">'+peso(c)+'</b></span>';
  var b=document.getElementById('jeBanner');
  if((d||c) && diff!==0) b.innerHTML='<div class="banner warn" style="margin-top:10px">⚠ Not balanced — debits and credits differ by '+peso(Math.abs(diff))+'. The entry will be saved as Unbalanced.</div>';
  else if(d && c && diff===0) b.innerHTML='<div class="banner ok" style="margin-top:10px">✓ Balanced — debits equal credits. Ready to post.</div>';
  else b.innerHTML='';
}
function addLine(){
  var w=document.getElementById('jeLines'), r=w.lastElementChild.cloneNode(true);
  r.querySelectorAll('input').forEach(function(i){ i.value=''; });
  w.appendChild(r); updateTotals();
}
function removeLine(btn){
  var w=document.getElementById('jeLines');
  if(w.children.length>2){ btn.closest('.je-line').remove(); updateTotals(); }
}
updateTotals();
</script>
<?php layout_end(); ?>
 