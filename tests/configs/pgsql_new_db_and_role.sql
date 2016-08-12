CREATE DATABASE "pesky_orm_test_db" UTF8

CREATE ROLE "pesky_orm_test" login PASSWORD '1111111' NOINHERIT;

GRANT ALL ON DATABASE "pesky_orm_test_db" TO "pesky_orm_test";

-- make sure it is not free for all access in pg_hba.conf

