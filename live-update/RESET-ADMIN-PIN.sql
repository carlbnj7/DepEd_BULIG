-- BULIG: clear the administrator PIN (use when a PIN is forgotten or was made with 6 digits).
-- phpMyAdmin → click the BULIG database → Import this file → Go. Then sign in: you will be asked to create a new 4-digit PIN.
UPDATE admin_security SET pin_hash=NULL,failed_pins=0,locked_until=NULL;
