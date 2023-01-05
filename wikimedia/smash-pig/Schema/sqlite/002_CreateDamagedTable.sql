CREATE TABLE IF NOT EXISTS damaged (
  `id` integer primary key,
  `original_date` datetime NOT NULL,
  `damaged_date` datetime NOT NULL,
  `retry_date` datetime NULL,
  `original_queue` varchar(255) NOT NULL,
  `gateway` varchar(255) NULL,
  `order_id` varchar(255) NULL,
  `gateway_txn_id` varchar(255) NULL,
  `error` text NULL,
  `trace` text NULL,
  `message` text NOT NULL
);
