<?php
/**
 * Invoice tool front controller logic. Loaded by webroot/tools/invoice/index.php;
 * everything else in tools/invoice/ stays outside the public web root.
 * Routes are selected with ?a=<action>.
 */
use InvoiceTool\Crypto;
use InvoiceTool\Db;
use InvoiceTool\Documents;
use InvoiceTool\Format;
use InvoiceTool\Invoices;
use InvoiceTool\Jira;
use InvoiceTool\Profile;
use InvoiceTool\TimeDoctorCsv;

require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';

// ---- Session (scoped to this tool's path) ----
session_name('har_invoice');
session_set_cookie_params([
    'path'     => $config['base_url'],
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$db       = new Db($config);
$crypto   = new Crypto($config['app_key']);
$invoices = new Invoices($db, $crypto);
$user     = Profile::load($db, $config['defaults']); // runs locally: no login, one profile
$user['rate'] = $crypto->decryptNumber($user['rate']); // stored encrypted
$action   = $_GET['a'] ?? 'home';
$isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';

// ---- Helpers ----
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function url(string $action = 'home', array $params = []): string
{
    global $config;
    return $config['base_url'] . '?' . http_build_query(['a' => $action] + $params);
}

function redirect(string $action = 'home', array $params = []): void
{
    header('Location: ' . url($action, $params));
    exit;
}

function flash(?string $msg = null, string $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$type, $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Masked value with an eye button to reveal it (e.g. hourly rate). */
function secret(string $value): string
{
    return '<span class="text-nowrap"><span data-value="' . e($value) . '">••••</span>'
         . ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none" data-reveal title="Show / hide">👁</button></span>';
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="_csrf" value="' . $_SESSION['csrf'] . '">';
}

/** Render templates/<view>.php inside the shared layout. */
function render(string $view, array $vars = []): void
{
    global $user;
    extract($vars);
    ob_start();
    require __DIR__ . "/templates/$view.php";
    $content = ob_get_clean();
    require __DIR__ . '/templates/layout.php';
    exit;
}

/** Jira client built from the user's saved credentials, or null when not configured. */
function jiraClient(array $user, array $config): ?Jira
{
    $token = $user['jira_token_enc'] ? (new Crypto($config['app_key']))->decrypt($user['jira_token_enc']) : null;
    return ($token && $user['jira_email']) ? new Jira($config['jira_base'], $user['jira_email'], $token) : null;
}

/** Remember the last Jira connection result for the status badge. */
function jiraStatus(bool $ok, string $message): void
{
    $_SESSION['jira_status'] = ['ok' => $ok, 'message' => $message, 'at' => date('M j, g:i a')];
}

/** Fetch bill codes for a draft from Jira using the user's own token. Returns an error message or null. */
function refreshBillCodes(array $invoice, array $user, array $config, Invoices $invoices): ?string
{
    $keys = array_values(array_unique(array_filter(array_column($invoice['items'], 'ticket_key'))));
    if (!$keys) {
        return null;
    }
    $jira = jiraClient($user, $config);
    if (!$jira) {
        jiraStatus(false, 'Not configured');
        return 'Add your Jira email and API token in Settings to fetch bill codes.';
    }
    try {
        $codes = $jira->billCodes($keys, $config['jira_bill_field']);
        $invoices->saveBillCodes((int) $invoice['id'], $codes);
        jiraStatus(true, count(array_filter($codes)) . ' of ' . count($codes) . ' bill codes found');
        return null;
    } catch (\RuntimeException $ex) {
        jiraStatus(false, $ex->getMessage());
        return $ex->getMessage();
    }
}

// ---- CSRF check on every POST ----
if ($isPost && !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
    http_response_code(400);
    exit('Session expired. Go back, refresh the page and try again.');
}

// ---- Routes ----
switch ($action) {
    case 'settings':
        if ($isPost) {
            $p = array_map('trim', $_POST);
            $fields = [
                'full_name'       => $p['full_name'] ?? '',
                'email'           => strtolower($p['email'] ?? ''),
                'company'         => $p['company'] ?? '',
                'role'            => $p['role'] ?? '',
                'rate'            => $crypto->encryptNumber((float) ($p['rate'] ?? 0)),
                'next_invoice_no' => preg_replace('/\D/', '', $p['next_invoice_no'] ?? '') ?: '1',
                'bill_to'         => $p['bill_to'] ?? '',
                'note_template'   => $p['note_template'] ?? '',
                'jira_email'      => $p['jira_email'] ?? '',
            ];
            // Token is write-only: only replaced when a new one is typed
            if (($p['jira_token'] ?? '') !== '') {
                $fields['jira_token_enc'] = $crypto->encrypt($p['jira_token']);
            }
            unset($_SESSION['jira_status']); // credentials may have changed; re-test
            $sets = implode(', ', array_map(function ($k) { return "$k = ?"; }, array_keys($fields)));
            $db->run("UPDATE invoice_users SET $sets WHERE id = ?", array_merge(array_values($fields), [$user['id']]));
            flash('Settings saved.');
            redirect('settings');
        }
        render('settings', []);

    case 'jiratest':
        if ($isPost) {
            $jira = jiraClient($user, $config);
            try {
                if (!$jira) {
                    throw new \RuntimeException('Not configured — add your Jira email and API token.');
                }
                jiraStatus(true, 'Signed in as ' . $jira->whoAmI());
            } catch (\RuntimeException $ex) {
                jiraStatus(false, $ex->getMessage());
            }
        }
        $back = $_GET['back'] ?? '';
        redirect(in_array($back, ['home', 'new'], true) ? $back : 'settings');

    case 'upload':
        if (!$isPost || empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            flash('Please choose a Time Doctor CSV file.', 'danger');
            redirect('new');
        }
        try {
            $parsed  = TimeDoctorCsv::parse($_FILES['csv']['tmp_name']);
            $id      = $invoices->createDraft($user, $parsed, basename($_FILES['csv']['name']));
            $warning = refreshBillCodes($invoices->find($id, (int) $user['id']), $user, $config, $invoices);
            flash($warning ?? 'CSV imported. Review the invoice below.', $warning ? 'warning' : 'success');
        } catch (\RuntimeException $ex) {
            flash($ex->getMessage(), 'danger');
        }
        redirect('new');

    case 'recheck':
        $draft = $invoices->currentDraft((int) $user['id']);
        if ($isPost && $draft) {
            $warning = refreshBillCodes($draft, $user, $config, $invoices);
            flash($warning ?? 'Bill codes refreshed from Jira.', $warning ? 'warning' : 'success');
        }
        redirect('new');

    case 'generate':
        $draft = $invoices->currentDraft((int) $user['id']);
        if ($isPost && $draft) {
            if ((float) $user['rate'] <= 0) {
                flash('Set your hourly rate in Settings first.', 'danger');
                redirect('settings');
            }
            $number = $invoices->finalize($draft, $user);
            flash("Invoice #$number created. Download the PDF and Excel below.");
            redirect('history', ['new' => $draft['id']]);
        }
        redirect('new');

    case 'download':
        $invoice = $invoices->find((int) ($_GET['id'] ?? 0), (int) $user['id']);
        $type    = $_GET['type'] ?? 'pdf';
        if (!$invoice || $invoice['status'] !== 'final' || !in_array($type, ['pdf', 'xlsx'], true)) {
            http_response_code(404);
            exit('Invoice not found.');
        }
        $docs = new Documents($config['jira_base']);
        if ($type === 'pdf') {
            $body = $docs->pdf($invoice, $user);
            $name = Invoices::fileBase('Invoice', $invoice, $user) . '.pdf';
            header('Content-Type: application/pdf');
        } else {
            $body = $docs->xlsx($invoice, $user);
            $name = Invoices::fileBase('Invoice_sheet', $invoice, $user) . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;

    case 'delete':
        if ($isPost) {
            $ok = $invoices->delete((int) ($_POST['id'] ?? 0), $user);
            flash($ok ? 'Invoice deleted.' : 'Invoice not found.', $ok ? 'success' : 'danger');
        }
        redirect('history');

    case 'history':
        render('history', ['rows' => $invoices->history((int) $user['id']), 'newId' => (int) ($_GET['new'] ?? 0)]);

    case 'new':
        $draft = $invoices->currentDraft((int) $user['id']);
        render('new', ['draft' => $draft, 'missing' => $draft ? Invoices::missingCodes($draft) : [], 'jiraBase' => $config['jira_base']]);

    default:
        $draft = $invoices->currentDraft((int) $user['id']);
        $history = $invoices->history((int) $user['id']);
        render('home', [
            'stats'   => Invoices::stats($history),
            'recent'  => array_slice($history, 0, 5),
            'draft'   => $draft,
            'missing' => $draft ? count(Invoices::missingCodes($draft)) : 0,
        ]);
}
