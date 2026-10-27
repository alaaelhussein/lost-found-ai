/**
 * Scripts côté client - Lost&Found AI (JavaScript vanilla, sans framework).
 *
 * Ce fichier est chargé sur toutes les pages (voir src/Views/layout.php, avec
 * « defer » : il s'exécute une fois le HTML lu). Chaque fonctionnalité est
 * initialisée seulement si les éléments HTML concernés existent sur la page.
 *
 * Règle imposée par la Content-Security-Policy : aucun JavaScript dans le HTML
 * (pas de onclick="...", onsubmit="..."). Les comportements sont branchés ici,
 * à partir d'attributs data-*. Le site reste utilisable sans JavaScript :
 * ces scripts ne font qu'améliorer le confort.
 */

initNavToggle();
initPasswordToggles();
initPasswordStrength();
initConfirmForms();
initCharCounters();
initPhotoPreview();
initChat();
initAutoSubmit();
initPrintButtons();

/**
 * Envoie automatiquement le formulaire quand on change une option
 * (choix du mode de livraison : la page se recharge avec les bonnes offres).
 * Sans JavaScript, l'utilisateur clique simplement sur le bouton du formulaire.
 *
 * HTML attendu : <input type="radio" name="mode" data-autosubmit>
 */
function initAutoSubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach((field) => {
        field.addEventListener('change', () => field.form.requestSubmit());
    });
}

/**
 * Bouton « Imprimer » (étiquette d'envoi). window.print() ne peut pas être
 * appelé depuis un attribut onclick : la Content-Security-Policy l'interdit.
 *
 * HTML attendu : <button type="button" data-print>Imprimer</button>
 */
function initPrintButtons() {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
}

/**
 * Menu repliable sur mobile. Sans JavaScript, le bouton reste caché et le
 * menu est toujours affiché (voir .js-nav dans le CSS).
 */
function initNavToggle() {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('site-nav');
    if (!toggle || !nav) {
        return;
    }

    document.documentElement.classList.add('js-nav');
    toggle.hidden = false;

    const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        nav.classList.toggle('is-open', open);
    };

    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
}

/**
 * Boutons « Afficher / Masquer » des champs mot de passe.
 *
 * HTML attendu :
 *   <input type="password" id="password">
 *   <button type="button" class="btn-toggle-password" data-target="password">Afficher</button>
 */
function initPasswordToggles() {
    document.querySelectorAll('.btn-toggle-password').forEach((button) => {
        const input = document.getElementById(button.dataset.target);
        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'Masquer' : 'Afficher';
            button.setAttribute('aria-pressed', String(isHidden));
        });
    });
}

/**
 * Indicateur de robustesse du mot de passe (inscription).
 * Purement indicatif : les vraies règles sont vérifiées en PHP.
 *
 * HTML attendu : <input data-strength="id-du-bloc"> + <div id="id-du-bloc" hidden>
 */
function initPasswordStrength() {
    document.querySelectorAll('[data-strength]').forEach((input) => {
        const meter = document.getElementById(input.dataset.strength);
        if (!meter) {
            return;
        }
        const label = meter.querySelector('.strength-label');
        const levels = ['Trop court', 'Faible', 'Moyen', 'Bon', 'Excellent'];

        input.addEventListener('input', () => {
            const value = input.value;
            meter.hidden = value === '';

            let score = 0;
            if (value.length >= 8) {
                score++;
                if (/\p{L}/u.test(value) && /\d/.test(value)) score++;
                if (value.length >= 12) score++;
                if (/[^\p{L}\d]/u.test(value) || /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value)) score++;
            }

            meter.dataset.level = String(score);
            label.textContent = levels[score];
        });
    });
}

/**
 * Demande une confirmation avant d'envoyer un formulaire dangereux.
 *
 * HTML attendu : <form data-confirm="Supprimer cette annonce ?">
 */
function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}

/**
 * Compteur de caractères sous un champ texte limité (maxlength).
 *
 * HTML attendu : <textarea maxlength="2000" data-counter="id-du-compteur">
 */
function initCharCounters() {
    document.querySelectorAll('[data-counter]').forEach((field) => {
        const counter = document.getElementById(field.dataset.counter);
        const max = Number(field.getAttribute('maxlength'));
        if (!counter || !max) {
            return;
        }

        const update = () => {
            const remaining = max - field.value.length;
            counter.textContent = `${field.value.length} / ${max}`;
            counter.classList.toggle('is-near-limit', remaining < max * 0.1);
        };
        field.addEventListener('input', update);
        update();
    });
}

/**
 * Aperçu de la photo choisie dans le formulaire d'annonce, avant l'envoi.
 * L'image est lue localement (URL blob:), rien n'est envoyé au serveur ici.
 */
function initPhotoPreview() {
    const input = document.querySelector('.dropzone-input');
    const zone = document.querySelector('.dropzone');
    if (!input || !zone) {
        return;
    }
    const preview = zone.querySelector('.dropzone-preview');
    const title = zone.querySelector('.dropzone-empty strong');
    let currentUrl = null;

    input.addEventListener('change', () => {
        if (currentUrl) {
            URL.revokeObjectURL(currentUrl);
            currentUrl = null;
        }

        const file = input.files && input.files[0];
        const isImage = file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);

        zone.classList.toggle('has-preview', Boolean(isImage));
        preview.hidden = !isImage;
        title.textContent = isImage ? file.name : 'Choisir une photo';

        if (isImage) {
            currentUrl = URL.createObjectURL(file);
            preview.src = currentUrl;
        } else {
            preview.removeAttribute('src');
        }
    });
}

/**
 * Messagerie : affiche directement les derniers messages et permet
 * d'envoyer avec Ctrl + Entrée (ou Cmd + Entrée sur Mac).
 */
function initChat() {
    const messages = document.getElementById('messages');
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }

    document.querySelectorAll('[data-submit-shortcut]').forEach((field) => {
        field.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && field.value.trim() !== '') {
                event.preventDefault();
                field.form.requestSubmit();
            }
        });
    });
}
