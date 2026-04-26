window.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const emailErrorFocus = body.dataset.emailErrorFocus === 'true';
    const passErrorFocus = body.dataset.passErrorFocus === 'true';

    if (emailErrorFocus) {
        document.getElementById('emailField').focus();
    } else if (passErrorFocus) {
        document.getElementById('passwordField').focus();
    }
});
