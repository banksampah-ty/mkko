// AUTHENTICATION JAVASCRIPT
document.addEventListener('DOMContentLoaded', function() {
    // REGISTER LINK HANDLER
    const registerLink = document.getElementById('registerLink');
    if (registerLink) {
        registerLink.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Fitur pendaftaran akan segera tersedia! Hubungi admin untuk membuat akun.');
        });
    }

    // FORM VALIDATION
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username) {
                e.preventDefault();
                alert('Username harus diisi!');
                return;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Password harus diisi!');
                return;
            }
            
            // LOADING STATE
            const submitBtn = this.querySelector('.btn');
            submitBtn.textContent = 'Memproses...';
            submitBtn.disabled = true;
        });
    }

    // DEMO CREDENTIALS AUTO-FILL
    document.getElementById('username')?.addEventListener('input', function(e) {
        const username = e.target.value;
        const demoPasswords = {
            'admin': 'password',
            'user': 'password'
        };
        
        if (demoPasswords[username]) {
            // SHOW DEMO HINT
            let hint = this.parentNode.querySelector('.demo-hint');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'demo-hint';
                hint.style.cssText = `
                    background: #fff3cd;
                    color: #856404;
                    padding: 5px 10px;
                    border-radius: 4px;
                    margin-top: 5px;
                    font-size: 0.8rem;
                    border: 1px solid #ffeaa7;
                `;
                this.parentNode.appendChild(hint);
            }
            hint.textContent = `Password demo: ${demoPasswords[username]}`;
        } else {
            // REMOVE HINT
            const hint = this.parentNode.querySelector('.demo-hint');
            if (hint) {
                hint.remove();
            }
        }
    });

    console.log('Auth JS loaded successfully');
    // Tambahkan di akhir auth.js

// Validasi Form Pendaftaran
const registerForm = document.querySelector('form[action*="register"]');
if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const username = document.getElementById('username').value;
        
        // Validasi username
        if (username.length < 3) {
            e.preventDefault();
            alert('Username minimal 3 karakter!');
            return;
        }
        
        if (username.includes(' ')) {
            e.preventDefault();
            alert('Username tidak boleh mengandung spasi!');
            return;
        }
        
        // Validasi password
        if (password.length < 6) {
            e.preventDefault();
            alert('Password minimal 6 karakter!');
            return;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Password dan konfirmasi password tidak cocok!');
            return;
        }
        
        // Loading state
        const submitBtn = this.querySelector('.btn');
        submitBtn.textContent = 'Mendaftarkan...';
        submitBtn.disabled = true;
    });
    
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        const strengthDiv = document.createElement('div');
        strengthDiv.className = 'password-strength';
        passwordInput.parentNode.appendChild(strengthDiv);
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthDiv.className = 'password-strength';
            if (password.length > 0) {
                if (strength <= 1) {
                    strengthDiv.classList.add('strength-weak');
                } else if (strength <= 2) {
                    strengthDiv.classList.add('strength-medium');
                } else {
                    strengthDiv.classList.add('strength-strong');
                }
            }
        });
    }
}
});