// Chamado por eventos_mysql.py; credenciais fictícias chegam apenas por stdin.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import net from 'node:net';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const data = JSON.parse(input);
const edge = process.env.EDGE_PATH || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
if (!existsSync(edge)) throw new Error('Microsoft Edge não encontrado; configure EDGE_PATH.');
const output = path.resolve('.test-runtime', 'browser-' + crypto.randomBytes(8).toString('hex'));
await mkdir(output, { recursive: true });
const port = await new Promise(resolve => {
  const server = net.createServer();
  server.listen(0, '127.0.0.1', () => {
    const port = server.address().port;
    server.close(() => resolve(port));
  });
});
const child = spawn(edge, ['--headless=new', '--disable-gpu', '--no-first-run',
  '--no-default-browser-check', '--remote-debugging-address=127.0.0.1',
  `--remote-debugging-port=${port}`, `--user-data-dir=${path.join(output, 'profile')}`, 'about:blank'],
  { windowsHide: true, stdio: 'ignore' });
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
let sequence = 0;
const pending = new Map();
const exceptions = [];
function call(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout CDP: ${method}`)); }, 15000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error('Falha JavaScript no teste do navegador.');
  return result.result.value;
}
async function waitFor(expression) {
  for (let i = 0; i < 100; i++) {
    try { if (await evaluate(expression)) return; } catch {}
    await sleep(100);
  }
  throw new Error('Página não atingiu estado esperado: ' + expression);
}
async function navigate(page) {
  await call('Page.navigate', { url: 'http://127.0.0.1:8000/' + page });
  await waitFor(`location.pathname === ${JSON.stringify('/' + page.split('?')[0])} && document.readyState === 'complete'`);
}
async function submit(fields) {
  await evaluate(`(() => {
    const fields = ${JSON.stringify(fields)};
    for (const [name,value] of Object.entries(fields)) {
      const el = document.querySelector('[name="'+name+'"]');
      if (!el) throw new Error('Campo ausente: '+name);
      el.value = value;
      el.dispatchEvent(new Event('input',{bubbles:true}));
      el.dispatchEvent(new Event('change',{bubbles:true}));
    }
    const form = document.querySelector('main form');
    if (!form.reportValidity()) throw new Error('Formulário inválido');
    form.requestSubmit();
  })()`);
}
function check(value, message) { if (!value) throw new Error(message); }
try {
  let targets;
  for (let i = 0; i < 100; i++) {
    try {
      targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
      if (targets.some(t => t.type === 'page')) break;
    } catch {}
    await sleep(100);
  }
  const target = targets?.find(t => t.type === 'page');
  if (!target) throw new Error('Edge não iniciou a interface de teste.');
  socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  socket.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
    const handler = pending.get(message.id);
    if (handler) {
      clearTimeout(handler.timer); pending.delete(message.id);
      if (message.error) handler.reject(new Error(message.error.message));
      else handler.resolve(message.result);
    }
  };
  await call('Page.enable');
  await call('Runtime.enable');
  await call('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
  await navigate('login.php');
  await submit({ perfil: 'organizador', email: data.email, senha: data.senha });
  await waitFor(`location.pathname === '/meus_eventos.php' && document.readyState === 'complete'`);
  await navigate('novo_evento.php');
  await submit({ titulo: data.titulo, descricao: 'Evento fictício criado pelo navegador para validar a Sprint 2.',
    id_categoria: String(data.categoria), data_evento: data.data, horario_inicio: '09:00',
    horario_termino: '10:30', modalidade: 'Online', cidade: '', local_evento: '',
    link: 'https://example.invalid/sprint2', carga_horaria: '1.5' });
  await waitFor(`location.pathname === '/evento.php' && document.readyState === 'complete'`);
  const evento = await evaluate(`Number(new URL(location.href).searchParams.get('id'))`);
  check(evento > 0, 'Evento não foi criado no navegador.');
  check(await evaluate(`document.querySelector('h1').textContent === ${JSON.stringify(data.titulo)}`), 'Título incorreto');
  check(await evaluate(`document.body.innerText.includes('1,5 horas') && document.body.innerText.includes('Necessita validação')`), 'Detalhes incompletos');
  const capturas = [];
  for (const [name, width, height] of [['desktop', 1366, 900], ['mobile', 390, 844]]) {
    await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await sleep(250);
    check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), `Rolagem horizontal em ${name}`);
    const screenshot = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    const file = path.join(output, `${name}.png`);
    await writeFile(file, Buffer.from(screenshot.data, 'base64'));
    capturas.push(file);
  }
  await evaluate(`document.querySelector('.menu-toggle').click()`);
  check(await evaluate(`document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'true'`), 'Menu mobile não abriu');
  await evaluate(`document.querySelector('#main-nav a[href="eventos.php"]').click()`);
  await waitFor(`location.pathname === '/eventos.php' && document.readyState === 'complete'`);
  check(await evaluate(`document.body.innerText.includes(${JSON.stringify(data.titulo)})`), 'Evento ausente na listagem');
  check(await evaluate(`document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'false'`), 'Menu não fechou');
  await navigate('meus_eventos.php');
  check(await evaluate(`document.body.innerText.includes(${JSON.stringify(data.titulo)})`), 'Evento ausente na área do organizador');
  await evaluate(`document.querySelector('form[action="logout.php"]').requestSubmit()`);
  await waitFor(`location.pathname === '/index.php' && document.readyState === 'complete'`);
  check(await evaluate(`!!document.querySelector('#main-nav a[href="login.php"]')`), 'Logout falhou');
  check(exceptions.length === 0, 'Erros JavaScript no navegador: ' + exceptions.join(', '));
  console.log(JSON.stringify({ evento, capturas }));
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    socket.send(JSON.stringify({ id: ++sequence, method: 'Browser.close' }));
    socket.close();
  }
  await sleep(500);
  if (child.exitCode === null) child.kill();
}
