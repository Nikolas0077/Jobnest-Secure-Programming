// Dashboard (Slot B): ask before destructive actions on forms with data-confirm.
// Runs in the capture phase so a cancelled submit never reaches other handlers.
// This is only a convenience; the server still checks role, ownership, and CSRF.
document.addEventListener('submit', event => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);
