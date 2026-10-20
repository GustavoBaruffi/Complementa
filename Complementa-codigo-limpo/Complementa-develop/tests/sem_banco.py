"""Testes HTTP sem banco real. Requer Python 3 e PHP no PATH.

O servidor usa uma porta de banco reservada e sem listener. Nenhuma consulta
chega ao MySQL existente. Sessões de teste são criadas em diretório temporário.
"""
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import uuid
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class HttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        runtime = ROOT / ".test-runtime"
        runtime.mkdir(exist_ok=True)
        cls.temp = runtime / uuid.uuid4().hex
        cls.temp.mkdir()
        cls.db_socket = socket.socket()
        cls.db_socket.bind(("127.0.0.1", 0))  # Reserva, mas não aceita conexões.
        with socket.socket() as web_socket:
            web_socket.bind(("127.0.0.1", 0))
            cls.port = web_socket.getsockname()[1]
        cls.base = f"http://127.0.0.1:{cls.port}/"
        env = os.environ.copy()
        env.update(DB_HOST="127.0.0.1", DB_PORT=str(cls.db_socket.getsockname()[1]),
                   DB_NAME="complementa_test_inacessivel", DB_USER="teste", DB_PASSWORD="")
        cls.log = open(cls.temp / "php.log", "w+", encoding="utf-8")
        cls.server = subprocess.Popen(
            ["php", "-d", f"session.save_path={cls.temp}", "-d", "display_errors=0",
             "-S", f"127.0.0.1:{cls.port}", "-t", str(ROOT)],
            env=env, stdout=cls.log, stderr=cls.log,
        )
        for _ in range(50):
            try:
                urllib.request.urlopen(cls.base + "login.php", timeout=1).close()
                break
            except (OSError, urllib.error.URLError):
                time.sleep(.1)
        else:
            cls.server.terminate()
            cls.server.wait()
            cls.log.close()
            cls.db_socket.close()
            cls.cleanup()
            raise RuntimeError("Servidor PHP não iniciou.")

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)
        cls.log.seek(0)
        output = cls.log.read()
        cls.log.close()
        cls.db_socket.close()
        cls.cleanup()
        if "Fatal error" in output or "PHP Warning" in output:
            raise AssertionError(output)

    @classmethod
    def cleanup(cls):
        assert cls.temp.resolve().parent == (ROOT / ".test-runtime").resolve()
        for item in cls.temp.iterdir():
            item.unlink()
        cls.temp.rmdir()

    def setUp(self):
        self.client = urllib.request.build_opener(
            NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, cookie=None):
        headers = {"Cookie": cookie} if cookie else {}
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        req = urllib.request.Request(self.base + path, data=encoded, headers=headers)
        try:
            response = self.client.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode(), response.headers

    def token(self, path="cadastro.php"):
        status, body, _ = self.request(path)
        self.assertEqual(status, 503 if path == "cadastro.php" else 200)
        return re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]

    def session(self, perfil):
        session_id = os.urandom(16).hex()
        code = """ini_set('session.use_strict_mode','0'); session_id($argv[1]);
session_start(); $_SESSION=['usuario'=>['id'=>1,'nome'=>'<Teste>','perfil'=>$argv[2]],
'csrf'=>str_repeat('a',64)]; session_write_close();"""
        subprocess.run(["php", "-d", f"session.save_path={self.temp}", "-r",
                        code, session_id, perfil], check=True, capture_output=True)
        return "PHPSESSID=" + session_id

    def test_public_forms_and_cookie(self):
        status, body, headers = self.request("cadastro.php")
        self.assertEqual(status, 503)
        self.assertIn('autocomplete="new-password"', body)
        self.assertIn("HttpOnly", headers["Set-Cookie"])
        self.assertIn("SameSite=Lax", headers["Set-Cookie"])
        self.assertNotIn('value="" type="password"', body)
        self.assertEqual(self.request("login.php")[0], 200)

    def test_csrf_required(self):
        for path in ("cadastro.php", "login.php", "logout.php"):
            self.assertEqual(self.request(path, {"csrf": "invalido"})[0], 403)

    def test_registration_validation_and_escape(self):
        token = self.token()
        status, body, _ = self.request("cadastro.php", {
            "csrf": token, "nome": "<script>alert(1)</script>", "email": "invalido",
            "curso": "", "senha": "123", "confirmacao": "456"})
        self.assertEqual(status, 503)
        self.assertIn("e-mail válido", body)
        self.assertIn("Informe seu curso", body)
        self.assertIn("confirmação da senha", body)
        self.assertIn("&lt;script&gt;", body)
        self.assertNotIn("<script>alert(1)</script>", body)
        self.assertNotIn('value="123"', body)

    def test_array_inputs_rejected_without_crash(self):
        token = self.token()
        status, body, _ = self.request("cadastro.php", {
            "csrf": token, "nome[]": "x", "email[]": "x", "senha[]": "x"})
        self.assertEqual(status, 503)
        self.assertIn("Informe seu nome", body)
        self.assertEqual(self.request("evento.php?id[]=1")[0], 404)

    def test_login_profile_allowlist(self):
        token = self.token("login.php")
        status, body, _ = self.request("login.php", {
            "csrf": token, "perfil": "aluno; DROP TABLE aluno", "email": "a@example.com", "senha": "12345678"})
        self.assertEqual(status, 200)
        self.assertIn("Confira o perfil", body)

    def test_guest_cannot_manage_events(self):
        for path in ("novo_evento.php", "meus_eventos.php"):
            status, _, headers = self.request(path)
            self.assertEqual(status, 303)
            self.assertEqual(headers["Location"], "login.php")

    def test_student_cannot_manage_events(self):
        cookie = self.session("aluno")
        for path in ("novo_evento.php", "meus_eventos.php"):
            self.assertEqual(self.request(path, cookie=cookie)[0], 403)
        self.assertEqual(self.request("novo_evento.php", {"csrf": "a"*64}, cookie)[0], 403)

    def test_logout_invalid_token_keeps_session(self):
        cookie = self.session("aluno")
        self.assertEqual(self.request("logout.php", {"csrf": "errado"}, cookie)[0], 403)
        self.assertEqual(self.request("login.php", cookie=cookie)[0], 303)

    def test_logout_destroys_session(self):
        cookie = self.session("aluno")
        status, _, headers = self.request("logout.php", {"csrf": "a"*64}, cookie)
        self.assertEqual(status, 303)
        self.assertEqual(headers["Location"], "index.php")
        self.assertEqual(self.request("novo_evento.php", cookie=cookie)[0], 303)
        self.assertEqual(self.request("login.php", cookie=cookie)[0], 200)

    def test_logout_get_not_allowed(self):
        status, _, headers = self.request("logout.php")
        self.assertEqual(status, 405)
        self.assertEqual(headers["Allow"], "POST")

    def test_missing_event(self):
        for query in ("", "?id=-1", "?id=abc", "?id=0"):
            self.assertEqual(self.request("evento.php" + query)[0], 404)

    def test_database_unavailable_handled(self):
        for path in ("index.php", "eventos.php", "evento.php?id=1"):
            status, body, _ = self.request(path)
            self.assertEqual(status, 503)
            self.assertIn("Não foi possível acessar o banco", body)
            self.assertNotIn("SQLSTATE", body)
            self.assertNotIn("Stack trace", body)

    def test_registration_and_login_database_errors(self):
        token = self.token()
        status, body, _ = self.request("cadastro.php", {
            "csrf": token, "nome": "Teste", "curso": "Software", "email": "teste@example.com",
            "matricula": "TESTE-001", "id_instituicao": "1", "data_nascimento": "2000-01-15",
            "cidade": "Cidade de teste", "meta_horas": "60",
            "senha": "senha-teste-123", "confirmacao": "senha-teste-123"})
        self.assertEqual(status, 503)
        self.assertIn("Não foi possível acessar o banco", body)
        status, _, _ = self.request("login.php", {
            "csrf": token, "perfil": "aluno", "email": "teste@example.com", "senha": "senha-teste-123"})
        self.assertEqual(status, 503)

    def test_organizer_csrf_and_database_error(self):
        cookie = self.session("organizador")
        self.assertEqual(self.request("novo_evento.php", {"csrf": "errado"}, cookie)[0], 403)
        status, body, _ = self.request("novo_evento.php", cookie=cookie)
        self.assertEqual(status, 503)
        self.assertIn("&lt;Teste&gt;", body)

    def test_cli_tools_not_accessible_on_web(self):
        for path in ("tools/criar_organizador.php", "tools/verificar_ambiente.php"):
            self.assertEqual(self.request(path)[0], 404)

    def test_password_hash_and_verify(self):
        code = """$hash=password_hash('senha-teste-123',PASSWORD_DEFAULT);
echo json_encode([$hash !== 'senha-teste-123',password_verify('senha-teste-123',$hash),
password_verify('senha-errada',$hash)]);"""
        result = subprocess.run(["php", "-r", code], check=True, capture_output=True, text=True)
        self.assertEqual(json.loads(result.stdout), [True, True, False])

    def test_database_log_codes_without_sensitive_message(self):
        code = """require 'app.php';
$erro=new PDOException('SEGREDO_DE_TESTE', 42);
$erro->errorInfo=['42S22',1054,'SEGREDO_DE_TESTE'];
echo falhaBanco($erro);"""
        result = subprocess.run(
            ["php", "-d", f"session.save_path={self.temp}", "-r", code],
            cwd=ROOT, check=True, capture_output=True,
        )
        self.assertIn(b"MySQL=1054", result.stderr)
        self.assertIn(b"causa=coluna_inexistente", result.stderr)
        self.assertNotIn(b"SEGREDO_DE_TESTE", result.stdout + result.stderr)
        self.assertNotIn(b"MySQL=1054", result.stdout)


if __name__ == "__main__":
    unittest.main(verbosity=2)
