<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A trilha de auditoria só recebe inserções: UPDATE, DELETE e TRUNCATE em `audit_logs` são
 * recusados pelo próprio banco, mesmo que alguém use a conexão da aplicação fora dela.
 * Consequência: um usuário com registros na trilha não pode ser apagado fisicamente (o
 * `ON DELETE SET NULL` seria uma alteração); usuários saem por soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs é somente inserção: % não é permitido', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER audit_logs_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();

            CREATE TRIGGER audit_logs_no_truncate
                BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs;
            DROP TRIGGER IF EXISTS audit_logs_no_update_delete ON audit_logs;
            DROP FUNCTION IF EXISTS audit_logs_append_only();
            SQL);
    }
};
