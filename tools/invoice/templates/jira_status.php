<?php
/** Jira connection status chip with inline re-test button. @var string $back page to return to */
$s     = $_SESSION['jira_status'] ?? null;
$state = !$s ? 'unknown' : ($s['ok'] ? 'ok' : 'error');
$label = ['unknown' => 'Jira not connected', 'ok' => 'Jira connected', 'error' => 'Jira connection failed'][$state];
?>
<style>
    .jira-status { display: inline-flex; align-items: center; gap: 12px; padding: 8px 8px 8px 14px; border: 1px solid #e3e6ea; border-radius: 999px; background: #fafbfc; max-width: 100%; }
    .jira-status .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
    .jira-status.ok .dot { background: #198754; animation: jira-pulse 2s ease-in-out infinite; }
    /* "Live" pulse for the connected state */
    @keyframes jira-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(25,135,84,.45); }
        100% { box-shadow: 0 0 0 8px rgba(25,135,84,0); }
    }
    @media (prefers-reduced-motion: reduce) { .jira-status.ok .dot { animation: none; box-shadow: 0 0 0 4px rgba(25,135,84,.15); } }
    .jira-status.error .dot { background: #dc3545; box-shadow: 0 0 0 4px rgba(220,53,69,.15); }
    .jira-status.unknown .dot { background: #adb5bd; box-shadow: 0 0 0 4px rgba(173,181,189,.2); }
    .jira-status .title { font-weight: 600; font-size: 14px; line-height: 1.2; }
    .jira-status .meta { font-size: 12px; color: #6c757d; line-height: 1.3; }
    .jira-status .btn { border-radius: 999px; white-space: nowrap; }
</style>
<div class="jira-status <?= $state ?>">
    <span class="dot"></span>
    <div class="min-w-0">
        <div class="title"><?= $label ?></div>
        <div class="meta"><?= $s ? e($s['message']) . ' · ' . e($s['at']) : 'Connect your Jira API token to fetch bill codes automatically' ?></div>
    </div>
    <form method="post" action="<?= url('jiratest', ['back' => $back]) ?>" class="m-0">
        <?= csrf() ?>
        <button class="btn btn-sm btn-outline-secondary">↻ <?= $s && $s['ok'] ? 'Re-test' : 'Connect' ?></button>
    </form>
</div>
