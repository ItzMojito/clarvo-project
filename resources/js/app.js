// Polyfill popover hanya jika dukungan native belum tersedia
if (!Object.hasOwn(HTMLElement.prototype, 'popover')) {
    import('@oddbird/popover-polyfill').catch(() => {});
}

let supportsDisplayTransition;
function canTransitionDisplay() {
    if (supportsDisplayTransition !== undefined) return supportsDisplayTransition;
    if (!window.CSS?.supports?.('transition-behavior', 'allow-discrete') || !document.body) {
        return false;
    }
    const probe = document.createElement('div');
    probe.style.cssText = 'transition: display 1s allow-discrete !important; display: block;';
    document.body.appendChild(probe);
    getComputedStyle(probe).display;
    probe.style.display = 'none';
    supportsDisplayTransition = getComputedStyle(probe).display === 'block';
    probe.remove();
    return supportsDisplayTransition;
}

// Buka/tutup dialog "Tentang" native secara halus
document.addEventListener('click', (event) => {
    const openTrigger = event.target.closest('[data-open-dialog]');
    if (openTrigger) {
        const dialogId = openTrigger.getAttribute('data-open-dialog');
        const dialog = document.getElementById(dialogId);
        if (dialog && typeof dialog.showModal === 'function') {
            // Tutup popover menu seluler jika sedang terbuka
            const mobileMenu = document.getElementById('kawung-mobile-menu');
            if (mobileMenu?.matches(':popover-open')) {
                mobileMenu.hidePopover?.();
            }
            dialog.showModal();
        }
        return;
    }

    const closeTrigger = event.target.closest('[data-close-dialog]');
    if (closeTrigger) {
        const dialog = closeTrigger.closest('dialog');
        if (dialog) closeDialogGracefully(dialog);
        return;
    }

    // Backdrop click to close native <dialog>
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        const rect = event.target.getBoundingClientRect();
        const clickedInDialog =
            event.clientY >= rect.top &&
            event.clientY <= rect.bottom &&
            event.clientX >= rect.left &&
            event.clientX <= rect.right;
        if (!clickedInDialog) {
            closeDialogGracefully(event.target);
        }
    }
});

async function closeDialogGracefully(dialog) {
    if (canTransitionDisplay()) {
        dialog.close();
        return;
    }
    dialog.setAttribute('data-closing', '');
    const animations = dialog.getAnimations();
    if (animations.length > 0) {
        await Promise.race([
            Promise.allSettled(animations.map((a) => a.finished)),
            new Promise((r) => setTimeout(r, 400)),
        ]);
    }
    dialog.removeAttribute('data-closing');
    dialog.close();
}

// Auto-scroll area chat tutor setiap kali Livewire memperbarui DOM
function scrollChatFeedToBottom() {
    const feed = document.getElementById('tutor-chat-feed');
    if (!feed) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    feed.scrollTo({
        top: feed.scrollHeight,
        behavior: reduced ? 'auto' : 'smooth',
    });
}

const openMathKeypads = new Set();
const dismissedMathKeypads = new Set();

// Keyboard matematika hanya dibuka otomatis di layar sentuh; di laptop pakai tombol toggle.
const isTouchScreen = () => window.matchMedia('(pointer: coarse)').matches;

// inputmode="none" menahan keyboard bawaan ponsel selama keypad matematika dipakai.
// Momen belajar: bandingkan penguasaan dan langkah aktif sebelum dan sesudah Livewire memperbarui halaman.
let learningState = null;

function readLearningState() {
    const mastery = {};
    document.querySelectorAll('[data-mastery-code]').forEach((track) => {
        mastery[track.dataset.masteryCode] = parseFloat(track.dataset.masteryValue);
    });
    const active = document.querySelector('[data-step-status="active"]');
    return { mastery, activeStep: active ? Number(active.dataset.stepOrder) : null };
}

