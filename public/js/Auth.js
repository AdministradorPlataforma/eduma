/* auth.js - Interactions for login */

document.addEventListener('DOMContentLoaded', () => {
    console.log('EDUMA: Login module initialized');

    const loginForm = document.querySelector('form');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerText;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Validando...';
                submitBtn.disabled = true;

                // Safety timeout in case of backend error preventing page reload
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerText = originalText;
                }, 10000);
            }
        });
    }

    // 2. Captcha Reload
    const captchaImg = document.getElementById('captchaImg');
    if (captchaImg) {
        captchaImg.addEventListener('click', function () {
            const baseUrl = window.BASE_URL || '/eduma2/';
            this.src = baseUrl + 'captcha/image?t=' + Math.random();
        });
    }
});
