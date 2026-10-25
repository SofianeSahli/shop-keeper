/** Ledger tables; kept free of native imports so tests can load it. */
export const LEDGER_SCHEMA_SQL = `
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS products (
  id TEXT PRIMARY KEY,
  barcode TEXT,
  name TEXT NOT NULL,
  selling_price_millimes INTEGER NOT NULL CHECK(selling_price_millimes > 0),
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
  id TEXT PRIMARY KEY,
  internal_code TEXT NOT NULL UNIQUE,
  display_label TEXT NOT NULL,
  real_name TEXT,
  phone_number TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tickets (
  id TEXT PRIMARY KEY,
  customer_id TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  total_millimes INTEGER NOT NULL CHECK(total_millimes > 0),
  status TEXT NOT NULL,
  voided_at TEXT,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS ticket_lines (
  id TEXT PRIMARY KEY,
  ticket_id TEXT NOT NULL,
  product_id TEXT,
  product_name_snapshot TEXT NOT NULL,
  barcode_snapshot TEXT,
  unit_price_millimes INTEGER NOT NULL CHECK(unit_price_millimes > 0),
  quantity INTEGER NOT NULL CHECK(quantity > 0),
  line_total_millimes INTEGER NOT NULL CHECK(line_total_millimes > 0),
  FOREIGN KEY (ticket_id) REFERENCES tickets(id)
);

CREATE TABLE IF NOT EXISTS payments (
  id TEXT PRIMARY KEY,
  ticket_id TEXT NOT NULL,
  customer_id TEXT NOT NULL,
  amount_millimes INTEGER NOT NULL CHECK(amount_millimes > 0),
  payment_kind TEXT NOT NULL,
  applied_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  voided_at TEXT,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS pending_changes (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  operation TEXT NOT NULL,
  payload_json TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS app_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS ledger_audit_events (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  action TEXT NOT NULL,
  before_json TEXT,
  after_json TEXT,
  created_at TEXT NOT NULL
);
`;