function markLearningMoments() {
    const next = readLearningState();
    if (learningState) {
        document.querySelectorAll('[data-mastery-code]').forEach((track) => {
            const before = learningState.mastery[track.dataset.masteryCode];
            const after = next.mastery[track.dataset.masteryCode];
            if (before === undefined || !(after > before)) return;
            track.classList.add('mastery-rising');
            if (before < 0.85 && after >= 0.85) track.classList.add('gate-just-crossed');
        });
        if (next.activeStep !== null && learningState.activeStep !== null && next.activeStep > learningState.activeStep) {
            document.querySelector('[data-step-status="active"]')?.classList.add('step-just-unlocked');
        }
    }
    learningState = next;
}

function syncMathInputModes() {
    document.querySelectorAll('[data-math-input]').forEach((input) => {
        if (isTouchScreen() && !dismissedMathKeypads.has(input.dataset.mathInput)) {
            input.setAttribute('inputmode', 'none');
        } else {
            input.removeAttribute('inputmode');
        }
    });
}

function syncMathKeypads() {
    for (const keypadId of [...openMathKeypads]) {
        const keypad = document.getElementById(keypadId);
        const toggle = document.querySelector(`[data-math-toggle="${keypadId}"]`);
        if (!keypad) {
            openMathKeypads.delete(keypadId);
            continue;
        }
        keypad.hidden = false;
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
    syncMathInputModes();
}

function setMathKeypadOpen(keypadId, shouldOpen, { markDismissed = false } = {}) {
    const keypad = document.getElementById(keypadId);
    const toggle = document.querySelector(`[data-math-toggle="${keypadId}"]`);

    if (shouldOpen) {
        for (const otherId of [...openMathKeypads]) {
            if (otherId !== keypadId) {
                setMathKeypadOpen(otherId, false);
            }
        }
        if (keypad) keypad.hidden = false;
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        openMathKeypads.add(keypadId);
        dismissedMathKeypads.delete(keypadId);
        syncMathInputModes();
        return;
    }

    if (keypad) keypad.hidden = true;
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    openMathKeypads.delete(keypadId);
    if (markDismissed) {
        dismissedMathKeypads.add(keypadId);
    } else {
        dismissedMathKeypads.delete(keypadId);
    }
    syncMathInputModes();
}

function insertIntoMathInput(input, rawText) {
    const val = input.value;
    const start = input.selectionStart ?? val.length;
    const end = input.selectionEnd ?? val.length;
    let text = rawText;

    if (text.startsWith(' ') && start > 0 && val[start - 1] === ' ') {
        text = text.slice(1);
    }

    input.value = val.slice(0, start) + text + val.slice(end);
    const nextPos = start + text.length;
    input.setSelectionRange(nextPos, nextPos);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
}

document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', () => {
            requestAnimationFrame(scrollChatFeedToBottom);
            markLearningMoments();
            syncMathKeypads();
        });
    }
});

