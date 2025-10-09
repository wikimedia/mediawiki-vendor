ALTER TABLE `pending`
ADD `is_resolved` tinyint(1) NOT NULL DEFAULT 0;

DROP INDEX `idx_pending_date_gateway` ON `pending`;

CREATE INDEX `idx_pending_resolved_gateway_method_date`
ON pending (`is_resolved`, `gateway`, `payment_method`, `date`);
