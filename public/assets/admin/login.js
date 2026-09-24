const password = document.querySelector('#admin-password');
const toggle = document.querySelector('.password-toggle');
if (password && toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const reveal = password.type === 'password';
        password.type = reveal ? 'text' : 'password';
        toggle.textContent = reveal ? 'Hide' : 'Show';
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
}
