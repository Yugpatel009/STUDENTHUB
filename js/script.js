
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
    roleSelect.addEventListener("change", function () {
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
    loginForm.addEventListener("submit", function (event) {
        // Prevent form from reloading page automatically
        event.preventDefault();

        // Get the values entered by the user
        var email = document.getElementById("login-email").value;
        var password = document.getElementById("login-password").value;

        // Simple validation check
        if (email === "" || password === "") {
            alert("Please fill in all fields!");
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
    registerForm.addEventListener("submit", function (event) {
        // Prevent form from reloading page automatically
        event.preventDefault();

        // Get values from the input fields
        var name = document.getElementById("student-name").value;
        var email = document.getElementById("student-email").value;
        var password = document.getElementById("student-password").value;

        if (name === "" || email === "" || password === "") {
            alert("Please fill in all fields!");
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
    logoutLink.addEventListener("click", function () {
        // Clear login session details
        localStorage.removeItem("isLoggedIn");
        localStorage.removeItem("email");

        // Redirect back to home landing page
        // Note: HTML anchor element already has href="../index.html" so this just cleans local storage
    });
}


// --- RANDOM QUOTES GENERATOR (FOR LOGIN/SIGNUP PAGES) ---
var quotes = [
    {
        quote: "Education is the passport to the future.",
        author: "Malcolm X"
    },
    {
        quote: "Success is the sum of small efforts repeated every day.",
        author: "Robert Collier"
    },
    {
        quote: "Learning never exhausts the mind.",
        author: "Leonardo da Vinci"
    },
    {
        quote: "The future belongs to those who learn more skills.",
        author: "Brian Tracy"
    },
    {
        quote: "Dream big. Work hard. Stay focused.",
        author: "Anonymous"
    },
    {
        quote: "Knowledge is power.",
        author: "Francis Bacon"
    },
    {
        quote: "Your education is your strongest investment.",
        author: "Anonymous"
    },
    {
        quote: "Every expert was once a beginner.",
        author: "Helen Hayes"
    }
];

var quoteEl = document.getElementById("quote");
var authorEl = document.getElementById("author");

// Check if these elements exist on the current page before running the quote slider
if (quoteEl !== null && authorEl !== null) {
    // Select and display a random quote on page load
    var randomIdx = Math.floor(Math.random() * quotes.length);
    quoteEl.textContent = '"' + quotes[randomIdx].quote + '"';
    authorEl.textContent = "— " + quotes[randomIdx].author;

    // Function to change quote dynamically
    function changeQuote() {
        var nextIdx = Math.floor(Math.random() * quotes.length);
        quoteEl.textContent = '"' + quotes[nextIdx].quote + '"';
        authorEl.textContent = "— " + quotes[nextIdx].author;
    }

    // Automatically rotate quotes every 3 seconds for readability
    setInterval(changeQuote, 3000);
}
