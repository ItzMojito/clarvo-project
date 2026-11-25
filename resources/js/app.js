// Polyfill popover hanya jika dukungan native belum tersedia
if (!Object.hasOwn(HTMLElement.prototype, 'popover')) {
    import('https://unpkg.com/@oddbird/popover-polyfill@latest/dist/popover.min.js').catch(() => {});
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

document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', () => {
            requestAnimationFrame(scrollChatFeedToBottom);
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

document.addEventListener('keydown', (event) => {
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
    document.querySelectorAll('dialog[data-auto-open="true"]').forEach((dialog) => {
        if (!dialog.open && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });
});
