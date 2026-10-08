<?php
require 'config.php'; require 'layout.php'; require_login();
$v = $_GET['v'] ?? 'ledger';
if (!in_array($v, ['ledger','trial','financial'], true)) $v = 'ledger';
$balances = account_balances($pdo);

if ($v === 'trial') {
    $tb = []; $totD = $totC = 0;
    foreach ($balances as $b) {
        if (abs($b['balance']) < 0.005) continue;
        $debitCol = (($b['debit_normal'] && $b['balance'] >= 0) || (!$b['debit_normal'] && $b['balance'] < 0)) ? abs($b['balance']) : 0;
        $creditCol = $debitCol ? 0 : abs($b['balance']);
        $totD += $debitCol; $totC += $creditCol;
        $tb[] = ['code'=>$b['account_code'], 'name'=>$b['account_name'], 'debit'=>$debitCol, 'credit'=>$creditCol];
    }
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="trial_balance_' . date('Ymd') . '.csv"');
        $o = fopen('php://output', 'w');
        fputcsv($o, ['Code','Account','Debit','Credit']);
        foreach ($tb as $r) fputcsv($o, [$r['code'], $r['name'], number_format($r['debit'],2,'.',''), number_format($r['credit'],2,'.','')]);
        fclose($o); audit($pdo, 'Exported trial balance'); exit;
    }
    layout_start('trial', 'Trial Balance', 'Summarized account balances, verifying that total debits equal total credits.');
    table_open(['Account','Debit','Credit'], [0,1,1]);
    foreach ($tb as $r): ?>
  <tr><td><?=h($r['code'] . ' · ' . $r['name'])?></td><td class="num"><?=$r['debit'] ? h(peso($r['debit'])) : ''?></td><td class="num"><?=$r['credit'] ? h(peso($r['credit'])) : ''?></td></tr>
<?php endforeach; table_close(3, !$tb); ?>
<div class="je-totals" style="margin-top:14px">
  <span>Total debits <b class="num"><?=h(peso($totD))?></b></span><span>Total credits <b class="num"><?=h(peso($totC))?></b></span>
  <?=pill(abs($totD - $totC) < 0.005 ? 'Valid' : 'Invalid')?>
  <a class="btn ghost small" href="reports.php?v=trial&export=1" style="margin-left:auto">Export CSV</a>
</div>
<?php layout_end(); exit; }

if ($v === 'financial') {
    $revenue = sum_by_type($balances, 'Revenue'); $expense = sum_by_type($balances, 'Expense');
    $net = $revenue - $expense;
    $assets = sum_by_type($balances, 'Asset'); $liab = sum_by_type($balances, 'Liability');
    $equity = sum_by_type($balances, 'Equity') + $net;
    layout_start('reports', 'Financial Reports', 'Income statement and balance sheet, generated from posted (balanced) journal entries.'); ?>
<section class="block"><h2>Income Statement</h2><div class="card">
  <div class="flex-between"><span>Total revenue</span><b class="num"><?=h(peso($revenue))?></b></div>
  <div class="flex-between"><span>Total expenses</span><b class="num"><?=h(peso($expense))?></b></div>
  <hr class="rule"><div class="flex-between"><b>Net income</b><b class="num" style="color:var(<?=$net >= 0 ? '--green-ok' : '--rust'?>)"><?=h(peso($net))?></b></div>
</div></section>
<section class="block"><h2>Balance Sheet</h2><div class="grid cols-2">
  <div class="card"><h3 style="font-size:.95rem">Assets</h3>
    <?php foreach ($balances as $b) if ($b['account_type'] === 'Asset'): ?><div class="flex-between small"><span><?=h($b['account_name'])?></span><span class="num"><?=h(peso($b['balance']))?></span></div><?php endif; ?>
    <hr class="rule"><div class="flex-between"><b>Total assets</b><b class="num"><?=h(peso($assets))?></b></div></div>
  <div class="card"><h3 style="font-size:.95rem">Liabilities &amp; Equity</h3>
    <?php foreach ($balances as $b) if ($b['account_type'] === 'Liability'): ?><div class="flex-between small"><span><?=h($b['account_name'])?></span><span class="num"><?=h(peso($b['balance']))?></span></div><?php endif; ?>
    <div class="flex-between small"><span>Owner's equity (incl. net income)</span><span class="num"><?=h(peso($equity))?></span></div>
    <hr class="rule"><div class="flex-between"><b>Total liabilities &amp; equity</b><b class="num"><?=h(peso($liab + $equity))?></b></div></div>
</div></section>
<?php layout_end(); exit; }

// ---- General ledger ----
$rows = $pdo->query("SELECT a.account_id, j.entry_date, j.description, l.debit_amount, l.credit_amount
    FROM journal_lines l JOIN journal_entries j ON j.entry_id=l.entry_id JOIN chart_of_accounts a ON a.account_id=l.account_id
    WHERE j.ai_validation_status='Valid' ORDER BY j.entry_date, j.entry_id, l.line_id")->fetchAll();
$byAcc = [];
foreach ($rows as $r) if ($r['debit_amount'] > 0 || $r['credit_amount'] > 0) $byAcc[$r['account_id']][] = $r;
layout_start('ledger', 'General Ledger', 'Balanced journal entries posted automatically to each account.');
$any = false;
foreach ($balances as $a):
    if (empty($byAcc[$a['account_id']])) continue; $any = true; $running = 0; ?>
<section class="block"><h2><?=h($a['account_code'] . ' · ' . $a['account_name'])?> <span class="tag"><?=h($a['account_type'])?></span></h2>
<?php table_open(['Date','Memo','Debit','Credit','Balance'], [0,0,1,1,1]);
      foreach ($byAcc[$a['account_id']] as $l):
          $running += $a['debit_normal'] ? $l['debit_amount'] - $l['credit_amount'] : $l['credit_amount'] - $l['debit_amount']; ?>
  <tr><td><?=h($l['entry_date'])?></td><td><?=h($l['description'])?></td><td class="num"><?=$l['debit_amount'] > 0 ? h(peso($l['debit_amount'])) : ''?></td><td class="num"><?=$l['credit_amount'] > 0 ? h(peso($l['credit_amount'])) : ''?></td><td class="num"><?=h(peso($running))?></td></tr>
<?php endforeach; table_close(5); ?></section>
<?php endforeach;
if (!$any) echo '<div class="empty">No balanced journal entries have been posted yet.</div>';
layout_end();
