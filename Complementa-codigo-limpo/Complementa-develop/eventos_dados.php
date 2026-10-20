<?php
declare(strict_types=1);

function validarEvento(array $dados): array
{
    $erros = [];
    foreach (['titulo' => 150, 'descricao' => 2000] as $campo => $limite) {
        if ($dados[$campo] === '' || (preg_match_all('/./us', $dados[$campo]) ?: 0) > $limite) {
            $erros[] = "Informe {$campo} com até {$limite} caracteres.";
        }
    }
    foreach (['cidade' => 100, 'local_evento' => 200, 'link' => 500] as $campo => $limite) {
        if ((preg_match_all('/./us', $dados[$campo]) ?: 0) > $limite) {
            $erros[] = "{$campo} deve ter até {$limite} caracteres.";
        }
    }
    $data = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dados['data_evento'])
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $dados['data_evento']) : false;
    if (!$data || $data->format('Y-m-d') !== $dados['data_evento'] || (int) $data->format('Y') < 1000) {
        $erros[] = 'Informe uma data válida.';
    }
    $horariosValidos = true;
    foreach (['horario_inicio', 'horario_termino'] as $campo) {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $dados[$campo])) {
            $horariosValidos = false;
        }
    }
    if (!$horariosValidos || $dados['horario_termino'] <= $dados['horario_inicio']) {
        $erros[] = 'Informe horários válidos; o término deve ser após o início no mesmo dia.';
    }
    $horas = str_replace(',', '.', $dados['carga_horaria']);
    if (!preg_match('/^\d{1,3}(?:\.\d)?$/D', $horas) || (float) $horas < .5 || (float) $horas > 999) {
        $erros[] = 'A carga horária deve ser de 0,5 a 999 horas, com até uma casa decimal.';
    }
    if (!in_array($dados['modalidade'], ['Presencial', 'Online', 'Híbrido'], true)) {
        $erros[] = 'Selecione uma modalidade válida.';
    }
    if ($dados['modalidade'] === 'Presencial' && ($dados['cidade'] === '' || $dados['local_evento'] === '')) {
        $erros[] = 'Eventos presenciais exigem cidade e local.';
    }
    if ($dados['link'] !== '' && (!filter_var($dados['link'], FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($dados['link'], PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $erros[] = 'Informe um link HTTP ou HTTPS válido.';
    }
    return $erros;
}
