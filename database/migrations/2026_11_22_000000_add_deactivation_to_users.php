<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usuários desativados (sem login, histórico preservado) e a garantia, no próprio banco, de que a
 * empresa nunca fica sem um administrador ativo: rebaixar, desativar ou excluir o último falha.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN deactivated_at timestamptz NULL');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION users_keep_one_active_admin() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.role = 'admin' AND OLD.deactivated_at IS NULL AND OLD.deleted_at IS NULL
                   AND (TG_OP = 'DELETE' OR NEW.role <> 'admin' OR NEW.deactivated_at IS NOT NULL OR NEW.deleted_at IS NOT NULL)
                   AND NOT EXISTS (
                       SELECT 1 FROM users
                        WHERE role = 'admin' AND deactivated_at IS NULL AND deleted_at IS NULL AND id <> OLD.id
                   )
                THEN
                    RAISE EXCEPTION 'last_active_admin: a empresa precisa de pelo menos um administrador ativo'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$
            SQL);

        DB::statement('CREATE TRIGGER users_keep_one_active_admin AFTER UPDATE OF role, deactivated_at, deleted_at OR DELETE ON users FOR EACH ROW EXECUTE FUNCTION users_keep_one_active_admin()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS users_keep_one_active_admin ON users');
        DB::statement('DROP FUNCTION IF EXISTS users_keep_one_active_admin()');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS deactivated_at');
    }
};
