-- Check if Email column exists in khachhang table
-- If not, add it

-- First, check the current structure
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='khachhang' AND TABLE_SCHEMA=DATABASE();

-- Add Email column if it doesn't exist (MySQL)
ALTER TABLE khachhang ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL DEFAULT NULL;

-- Or if using MariaDB/older MySQL, use this instead:
-- ALTER TABLE khachhang ADD email VARCHAR(255) NULL DEFAULT NULL;

-- Add index on email for better performance
ALTER TABLE khachhang ADD INDEX IF NOT EXISTS idx_email (email);

-- Show the updated table structure
SHOW COLUMNS FROM khachhang;
