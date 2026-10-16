<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1e3a8a; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 3px 4px; }
    </style>
</head>
<body>
    <h1>Escala de plantão — {{ $schedule->unit->name }}</h1>
    <div>Mês: {{ $schedule->month->format('m/Y') }} · Situação: {{ $schedule->status->value }} · Semente: {{ $schedule->seed }}</div>
    <table>
        <thead><tr><th>Data</th><th>Dia</th><th>Turno</th><th>Início</th><th>Duração</th><th>Membro</th><th>Matrícula</th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr>@foreach ($r as $c)<td>{{ $c }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
