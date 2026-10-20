-- Invoice tool tables (MySQL). Run once on the target DB, then point
-- config.local.php 'db_dsn' at it.

CREATE TABLE invoice_users (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(190)  NOT NULL,
    password_hash    VARCHAR(255)  NOT NULL,
    full_name        VARCHAR(150)  NOT NULL DEFAULT '',
    company          VARCHAR(150)  NOT NULL DEFAULT '',
    role             VARCHAR(100)  NOT NULL DEFAULT '',
    rate             VARCHAR(255)  NOT NULL DEFAULT '',   -- encrypted
    next_invoice_no  VARCHAR(30)   NOT NULL DEFAULT '1',
    bill_to          TEXT          NOT NULL,
    note_template    VARCHAR(255)  NOT NULL DEFAULT '',
    jira_email       VARCHAR(190)  NOT NULL DEFAULT '',
    jira_token_enc   TEXT          NULL,
    created_at       DATETIME      NOT NULL,
    UNIQUE KEY uq_invoice_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_invoices (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED  NOT NULL,
    invoice_no     VARCHAR(30)   NULL,              -- NULL while draft
    status         ENUM('draft','final') NOT NULL DEFAULT 'draft',
    period_start   DATE          NOT NULL,
    period_end     DATE          NOT NULL,
    csv_name       VARCHAR(255)  NOT NULL DEFAULT '',
    total_seconds  INT UNSIGNED  NOT NULL DEFAULT 0,
    rate           VARCHAR(255)  NOT NULL DEFAULT '',   -- encrypted
    amount         VARCHAR(255)  NOT NULL DEFAULT '',   -- encrypted
    created_at     DATETIME      NOT NULL,
    KEY idx_invoice_invoices_user (user_id, status),
    CONSTRAINT fk_invoice_invoices_user FOREIGN KEY (user_id) REFERENCES invoice_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id    INT UNSIGNED  NOT NULL,
    ticket_key    VARCHAR(40)   NOT NULL DEFAULT '',  -- '' when the task has no Jira key
    project_name  VARCHAR(150)  NOT NULL DEFAULT '',
    title         VARCHAR(500)  NOT NULL DEFAULT '',
    work_date     DATE          NOT NULL,             -- last date the ticket was worked on
    seconds       INT UNSIGNED  NOT NULL DEFAULT 0,
    bill_code     VARCHAR(50)   NOT NULL DEFAULT '',
    KEY idx_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoice_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