window.addEventListener('open-auth-dialog', () => {
    const mobileMenu = document.getElementById('landing-mobile-menu') || document.getElementById('kawung-mobile-menu');
    if (mobileMenu?.matches(':popover-open')) {
        mobileMenu.hidePopover?.();
    }
    const dialog = document.getElementById('tuntas-auth-dialog');
    if (dialog && !dialog.open && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
});

// Auto-open math keyboard saat mengetuk/memfokuskan box soal maupun box jawaban/chat AI
document.addEventListener('focusin', (event) => {
    const input = event.target.closest?.('[data-math-input]');
    if (!input) return;
    const keypadId = input.dataset.mathInput;
    if (keypadId && isTouchScreen() && !dismissedMathKeypads.has(keypadId)) {
        setMathKeypadOpen(keypadId, true);
    }
});

document.addEventListener('mousedown', (event) => {
    if (event.target.closest?.('[data-math-keypad] button, [data-math-toggle]')) {
        event.preventDefault();
    }
});

document.addEventListener('click', (event) => {
    const toggleBtn = event.target.closest?.('[data-math-toggle]');
    if (toggleBtn) {
        const keypadId = toggleBtn.getAttribute('data-math-toggle');
        const keypad = keypadId ? document.getElementById(keypadId) : null;
        if (!keypad) return;
        const willOpen = keypad.hidden;
        setMathKeypadOpen(keypadId, willOpen, { markDismissed: !willOpen });
        const input = document.getElementById(keypad.dataset.targetInput);
        input?.focus();
        return;
    }

    const keyBtn = event.target.closest?.('[data-math-keypad] button');
    if (keyBtn) {
        const keypad = keyBtn.closest('[data-math-keypad]');
        const keypadId = keypad?.id;
        const input = keypad ? document.getElementById(keypad.dataset.targetInput) : null;
        if (!keypad || !input) return;

        if (keyBtn.dataset.mathInsert !== undefined) {
            insertIntoMathInput(input, keyBtn.dataset.mathInsert);
            return;
        }

        const action = keyBtn.dataset.mathAction;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;

        if (action === 'backspace') {
            if (start !== end) {
                input.value = input.value.slice(0, start) + input.value.slice(end);
                input.setSelectionRange(start, start);
            } else if (start > 0) {
                input.value = input.value.slice(0, start - 1) + input.value.slice(start);
                input.setSelectionRange(start - 1, start - 1);
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        } else if (action === 'left') {
            const pos = Math.max(0, start - 1);
            input.setSelectionRange(pos, pos);
            input.focus();
        } else if (action === 'right') {
            const pos = Math.min(input.value.length, end + 1);
            input.setSelectionRange(pos, pos);
            input.focus();
        } else if (action === 'abc') {
            // Fokus ulang agar ponsel menampilkan keyboard huruf setelah inputmode dilepas.
            setMathKeypadOpen(keypadId, false, { markDismissed: true });
            input.blur();
            input.focus();
        } else if (action === 'close') {
            setMathKeypadOpen(keypadId, false, { markDismissed: true });
            if (isTouchScreen()) {
                input.blur();
            } else {
                input.focus();
            }
        } else if (action === 'submit') {
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.form?.requestSubmit();
        }
        return;
    }

    for (const keypadId of [...openMathKeypads]) {
        const keypad = document.getElementById(keypadId);
        const wrapper = keypad?.closest('[data-math-wrapper]');
        if (wrapper && !wrapper.contains(event.target)) {
            setMathKeypadOpen(keypadId, false, { markDismissed: false });
        }
    }
});

document.addEventListener('keydown', (event) => {
    const mathInput = event.target.closest?.('[data-math-input]');
    if (mathInput && event.key === 'Enter') {
        if (event.isComposing || event.keyCode === 229) {
            event.preventDefault();
            return;
        }
    }

    if (event.key === 'Escape') {
        const wrapper = event.target.closest?.('[data-math-wrapper]');
        const keypad = wrapper?.querySelector('[data-math-keypad]');
        if (keypad && !keypad.hidden) {
            event.preventDefault();
            setMathKeypadOpen(keypad.id, false, { markDismissed: true });
            document.getElementById(keypad.dataset.targetInput)?.focus();
            return;
        }
    }

    const tab = event.target.closest('[role="tab"]');
    if (!tab) return;
    const tablist = tab.closest('[role="tablist"]');
    if (!tablist) return;

    const tabs = [...tablist.querySelectorAll('[role="tab"]')];
    const idx = tabs.indexOf(tab);
    if (idx === -1) return;

    let nextIdx = null;
    if (event.key === 'ArrowRight') nextIdx = (idx + 1) % tabs.length;
    else if (event.key === 'ArrowLeft') nextIdx = (idx - 1 + tabs.length) % tabs.length;
    else if (event.key === 'Home') nextIdx = 0;
    else if (event.key === 'End') nextIdx = tabs.length - 1;

    if (nextIdx !== null) {
        event.preventDefault();
        tabs[nextIdx].focus();
        tabs[nextIdx].click();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    scrollChatFeedToBottom();
    syncMathInputModes();
    learningState = readLearningState();
    document.querySelectorAll('dialog[data-auto-open="true"]').forEach((dialog) => {
        if (!dialog.open && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });
});
