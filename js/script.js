/**
 * ==========================================================================
 * STUDENT HUB - MASTER SCRIPT
 * Dynamic UI Components & Interactive Logic
 * ==========================================================================
 */

// --- 1. AUTHENTICATION & ACCESS CONTROL ---
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

// Run auth check immediately
checkAuthentication();


// --- 2. LIGHT / DARK THEME SWITCHER ---
var THEME_STORAGE_KEY = "studenthub_theme";

function getActiveTheme() {
    var saved = localStorage.getItem(THEME_STORAGE_KEY);
    if (saved === "dark" || saved === "light") {
        return saved;
    }
    return (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light";
}

function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    document.documentElement.style.colorScheme = theme;
    var toggleBtns = document.querySelectorAll(".theme-toggle");
    toggleBtns.forEach(function (btn) {
        var isDark = theme === "dark";
        btn.setAttribute("aria-label", "Switch to " + (isDark ? "light" : "dark") + " mode");
        btn.setAttribute("title", "Switch to " + (isDark ? "light" : "dark") + " mode");
    });
}

function toggleTheme() {
    var currentTheme = document.documentElement.getAttribute("data-theme") || "light";
    var nextTheme = currentTheme === "dark" ? "light" : "dark";
    localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    applyTheme(nextTheme);
}

var themeSwitcherBound = false;
function initThemeSwitcher() {
    // Always apply current theme immediately
    applyTheme(getActiveTheme());

    // Only bind event listeners once
    if (themeSwitcherBound) return;
    themeSwitcherBound = true;

    var toggleBtns = document.querySelectorAll(".theme-toggle");
    toggleBtns.forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleTheme();
        });
    });

    // Listen for system theme changes if user hasn't set explicit preference
    if (window.matchMedia) {
        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function (e) {
            if (!localStorage.getItem(THEME_STORAGE_KEY)) {
                applyTheme(e.matches ? "dark" : "light");
            }
        });
    }
}


// --- 3. RESPONSIVE HAMBURGER MENU ---
function initHamburgerMenu() {
    var hamburgerBtn = document.getElementById("hamburgerBtn");
    var navGroup = document.getElementById("mobileNavDrawer") || document.getElementById("headerNavGroup");

    if (!hamburgerBtn || !navGroup) return;

    function toggleMenu() {
        var isOpen = hamburgerBtn.classList.contains("active");
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    function openMenu() {
        hamburgerBtn.classList.add("active");
        navGroup.classList.add("active");
        hamburgerBtn.setAttribute("aria-expanded", "true");
        document.body.classList.add("modal-open");
    }

    function closeMenu() {
        hamburgerBtn.classList.remove("active");
        navGroup.classList.remove("active");
        hamburgerBtn.setAttribute("aria-expanded", "false");
        document.body.classList.remove("modal-open");
    }

    hamburgerBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        toggleMenu();
    });

    // Close when clicking any nav link
    var navLinks = navGroup.querySelectorAll("a");
    navLinks.forEach(function (link) {
        link.addEventListener("click", function () {
            closeMenu();
        });
    });

    // Close on click outside
    document.addEventListener("click", function (e) {
        if (navGroup.classList.contains("active") && !navGroup.contains(e.target) && !hamburgerBtn.contains(e.target)) {
            closeMenu();
        }
    });

    // Close on Escape key
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && navGroup.classList.contains("active")) {
            closeMenu();
        }
    });

    // Auto-close if screen resized to desktop
    window.addEventListener("resize", function () {
        if (window.innerWidth > 880 && navGroup.classList.contains("active")) {
            closeMenu();
        }
    });
}


// --- 4. NOTIFICATION BANNER COMPONENT ---
function initNotificationBanner() {
    var banner = document.getElementById("notificationBanner");
    var closeBtn = document.getElementById("closeBannerBtn");

    if (!banner) return;

    // Check if user dismissed the banner in this session
    var isDismissed = sessionStorage.getItem("studenthub_banner_dismissed");
    if (isDismissed === "true") {
        banner.classList.add("hidden");
    }

    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            banner.classList.add("hidden");
            sessionStorage.setItem("studenthub_banner_dismissed", "true");
        });
    }
}


