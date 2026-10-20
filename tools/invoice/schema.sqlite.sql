-- Temporary SQLite schema (auto-applied on first run). Mirrors schema.mysql.sql.
CREATE TABLE IF NOT EXISTS invoice_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL DEFAULT '',
    company TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT '',
    rate TEXT NOT NULL DEFAULT '',
    next_invoice_no TEXT NOT NULL DEFAULT '1',
    bill_to TEXT NOT NULL DEFAULT '',
    note_template TEXT NOT NULL DEFAULT '',
    jira_email TEXT NOT NULL DEFAULT '',
    jira_token_enc TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS invoice_invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES invoice_users(id) ON DELETE CASCADE,
    invoice_no TEXT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    period_start TEXT NOT NULL,
    period_end TEXT NOT NULL,
    csv_name TEXT NOT NULL DEFAULT '',
    total_seconds INTEGER NOT NULL DEFAULT 0,
    rate TEXT NOT NULL DEFAULT '',
    amount TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_invoice_invoices_user ON invoice_invoices (user_id, status);
CREATE TABLE IF NOT EXISTS invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL REFERENCES invoice_invoices(id) ON DELETE CASCADE,
    ticket_key TEXT NOT NULL DEFAULT '',
    project_name TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    work_date TEXT NOT NULL,
    seconds INTEGER NOT NULL DEFAULT 0,
    bill_code TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items (invoice_id);
