ALTER TABLE `pending`
ADD `is_resolved` tinyint(1) NOT NULL DEFAULT 0;

DROP INDEX `idx_pending_date_gateway` ON `pending`;

CREATE INDEX `idx_pending_date_gateway_resolved`
ON pending (`date`, `gateway`, `is_resolved`);
