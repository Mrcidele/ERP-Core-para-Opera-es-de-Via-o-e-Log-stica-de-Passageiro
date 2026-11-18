-- Role restrita para a aplicação no Neon (privilégio mínimo).
--
-- A aplicação passa a conectar como erp_app: lê e grava dados, mas não cria, altera nem apaga
-- tabelas (CREATE, ALTER, DROP, TRUNCATE) e não altera a trilha de auditoria. As migrations
-- continuam com a dona do banco (neondb_owner), rodadas do seu terminal pela conexão direta.
--
-- Rode no SQL Editor do Neon, conectado como neondb_owner, no banco neondb.
-- Troque <SENHA_FORTE> por uma senha gerada por você (o Neon exige ao menos 60 bits de entropia;
-- ex.: 24+ caracteres aleatórios). Não use o Console > Roles para criar esta role: roles criadas
-- por lá entram em neon_superuser e podem criar objetos.

-- 1. A role
CREATE ROLE erp_app WITH LOGIN PASSWORD '<SENHA_FORTE>'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;

-- 2. Acesso ao banco e ao schema, sem poder criar objetos
GRANT CONNECT ON DATABASE neondb TO erp_app;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO erp_app;

-- 3. Dados: leitura e escrita nas tabelas e sequências que já existem
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO erp_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO erp_app;

-- 4. Tabelas e sequências criadas por migrations futuras (rodadas como neondb_owner)
ALTER DEFAULT PRIVILEGES FOR ROLE neondb_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO erp_app;
ALTER DEFAULT PRIVILEGES FOR ROLE neondb_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO erp_app;

-- 5. Trilha de auditoria: só inserção e leitura (o trigger também recusa; aqui nem chega a ele)
REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM erp_app;

-- 6. Controle de migrations: só leitura
REVOKE INSERT, UPDATE, DELETE ON migrations FROM erp_app;

-- 7. Mesmo teto de tempo por consulta da dona do banco
ALTER ROLE erp_app SET statement_timeout = '15s';

-- Conferência (deve listar SELECT, INSERT, UPDATE, DELETE em cada tabela, e só SELECT, INSERT em audit_logs):
-- SELECT table_name, string_agg(privilege_type, ', ' ORDER BY privilege_type)
--   FROM information_schema.role_table_grants WHERE grantee = 'erp_app' GROUP BY table_name ORDER BY 1;
