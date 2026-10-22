<?php
declare(strict_types=1);

function campoForm(string $nome,string $rotulo,mixed $valor='',string $tipo='text',int $max=150,bool $obrigatorio=true): void
{
    echo '<label>'.escapar($rotulo);
    if ($tipo==='textarea') {
        echo '<textarea name="'.escapar($nome).'" maxlength="'.$max.'"'.($obrigatorio?' required':'').'>'.escapar((string)$valor).'</textarea>';
    } else {
        echo '<input name="'.escapar($nome).'" type="'.escapar($tipo).'" maxlength="'.$max.'"'.($obrigatorio?' required':'').' value="'.escapar((string)$valor).'"';
        if ($tipo==='number') echo ' step="0.1" min="0" max="999"';
        if ($tipo==='file') echo ' accept="application/pdf,image/jpeg,image/png"';
        echo '>';
    }
    echo '</label>';
}

function selecionar(string $nome,string $rotulo,array $opcoes,mixed $atual=''): void
{
    echo '<label>'.escapar($rotulo).'<select name="'.escapar($nome).'" required>';
    foreach ($opcoes as $valor=>$texto) echo '<option value="'.escapar((string)$valor).'"'.((string)$valor===(string)$atual?' selected':'').'>'.escapar($texto).'</option>';
    echo '</select></label>';
}

function formulario(string $acao,int $id=0,bool $arquivo=false): void
{
    echo '<form method="post" class="form-stack"'.($arquivo?' enctype="multipart/form-data"':'').'>';
    campoCsrf();
    echo '<input type="hidden" name="acao" value="'.escapar($acao).'"><input type="hidden" name="id" value="'.$id.'">';
}

function botao(string $texto): void { echo '<button class="button" type="submit">'.escapar($texto).'</button></form>'; }

function inicioCard(string $titulo): void { echo '<article class="detail-card workflow-card"><h2>'.escapar($titulo).'</h2>'; }
function dado(string $rotulo,mixed $valor): void { echo '<p><strong>'.escapar($rotulo).':</strong> '.escapar((string)($valor ?? 'Não informado')).'</p>'; }
function vazio(array $lista): void { if (!$lista) echo '<p class="empty-state">Nenhum registro encontrado.</p>'; }

function menuPainel(string $perfil): array
{
    return match ($perfil) {
        'aluno'=>['inscricoes'=>'Minhas inscrições','certificados'=>'Certificados','horas'=>'Minhas horas','notificacoes'=>'Notificações','perfil'=>'Meu perfil'],
        'organizador'=>['participantes'=>'Participantes'],
        'instituicao'=>['analises'=>'Analisar horas'],
        'equipe'=>['instituicao'=>'Instituições','categoria'=>'Categorias','organizador'=>'Organizadores'],
        default=>[],
    };
}
