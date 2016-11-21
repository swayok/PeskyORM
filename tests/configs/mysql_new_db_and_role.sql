CREATE DATABASE `pesky_orm_test_db`` CHARACTER SET = "UTF8" COLLATE "utf8_general_ci";

CREATE USER 'pesky_orm_test'@'localhost' IDENTIFIED BY '1111111';

REVOKE SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER ON *.* FROM 'pesky_orm_test'@'localhost';
GRANT SELECT, INSERT, UPDATE, REFERENCES, DELETE, CREATE, DROP, ALTER, INDEX, TRIGGER, CREATE VIEW, SHOW VIEW, EXECUTE, ALTER ROUTINE, CREATE ROUTINE, CREATE TEMPORARY TABLES, LOCK TABLES, EVENT ON `pesky\_orm\_test\_db`.* TO 'pesky_orm_test'@'localhost';REVOKE GRANT OPTION ON *.* FROM 'pesky_orm_test'@'localhost';
