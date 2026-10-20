<?php
namespace InvoiceTool;

/**
 * The tool runs locally (one Docker container per developer), so there are no accounts:
 * a single profile row holds the developer's settings and owns all invoices.
 */
class Profile
{
    /** The profile row, created with defaults on first run. */
    public static function load(Db $db, array $defaults): array
    {
        $user = $db->one('SELECT * FROM invoice_users ORDER BY id LIMIT 1');
        if (!$user) {
            $db->insert(
                "INSERT INTO invoice_users (email, password_hash, bill_to, note_template, created_at) VALUES ('', '', ?, ?, ?)",
                [$defaults['bill_to'], $defaults['note_template'], date('Y-m-d H:i:s')]
            );
            $user = $db->one('SELECT * FROM invoice_users ORDER BY id LIMIT 1');
        }
        return $user;
    }
}
