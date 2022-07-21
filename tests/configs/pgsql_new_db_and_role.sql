CREATE ROLE "pesky_orm_test" LOGIN PASSWORD '1111111' NOINHERIT;

CREATE DATABASE "pesky_orm_test_db" ENCODING 'UTF8' OWNER "pesky_orm_test";

-- make sure it is not free for all access in pg_hba.conf

