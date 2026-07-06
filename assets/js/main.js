// Main JavaScript file for basic interactions

document.addEventListener('DOMContentLoaded', function() {
    console.log("AutoRental initialized.");

    // Simple form validation example for registration
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert("Passwords do not match!");
            }
        });
    }
});
