<?php /** @var array $user */ ?>
<div class="card p-4">
    <h1 class="h4 mb-3">My Settings</h1>
    <form method="post" action="<?= url('settings') ?>" class="row g-3">
        <?= csrf() ?>
        <div class="col-md-6"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email <span class="text-muted small">(shown on the invoice)</span></label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Company / business name</label><input name="company" class="form-control" value="<?= e($user['company']) ?>" placeholder="e.g. Webcornice"></div>
        <div class="col-md-6"><label class="form-label">Role</label><input name="role" class="form-control" value="<?= e($user['role']) ?>" placeholder="e.g. Front-end Developer"></div>
        <div class="col-md-6"><label class="form-label">Hourly rate ($)</label><div class="input-group">
                <input type="password" inputmode="decimal" pattern="\d+(\.\d{1,2})?" name="rate" class="form-control" value="<?= e($user['rate']) ?>" autocomplete="off" required>
                <button type="button" class="btn btn-outline-secondary" data-reveal-input title="Show / hide rate">👁</button>
            </div></div>
        <div class="col-md-6">
            <label class="form-label">Next invoice #</label>
            <input name="next_invoice_no" class="form-control" value="<?= e($user['next_invoice_no']) ?>" pattern="\d+" required>
            <div class="form-text">Goes up by 1 automatically after each invoice.</div>
        </div>
        <div class="col-md-6"><label class="form-label">Bill To <span class="text-muted small">(one line each)</span></label><textarea name="bill_to" rows="4" class="form-control"><?= e($user['bill_to']) ?></textarea></div>
        <div class="col-md-6">
            <label class="form-label">Invoice note</label>
            <input name="note_template" class="form-control" value="<?= e($user['note_template']) ?>">
            <div class="form-text"><code>{period}</code> is replaced with the invoice period, e.g. "June 1st-15th, 2026".</div>
        </div>

        <h2 class="h6 mt-4 mb-0">Jira (used to fetch Application Codes)</h2>
        <div class="col-md-6"><label class="form-label">Jira email</label><input type="email" name="jira_email" class="form-control" value="<?= e($user['jira_email']) ?>"></div>
        <div class="col-md-6">
            <label class="form-label">Jira API token</label>
            <input type="password" name="jira_token" class="form-control" autocomplete="new-password" placeholder="<?= $user['jira_token_enc'] ? 'Saved — leave blank to keep' : 'Paste token' ?>">
            <div class="form-text">Create one at <a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank" rel="noopener">Atlassian → API tokens</a>. Stored encrypted.</div>
        </div>
        <div class="col-12"><button class="btn btn-primary">Save settings</button></div>
    </form>
    <hr>
    <?php $back = 'settings'; require __DIR__ . '/jira_status.php'; ?>
</div>
