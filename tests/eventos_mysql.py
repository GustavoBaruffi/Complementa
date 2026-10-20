"""Teste real da Sprint 2. Requer servidor localhost:8000 e migração aplicada.

Cria dados fictícios identificados e os mantém; não altera estrutura nem apaga
registros. Credenciais de teste são aleatórias e não são impressas.
"""
import datetime
import http.cookiejar
import json
from pathlib import Path
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = "http://127.0.0.1:8000/"
PHP = r"""<?php
if (($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'
 || !hash_equals('__TOKEN__',$_SERVER['HTTP_X_TESTE']??'')
 || $_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(404); exit; }
header('Content-Type: application/json');
require __DIR__.'/conexao.php';
require __DIR__.'/organizadores.php';
try {
 $p=obterConexao();
 if ($_POST['acao']==='preparar') {
  $q=$p->prepare('SHOW COLUMNS FROM organizador'); $q->execute();
  if (!in_array('senha_hash',array_column($q->fetchAll(),'Field'),true)) {
   http_response_code(409); echo json_encode(['pendente'=>'migracao organizador']); exit;
  }
  $id=criarOrganizador($p,['nome'=>$_POST['nome'],'tipo'=>'Outro','cidade'=>'Cidade de teste',
   'email'=>$_POST['email'],'senha'=>$_POST['senha'],'telefone'=>'']);
  $q=$p->prepare('SELECT senha_hash FROM organizador WHERE id_organizador=:id'); $q->execute(['id'=>$id]);
  $hash=$q->fetchColumn();
  $q=$p->prepare('SELECT id_categoria FROM categoria WHERE nome=:nome'); $q->execute(['nome'=>'Teste Sprint 2']);
  $cat=$q->fetchColumn();
  if (!$cat) {$q=$p->prepare('INSERT INTO categoria (nome,descricao) VALUES (:nome,:descricao)');
   $q->execute(['nome'=>'Teste Sprint 2','descricao'=>'Categoria fictícia para testes autorizados.']); $cat=$p->lastInsertId();}
  echo json_encode(['id'=>(int)$id,'categoria'=>(int)$cat,'hash_valido'=>password_verify($_POST['senha'],$hash)]);
 } elseif ($_POST['acao']==='evento') {
  $q=$p->prepare('SELECT e.*, c.nome AS categoria FROM evento e JOIN categoria c ON c.id_categoria=e.id_categoria WHERE e.id_evento=:id');
  $q->execute(['id'=>$_POST['id']]); echo json_encode($q->fetch());
 } elseif ($_POST['acao']==='rascunho') {
  $q=$p->prepare("INSERT INTO evento (id_organizador,id_categoria,titulo,descricao,data_evento,horario_inicio,horario_termino,carga_horaria,modalidade,cidade,local_evento,link,status,validade_horas)
   SELECT id_organizador,id_categoria,:titulo,descricao,data_evento,horario_inicio,horario_termino,carga_horaria,modalidade,cidade,local_evento,link,'Rascunho',validade_horas
   FROM evento WHERE id_evento=:id AND id_organizador=:organizador");
  $q->execute(['titulo'=>$_POST['titulo'],'id'=>$_POST['id'],'organizador'=>$_POST['organizador']]);
  echo json_encode(['id'=>(int)$p->lastInsertId()]);
 } else {http_response_code(400);}
} catch(PDOException $e) {
 http_response_code(409); echo json_encode(['sqlstate'=>$e->getCode(),'mysql'=>$e->errorInfo[1]??null]);
}
"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def client():
    return urllib.request.build_opener(NoRedirect(),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(c, path, data=None, headers=None):
    req = urllib.request.Request(BASE + path,
        data=urllib.parse.urlencode(data).encode() if data is not None else None,
        headers=headers or {})
    try:
        response = c.open(req, timeout=20)
    except urllib.error.HTTPError as e:
        response = e
    with response:
        body = response.read().decode()
        assert not re.search(r"(Warning:|Fatal error:|Stack trace:)", body), path
        return response.status, body, response.headers


def token(c, path):
    status, body, _ = request(c, path)
    assert status == 200, (path, status)
    return re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]


def main():
    bridge = ROOT / ("_teste_eventos_" + secrets.token_hex(12) + ".php")
    secret = secrets.token_hex(32)
    c = client()

    def db(data):
        status, body, _ = request(c, bridge.name, data, {"X-Teste": secret})
        assert status == 200, f"Verificação MySQL: HTTP {status}, {body}"
        return json.loads(body)

    try:
        bridge.write_text(PHP.replace("__TOKEN__", secret), encoding="utf-8")
        suffix = secrets.token_hex(5)
        credentials = {"acao": "preparar", "nome": "Organizador Teste " + suffix,
            "email": f"organizador-{suffix}@example.invalid", "senha": secrets.token_urlsafe(24)}
        organizer = db(credentials)
        assert organizer["hash_valido"]
        status, body, _ = request(c, bridge.name, credentials, {"X-Teste": secret})
        assert status == 409 and json.loads(body)["mysql"] == 1062
        other = db(credentials | {"email": f"outro-{suffix}@example.invalid", "nome": "Outro Teste " + suffix})
        print("Criação de organizadores, hash e unicidade de e-mail: OK")
        assert request(c, "novo_evento.php")[0] == 303
        login = {"csrf": token(c, "login.php"), "perfil": "organizador",
                 "email": credentials["email"], "senha": "incorreta"}
        status, body, _ = request(c, "login.php", login)
        assert status == 200 and "incorretos" in body
        status, _, headers = request(c, "login.php", login | {"senha": credentials["senha"]})
        assert status == 303 and headers["Location"] == "meus_eventos.php"
        csrf = token(c, "novo_evento.php")
        date = (datetime.date.today() + datetime.timedelta(days=30)).isoformat()
        title = "Evento Teste " + suffix
        data = {"csrf": csrf, "titulo": title, "descricao": "Descrição de teste <script>alert(1)</script>",
            "id_categoria": organizer["categoria"], "data_evento": date,
            "horario_inicio": "14:00", "horario_termino": "16:00", "carga_horaria": "2.0",
            "modalidade": "Presencial", "cidade": "Cidade de teste", "local_evento": "Auditório de teste",
            "link": "https://example.invalid/evento", "id_organizador": other["id"],
            "status": "Rascunho", "validade_horas": "Validado"}
        assert request(c, "novo_evento.php", data | {"csrf": "invalido"})[0] == 403
        for override in ({"horario_termino": "13:00"}, {"data_evento": "2026-02-30"},
                         {"carga_horaria": "0.4"}, {"carga_horaria": "2.55"},
                         {"id_categoria": "0"}, {"modalidade": "Invalida"},
                         {"local_evento": ""}, {"titulo": "x"*151}, {"link": "javascript:alert(1)"}):
            status, body, _ = request(c, "novo_evento.php", data | override)
            assert status == 200 and 'role="alert"' in body, override
        status, _, headers = request(c, "novo_evento.php", data)
        assert status == 303, status
        event_id = int(headers["Location"].split("=")[1])
        event = db({"acao": "evento", "id": event_id})
        assert event["titulo"] == title and int(event["id_organizador"]) == organizer["id"]
        assert event["horario_inicio"] == "14:00:00" and event["horario_termino"] == "16:00:00"
        assert float(event["carga_horaria"]) == 2 and event["data_evento"] == date
        assert int(event["id_categoria"]) == organizer["categoria"]
        assert event["status"] == "Publicado" and event["validade_horas"] == "Necessita validação"
        assert event["modalidade"] == "Presencial" and event["cidade"] == data["cidade"]
        assert event["local_evento"] == data["local_evento"] and event["link"] == data["link"]
        for page in ("eventos.php", "meus_eventos.php", f"evento.php?id={event_id}"):
            status, body, _ = request(c, page)
            assert status == 200 and title in body and "14:00" in body and "2,0 horas" in body
            assert "<script>alert(1)</script>" not in body
        status, body, _ = request(c, f"evento.php?id={event_id}")
        assert "&lt;script&gt;" in body and "Necessita validação" in body
        print("Cadastro HTTP, validações, campos persistidos, detalhes e listagens: OK. Evento ID:", event_id)
        draft_title = "Rascunho Teste " + suffix
        draft = db({"acao": "rascunho", "id": event_id, "organizador": organizer["id"], "titulo": draft_title})
        assert draft_title in request(c, "meus_eventos.php")[1]
        assert request(c, f"evento.php?id={draft['id']}")[0] == 200
        guest = client()
        assert draft_title not in request(guest, "eventos.php")[1]
        assert request(guest, f"evento.php?id={draft['id']}")[0] == 404
        c2 = client()
        request(c2, "login.php", {"csrf": token(c2, "login.php"), "perfil": "organizador",
            "email": f"outro-{suffix}@example.invalid", "senha": credentials["senha"]})
        assert title not in request(c2, "meus_eventos.php")[1]
        assert request(c2, f"evento.php?id={draft['id']}")[0] == 404
        assert request(c, "logout.php", {"csrf": token(c, "meus_eventos.php")})[0] == 303
        assert request(c, "novo_evento.php")[0] == 303
        print("Autorizações, organizador da sessão, rascunhos, escape HTML e logout: OK")
        browser = subprocess.run(["node", str(ROOT / "tests/navegador.mjs")],
            input=json.dumps({"email": credentials["email"], "senha": credentials["senha"],
                "categoria": organizer["categoria"], "titulo": "Navegador Teste " + suffix, "data": date}),
            text=True, capture_output=True, cwd=ROOT)
        if browser.returncode:
            raise RuntimeError("Teste de navegador falhou: " + browser.stderr)
        result = json.loads(browser.stdout)
        browser_event = db({"acao": "evento", "id": result["evento"]})
        assert browser_event["titulo"] == "Navegador Teste " + suffix
        assert int(browser_event["id_organizador"]) == organizer["id"]
        assert browser_event["modalidade"] == "Online"
        print("Navegador Edge: login, cadastro, detalhes, desktop, mobile, menu e logout: OK")
        print("Evento criado no navegador e conferido no MySQL, ID:", result["evento"])
        print("Capturas:", ", ".join(result["capturas"]))
        print("Dados fictícios mantidos; nenhuma exclusão ou alteração estrutural executada.")
    finally:
        bridge.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
