<?php
/**
 * Dashboard: key figures, current draft, recent invoices and Jira status.
 * @var array $user @var array $stats @var array $recent @var array|null $draft @var int $missing
 */
use InvoiceTool\Format;

$last  = $recent[0] ?? null;
$first = strtok(trim($user['full_name']), ' ') ?: 'there';
?>
<style>
    .stat { padding: 18px 20px; height: 100%; }
    .stat .label { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #6c757d; }
    .stat .value { font-size: 26px; font-weight: 700; color: #0b1b4d; line-height: 1.2; margin-top: 6px; }
    .stat .hint { font-size: 13px; color: #6c757d; margin-top: 2px; }
    .stat .icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #e8eefc; color: #0738cd; font-size: 18px; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1">Welcome back, <?= e($first) ?></h1>
        <div class="text-muted small"><?= e(date('l, F j, Y')) ?></div>
    </div>
    <a href="<?= url('new') ?>" class="btn btn-primary">+ New Invoice</a>
</div>

<!-- Key figures -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3"><div class="card stat">
        <div class="d-flex justify-content-between"><span class="label">Billed <?= date('Y') ?></span><span class="icon">$</span></div>
        <div class="value">$<?= Format::money((float) $stats['year_amount']) ?></div>
        <div class="hint"><?= (int) $stats['year_invoices'] ?> invoice<?= (int) $stats['year_invoices'] === 1 ? '' : 's' ?> this year</div>
    </div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat">
        <div class="d-flex justify-content-between"><span class="label">All-time billed</span><span class="icon">Σ</span></div>
        <div class="value">$<?= Format::money((float) $stats['amount']) ?></div>
        <div class="hint"><?= (int) $stats['invoices'] ?> invoices · <?= Format::hhmm((int) $stats['seconds']) ?> hrs</div>
    </div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat">
        <div class="d-flex justify-content-between"><span class="label">Last invoice</span><span class="icon">⎙</span></div>
        <?php if ($last): ?>
            <div class="value">#<?= e($last['invoice_no']) ?></div>
            <div class="hint">$<?= Format::money((float) $last['amount']) ?> · <?= e(Format::period($last['period_start'], $last['period_end'])) ?></div>
        <?php else: ?>
            <div class="value">—</div><div class="hint">No invoices yet</div>
        <?php endif; ?>
    </div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat">
        <div class="d-flex justify-content-between"><span class="label">Next invoice</span><span class="icon">#</span></div>
        <div class="value">#<?= e($user['next_invoice_no']) ?></div>
        <div class="hint">Rate <?= secret('$' . $user['rate'] . '/hr') ?> · <a href="<?= url('settings') ?>">edit</a></div>
    </div></div>
</div>

<div class="row g-3">
    <!-- Recent invoices -->
    <div class="col-lg-8">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">Recent invoices</h2>
                <a href="<?= url('history') ?>" class="small">View all</a>
            </div>
            <?php if (!$recent): ?>
                <p class="text-muted mb-0">No invoices yet. <a href="<?= url('new') ?>">Create your first one</a>.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Period</th><th class="text-end">Hours</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e($r['invoice_no']) ?></td>
                                <td><?= e(Format::period($r['period_start'], $r['period_end'])) ?></td>
                                <td class="text-end"><?= Format::hhmm((int) $r['total_seconds']) ?></td>
                                <td class="text-end">$<?= Format::money((float) $r['amount']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('download', ['id' => $r['id'], 'type' => 'pdf']) ?>">PDF</a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= url('download', ['id' => $r['id'], 'type' => 'xlsx']) ?>">Excel</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Draft + Jira status -->
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card p-4">
            <h2 class="h6 mb-3">Draft in progress</h2>
            <?php if ($draft): ?>
                <div class="fw-semibold"><?= e(Format::period($draft['period_start'], $draft['period_end'])) ?></div>
                <div class="text-muted small mb-2"><?= count($draft['items']) ?> entries · <?= Format::hhmm((int) $draft['total_seconds']) ?> hrs</div>
                <?php if ($missing): ?>
                    <div class="small text-warning-emphasis mb-3">⚠ <?= $missing ?> ticket<?= $missing === 1 ? '' : 's' ?> missing bill code</div>
                <?php else: ?>
                    <div class="small text-success mb-3">✓ All bill codes found</div>
                <?php endif; ?>
                <a href="<?= url('new') ?>" class="btn btn-sm btn-outline-primary">Continue</a>
            <?php else: ?>
                <p class="text-muted small mb-3">No draft. Upload a Time Doctor CSV to start.</p>
                <a href="<?= url('new') ?>" class="btn btn-sm btn-outline-primary">Upload CSV</a>
            <?php endif; ?>
        </div>
        <div class="card p-4">
            <h2 class="h6 mb-3">Connection</h2>
            <?php $back = 'home'; require __DIR__ . '/jira_status.php'; ?>
        </div>
    </div>
</div>
