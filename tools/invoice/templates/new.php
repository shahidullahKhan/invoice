<?php
/** Upload form + draft review. @var array|null $draft @var array $missing @var string $jiraBase @var array $user */
use InvoiceTool\Format;
?>
<div class="card p-4 mb-4">
    <h1 class="h5 mb-3">1. Upload Time Doctor CSV</h1>
    <form method="post" action="<?= url('upload') ?>" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
        <?= csrf() ?>
        <input type="file" name="csv" accept=".csv,text/csv" class="form-control" style="max-width:420px" required>
        <button class="btn btn-primary">Upload &amp; combine</button>
    </form>
    <div class="form-text">Time Doctor → Reports → Projects &amp; Tasks → Daily → Export CSV. Uploading replaces the current draft.</div>
    <hr>
    <?php $back = 'new'; require __DIR__ . '/jira_status.php'; ?>
</div>

<?php if ($draft): ?>
    <?php if ($missing): ?>
        <div class="card p-4 mb-4 border-start border-4 border-warning">
            <h2 class="h5">Missing Bill Codes (<?= count($missing) ?>)</h2>
            <p class="small text-muted mb-2">Open each ticket, set its <b>Application Code</b> in Jira, then click Re-check.</p>
            <ul class="mb-3">
                <?php foreach ($missing as $key => $title): ?>
                    <li><a href="<?= e($jiraBase . '/browse/' . $key) ?>" target="_blank" rel="noopener"><?= e($key) ?></a> — <?= e($title) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= url('recheck') ?>"><?= csrf() ?><button class="btn btn-outline-secondary btn-sm">Re-check from Jira</button></form>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">2. Review</h2>
                <div class="text-muted small">
                    <?= e(Format::period($draft['period_start'], $draft['period_end'])) ?> ·
                    <?= count($draft['items']) ?> entries · <?= Format::hhmm((int) $draft['total_seconds']) ?> hrs ·
                    $<?= Format::money(Format::amount((int) $draft['total_seconds'], (float) $user['rate'])) ?> at <?= secret('$' . $user['rate'] . '/hr') ?>
                </div>
            </div>
            <form method="post" action="<?= url('generate') ?>"
                  <?php if ($missing): ?>onsubmit="return confirm('<?= count($missing) ?> ticket(s) still have no bill code. Generate anyway?')"<?php endif; ?>>
                <?= csrf() ?>
                <button class="btn btn-success">Generate Invoice #<?= e($user['next_invoice_no']) ?></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Jira</th><th>Project</th><th>Description</th><th>Bill Code</th><th class="text-end">Time</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($draft['items'] as $item): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(Format::dayMonth($item['work_date'])) ?></td>
                        <td class="text-nowrap"><?php if ($item['ticket_key']): ?><a href="<?= e($jiraBase . '/browse/' . $item['ticket_key']) ?>" target="_blank" rel="noopener"><?= e($item['ticket_key']) ?></a><?php else: ?><span class="badge text-bg-secondary">no key</span><?php endif; ?></td>
                        <td><?= e($item['project_name']) ?></td>
                        <td><?= e($item['title']) ?></td>
                        <td><?= $item['bill_code'] !== '' ? e($item['bill_code']) : '<span class="badge text-bg-warning">missing</span>' ?></td>
                        <td class="text-end text-nowrap"><?= Format::hhmm((int) $item['seconds']) ?></td>
                        <td class="text-end">$<?= Format::money(Format::amount((int) $item['seconds'], (float) $user['rate'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
