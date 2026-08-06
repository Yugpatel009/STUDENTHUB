
// Check if user is logged in for protected pages
function checkAuthentication() {
    var path = window.location.pathname;
    var page = path.split("/").pop();

    // List of pages that require login
    if (page === "dashboard.html" || 
        page === "courses.html" || 
        page === "assignment.html" || 
        page === "attendance.html" || 
        page === "result.html" || 
        page === "profile.html") {
        
        // If not logged in, redirect to login page
        if (localStorage.getItem("isLoggedIn") !== "true") {
            window.location.href = "login.html";
        }
    }
}

// Run auth check immediately when the script loads
checkAuthentication();

// --- TOGGLE COURSE DROPDOWN FOR STUDENT ROLE ---
var roleSelect = document.getElementById("login-role");
var courseGroup = document.getElementById("course-group");
var courseSelect = document.getElementById("student-course");

if (roleSelect !== null && courseGroup !== null && courseSelect !== null) {
    roleSelect.addEventListener("change", function() {
        if (roleSelect.value === "student") {
            courseGroup.style.display = "block";
            courseSelect.required = true;
        } else {
            courseGroup.style.display = "none";
            courseSelect.required = false;
            courseSelect.value = ""; // Reset course selection
        }
    });
}

// --- LOGIN FORM SUBMISSION ---
var loginForm = document.getElementById("loginForm");
if (loginForm !== null) {
    var loginPasswordInput = document.getElementById("login-password");

    if (loginPasswordInput !== null) {
        loginPasswordInput.addEventListener("input", function() {
            if (loginPasswordInput.value !== "" && loginPasswordInput.value.charAt(0) !== "@") {
                loginPasswordInput.setCustomValidity("@ required");
            } else {
                loginPasswordInput.setCustomValidity("");
            }
        });
    }

    loginForm.addEventListener("submit", function(event) {
        // Prevent form from reloading page automatically
        event.preventDefault();

        // Get the values entered by the user
        var email = document.getElementById("login-email").value;
        var password = document.getElementById("login-password").value;

        // Simple validation check
        if (email === "" || password === "") {
            alert("Please fill in all fields!");
        } else if (password.charAt(0) !== "@") {
            alert("@ required");
        } else {
            // Save login state in local storage
            localStorage.setItem("isLoggedIn", "true");
            localStorage.setItem("email", email);

            // Redirect to dashboard page
            window.location.href = "dashboard.html";
        }
    });
}

// --- SIGN UP FORM SUBMISSION ---
var registerForm = document.getElementById("registerForm");
if (registerForm !== null) {
    var studentPasswordInput = document.getElementById("student-password");
    var confirmPasswordInput = document.getElementById("confirm-password");
    var passwordStrengthMsg = document.getElementById("password-strength");
    var confirmPasswordMsg = document.getElementById("confirm-password-msg");

    function isStrongPassword(password) {
        return password.length >= 8 &&
            /[A-Z]/.test(password) &&
            /[a-z]/.test(password) &&
            /[0-9]/.test(password) &&
            /[^A-Za-z0-9]/.test(password);
    }

    function updatePasswordMessages() {
        var password = studentPasswordInput.value;
        var confirmPassword = confirmPasswordInput.value;
        var strongPassword = isStrongPassword(password);

        if (password === "") {
            passwordStrengthMsg.textContent = "Enter strong password";
            passwordStrengthMsg.className = "password-msg";
            studentPasswordInput.setCustomValidity("");
        } else if (strongPassword) {
            passwordStrengthMsg.textContent = "Strong password";
            passwordStrengthMsg.className = "password-msg success";
            studentPasswordInput.setCustomValidity("");
        } else {
            passwordStrengthMsg.textContent = "Enter strong password";
            passwordStrengthMsg.className = "password-msg error";
            studentPasswordInput.setCustomValidity("Enter strong password");
        }

        if (confirmPassword === "") {
            confirmPasswordMsg.textContent = "";
            confirmPasswordMsg.className = "password-msg";
            confirmPasswordInput.setCustomValidity("");
        } else if (!strongPassword) {
            confirmPasswordMsg.textContent = "Enter strong password first";
            confirmPasswordMsg.className = "password-msg error";
            confirmPasswordInput.setCustomValidity("Enter strong password first");
        } else if (password === confirmPassword) {
            confirmPasswordMsg.textContent = "Password matched";
            confirmPasswordMsg.className = "password-msg success";
            confirmPasswordInput.setCustomValidity("");
        } else {
            confirmPasswordMsg.textContent = "Password not matched";
            confirmPasswordMsg.className = "password-msg error";
            confirmPasswordInput.setCustomValidity("Password not matched");
        }
    }

    studentPasswordInput.addEventListener("input", updatePasswordMessages);
    confirmPasswordInput.addEventListener("input", updatePasswordMessages);

    registerForm.addEventListener("submit", function(event) {
        // Prevent form from reloading page automatically
        event.preventDefault();

        // Get values from the input fields
        var name = document.getElementById("student-name").value;
        var email = document.getElementById("student-email").value;
        var password = document.getElementById("student-password").value;
        var confirmPassword = document.getElementById("confirm-password").value;

        if (name === "" || email === "" || password === "" || confirmPassword === "") {
            alert("Please fill in all fields!");
        } else if (!isStrongPassword(password)) {
            alert("Enter strong password");
        } else if (password !== confirmPassword) {
            alert("Password not matched");
        } else {
            // Show registration success message
            alert("Account created successfully for " + name + "! Please login.");
            
            // Redirect to login page
            window.location.href = "login.html";
        }
    });
}

// --- LOGOUT LINK ---
var logoutLink = document.getElementById("logoutLink");
if (logoutLink !== null) {
    logoutLink.addEventListener("click", function() {
        // Clear login session details
        localStorage.removeItem("isLoggedIn");
        localStorage.removeItem("email");
        
        // Redirect back to home landing page
        // Note: HTML anchor element already has href="../index.html" so this just cleans local storage
    });
}
