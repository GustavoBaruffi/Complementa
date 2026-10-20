"""Teste real autorizado de cadastro. Mantém registros de teste, não apaga dados.

Execute com o servidor local já conectado ao MySQL:
    python tests/cadastro_mysql.py

Usa um endpoint temporário restrito a loopback e token aleatório para conferir
o registro pelo mesmo PDO do servidor, sem ler ou exportar credenciais.
"""
import http.cookiejar
import json
from pathlib import Path
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = "http://127.0.0.1:8000/"

PHP = r"""<?php
if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || !hash_equals('__TOKEN__', $_SERVER['HTTP_X_TESTE'] ?? '')
    || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(404); exit; }
header('Content-Type: application/json');
require __DIR__.'/conexao.php';
try {
    $pdo = obterConexao();
    if ($_POST['acao'] === 'preparar') {
        $nome = 'Instituicao Ficticia - Teste Cadastro Complementa';
        $q=$pdo->prepare('SELECT id_instituicao FROM instituicao WHERE nome=:nome AND email=:email');
        $q->execute(['nome'=>$nome,'email'=>'teste-cadastro@example.invalid']);
        $id=$q->fetchColumn();
        if (!$id) {
            $q=$pdo->prepare('INSERT INTO instituicao (nome,sigla,cidade,email) VALUES (:nome,:sigla,:cidade,:email)');
            $q->execute(['nome'=>$nome,'sigla'=>'TESTE','cidade'=>'Cidade de teste','email'=>'teste-cadastro@example.invalid']);
            $id=$pdo->lastInsertId();
        }
        $q=$pdo->prepare('SHOW COLUMNS FROM aluno'); $q->execute();
        $hash=in_array('senha_hash',array_column($q->fetchAll(),'Field'),true);
        $q=$pdo->prepare('SHOW INDEX FROM aluno'); $q->execute();
        $indices=[];
        foreach ($q->fetchAll() as $i) {
            if ((int)$i['Non_unique']===0) $indices[$i['Key_name']][]=$i['Column_name'];
        }
        echo json_encode(['instituicao'=>(int)$id,'senha_hash'=>$hash,'email_unico'=>in_array(['email'],$indices,true)]);
    } elseif ($_POST['acao'] === 'conferir') {
        $q=$pdo->prepare('SELECT id_aluno,nome,email,matricula,id_instituicao,data_nascimento,curso,cidade,meta_horas,senha_hash FROM aluno WHERE email=:email');
        $q->execute(['email'=>$_POST['email']]); $rows=$q->fetchAll(); $aluno=$rows[0]??null;
        echo json_encode(['quantidade'=>count($rows),'id'=>$aluno['id_aluno']??null,
            'hash_valido'=>$aluno && is_string($aluno['senha_hash']) && password_verify($_POST['senha'],$aluno['senha_hash']),
            'senha_nao_texto'=>$aluno && $aluno['senha_hash']!==$_POST['senha'],
            'dados_corretos'=>$aluno && $aluno['matricula']===$_POST['matricula']
                && (int)$aluno['id_instituicao']===(int)$_POST['instituicao']
                && $aluno['data_nascimento']==='2000-01-15'
                && $aluno['cidade']==='Cidade de teste' && (int)$aluno['meta_horas']===60]);
    } else { http_response_code(400); }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['sqlstate'=>$e->getCode(),'mysql'=>$e->errorInfo[1]??null]);
}
"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    client = urllib.request.build_opener(
        NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    token = secrets.token_hex(32)
    bridge = ROOT / ("_teste_cadastro_" + secrets.token_hex(16) + ".php")

    def request(path, data=None, headers=None):
        req = urllib.request.Request(BASE + path,
            data=urllib.parse.urlencode(data).encode() if data is not None else None,
            headers=headers or {})
        try:
            response = client.open(req, timeout=15)
        except urllib.error.HTTPError as e:
            response = e
        with response:
            return response.status, response.read().decode("utf-8"), response.headers

    def db(data):
        status, body, _ = request(bridge.name, data, {"X-Teste": token})
        result = json.loads(body)
        if status != 200:
            raise RuntimeError("Falha no diagnóstico PDO: " + json.dumps(result))
        return result

    def csrf(path="cadastro.php"):
        status, body, _ = request(path)
        assert status == 200, f"GET {path}: HTTP {status}"
        return re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]

    try:
        bridge.write_text(PHP.replace("__TOKEN__", token), encoding="utf-8")
        config = db({"acao": "preparar"})
        print("Instituição fictícia disponível, ID:", config["instituicao"])
        if not config["senha_hash"] or not config["email_unico"]:
            print("PENDENTE: aplicar a alteração SQL autorizada com conta administrativa.")
            return 2
        suffix = secrets.token_hex(6)
        password = secrets.token_urlsafe(24)
        data = {
            "csrf": csrf(), "nome": "Teste Cadastro Complementa", "email": f"teste-{suffix}@example.invalid",
            "curso": "Engenharia de Software", "matricula": "TESTE-" + suffix,
            "id_instituicao": config["instituicao"], "data_nascimento": "2000-01-15",
            "cidade": "Cidade de teste", "meta_horas": "60", "senha": password, "confirmacao": password,
        }
        for override in ({"meta_horas": "1000"}, {"data_nascimento": "2000-02-30"},
                         {"nome": "x"*101}, {"id_instituicao": "0"}):
            status, body, _ = request("cadastro.php", data | override)
            assert status == 200 and 'role="alert"' in body, "Validação de campo falhou"
        print("Validações do schema real: OK")
        status, _, headers = request("cadastro.php", data)
        assert status == 303 and headers["Location"] == "login.php", f"Cadastro falhou: HTTP {status}"
        print("Cadastro HTTP real: OK")
        checked = db({"acao": "conferir", "email": data["email"], "senha": password,
                      "matricula": data["matricula"], "instituicao": config["instituicao"]})
        assert checked["quantidade"] == 1 and checked["hash_valido"] and checked["senha_nao_texto"] and checked["dados_corretos"], "Persistência incorreta"
        print("Persistência, relacionamento e hash: OK. Aluno de teste ID:", checked["id"])
        for override in ({"matricula": "OUTRA-" + suffix}, {"email": f"outro-{suffix}@example.invalid"}):
            status, body, _ = request("cadastro.php", data | override)
            assert status == 200 and "já possui cadastro" in body, "Duplicidade não rejeitada"
        print("E-mail duplicado e matrícula na mesma instituição: rejeitados")
        login_token = csrf("login.php")
        login = {"csrf": login_token, "perfil": "aluno", "email": data["email"], "senha": "senha-incorreta"}
        status, body, _ = request("login.php", login)
        assert status == 200 and "incorretos" in body
        status, _, headers = request("login.php", login | {"senha": password})
        assert status == 303 and headers["Location"] == "eventos.php"
        assert request("login.php")[0] == 303  # Sessão reconhecida.
        status, body, _ = request("evento.php?id=0")  # Página sem consulta a eventos.
        assert status == 404 and "Teste Cadastro Complementa" in body
        logout_token = re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]
        assert logout_token != login_token
        assert request("logout.php", {"csrf": logout_token})[0] == 303
        assert request("login.php")[0] == 200
        print("Senha incorreta, login, renovação de CSRF e logout: OK")
        print("Registros de teste mantidos. Nenhum registro foi apagado.")
        return 0
    finally:
        bridge.unlink(missing_ok=True)


if __name__ == "__main__":
    raise SystemExit(main())
