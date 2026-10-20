<?php
/**
 * PDF invoice layout (rendered by dompdf).
 * @var array $invoice  invoice row + items
 * @var array $user     invoice owner
 * @var string $jiraBase
 */
use InvoiceTool\Format;

$e      = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$period = Format::period($invoice['period_start'], $invoice['period_end']);
$note   = str_replace('{period}', $period, $user['note_template']);
$billTo = array_values(array_filter(array_map('trim', explode("\n", $user['bill_to'])), 'strlen'));
$mailto = function ($line) use ($e) {
    return filter_var($line, FILTER_VALIDATE_EMAIL) ? '<a href="mailto:' . $e($line) . '">' . $e($line) . '</a>' : $e($line);
};

// Every day of the period (days without work show 0 hours), each with its items
$days = [];
for ($d = strtotime($invoice['period_start']); $d <= strtotime($invoice['period_end']); $d = strtotime('+1 day', $d)) {
    $days[date('Y-m-d', $d)] = [];
}
foreach ($invoice['items'] as $item) {
    $days[$item['work_date']][] = $item;
}
ksort($days);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 60px 50px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
    a { color: #1a0dab; }
    .head td { vertical-align: top; }
    .title { font-size: 30px; font-weight: bold; text-align: right; padding-bottom: 18px; }
    .from { font-size: 13px; line-height: 1.5; padding-bottom: 8px; border-bottom: 1px solid #999; }
    .billto { font-size: 13px; line-height: 1.5; padding-top: 8px; }
    .meta td { font-size: 13px; padding: 1px 0; }
    .meta .lbl { text-align: right; padding-right: 6px; }
    .due { border: 1px solid #999; text-align: center; padding: 8px; margin-top: 12px; }
    .due .lbl { font-size: 13px; }
    .due .val { font-size: 24px; font-weight: bold; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 30px; }
    table.items th, table.items td { border: 1px solid #222; padding: 1px 3px; vertical-align: top; text-align: left; font-size: 9px; }
    table.items th { font-weight: bold; }
    table.items tbody.day { page-break-inside: avoid; }
    /* Rows of the same day read as one block: no line between them */
    table.items tbody.day td { border-top: none; border-bottom: none; }
    table.items tbody.day tr.first td { border-top: 1px solid #222; }
    table.items td.date, table.items td.hrs { vertical-align: middle; white-space: nowrap; }
    .date { width: 42px; }
    .code { width: 48px; white-space: nowrap; }
    .app { width: 92px; }
    .hrs { width: 48px; }
    .total td { font-size: 13px; }
</style>
</head>
<body>

<table class="head" width="100%">
    <tr>
        <td width="52%"></td>
        <td class="title">INVOICE</td>
    </tr>
    <tr>
        <td>
            <div class="from">
                <?= $e($user['company']) ?><br>
                <?= $e($user['full_name']) ?><br>
                <?= $mailto($user['email']) ?>
            </div>
            <div class="billto">
                <b>Bill To:</b><br>
                <?php foreach ($billTo as $line): ?><?= $mailto($line) ?><br><?php endforeach; ?>
            </div>
        </td>
        <td>
            <table class="meta" width="100%">
                <tr><td class="lbl">Invoice #:</td><td><?= $e($invoice['invoice_no']) ?></td></tr>
                <tr><td class="lbl">Invoice Period:</td><td><?= $e($period) ?></td></tr>
            </table>
            <table width="100%"><tr><td width="35%"></td><td>
                <div class="due">
                    <div class="lbl">Amount Due:</div>
                    <div class="val"><?= number_format((float) $invoice['amount'], 2, '.', '') ?></div>
                </div>
            </td></tr></table>
        </td>
    </tr>
</table>

<table class="items">
    <tbody>
        <!-- Header in tbody so it shows on page 1 only, like the template -->
        <tr><th class="date">Date</th><th class="code">Bill Code</th><th class="app">Application Name</th><th>Description</th><th class="hrs">Hours</th></tr>
    </tbody>
    <?php foreach ($days as $date => $items):
        // Date and day total sit on the middle row (dompdf can't split rowspan cells across pages)
        $mid = intdiv(count($items) - 1, 2); ?>
    <tbody class="day">
        <?php if (!$items): ?>
        <tr class="first">
            <td class="date"><?= $e(Format::monthDay($date)) ?></td><td></td><td></td><td></td>
            <td class="hrs"><?= number_format(0, 5) ?></td>
        </tr>
        <?php endif; ?>
        <?php foreach ($items as $i => $item): ?>
        <tr class="<?= $i ? '' : 'first' ?>">
            <td class="date"><?= $i === $mid ? $e(Format::monthDay($date)) : '' ?></td>
            <td class="code"><?= $e($item['bill_code']) ?></td>
            <td class="app"><?= $e($item['project_name']) ?></td>
            <td><?= $e(implode(' - ', array_filter([$item['title'], $item['ticket_key'], Format::duration((int) $item['seconds'])], 'strlen'))) ?></td>
            <td class="hrs"><?= $i === $mid ? number_format(Format::hours((int) array_sum(array_column($items, 'seconds'))), 5, '.', '') : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
    <tbody>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
        <!-- Amount breakdown: exact decimal hours × hourly rate -->
        <tr class="total">
            <td colspan="5" style="text-align:right">
                Total Amount:
                <?= number_format(Format::hours((int) $invoice['total_seconds']), 5, '.', '') ?>
                x <?= rtrim(rtrim(number_format((float) $invoice['rate'], 2, '.', ''), '0'), '.') ?>
                = <?= Format::money((float) $invoice['amount']) ?>
            </td>
        </tr>
        <tr><td colspan="5">Note: <?= $e($note) ?></td></tr>
    </tbody>
</table>

</body>
</html>
