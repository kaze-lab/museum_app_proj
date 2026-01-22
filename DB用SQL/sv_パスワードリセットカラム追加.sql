ALTER TABLE `supervisors` 
ADD `reset_token` VARCHAR(255) NULL DEFAULT NULL AFTER `auth_expiry`,
ADD `reset_expiry` DATETIME NULL DEFAULT NULL AFTER `reset_token`;