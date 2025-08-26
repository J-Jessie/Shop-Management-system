<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: url('images/family.jpeg') no-repeat center/cover;
            font-family: 'Arial', sans-serif;
            overflow-y: auto; /* Changed from hidden to auto for scrolling */
        }
        .container {
            position: relative;
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            padding: 30px;
            text-align: center;
            transform: translateX(100%); /* Start off-screen */
            transition: transform 0.6s ease-in-out;
            z-index: 1;
        }
        .container.active {
            transform: translateX(0%); /* Slide into view */
        }
        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/lg.jpg'); /* This image path also needs checking */
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;
            opacity: 0.5;
            z-index: -1;
            border-radius: 15px;
        }
        h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 28px;
        }
        .dropdown {
            position: relative;
            width: 100%;
            margin-bottom: 20px;
        }
        .dropdown label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            text-align: left;
            color: #444;
        }
        .dropdown select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background-color: #f9f9f9;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #444;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border 0.3s;
        }
        .form-group input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        .password-strength {
            height: 5px;
            background: #eee;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            text-align: left;
        }
        .password-requirements ul {
            padding-left: 20px;
            margin: 5px 0 0 0;
        }
        .password-requirements li {
            margin-bottom: 3px;
        }
        .password-requirements .valid {
            color: #28a745;
        }
        .password-requirements .invalid {
            color: #dc3545;
        }
        .btn {
            background-color: #2c3e50;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            margin-top: 15px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            background-color: #1a252f;
            transform: translateY(-2px);
        }
        .btn:active {
            transform: translateY(0);
        }
        .role-form {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            display: none;
            animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        .login-link {
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }
        .login-link a:hover {
            color: #1a252f;
            text-decoration: underline;
        }
        .terms {
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            text-align: center;
        }
        .terms a {
            color: #2c3e50;
            text-decoration: none;
        }
        .terms a:hover {
            text-decoration: underline;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            right: 15px;
            top: 42px;
            color: #999;
            cursor: pointer;
        }
        .progress-container {
            width: 100%;
            margin: 20px 0;
            display: none;
        }
        .progress-bar {
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            width: 0%;
            background: #4CAF50;
            transition: width 0.4s;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        .step {
            font-size: 12px;
            color: #999;
        }
        .step.active {
            color: #2c3e50;
            font-weight: bold;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <div class="container" id="signup-container">
        <h2>Create Your Account</h2>
        
        <!-- Message display area -->
        <div id="message" class="message"></div>

        <!-- Progress Bar -->
        <div class="progress-container" id="progress-container">
            <div class="progress-bar">
                <div class="progress" id="progress"></div>
            </div>
            <div class="progress-steps">
                <div class="step" id="step1">1. Select Role</div>
                <div class="step" id="step2">2. Fill Details</div>
                <div class="step" id="step3">3. Complete</div>
            </div>
        </div>

        <!-- Role Selection Dropdown -->
        <div class="dropdown">
            <label for="role">I am signing up as:</label>
            <select id="role" name="role" onchange="showForm()" required>
                <option value="">-- Select Your Role --</option>
                <option value="sales_staff">Sales Staff</option>
                <option value="customer">Customer</option>
                <option value="shopkeeper">Shopkeeper</option>
            </select>
        </div>

        <!-- Sales Staff Signup Form -->
        <form id="sales_staff-form" class="role-form" action="process_signup.php" method="post">
            <input type="hidden" name="role" value="sales_staff">
            <div class="form-group">
                <label for="sales_staff-fullname">Full Name:</label>
                <input type="text" id="sales_staff-fullname" name="fullname" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="sales_staff-email">Email:</label>
                <input type="email" id="sales_staff-email" name="email" required placeholder="Enter your email address">
            </div>
            <div class="form-group">
                <label for="sales_staff-password">Password:</label>
                <div class="input-icon">
                    <input type="password" id="sales_staff-password" name="password" required 
                           placeholder="Create a password" oninput="checkPasswordStrength(this.value, 'sales_staff')">
                    <i class="fas fa-eye" onclick="togglePassword('sales_staff-password', this)"></i>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="sales_staff-strength-bar"></div>
                </div>
                <div class="password-requirements" id="sales_staff-password-reqs">
                    <p>Password must contain:</p>
                    <ul>
                        <li class="invalid" id="sales_staff-length">At least 8 characters</li>
                        <li class="invalid" id="sales_staff-uppercase">1 uppercase letter</li>
                        <li class="invalid" id="sales_staff-number">1 number</li>
                        <li class="invalid" id="sales_staff-special">1 special character</li>
                    </ul>
                </div>
            </div>
            <div class="form-group">
                <label for="sales_staff-confirm_password">Confirm Password:</label>
                <div class="input-icon">
                    <input type="password" id="sales_staff-confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password" oninput="checkPasswordMatch('sales_staff')">
                    <i class="fas fa-eye" onclick="togglePassword('sales_staff-confirm_password', this)"></i>
                </div>
                <p id="error-message-sales_staff" style="color: red; display: none; margin-top: 5px;">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match!
                </p>
            </div>
            <div class="form-group">
                <input type="checkbox" id="sales_staff-terms" name="terms" required>
                <label for="sales_staff-terms" style="display: inline; font-weight: normal;">
                    I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a>
                </label>
            </div>
            <button type="submit" class="btn" id="sales_staff-submit">
                <i class="fas fa-user-tie"></i> Sign Up as Sales Staff
            </button>
            <div class="login-link">
                Already have an account? <a href="Login.php">Log in here</a>
            </div>
        </form>

        <!-- Customer Signup Form -->
        <form id="customer-form" class="role-form" action="process_signup.php" method="post">
            <input type="hidden" name="role" value="customer">
            <div class="form-group">
                <label for="customer-fullname">Full Name:</label>
                <input type="text" id="customer-fullname" name="fullname" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="customer-email">Email:</label>
                <input type="email" id="customer-email" name="email" required placeholder="Enter your email address">
            </div>
            <div class="form-group">
                <label for="customer-phone">Phone Number:</label>
                <input type="tel" id="customer-phone" name="phone" required placeholder="Enter your phone number">
            </div>
            <div class="form-group">
                <label for="customer-password">Password:</label>
                <div class="input-icon">
                    <input type="password" id="customer-password" name="password" required 
                           placeholder="Create a password" oninput="checkPasswordStrength(this.value, 'customer')">
                    <i class="fas fa-eye" onclick="togglePassword('customer-password', this)"></i>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="customer-strength-bar"></div>
                </div>
                <div class="password-requirements" id="customer-password-reqs">
                    <p>Password must contain:</p>
                    <ul>
                        <li class="invalid" id="customer-length">At least 8 characters</li>
                        <li class="invalid" id="customer-uppercase">1 uppercase letter</li>
                        <li class="invalid" id="customer-number">1 number</li>
                        <li class="invalid" id="customer-special">1 special character</li>
                    </ul>
                </div>
            </div>
            <div class="form-group">
                <label for="customer-confirm_password">Confirm Password:</label>
                <div class="input-icon">
                    <input type="password" id="customer-confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password" oninput="checkPasswordMatch('customer')">
                    <i class="fas fa-eye" onclick="togglePassword('customer-confirm_password', this)"></i>
                </div>
                <p id="error-message-customer" style="color: red; display: none; margin-top: 5px;">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match!
                </p>
            </div>
            <div class="form-group">
                <input type="checkbox" id="customer-terms" name="terms" required>
                <label for="customer-terms" style="display: inline; font-weight: normal;">
                    I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a>
                </label>
            </div>
            <button type="submit" class="btn" id="customer-submit">
                <i class="fas fa-user"></i> Sign Up as Customer
            </button>
            <div class="login-link">
                Already have an account? <a href="Login.php">Log in here</a>
            </div>
        </form>

        <!-- Shopkeeper Signup Form -->
        <form id="shopkeeper-form" class="role-form" action="process_signup.php" method="post">
            <input type="hidden" name="role" value="shopkeeper">
            <div class="form-group">
                <label for="shopkeeper-fullname">Full Name:</label>
                <input type="text" id="shopkeeper-fullname" name="fullname" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="shopkeeper-email">Email:</label>
                <input type="email" id="shopkeeper-email" name="email" required placeholder="Enter your email address">
            </div>
            <div class="form-group">
                <label for="shopkeeper-phone">Phone Number:</label>
                <input type="tel" id="shopkeeper-phone" name="phone" required placeholder="Enter your phone number">
            </div>
            <div class="form-group">
                <label for="shopkeeper-business_name">Business Name:</label>
                <input type="text" id="shopkeeper-business_name" name="business_name" required placeholder="Enter your business name">
            </div>
            <div class="form-group">
                <label for="shopkeeper-password">Password:</label>
                <div class="input-icon">
                    <input type="password" id="shopkeeper-password" name="password" required 
                           placeholder="Create a password" oninput="checkPasswordStrength(this.value, 'shopkeeper')">
                    <i class="fas fa-eye" onclick="togglePassword('shopkeeper-password', this)"></i>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="shopkeeper-strength-bar"></div>
                </div>
                <div class="password-requirements" id="shopkeeper-password-reqs">
                    <p>Password must contain:</p>
                    <ul>
                        <li class="invalid" id="shopkeeper-length">At least 8 characters</li>
                        <li class="invalid" id="shopkeeper-uppercase">1 uppercase letter</li>
                        <li class="invalid" id="shopkeeper-number">1 number</li>
                        <li class="invalid" id="shopkeeper-special">1 special character</li>
                    </ul>
                </div>
            </div>
            <div class="form-group">
                <label for="shopkeeper-confirm_password">Confirm Password:</label>
                <div class="input-icon">
                    <input type="password" id="shopkeeper-confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password" oninput="checkPasswordMatch('shopkeeper')">
                    <i class="fas fa-eye" onclick="togglePassword('shopkeeper-confirm_password', this)"></i>
                </div>
                <p id="error-message-shopkeeper" style="color: red; display: none; margin-top: 5px;">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match!
                </p>
            </div>
            <div class="form-group">
                <input type="checkbox" id="shopkeeper-terms" name="terms" required>
                <label for="shopkeeper-terms" style="display: inline; font-weight: normal;">
                    I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a>
                </label>
            </div>
            <button type="submit" class="btn" id="shopkeeper-submit">
                <i class="fas fa-store"></i> Sign Up as Shopkeeper
            </button>
            <div class="login-link">
                Already have an account? <a href="Login.php">Log in here</a>
            </div>
        </form>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" style="display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <h2>Terms and Conditions</h2>
            <div style="margin-bottom: 20px;">
                <p>Please read these terms and conditions carefully before using our service.</p>
                <h3>1. Account Registration</h3>
                <p>You must provide accurate and complete information when creating an account. You are responsible for maintaining the confidentiality of your account credentials.</p>
                
                <h3>2. User Responsibilities</h3>
                <p>You agree to use the service only for lawful purposes and in accordance with these terms. You must not misuse the service by knowingly introducing viruses or other malicious material.</p>
                
                <h3>3. Privacy Policy</h3>
                <p>Your personal information will be handled in accordance with our Privacy Policy, which explains how we collect, use, and protect your data.</p>
                
                <h3>4. Termination</h3>
                <p>We may terminate or suspend your account immediately, without prior notice, for any breach of these terms.</p>
            </div>
            <button onclick="closeTerms()" style="padding: 10px 20px; background-color: #2c3e50; color: white; border: none; border-radius: 5px; cursor: pointer;">I Understand</button>
        </div>
    </div>

    <script>
        // Slide-in effect
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                document.getElementById('signup-container').classList.add('active');
            }, 1500);
            
            // Check for success/error messages in URL
            const urlParams = new URLSearchParams(window.location.search);
            const message = urlParams.get('message');
            const status = urlParams.get('status');
            
            if (message && status) {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = decodeURIComponent(message);
                messageDiv.className = 'message ' + status;
                messageDiv.style.display = 'block';
                
                // Hide message after 5 seconds
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 8000); // Increased timeout to 8 seconds
            }
        });

        // Show form based on selected role
        function showForm() {
            var role = document.getElementById("role").value;
            const progressContainer = document.getElementById('progress-container');
            
            // Hide all forms
            document.getElementById("sales_staff-form").style.display = "none";
            document.getElementById("customer-form").style.display = "none";
            document.getElementById("shopkeeper-form").style.display = "none";

            // Show progress bar when a role is selected
            if (role) {
                progressContainer.style.display = 'block';
                document.getElementById('progress').style.width = '33%';
                document.getElementById('step1').classList.add('active');
                document.getElementById('step2').classList.remove('active'); // Reset for new selection
                document.getElementById('step3').classList.remove('active'); // Reset for new selection
            } else {
                progressContainer.style.display = 'none';
                document.getElementById('step1').classList.remove('active');
            }

            // Show the selected form
            if (role === "sales_staff") {
                document.getElementById("sales_staff-form").style.display = "block";
            } else if (role === "customer") {
                document.getElementById("customer-form").style.display = "block";
            } else if (role === "shopkeeper") {
                document.getElementById("shopkeeper-form").style.display = "block";
            }
        }

        // Password confirmation logic
        function checkPasswordMatch(role) {
            const password = document.getElementById(`${role}-password`).value;
            const confirmPassword = document.getElementById(`${role}-confirm_password`).value;
            const errorMessage = document.getElementById(`error-message-${role}`);
            const submitBtn = document.getElementById(`${role}-submit`);

            let passwordsMatch = false;

            if (confirmPassword && password !== confirmPassword) {
                errorMessage.style.display = "block";
                passwordsMatch = false;
            } else if (password && confirmPassword && password === confirmPassword) {
                errorMessage.style.display = "none";
                passwordsMatch = true;
            } else {
                errorMessage.style.display = "none";
                passwordsMatch = false; // Or true if both are empty, adjust as needed
            }

            // Also check password strength before enabling submit
            const strengthBar = document.getElementById(`${role}-strength-bar`);
            const isPasswordStrong = (strengthBar.style.width === '100%'); // Check if all criteria met

            if (passwordsMatch && isPasswordStrong) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = "1";
                submitBtn.style.cursor = "pointer";
                document.getElementById('progress').style.width = '66%';
                document.getElementById('step2').classList.add('active');
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = "0.7";
                submitBtn.style.cursor = "not-allowed";
                document.getElementById('step2').classList.remove('active');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password, role) {
            // Strength indicators
            const strengthBar = document.getElementById(`${role}-strength-bar`);
            const lengthReq = document.getElementById(`${role}-length`);
            const uppercaseReq = document.getElementById(`${role}-uppercase`);
            const numberReq = document.getElementById(`${role}-number`);
            const specialReq = document.getElementById(`${role}-special`);
            
            // Reset
            strengthBar.style.width = '0%';
            strengthBar.style.backgroundColor = '#dc3545';
            lengthReq.className = 'invalid';
            uppercaseReq.className = 'invalid';
            numberReq.className = 'invalid';
            specialReq.className = 'invalid';
            
            // Check password length
            if (password.length >= 8) {
                lengthReq.className = 'valid';
            }
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) {
                uppercaseReq.className = 'valid';
            }
            
            // Check for numbers
            if (/[0-9]/.test(password)) {
                numberReq.className = 'valid';
            }
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) {
                specialReq.className = 'valid';
            }
            
            // Calculate strength
            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Change color based on strength
            if (strength > 75) {
                strengthBar.style.backgroundColor = '#28a745';
            } else if (strength > 50) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else if (strength > 25) {
                strengthBar.style.backgroundColor = '#fd7e14';
            } else {
                strengthBar.style.backgroundColor = '#dc3545';
            }
            
            // Also check password match if confirm password field has value
            checkPasswordMatch(role); // This will enable/disable the submit button
        }

        // Toggle password visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Terms and Conditions modal
        function showTerms() {
            document.getElementById('termsModal').style.display = 'block';
        }

        function closeTerms() {
            document.getElementById('termsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Form submission handler with AJAX
        document.addEventListener("DOMContentLoaded", function() {
            const forms = document.querySelectorAll(".role-form");
            
            forms.forEach(form => {
                form.addEventListener("submit", async function(event) {
                    event.preventDefault(); // Prevent default form submission
                    
                    // Validate terms checkbox
                    const termsCheckbox = form.querySelector('input[type="checkbox"][name="terms"]');
                    if (!termsCheckbox.checked) {
                        displayMessage("Please agree to the Terms and Conditions to proceed.", "error");
                        return;
                    }
                    
                    // Update progress to complete (optimistic update)
                    document.getElementById('progress').style.width = '100%';
                    document.getElementById('step3').classList.add('active');
                    
                    try {
                        const formData = new FormData(form);
                        // IMPORTANT: We now expect a JSON response, not a redirect.
                        const response = await fetch(form.action, { 
                            method: form.method,
                            body: formData,
                            // Do not set Content-Type header for FormData, browser sets it correctly
                            redirect: 'manual' // Keep manual to catch any unexpected redirects, though PHP won't send them now
                        });
                        
                        console.log('process_signup.php response status:', response.status);
                        
                        // Parse JSON response
                        const result = await response.json(); // Expect JSON
                        console.log('Server JSON response:', result);

                        if (result.status === 'success') {
                            displayMessage(result.message, 'success');
                            // Perform client-side redirect after a short delay
                            setTimeout(() => {
                                window.location.replace(result.redirect_url); 
                            }, 1500); // Redirect after 1.5 seconds to show success message
                        } else if (result.status === 'error' && result.message) {
                            displayMessage(result.message, 'error');
                            // Reset progress bar on error
                            document.getElementById('progress').style.width = '66%';
                            document.getElementById('step3').classList.remove('active');
                        } else {
                            // Fallback for unexpected JSON structure
                            displayMessage("An unexpected server response occurred. Please check console for details.", "error");
                            console.error('Unexpected JSON structure:', result);
                            // Reset progress bar on error
                            document.getElementById('progress').style.width = '66%';
                            document.getElementById('step3').classList.remove('active');
                        }
                    } catch (error) {
                        // This catch block handles network errors (like Status 0) or JSON parsing errors
                        displayMessage("A network or parsing error occurred. Please check your internet connection and console for details.", "error");
                        console.error('Fetch or JSON parse error:', error);
                        // Reset progress bar on error
                        document.getElementById('progress').style.width = '66%';
                        document.getElementById('step3').classList.remove('active');
                    } finally {
                        // Ensure progress bar is reset or finalized based on outcome
                        const currentProgress = document.getElementById('progress').style.width;
                        if (currentProgress === '100%' && !window.location.href.includes('Login.php')) {
                             document.getElementById('progress').style.width = '0%'; // Reset completely
                             document.getElementById('step3').classList.remove('active');
                             document.getElementById('step2').classList.remove('active');
                             document.getElementById('step1').classList.remove('active');
                        }
                    }
                });
            });
            
            // Helper function to display messages
            function displayMessage(msg, type) {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = msg;
                messageDiv.className = `message ${type}`;
                messageDiv.style.display = 'block';
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 8000); // Hide after 8 seconds
            }

            // Initial check for success message when page loads (for non-AJAX fallback or initial load)
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            
            if (message && status) {
                displayMessage(decodeURIComponent(message), status);
            }
        });
    </script>
</body>
</html>