// --- 4B. NOTIFICATION DROPDOWN COMPONENT ---
function initNotificationDropdown() {
    var notificationBtn = document.getElementById("notificationBtn");
    var notificationDropdown = document.getElementById("notificationDropdown");
    var markAllReadBtn = document.getElementById("markAllReadBtn");
    var notificationBadge = document.getElementById("notificationBadge");
    var notificationCountTag = document.getElementById("notificationCountTag");
    var unreadItems = document.querySelectorAll(".notification-item.unread");

    if (!notificationBtn || !notificationDropdown) return;

    function openDropdown() {
        notificationDropdown.classList.add("active");
        notificationBtn.setAttribute("aria-expanded", "true");
    }

    function closeDropdown() {
        notificationDropdown.classList.remove("active");
        notificationBtn.setAttribute("aria-expanded", "false");
    }

    function toggleDropdown(e) {
        e.stopPropagation();
        var isOpen = notificationDropdown.classList.contains("active");
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    notificationBtn.addEventListener("click", toggleDropdown);

    // Close when clicking outside dropdown and button
    document.addEventListener("click", function (e) {
        if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
            closeDropdown();
        }
    });

    // Close on Escape key
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && notificationDropdown.classList.contains("active")) {
            closeDropdown();
            notificationBtn.focus();
        }
    });

    // Mark as read button
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            unreadItems.forEach(function (item) {
                item.classList.remove("unread");
            });
            if (notificationBadge) {
                notificationBadge.classList.add("hidden");
            }
            if (notificationCountTag) {
                notificationCountTag.textContent = "All read";
                notificationCountTag.style.opacity = "0.7";
            }
            markAllReadBtn.textContent = "Done";
            markAllReadBtn.disabled = true;
            markAllReadBtn.style.cursor = "default";
            markAllReadBtn.style.opacity = "0.6";
        });
    }

    // Auto-close dropdown when clicking an action that opens the modal
    var modalTriggers = notificationDropdown.querySelectorAll("[data-modal-target]");
    modalTriggers.forEach(function (btn) {
        btn.addEventListener("click", function () {
            closeDropdown();
        });
    });
}


// --- 5. MODAL POPUP SYSTEM ---
function initModalSystem() {
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add("active");
        document.body.classList.add("modal-open");

        // Focus first close button or primary button inside modal
        var focusable = modal.querySelector(".modal-close-btn, .btn");
        if (focusable) focusable.focus();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove("active");
        // Remove scroll lock if no other modals are active
        if (!document.querySelector(".modal-overlay.active")) {
            document.body.classList.remove("modal-open");
        }
    }

    // Trigger buttons via data-modal-target
    document.querySelectorAll("[data-modal-target]").forEach(function (trigger) {
        trigger.addEventListener("click", function (e) {
            e.preventDefault();
            var targetSelector = trigger.getAttribute("data-modal-target");
            var modal = document.querySelector(targetSelector);
            if (modal) openModal(modal);
        });
    });

    // Close buttons via data-modal-close or .modal-close-btn
    document.querySelectorAll("[data-modal-close], .modal-close-btn").forEach(function (closeBtn) {
        closeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            var modal = closeBtn.closest(".modal-overlay");
            if (modal) closeModal(modal);
        });
    });

    // Close on backdrop click (click on overlay itself, not container)
    document.querySelectorAll(".modal-overlay").forEach(function (overlay) {
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });

    // Close on Escape key
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            var activeModal = document.querySelector(".modal-overlay.active");
            if (activeModal) closeModal(activeModal);
        }
    });

    // Expose helpers globally if needed
    window.StudentHub = window.StudentHub || {};
    window.StudentHub.openModal = openModal;
    window.StudentHub.closeModal = closeModal;
}


// --- 6. IMAGE / CONTENT SLIDER (CAROUSEL) ---
function initSlider() {
    var track = document.getElementById("carouselTrack");
    var slides = document.querySelectorAll(".carousel-slide");
    var prevBtn = document.getElementById("carouselPrevBtn");
    var nextBtn = document.getElementById("carouselNextBtn");
    var dotsContainer = document.getElementById("carouselDots");
    var container = document.querySelector(".carousel-container");

    if (!track || slides.length === 0) return;

    var currentIndex = 0;
    var totalSlides = slides.length;
    var autoPlayInterval = null;
    var autoPlayDelay = 4500; // 4.5 seconds

    // Build pagination dots
    if (dotsContainer) {
        dotsContainer.innerHTML = "";
        for (var i = 0; i < totalSlides; i++) {
            var dot = document.createElement("button");
            dot.className = "carousel-dot" + (i === 0 ? " active" : "");
            dot.setAttribute("aria-label", "Go to slide " + (i + 1));
            dot.setAttribute("data-slide-index", i);
            (function (index) {
                dot.addEventListener("click", function () {
                    goToSlide(index);
                    restartAutoPlay();
                });
            })(i);
            dotsContainer.appendChild(dot);
        }
    }

    function updateDots() {
        if (!dotsContainer) return;
        var dots = dotsContainer.querySelectorAll(".carousel-dot");
        dots.forEach(function (dot, idx) {
            if (idx === currentIndex) {
                dot.classList.add("active");
            } else {
                dot.classList.remove("active");
            }
        });
    }

    function goToSlide(index) {
        if (index < 0) {
            currentIndex = totalSlides - 1;
        } else if (index >= totalSlides) {
            currentIndex = 0;
        } else {
            currentIndex = index;
        }

        track.style.transform = "translateX(-" + (currentIndex * 100) + "%)";
        updateDots();
    }

    function nextSlide() {
        goToSlide(currentIndex + 1);
    }

    function prevSlide() {
        goToSlide(currentIndex - 1);
    }

    if (nextBtn) {
        nextBtn.addEventListener("click", function () {
            nextSlide();
            restartAutoPlay();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener("click", function () {
            prevSlide();
            restartAutoPlay();
        });
    }

    // Auto Play
    function startAutoPlay() {
        stopAutoPlay();
        autoPlayInterval = setInterval(nextSlide, autoPlayDelay);
    }

    function stopAutoPlay() {
        if (autoPlayInterval) {
            clearInterval(autoPlayInterval);
            autoPlayInterval = null;
        }
    }

    function restartAutoPlay() {
        stopAutoPlay();
        startAutoPlay();
    }

    if (container) {
        container.addEventListener("mouseenter", stopAutoPlay);
        container.addEventListener("mouseleave", startAutoPlay);
        container.addEventListener("focusin", stopAutoPlay);
        container.addEventListener("focusout", startAutoPlay);
    }

    // Touch Swipe Gesture Support
    var touchStartX = 0;
    var touchEndX = 0;

    track.addEventListener("touchstart", function (e) {
        touchStartX = e.changedTouches[0].screenX;
        stopAutoPlay();
    }, { passive: true });

    track.addEventListener("touchend", function (e) {
        touchEndX = e.changedTouches[0].screenX;
        var diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 40) {
            if (diff > 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
        startAutoPlay();
    }, { passive: true });

    startAutoPlay();
}


// --- 7. COLLAPSIBLE FAQ (ACCORDION) ---
function initAccordion() {
    var faqItems = document.querySelectorAll(".faq-item");
    if (faqItems.length === 0) return;

    faqItems.forEach(function (item) {
        var trigger = item.querySelector(".faq-trigger");
        if (!trigger) return;

        trigger.addEventListener("click", function () {
            var isActive = item.classList.contains("active");

            // Accordion behavior: close other open items for clean presentation
            faqItems.forEach(function (otherItem) {
                if (otherItem !== item && otherItem.classList.contains("active")) {
                    otherItem.classList.remove("active");
                    var otherTrigger = otherItem.querySelector(".faq-trigger");
                    if (otherTrigger) {
                        otherTrigger.setAttribute("aria-expanded", "false");
                    }
                }
            });

            // Toggle current item
            if (isActive) {
                item.classList.remove("active");
                trigger.setAttribute("aria-expanded", "false");
            } else {
                item.classList.add("active");
                trigger.setAttribute("aria-expanded", "true");
            }
        });
    });
}


// --- 8. FORM HANDLING & EXISTING WORKFLOWS ---
// Role dropdown toggle for student course
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
            courseSelect.value = "";
        }
    });
}

