"""Integração HTTP real em cópia e banco temporários, sem usar os dados locais.

Execute: python tests/certificados.py
Requer PHP com pdo_mysql/fileinfo e MariaDB (mysql_install_db, mysqld, mysql).
Defina PHP_BIN e MYSQL_BIN_DIR se os executáveis não estiverem no PATH/XAMPP.
"""
import http.cookiejar
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid


ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN", shutil.which("php") or "C:/xampp/php/php.exe")
MYSQL_BIN = Path(os.environ.get("MYSQL_BIN_DIR", "C:/xampp/mysql/bin"))
PDF = b"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"


def port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


class Certificados(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix="complementa-certificados-")
        cls.addClassCleanup(cls.temp.cleanup)
        cls.work = Path(cls.temp.name)
        cls.app = cls.work / "app"
        cls.app.mkdir()
        for source in ROOT.glob("*.php"):
            shutil.copy2(source, cls.app / source.name)
        cls.dbport, cls.webport = port(), port()
        subprocess.run([str(MYSQL_BIN / "mysql_install_db.exe"),
                        f"--datadir={cls.work / 'data'}", f"--port={cls.dbport}"],
                       check=True, stdout=subprocess.DEVNULL, timeout=60)
        cls.log = (cls.work / "servers.log").open("wb")
        cls.addClassCleanup(cls.log.close)
        db = subprocess.Popen([str(MYSQL_BIN / "mysqld.exe"), "--no-defaults",
                               f"--datadir={cls.work / 'data'}", f"--port={cls.dbport}",
                               "--bind-address=127.0.0.1", "--console"],
                              stdout=cls.log, stderr=cls.log)
        cls.addClassCleanup(cls.stop, db)
        cls.wait_port(cls.dbport, db)
        schema = (ROOT / "banco.sql").read_text(encoding="utf-8").split("INSERT INTO instituicao")[0]
        cls.query(schema, database=False)
        password = subprocess.check_output(
            [PHP, "-r", "echo password_hash('Teste123!', PASSWORD_DEFAULT);"], text=True)
        cls.query(f"""
            INSERT INTO instituicao VALUES
                (1,'Instituição A','A','Curitiba','a@instituicao.test'),
                (2,'Instituição B','B','Curitiba','b@instituicao.test');
            INSERT INTO categoria VALUES (1,'Teste',NULL);
            INSERT INTO organizador VALUES
                (1,'Organizador A','Empresa','org1@teste.test',NULL,'Curitiba','{password}'),
                (2,'Organizador B','Empresa','org2@teste.test',NULL,'Curitiba','{password}');
            INSERT INTO aluno VALUES
                (1,'Aluno A','aluno1@teste.test','{password}','A1',1,'2000-01-01','Curso','Curitiba',60),
                (2,'Aluno B','aluno2@teste.test','{password}','B1',2,'2000-01-01','Curso','Curitiba',60);
            INSERT INTO usuario_gestao VALUES
                (1,'Analista A','inst1@teste.test','{password}','instituicao',1),
                (2,'Analista B','inst2@teste.test','{password}','instituicao',2);
        """)
        env = dict(os.environ, DB_HOST="127.0.0.1", DB_PORT=str(cls.dbport),
                   DB_NAME="complementa", DB_USER="root", DB_PASSWORD="")
        (cls.work / "sessions").mkdir()
        web = subprocess.Popen([PHP, "-d", f"session.save_path={cls.work / 'sessions'}",
                                "-d", "upload_max_filesize=6M", "-d", "post_max_size=8M",
                                "-S", f"127.0.0.1:{cls.webport}", "router.php"],
                               cwd=cls.app, env=env, stdout=cls.log, stderr=cls.log)
        cls.addClassCleanup(cls.stop, web)
        cls.wait_port(cls.webport, web)

    @staticmethod
    def stop(process):
        process.terminate()
        process.wait(timeout=15)

    @staticmethod
    def wait_port(number, process):
        for _ in range(100):
            if process.poll() is not None:
                raise RuntimeError("Servidor encerrou antes de iniciar.")
            try:
                with socket.create_connection(("127.0.0.1", number), timeout=0.2):
                    return
            except OSError:
                time.sleep(0.1)
        raise RuntimeError("Servidor não iniciou.")

    @classmethod
    def query(cls, sql, database=True):
        command = [str(MYSQL_BIN / "mysql.exe"), "--host=127.0.0.1", f"--port={cls.dbport}",
                   "--user=root", "--default-character-set=utf8mb4", "--batch", "--skip-column-names"]
        if database:
            command.append("complementa")
        return subprocess.check_output(command, input="SET time_zone='-03:00';\n" + sql, text=True, encoding="utf-8").strip()

    def request(self, client, path, fields=None, upload=None):
        headers, body = {}, None
        if fields is not None:
            if upload is None:
                body = urllib.parse.urlencode(fields).encode()
            else:
                boundary = uuid.uuid4().hex
                parts = [f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
                         for k, v in fields.items()]
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="arquivo"; filename="certificado.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()
                             + upload + f'\r\n--{boundary}--\r\n'.encode())
                body = b"".join(parts)
                headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
        req = urllib.request.Request(f"http://127.0.0.1:{self.webport}/{path}", body, headers)
        try:
            response = client.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode("utf-8", errors="replace")

    def login(self, role, email):
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        _, page = self.request(client, "login.php")
        status, page = self.request(client, "login.php", dict(
            csrf=self.csrf(page), perfil=role, email=email, senha="Teste123!"))
        self.assertEqual(status, 200)
        self.assertIn("Sair", page)
        return client

    @staticmethod
    def csrf(page):
        return re.search(r'name="csrf" value="([^"]+)"', page).group(1)

    def post(self, client, area, fields, upload=None):
        path = "meus_eventos.php" if area == "participantes" else "painel.php?pagina=" + area
        status, page = self.request(client, path)
        self.assertEqual(status, 200)
        return self.request(client, path, dict(fields, csrf=self.csrf(page)), upload)

    def setUp(self):
        self.event = int(self.query("""
            INSERT INTO evento (id_organizador,id_categoria,titulo,descricao,data_evento,
                horario_inicio,horario_termino,carga_horaria,modalidade)
            VALUES (1,1,'Evento de teste','Descrição','2020-01-01','08:00','12:00',4,'Online');
            SELECT LAST_INSERT_ID();
        """))
        self.registration = int(self.query(f"""
            INSERT INTO inscricao (id_aluno,id_evento,data_inscricao,status,presenca)
            VALUES (1,{self.event},NOW(),'Concluída',1); SELECT LAST_INSERT_ID();
        """))
        self.org = self.login("organizador", "org1@teste.test")
        self.student = self.login("aluno", "aluno1@teste.test")
        self.institution = self.login("instituicao", "inst1@teste.test")

    def upload(self, direct=False, client=None, student=False, content=PDF):
        return self.post(client or self.org, "inscricoes" if student else "participantes",
                         dict(acao="enviar_certificado" if student else "enviar_certificado_organizador",
                              id=self.registration, horas="4", enviar_instituicao="sim" if direct else "nao"), content)

    def certificate(self):
        return self.query(f"SELECT id_certificado FROM certificado WHERE id_inscricao={self.registration}")

    def validation_count(self):
        return self.query(f"SELECT COUNT(*) FROM validacao_horas WHERE id_certificado={self.certificate()}")

    def test_manual_delivery_and_partial_approval(self):
        status, page = self.upload()
        self.assertEqual(status, 200)
        cert = self.certificate()
        self.assertEqual(self.validation_count(), "0")
        self.assertEqual(self.request(self.institution, f"arquivo.php?id={cert}")[0], 404)
        self.assertEqual(self.request(self.student, f"arquivo.php?id={cert}"), (200, PDF.decode()))
        self.assertIn("Aguardando envio à instituição", self.request(self.student, "painel.php?pagina=certificados")[1])
        status, page = self.post(self.student, "certificados", dict(acao="solicitar", id=cert, horas="3"))
        self.assertEqual(status, 200)
        self.assertEqual(self.validation_count(), "1")
        self.assertIn("Aguardando análise da instituição", page)
        self.assertEqual(self.request(self.institution, f"arquivo.php?id={cert}")[0], 200)
        validation = self.query(f"SELECT id_validacao FROM validacao_horas WHERE id_certificado={cert}")
        status, _ = self.post(self.institution, "analises", dict(
            acao="analisar", id=validation, status="Parcialmente aprovada", horas="2", observacao="Limite da categoria"))
        self.assertEqual(status, 200)
        page = self.request(self.student, "painel.php?pagina=certificados")[1]
        self.assertIn("Parcialmente aprovada", page)
        self.assertIn("Limite da categoria", page)
        self.assertEqual(self.query(f"SELECT horas_aprovadas FROM validacao_horas WHERE id_validacao={validation}"), "2.0")

    def test_direct_delivery_permissions_and_duplicates(self):
        self.assertEqual(self.upload(direct=True)[0], 200)
        cert = self.certificate()
        self.assertEqual(self.validation_count(), "1")
        self.assertEqual(self.query(f"SELECT id_instituicao,horas_solicitadas,status FROM validacao_horas WHERE id_certificado={cert}"), "1\t4.0\tPendente")
        self.assertEqual(self.request(self.institution, f"arquivo.php?id={cert}")[0], 200)
        for role, email in [("aluno", "aluno2@teste.test"), ("instituicao", "inst2@teste.test")]:
            client = self.login(role, email)
            self.assertEqual(self.request(client, f"arquivo.php?id={cert}")[0], 404)
        outsider = self.login("aluno", "aluno2@teste.test")
        self.assertIn("acesso não permitido", self.post(outsider, "certificados", dict(acao="solicitar", id=cert, horas="4"))[1])
        self.assertIn("já foi enviado", self.post(self.student, "certificados", dict(acao="solicitar", id=cert, horas="4"))[1])
        files_before = set((self.app / "uploads/certificados").iterdir())
        self.assertIn("já possui um certificado", self.upload(direct=True)[1])
        self.assertEqual(set((self.app / "uploads/certificados").iterdir()), files_before)
        self.assertEqual(self.validation_count(), "1")
        stored = self.query(f"SELECT caminho_arquivo FROM certificado_conteudo WHERE id_certificado={cert}")
        self.assertEqual(self.request(self.student, stored)[0], 404)
        anonymous = urllib.request.build_opener()
        self.assertEqual(self.request(anonymous, stored)[0], 404)

    def test_institution_grouped_list_and_selected_analysis(self):
        self.assertEqual(self.upload(direct=True)[0], 200)
        cert = self.certificate()
        self.assertEqual(self.query(f'SELECT data_recebimento IS NOT NULL AND ABS(TIMESTAMPDIFF(SECOND,data_recebimento,NOW()))<60 FROM validacao_horas WHERE id_certificado={cert}'), '1')
        self.query(f"UPDATE validacao_horas SET data_recebimento='2026-11-03 14:35:00' WHERE id_certificado={cert}")
        validation = self.query(f'SELECT id_validacao FROM validacao_horas WHERE id_certificado={cert}')
        page = self.request(self.institution, 'painel.php?pagina=analises')[1]
        self.assertIn('participant-group', page)
        self.assertIn('Aluno A</a>', page)
        self.assertIn('03/11/2026 14:35 (Brasília)', page)
        self.assertNotIn('data-certificate-url', page)
        selected = self.request(self.institution, f'painel.php?pagina=analises&analise={validation}')[1]
        self.assertIn('data-certificate-url', selected)
        self.assertIn('Recebido em', selected)
        self.assertIn('03/11/2026 14:35 (Brasília)', selected)
        self.assertIn('Concluir análise', selected)
        self.assertRegex(selected, r'<details[^>]+ open>')
        other = self.login('instituicao', 'inst2@teste.test')
        denied = self.request(other, f'painel.php?pagina=analises&analise={validation}')[1]
        self.assertNotIn('data-certificate-url', denied)
        self.assertNotIn('Aluno A', denied)

    def test_student_upload_direct(self):
        self.assertEqual(self.upload(direct=True, client=self.student, student=True)[0], 200)
        self.assertEqual(self.validation_count(), "1")

    def test_organizer_navigation_and_grouped_participants(self):
        second = int(self.query("""
            INSERT INTO evento (id_organizador,id_categoria,titulo,descricao,data_evento,
                horario_inicio,horario_termino,carga_horaria,modalidade)
            VALUES (1,1,'Outro evento','Descrição','2020-01-02','08:00','12:00',4,'Online');
            SELECT LAST_INSERT_ID();
        """))
        self.query(f"INSERT INTO inscricao (id_aluno,id_evento,data_inscricao,status,presenca) VALUES (2,{second},NOW(),'Confirmada',0)")
        status, page = self.request(self.org, "meus_eventos.php")
        self.assertEqual(status, 200)
        self.assertNotIn('>Minha área</a>', page)
        groups = dict(re.findall(r'id="evento-(\d+)"(.*?)</details>', page, re.S))
        self.assertRegex(page, rf'<details class="participant-group" id="evento-{self.event}"\s*>')
        self.assertRegex(page, rf'<details class="participant-group" id="evento-{second}"\s*>')
        self.assertIn('Aluno A', groups[str(self.event)])
        self.assertNotIn('Aluno B', groups[str(self.event)])
        self.assertIn('Aluno B', groups[str(second)])
        self.assertNotIn('Aluno A', groups[str(second)])
        self.assertIn('1 inscrito', groups[str(second)])
        status, old_page = self.request(self.org, 'painel.php?pagina=participantes')
        self.assertEqual(status, 200)
        self.assertIn('<h1>Participantes</h1>', old_page)
        outsider = self.login('organizador', 'org2@teste.test')
        other_page = self.request(outsider, 'meus_eventos.php')[1]
        self.assertNotIn('Aluno A', other_page)
        self.assertNotIn('Aluno B', other_page)
        self.assertIn('Você ainda não cadastrou eventos.', other_page)
        self.assertEqual(self.request(self.student, 'meus_eventos.php')[0], 403)
        self.assertIn('>Minha área</a>', self.request(self.student, 'painel.php')[1])

    def test_participant_table_search_filters_and_details(self):
        status, page = self.request(self.org, 'meus_eventos.php')
        self.assertEqual(status, 200)
        self.assertIn('<table class="participants-table">', page)
        self.assertNotIn('Todo mundo', page)
        self.assertNotIn('participants-tabs', page)
        self.assertIn('class="participant-group"', page)
        self.assertIn('Aluno A', page)
        filtered = self.request(self.org, 'meus_eventos.php?' + urllib.parse.urlencode(dict(evento=self.event, q='Aluno A', situacao='Concluída')))[1]
        self.assertIn('1 inscrição encontrada', filtered)
        self.assertRegex(filtered, rf'<details class="participant-group" id="evento-{self.event}"\s+open\s*>')
        self.assertIn('Aluno A', filtered)
        empty = self.request(self.org, 'meus_eventos.php?q=sem-correspondencia')[1]
        self.assertIn('Nenhum participante encontrado', empty)
        self.assertNotIn('Aluno A</a>', empty)
        mismatch = self.request(self.org, f'meus_eventos.php?evento={self.event}&situacao=Cancelada')[1]
        self.assertIn('Nenhum participante encontrado', mismatch)
        details = self.request(self.org, f'meus_eventos.php?inscricao={self.registration}')[1]
        self.assertIn('Atualizar participação', details)
        self.assertIn('Enviar certificado ao aluno', details)
        outsider = self.login('organizador', 'org2@teste.test')
        denied = self.request(outsider, f'meus_eventos.php?inscricao={self.registration}')[1]
        self.assertIn('acesso não permitido', denied)
        self.assertNotIn('Aluno A', denied)
        escaped = self.request(self.org, 'meus_eventos.php?' + urllib.parse.urlencode(dict(q='<script>alert(1)</script>')))[1]
        self.assertNotIn('<script>alert(1)</script>', escaped)

    def test_participation_update_and_csrf_on_my_events(self):
        self.query(f"UPDATE inscricao SET presenca=0,status='Confirmada' WHERE id_inscricao={self.registration}")
        status, page = self.post(self.org, 'participantes', dict(
            acao='participante', id=self.registration, status='Concluída', presenca='1'))
        self.assertEqual(status, 200)
        self.assertIn('Participação atualizada.', page)
        self.assertEqual(self.query(f'SELECT status,presenca FROM inscricao WHERE id_inscricao={self.registration}'), 'Concluída\t1')
        status, _ = self.request(self.org, 'meus_eventos.php', dict(
            acao='participante', id=self.registration, status='Confirmada', presenca='0', csrf='invalid'))
        self.assertEqual(status, 403)
        self.assertEqual(self.query(f'SELECT presenca FROM inscricao WHERE id_inscricao={self.registration}'), '1')

    def prepare_future_registration(self, status='Confirmada'):
        self.query(f"UPDATE evento SET data_evento=DATE_ADD(CURDATE(),INTERVAL 1 DAY),status='Publicado' WHERE id_evento={self.event}; UPDATE inscricao SET status='{status}',presenca=0 WHERE id_inscricao={self.registration}")

    def test_cancel_and_reenroll_from_panel_and_event(self):
        self.prepare_future_registration()
        status, page = self.post(self.student, 'inscricoes', dict(acao='cancelar', id=self.registration))
        self.assertEqual(status, 200)
        self.assertIn('registration-cancelled', page)
        self.assertIn('Inscrever-me novamente', page)
        detail_path = f'evento.php?id={self.event}'
        detail = self.request(self.student, detail_path)[1]
        self.assertIn('Sua inscrição foi cancelada', detail)
        self.assertIn('Inscrever-me novamente', detail)
        catalog = self.request(self.student, 'eventos.php')[1]
        self.assertIn('Sua inscrição foi cancelada', catalog)
        other = self.login('aluno', 'aluno2@teste.test')
        self.assertNotIn('Sua inscrição foi cancelada', self.request(other, 'eventos.php')[1])
        self.assertEqual(self.request(self.student, 'index.php')[0], 200)
        status, page = self.post(self.student, 'inscricoes', dict(acao='reativar', id=self.registration))
        self.assertEqual(status, 200)
        self.assertIn('Inscrição reativada.', page)
        self.assertEqual(self.query(f'SELECT id_inscricao,status,presenca FROM inscricao WHERE id_aluno=1 AND id_evento={self.event}'), f'{self.registration}\tConfirmada\t0')
        self.assertEqual(self.query(f'SELECT COUNT(*) FROM inscricao WHERE id_aluno=1 AND id_evento={self.event}'), '1')
        active = self.request(self.student, detail_path)[1]
        self.assertIn('Sua inscrição: Confirmada', active)
        self.assertNotIn('>Inscrever-me', active)
        duplicate = self.request(self.student, detail_path, dict(acao='inscrever', csrf=self.csrf(active)))[1]
        self.assertIn('Você já está inscrito', duplicate)
        self.post(self.student, 'inscricoes', dict(acao='cancelar', id=self.registration))
        cancelled = self.request(self.student, detail_path)[1]
        status, page = self.request(self.student, detail_path, dict(acao='inscrever', csrf=self.csrf(cancelled)))
        self.assertEqual(status, 200)
        self.assertIn('Inscrição confirmada.', page)
        self.assertEqual(self.query(f'SELECT id_inscricao,status FROM inscricao WHERE id_aluno=1 AND id_evento={self.event}'), f'{self.registration}\tConfirmada')

    def test_reenrollment_deadline_event_status_and_ownership(self):
        self.prepare_future_registration('Cancelada')
        self.query(f"UPDATE evento SET titulo='Evento para prazo' WHERE id_evento={self.event}")
        other = self.login('aluno', 'aluno2@teste.test')
        self.assertIn('acesso não permitido', self.post(other, 'inscricoes', dict(acao='reativar', id=self.registration))[1])
        cases = [
            "status='Cancelado',data_evento=DATE_ADD(CURDATE(),INTERVAL 1 DAY)",
            "status='Rascunho',data_evento=DATE_ADD(CURDATE(),INTERVAL 1 DAY)",
            "status='Publicado',data_evento=DATE_SUB(CURDATE(),INTERVAL 1 DAY)",
            "status='Publicado',data_evento=CURDATE(),horario_inicio=CURTIME(),horario_termino='23:59:59'",
        ]
        for update in cases:
            with self.subTest(update=update):
                self.query(f'UPDATE evento SET {update} WHERE id_evento={self.event}')
                page = self.request(self.student, 'painel.php?pagina=inscricoes')[1]
                card = re.search(r'<article[^>]*>\s*<h2>Evento para prazo</h2>(.*?)</article>', page, re.S)
                self.assertNotIn('Inscrever-me novamente', card.group(1))
                self.assertIn('não recebe inscrições', self.post(self.student, 'inscricoes', dict(acao='reativar', id=self.registration))[1])
                self.assertEqual(self.query(f'SELECT status FROM inscricao WHERE id_inscricao={self.registration}'), 'Cancelada')

    def test_first_enrollment_and_protected_reenrollment(self):
        self.prepare_future_registration('Cancelada')
        other = self.login('aluno', 'aluno2@teste.test')
        path = f'evento.php?id={self.event}'
        page = self.request(other, path)[1]
        self.assertIn('>Inscrever-me<', page)
        self.assertEqual(self.request(other, path, dict(acao='inscrever', csrf=self.csrf(page)))[0], 200)
        self.assertEqual(self.query(f'SELECT status FROM inscricao WHERE id_aluno=2 AND id_evento={self.event}'), 'Confirmada')
        self.query(f'UPDATE inscricao SET presenca=1 WHERE id_inscricao={self.registration}')
        self.assertIn('presença ou certificado', self.post(self.student, 'inscricoes', dict(acao='reativar', id=self.registration))[1])
        self.query(f"UPDATE inscricao SET presenca=0 WHERE id_inscricao={self.registration}; INSERT INTO certificado (id_inscricao,arquivo,data_envio,carga_horaria_declarada,status) VALUES ({self.registration},'teste.pdf',NOW(),4,'Pendente')")
        self.assertIn('presença ou certificado', self.post(self.student, 'inscricoes', dict(acao='reativar', id=self.registration))[1])
        self.assertEqual(self.query(f'SELECT status FROM inscricao WHERE id_inscricao={self.registration}'), 'Cancelada')

    def test_invalid_file_wrong_organizer_and_no_presence(self):
        self.assertIn("somente PDF", self.upload(content=b"not a certificate")[1])
        self.assertEqual(self.certificate(), "")
        outsider = self.login("organizador", "org2@teste.test")
        self.assertIn("acesso não permitido", self.upload(client=outsider, direct=True)[1])
        self.assertEqual(self.certificate(), "")
        self.query(f"UPDATE inscricao SET presenca=0 WHERE id_inscricao={self.registration}")
        self.assertIn("presença confirmada", self.upload(direct=True)[1])
        self.assertEqual(self.certificate(), "")

    def test_failed_forwarding_rolls_back_upload(self):
        self.query("""CREATE TRIGGER recusar_solicitacao BEFORE INSERT ON validacao_horas
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';""")
        try:
            files_before = set((self.app / "uploads/certificados").glob("*"))
            self.assertEqual(self.upload(direct=True)[0], 503)
            self.assertEqual(self.certificate(), "")
            self.assertEqual(set((self.app / "uploads/certificados").glob("*")), files_before)
        finally:
            self.query("DROP TRIGGER recusar_solicitacao")


if __name__ == "__main__":
    unittest.main(verbosity=2)
