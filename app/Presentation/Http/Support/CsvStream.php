<?php

declare(strict_types=1);

namespace App\Presentation\Http\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Download de CSV gerado linha a linha, sem montar o arquivo inteiro na memória. */
final class CsvStream
{
    /** Ponto e vírgula: é o separador que o Excel em português abre direto em colunas. */
    private const SEPARATOR = ';';

    /**
     * @param  list<string>  $header
     * @param  iterable<list<string>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // BOM para o Excel reconhecer UTF-8 (acentos).
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $header, self::SEPARATOR, '"', '');

            foreach ($rows as $row) {
                fputcsv($output, array_map(self::safe(...), $row), self::SEPARATOR, '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Texto que começa com =, +, - ou @ seria executado como fórmula pela planilha:
     * um apóstrofo na frente o mantém como texto.
     */
    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
