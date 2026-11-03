const menuToggle = document.querySelector('.menu-toggle');
// As prévias usam arquivos locais ou o download autenticado, sem expor uploads.
const certificateUrls = new Map();

function openCertificateViewer(url, isPdf) {
    const dialog = document.createElement('dialog');
    dialog.className = 'certificate-viewer';
    dialog.setAttribute('aria-label', 'Certificado em tela cheia');
    const toolbar = document.createElement('div');
    toolbar.className = 'certificate-viewer-toolbar';
    const title = document.createElement('strong');
    title.textContent = 'Certificado';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'close-control';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Fechar certificado');
    close.title = 'Fechar certificado';
    close.autofocus = true;
    close.addEventListener('click', () => dialog.close());
    toolbar.append(title, close);
    const documentView = document.createElement(isPdf ? 'object' : 'img');
    if (isPdf) {
        documentView.type = 'application/pdf';
        documentView.data = `${url}#view=Fit`;
        documentView.setAttribute('aria-label', 'Certificado em PDF');
        const fallback = document.createElement('a');
        fallback.href = url;
        fallback.target = '_blank';
        fallback.rel = 'noopener';
        fallback.textContent = 'Abrir PDF no navegador';
        documentView.append(fallback);
    } else {
        documentView.src = url;
        documentView.alt = 'Certificado ampliado';
    }
    const previousFocus = document.activeElement;
    dialog.append(toolbar, documentView);
    document.body.append(dialog);
    dialog.addEventListener('close', () => {
        document.documentElement.classList.remove('certificate-viewer-open');
        dialog.remove();
        previousFocus?.focus();
    }, { once: true });
    document.documentElement.classList.add('certificate-viewer-open');
    dialog.showModal();
}

function clearCertificatePreview(container) {
    const oldUrl = certificateUrls.get(container);
    container.replaceChildren();
    if (oldUrl) URL.revokeObjectURL(oldUrl);
    certificateUrls.delete(container);
}

function showCertificatePreview(container, file) {
    clearCertificatePreview(container);
    container.hidden = false;
    if (!['application/pdf', 'image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        container.textContent = 'Escolha um PDF, JPG ou PNG de até 5 MB para visualizar.';
        return;
    }
    const url = URL.createObjectURL(file);
    certificateUrls.set(container, url);
    const isPdf = file.type === 'application/pdf';
    const preview = document.createElement(isPdf ? 'object' : 'img');
    if (isPdf) {
        preview.type = 'application/pdf';
        preview.data = `${url}#page=1&view=Fit&toolbar=0&navpanes=0`;
        preview.setAttribute('aria-label', 'Prévia do certificado em PDF');
        preview.textContent = 'Prévia indisponível neste navegador. Abra o certificado abaixo.';
    } else {
        preview.src = url;
        preview.alt = 'Prévia do certificado';
        preview.addEventListener('error', () => {
            preview.replaceWith(document.createTextNode('Não foi possível exibir a imagem.'));
        });
    }
    const thumbnail = document.createElement('button');
    thumbnail.type = 'button';
    thumbnail.className = 'certificate-thumbnail';
    thumbnail.setAttribute('aria-label', 'Abrir certificado em tela cheia');
    thumbnail.setAttribute('aria-haspopup', 'dialog');
    preview.setAttribute('aria-hidden', 'true');
    preview.setAttribute('tabindex', '-1');
    thumbnail.append(preview);
    thumbnail.addEventListener('click', () => openCertificateViewer(url, isPdf));
    const link = document.createElement('button');
    link.type = 'button';
    link.className = 'text-link';
    link.textContent = 'Ampliar certificado';
    link.setAttribute('aria-haspopup', 'dialog');
    link.addEventListener('click', () => openCertificateViewer(url, isPdf));
    container.append(thumbnail, link);
}

document.querySelectorAll('[data-certificate-input]').forEach((input) => {
    const container = input.closest('label').nextElementSibling;
    input.addEventListener('change', () => {
        if (input.files[0]) showCertificatePreview(container, input.files[0]);
        else {
            clearCertificatePreview(container);
            container.hidden = true;
        }
    });
    input.form.addEventListener('reset', () => {
        clearCertificatePreview(container);
        container.hidden = true;
    });
});

document.querySelectorAll('[data-certificate-url]').forEach(async (container) => {
    container.textContent = 'Carregando prévia…';
    try {
        const response = await fetch(container.dataset.certificateUrl, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('Arquivo indisponível');
        const file = await response.blob();
        if (!['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)) throw new Error('Arquivo indisponível');
        showCertificatePreview(container, file);
    } catch {
        container.textContent = 'Prévia indisponível. Tente baixar o certificado.';
    }
});

document.querySelectorAll('form').forEach((form) => {
    const decision = form.querySelector('select[name="status"]');
    const approvedHours = form.querySelector('input[name="horas"]');
    if (!decision || !approvedHours) return;
    decision.addEventListener('change', () => {
        if (decision.value === 'Recusada') approvedHours.value = '0';
    });
});

window.addEventListener('pagehide', (event) => {
    if (!event.persisted) certificateUrls.forEach((url) => URL.revokeObjectURL(url));
});

const mainNav = document.querySelector('#main-nav');

if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = mainNav.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', String(isOpen));
    });

    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            mainNav.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
        });
    });
}
