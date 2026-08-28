<?php

namespace iEducar\Packages\Educacenso\Services;

use iEducar\Packages\Educacenso\Exception\ImportInepException;
use Throwable;

class EducacensoImportErrorMessage
{
    public const GENERIC = 'O arquivo não pode ser importado. Tente novamente e, se o erro persistir, contate o suporte.';

    public const TIMEOUT = 'O processamento não foi concluído no tempo esperado. Verifique se a fila de importação está em execução e tente novamente.';

    public static function fromThrowable(Throwable $exception): string
    {
        if ($exception instanceof ImportInepException) {
            return self::truncate($exception->getMessage()) ?: self::GENERIC;
        }

        $message = $exception->getMessage();

        if (str_contains($message, 'invalid input syntax for type numeric')) {
            return 'Há um valor numérico inválido no arquivo (INEP, CPF ou código da turma). Verifique a planilha e tente novamente.';
        }

        if (str_contains($message, 'duplicate key') || str_contains($message, 'Unique violation')) {
            return 'Um código INEP deste arquivo já está vinculado a outro cadastro.';
        }

        if (str_contains($message, 'SQLSTATE')) {
            return 'Ocorreu um erro ao gravar os INEPs. Tente novamente e, se o erro persistir, contate o suporte.';
        }

        return self::GENERIC;
    }

    public static function fromLineFailures(int $count): ?string
    {
        if ($count <= 0) {
            return null;
        }

        if ($count === 1) {
            return 'A importação foi concluída, mas 1 registro não pôde ter o INEP atualizado.';
        }

        return "A importação foi concluída, mas {$count} registros não puderam ter o INEP atualizado.";
    }

    private static function truncate(string $message): string
    {
        $message = trim(explode("\n", $message)[0] ?? '');

        if (mb_strlen($message) <= 300) {
            return $message;
        }

        return mb_substr($message, 0, 297) . '...';
    }
}
