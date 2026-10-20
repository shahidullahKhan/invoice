<?php
namespace InvoiceTool;

/**
 * Invoice persistence: drafts from a parsed CSV, bill-code refresh, finalisation.
 * Rate and amount are stored encrypted (amount ÷ hours would reveal the rate).
 */
class Invoices
{
    /** @var Db */
    private $db;
    /** @var Crypto */
    private $crypto;

    public function __construct(Db $db, Crypto $crypto)
    {
        $this->db     = $db;
        $this->crypto = $crypto;
    }

    /** Replace encrypted rate/amount columns of a row with their numeric values. */
    private function decryptMoney(array $row): array
    {
        foreach (['rate', 'amount'] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = $this->crypto->decryptNumber($row[$col]);
            }
        }
        return $row;
    }

    /** Store a parsed CSV as a draft invoice; returns its id. */
    public function createDraft(array $user, array $parsed, string $csvName): int
    {
        return $this->db->transaction(function (Db $db) use ($user, $parsed, $csvName) {
            // Replace any previous draft so a user only ever has one in progress
            $db->run("DELETE FROM invoice_invoices WHERE user_id = ? AND status = 'draft'", [$user['id']]);

            $total = array_sum(array_column($parsed['items'], 'seconds'));
            $id = $db->insert(
                'INSERT INTO invoice_invoices (user_id, status, period_start, period_end, csv_name, total_seconds, rate, amount, created_at)
                 VALUES (?, \'draft\', ?, ?, ?, ?, ?, ?, ?)',
                [$user['id'], $parsed['period_start'], $parsed['period_end'], $csvName, $total,
                 $this->crypto->encryptNumber((float) $user['rate']),
                 $this->crypto->encryptNumber(Format::amount($total, (float) $user['rate'])), date('Y-m-d H:i:s')]
            );
            foreach ($parsed['items'] as $item) {
                $db->run(
                    'INSERT INTO invoice_items (invoice_id, ticket_key, project_name, title, work_date, seconds) VALUES (?, ?, ?, ?, ?, ?)',
                    [$id, $item['ticket_key'], $item['project_name'], $item['title'], $item['work_date'], $item['seconds']]
                );
            }
            return $id;
        });
    }

    /** Load an invoice (owned by $userId) with its items, or null. */
    public function find(int $id, int $userId): ?array
    {
        $invoice = $this->db->one('SELECT * FROM invoice_invoices WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($invoice) {
            $invoice = $this->decryptMoney($invoice);
            $invoice['items'] = $this->db->all(
                'SELECT ticket_key, project_name, title, work_date, seconds, bill_code
                 FROM invoice_items WHERE invoice_id = ? ORDER BY work_date, ticket_key, id',
                [$id]
            );
        }
        return $invoice;
    }

    public function currentDraft(int $userId): ?array
    {
        $row = $this->db->one("SELECT id FROM invoice_invoices WHERE user_id = ? AND status = 'draft'", [$userId]);
        return $row ? $this->find((int) $row['id'], $userId) : null;
    }

    /** Finalised invoices, newest first (no items needed for the list). */
    public function history(int $userId): array
    {
        $rows = $this->db->all(
            "SELECT id, invoice_no, period_start, period_end, total_seconds, amount, created_at
             FROM invoice_invoices WHERE user_id = ? AND status = 'final' ORDER BY id DESC",
            [$userId]
        );
        return array_map([$this, 'decryptMoney'], $rows);
    }

    /**
     * Dashboard totals (all-time and current year) from already-decrypted history rows;
     * amounts are encrypted so they can't be summed in SQL.
     */
    public static function stats(array $history): array
    {
        $yearStart = date('Y') . '-01-01';
        $stats = ['invoices' => count($history), 'amount' => 0.0, 'seconds' => 0, 'year_invoices' => 0, 'year_amount' => 0.0];
        foreach ($history as $row) {
            $stats['amount']  += $row['amount'];
            $stats['seconds'] += (int) $row['total_seconds'];
            if ($row['period_end'] >= $yearStart) {
                $stats['year_invoices']++;
                $stats['year_amount'] += $row['amount'];
            }
        }
        return $stats;
    }

    /** Save bill codes fetched from Jira (key → code). */
    public function saveBillCodes(int $invoiceId, array $codes): void
    {
        $this->db->transaction(function (Db $db) use ($invoiceId, $codes) {
            foreach ($codes as $key => $code) {
                $db->run('UPDATE invoice_items SET bill_code = ? WHERE invoice_id = ? AND ticket_key = ?', [$code, $invoiceId, $key]);
            }
        });
    }

    /** Assign the user's next invoice number, mark final and advance the counter. Returns the number used. */
    public function finalize(array $invoice, array $user): string
    {
        return $this->db->transaction(function (Db $db) use ($invoice, $user) {
            $number = $user['next_invoice_no'];
            $db->run(
                "UPDATE invoice_invoices SET status = 'final', invoice_no = ?, rate = ?, amount = ?, created_at = ? WHERE id = ?",
                [$number, $this->crypto->encryptNumber((float) $user['rate']),
                 $this->crypto->encryptNumber(Format::amount((int) $invoice['total_seconds'], (float) $user['rate'])),
                 date('Y-m-d H:i:s'), $invoice['id']]
            );
            $db->run('UPDATE invoice_users SET next_invoice_no = ? WHERE id = ?', [Format::nextNumber($number), $user['id']]);
            return $number;
        });
    }

    /**
     * Delete a finalised invoice (items cascade). If it was the most recent number,
     * the user's counter steps back so that number is reused. Returns false if not found.
     */
    public function delete(int $id, array $user): bool
    {
        return $this->db->transaction(function (Db $db) use ($id, $user) {
            $invoice = $db->one("SELECT invoice_no FROM invoice_invoices WHERE id = ? AND user_id = ? AND status = 'final'", [$id, $user['id']]);
            if (!$invoice) {
                return false;
            }
            $db->run('DELETE FROM invoice_invoices WHERE id = ?', [$id]);
            if (Format::nextNumber($invoice['invoice_no']) === $user['next_invoice_no']) {
                $db->run('UPDATE invoice_users SET next_invoice_no = ? WHERE id = ?', [$invoice['invoice_no'], $user['id']]);
            }
            return true;
        });
    }

    /** Unique ticket keys of an invoice that have no bill code yet. */
    public static function missingCodes(array $invoice): array
    {
        $missing = [];
        foreach ($invoice['items'] as $item) {
            if ($item['ticket_key'] !== '' && $item['bill_code'] === '') {
                $missing[$item['ticket_key']] = $item['title'];
            }
        }
        return $missing;
    }

    /** Download file base name, e.g. "Invoice_550279_September_16th-30th_2026_Shahid". */
    public static function fileBase(string $prefix, array $invoice, array $user): string
    {
        $first = strtok(trim($user['full_name']), ' ') ?: 'User';
        return Format::slug($prefix . ' ' . $invoice['invoice_no'] . ' ' . Format::period($invoice['period_start'], $invoice['period_end']) . ' ' . $first);
    }
}
