-- STU LMS — peran basis data (docs/05-desain-database.md §6).
-- Dijalankan sekali oleh superuser saat inisialisasi cluster (Docker) atau manual (lokal).
-- Kata sandi di bawah HANYA untuk lokal/CI. Produksi: kredensial dari secret manager.

CREATE ROLE stu_migrator LOGIN PASSWORD 'stu_migrator_local_only';
CREATE ROLE stu_app      LOGIN PASSWORD 'stu_app_local_only' NOBYPASSRLS;

CREATE DATABASE stu_lms OWNER stu_migrator;
CREATE DATABASE stu_lms_test OWNER stu_migrator;

\connect stu_lms
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO stu_migrator;
GRANT USAGE ON SCHEMA public TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT EXECUTE ON FUNCTIONS TO stu_app;

\connect stu_lms_test
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO stu_migrator;
GRANT USAGE ON SCHEMA public TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO stu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE stu_migrator IN SCHEMA public
    GRANT EXECUTE ON FUNCTIONS TO stu_app;
