<?php

namespace App\Enums;

enum ExportStatus: string
{
    case NaFila = 'na_fila';
    case Processando = 'processando';
    case Concluida = 'concluida';
    case Falhou = 'falhou';
}