// Login form submission
var loginForm = document.getElementById("loginForm");
if (loginForm !== null) {
    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var email = document.getElementById("login-email").value;
        var password = document.getElementById("login-password").value;

        if (email === "" || password === "") {
            alert("Please fill in all fields!");
        } else {
            localStorage.setItem("isLoggedIn", "true");
            localStorage.setItem("email", email);
            window.location.href = "dashboard.html";
        }
    });
}

// Sign up form submission
var registerForm = document.getElementById("registerForm");
if (registerForm !== null) {
    registerForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var name = document.getElementById("student-name").value;
        var email = document.getElementById("student-email").value;
        var password = document.getElementById("student-password").value;

        if (name === "" || email === "" || password === "") {
            alert("Please fill in all fields!");
        } else {
            alert("Account created successfully for " + name + "! Please login.");
            window.location.href = "login.html";
        }
    });
}

// Logout link session cleanup
var logoutLink = document.getElementById("logoutLink");
if (logoutLink !== null) {
    logoutLink.addEventListener("click", function () {
        localStorage.removeItem("isLoggedIn");
        localStorage.removeItem("email");
    });
}

// Random Quotes generator for login/register pages
var quotes = [
    { quote: "Education is the passport to the future.", author: "Malcolm X" },
    { quote: "Success is the sum of small efforts repeated every day.", author: "Robert Collier" },
    { quote: "Learning never exhausts the mind.", author: "Leonardo da Vinci" },
    { quote: "The future belongs to those who learn more skills.", author: "Brian Tracy" },
    { quote: "Dream big. Work hard. Stay focused.", author: "Anonymous" },
    { quote: "Knowledge is power.", author: "Francis Bacon" },
    { quote: "Your education is your strongest investment.", author: "Anonymous" },
    { quote: "Every expert was once a beginner.", author: "Helen Hayes" }
];

var quoteEl = document.getElementById("quote");
var authorEl = document.getElementById("author");

if (quoteEl !== null && authorEl !== null) {
    var randomIdx = Math.floor(Math.random() * quotes.length);
    quoteEl.textContent = '"' + quotes[randomIdx].quote + '"';
    authorEl.textContent = "— " + quotes[randomIdx].author;

    function changeQuote() {
        var nextIdx = Math.floor(Math.random() * quotes.length);
        quoteEl.textContent = '"' + quotes[nextIdx].quote + '"';
        authorEl.textContent = "— " + quotes[nextIdx].author;
    }

    setInterval(changeQuote, 3000);
}


// --- 9. DOM INITIALIZATION ---
document.addEventListener("DOMContentLoaded", function () {
    initThemeSwitcher();
    initHamburgerMenu();
    initNotificationBanner();
    initNotificationDropdown();
    initModalSystem();
    initSlider();
    initAccordion();
});

// Run theme immediately to prevent flash of light theme
initThemeSwitcher();
