<?php

declare(strict_types=1);

namespace App\Presentation\Http\Support;

/** Fuso em que relatórios exportados mostram datas e horas (o banco e a API usam UTC). */
final class ReportTimezone
{
    public static function name(): string
    {
        $zone = config('app.report_timezone');

        return is_string($zone) && $zone !== '' ? $zone : 'America/Sao_Paulo';
    }
}
