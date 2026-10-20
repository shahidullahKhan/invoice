<?php
/** Finalised invoices with download links. @var array $rows @var int $newId */
use InvoiceTool\Format;
?>
<div class="card p-4">
    <h1 class="h5 mb-3">Invoice History</h1>
    <?php if (!$rows): ?>
        <p class="text-muted mb-0">No invoices yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Invoice #</th><th>Period</th><th class="text-end">Hours</th><th class="text-end">Amount</th><th>Created</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?= (int) $r['id'] === $newId ? 'table-success' : '' ?>">
                        <td><?= e($r['invoice_no']) ?></td>
                        <td><?= e(Format::period($r['period_start'], $r['period_end'])) ?></td>
                        <td class="text-end"><?= Format::hhmm((int) $r['total_seconds']) ?></td>
                        <td class="text-end">$<?= Format::money((float) $r['amount']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('download', ['id' => $r['id'], 'type' => 'pdf']) ?>">PDF</a>
                            <a class="btn btn-sm btn-outline-success" href="<?= url('download', ['id' => $r['id'], 'type' => 'xlsx']) ?>">Excel</a>
                            <form method="post" action="<?= url('delete') ?>" class="d-inline"
                                  onsubmit="return confirm('Delete invoice #<?= e($r['invoice_no']) ?>? This cannot be undone.')">
                                <?= csrf() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
