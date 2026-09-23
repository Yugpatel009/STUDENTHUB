/**
 * ==========================================================================
 * STUDENT HUB - CAMPUS HUB & DYNAMIC DATA EXPLORER
 * Powered by Fetch API, Real-time Search, Multi-Filter, Sorting & Pagination
 * ==========================================================================
 */

(function () {
    "use strict";

    // --- CONFIGURATION & STATE ---
    var state = {
        activeTab: "events",       // 'events' | 'students' | 'notices' | 'faqs'
        searchQuery: "",
        categoryFilter: "all",
        secondaryFilter: "all",
        sortBy: "default",
        currentPage: 1,
        itemsPerPage: 6,
        datasets: {
            events: null,
            students: null,
            notices: null,
            faqs: null
        },
        filteredItems: []
    };

    // Determine correct relative path to /data/ directory
    var isPagesFolder = window.location.pathname.indexOf("/Pages/") !== -1 || window.location.pathname.indexOf("\\Pages\\") !== -1;
    var DATA_BASE_PATH = isPagesFolder ? "../data/" : "data/";

    // --- FALLBACK IN-MEMORY DATA (Ensures zero breakage under file:/// CORS restriction) ---
    var FALLBACK_DATA = {
        events: [
            {
                id: "EVT-101",
                title: "CodeStorm 2026: 48-Hour National Hackathon",
                category: "Technical",
                date: "2026-10-15",
                time: "09:00 AM - 05:00 PM (Next Day)",
                venue: "Campus Innovation Lab & Main Auditorium",
                department: "Computer Science & Engineering",
                organizer: "Student Tech Committee & ACM Chapter",
                seats: 150,
                registered: 138,
                status: "Upcoming",
                description: "Collaborate in teams of 2 to 4 to build innovative Web, Cloud, or AI solutions addressing real-world university challenges.",
                tags: ["Coding", "Hackathon", "Web", "AI"],
                image: isPagesFolder ? "../img/web-development.jpg" : "img/web-development.jpg"
            },
            {
                id: "EVT-102",
                title: "Hands-on Workshop: Relational Query Optimization & Indexing",
                category: "Workshop",
                date: "2026-10-08",
                time: "02:00 PM - 05:00 PM",
                venue: "Computer Lab 3, Third Floor",
                department: "Information Technology",
                organizer: "Prof. Amit Patel (Database Faculty)",
                seats: 60,
                registered: 58,
                status: "Upcoming",
                description: "Deep-dive into SQL execution plans, B-Tree index structures, subquery tuning, and multi-table join performance.",
                tags: ["Database", "SQL", "Optimization", "Lab"],
                image: isPagesFolder ? "../img/database-management.jpg" : "img/database-management.jpg"
            },
            {
                id: "EVT-103",
                title: "Seminar: Future of Edge Computing and Network Protocols",
                category: "Seminar",
                date: "2026-09-28",
                time: "11:00 AM - 01:00 PM",
                venue: "Seminar Hall A",
                department: "Computer Science & Engineering",
                organizer: "Department of Computer Networks",
                seats: 120,
                registered: 95,
                status: "Ongoing",
                description: "Keynote address examining 5G/6G transport protocols, IoT edge infrastructure, and decentralized routing topologies.",
                tags: ["Networks", "Edge Computing", "IoT"],
                image: isPagesFolder ? "../img/computer-networks.jpg" : "img/computer-networks.jpg"
            },
            {
                id: "EVT-104",
                title: "Campus Cultural Fest: Spectrum 2026",
                category: "Cultural",
                date: "2026-11-12",
                time: "10:00 AM - 09:00 PM",
                venue: "University Open Air Amphitheatre",
                department: "Campus Life & Cultural Council",
                organizer: "Student Council",
                seats: 500,
                registered: 340,
                status: "Upcoming",
                description: "Celebrate campus talent with music performances, drama, dance battles, art exhibits, and food stalls.",
                tags: ["Cultural", "Music", "Dance", "Festival"],
                image: isPagesFolder ? "../img/dashboard.png" : "img/dashboard.png"
            },
            {
                id: "EVT-105",
                title: "Inter-Department Cricket & Badminton Tournament",
                category: "Sports",
                date: "2026-10-22",
                time: "08:00 AM - 06:00 PM",
                venue: "University Sports Complex",
                department: "Physical Education Department",
                organizer: "Campus Sports Board",
                seats: 200,
                registered: 180,
                status: "Upcoming",
                description: "Annual sports championship featuring knock-out matches between Engineering, IT, and Management departments.",
                tags: ["Sports", "Cricket", "Badminton"],
                image: isPagesFolder ? "../img/dashboard.png" : "img/dashboard.png"
            },
            {
                id: "EVT-106",
                title: "Full-Stack Web Dev Bootcamp: REST APIs & Asynchronous JS",
                category: "Workshop",
                date: "2026-10-02",
                time: "10:00 AM - 04:00 PM",
                venue: "Advanced Software Studio",
                department: "Computer Science & Engineering",
                organizer: "Web Development Faculty Lead",
                seats: 80,
                registered: 80,
                status: "Upcoming",
                description: "Intensive hands-on lab on modern Fetch API, Promises, async/await, DOM updates, and reactive components.",
                tags: ["Web", "JavaScript", "Fetch API", "Frontend"],
                image: isPagesFolder ? "../img/web-development.jpg" : "img/web-development.jpg"
            },
            {
                id: "EVT-107",
                title: "Cybersecurity & Ethical Hacking Symposium",
                category: "Technical",
                date: "2026-11-04",
                time: "10:30 AM - 03:30 PM",
                venue: "Auditorium Hall C",
                department: "Information Security Cell",
                organizer: "CyberSec Student Group",
                seats: 130,
                registered: 98,
                status: "Upcoming",
                description: "Learn network vulnerability analysis, web application penetration testing, and security best practices.",
                tags: ["Security", "Ethical Hacking", "OWASP"],
                image: isPagesFolder ? "../img/computer-networks.jpg" : "img/computer-networks.jpg"
            },
            {
                id: "EVT-108",
                title: "Resume Building & Technical Interview Prep Session",
                category: "Seminar",
                date: "2026-09-18",
                time: "03:00 PM - 05:30 PM",
                venue: "Central Placement Hall",
                department: "Training & Placement Cell",
                organizer: "Placement Directorate",
                seats: 250,
                registered: 250,
                status: "Completed",
                description: "Career guidance on crafting industry-ready resumes and tackling technical coding interviews.",
                tags: ["Placement", "Career", "Interview"],
                image: isPagesFolder ? "../img/dashboard.png" : "img/dashboard.png"
            }
        ],
        students: [
            {
                id: "STU-001",
                name: "Yug Kansagara",
                enrollment: "SH2026CE031",
                department: "Computer Science & Engineering",
                semester: 3,
                email: "yug.kansagara@gmail.com",
                phone: "+91 98765 43210",
                cgpa: 8.7,
                attendance: 86,
                status: "Active",
                skills: ["HTML5/CSS3", "JavaScript", "SQL", "Git"],
                avatar: "YK",
                interests: "Full-Stack Development, Cloud Computing, Database Optimization"
            },
            {
                id: "STU-002",
                name: "Aarav Sharma",
                enrollment: "SH2026CE012",
                department: "Computer Science & Engineering",
                semester: 3,
                email: "aarav.sharma@studenthub.com",
                phone: "+91 98111 22334",
                cgpa: 9.2,
                attendance: 94,
                status: "Active",
                skills: ["Python", "Machine Learning", "C++", "Algorithms"],
                avatar: "AS",
                interests: "Deep Learning, Algorithms, Competitive Programming"
            },
            {
                id: "STU-003",
                name: "Priya Patel",
                enrollment: "SH2026IT045",
                department: "Information Technology",
                semester: 3,
                email: "priya.patel@studenthub.com",
                phone: "+91 98222 33445",
                cgpa: 8.9,
                attendance: 89,
                status: "Active",
                skills: ["UI/UX Design", "Figma", "CSS Grid", "JavaScript"],
                avatar: "PP",
                interests: "Human-Computer Interaction, Frontend Frameworks"
            },
            {
                id: "STU-004",
                name: "Rohan Verma",
                enrollment: "SH2026EC028",
                department: "Electronics & Communication",
                semester: 5,
                email: "rohan.verma@studenthub.com",
                phone: "+91 98333 44556",
                cgpa: 7.8,
                attendance: 82,
                status: "Active",
                skills: ["Embedded C", "Arduino", "IoT", "MATLAB"],
                avatar: "RV",
                interests: "Sensor Networks, Robotics, Hardware Design"
            },
            {
                id: "STU-005",
                name: "Ananya Joshi",
                enrollment: "SH2026CE008",
                department: "Computer Science & Engineering",
                semester: 5,
                email: "ananya.joshi@studenthub.com",
                phone: "+91 98444 55667",
                cgpa: 9.5,
                attendance: 96,
                status: "Active",
                skills: ["Node.js", "MongoDB", "Express", "Docker"],
                avatar: "AJ",
                interests: "Microservices, Distributed Systems, Cloud"
            },
            {
                id: "STU-006",
                name: "Dev Mehta",
                enrollment: "SH2026ME052",
                department: "Mechanical Engineering",
                semester: 3,
                email: "dev.mehta@studenthub.com",
                phone: "+91 98555 66778",
                cgpa: 8.1,
                attendance: 79,
                status: "Active",
                skills: ["AutoCAD", "SolidWorks", "Python Scripting"],
                avatar: "DM",
                interests: "Thermal Dynamics, 3D Prototyping"
            },
            {
                id: "STU-007",
                name: "Sneha Roy",
                enrollment: "SH2026IT019",
                department: "Information Technology",
                semester: 5,
                email: "sneha.roy@studenthub.com",
                phone: "+91 98666 77889",
                cgpa: 8.4,
                attendance: 88,
                status: "Active",
                skills: ["Cybersecurity", "Network Forensics", "Linux"],
                avatar: "SR",
                interests: "Ethical Hacking, Threat Hunting"
            },
            {
                id: "STU-008",
                name: "Kavya Desai",
                enrollment: "SH2026CE042",
                department: "Computer Science & Engineering",
                semester: 1,
                email: "kavya.desai@studenthub.com",
                phone: "+91 98777 88990",
                cgpa: 8.6,
                attendance: 92,
                status: "Active",
                skills: ["C Programming", "HTML", "Logic Design"],
                avatar: "KD",
                interests: "Software Engineering, Web Fundamentals"
            }
        ],
        notices: [
            {
                id: "NOT-201",
                title: "Mid-Semester Examination Schedules & Room Allotment Published",
                category: "Examinations",
                date: "2026-09-21",
                priority: "High",
                author: "Controller of Examinations",
                department: "All Engineering Departments",
                description: "Mid-Semester assessments for Semester 3, 5, and 7 commence from October 14, 2026. Hall tickets are accessible on portal.",
                attachment: "Mid_Semester_Schedule_Fall2026.pdf"
            },
            {
                id: "NOT-202",
                title: "Elective Course Add/Drop Window Final Deadline Notice",
                category: "Academic",
                date: "2026-09-20",
                priority: "High",
                author: "Academic Registrar",
                department: "Dean of Academics",
                description: "The online course change and elective finalization portal will close strictly on September 28, 2026 at 11:59 PM.",
                attachment: "Course_Change_Circular.pdf"
            },
            {
                id: "NOT-203",
                title: "Merit Scholarship Applications Open for Academic Year 2026-27",
                category: "Scholarship",
                date: "2026-09-18",
                priority: "Medium",
                author: "Student Welfare Board",
                department: "Dean of Student Affairs",
                description: "Applications are invited from eligible students with CGPA >= 8.5 for University Merit tuition fee waiver scholarships.",
                attachment: "Scholarship_Criteria_Form.pdf"
            },
            {
                id: "NOT-204",
                title: "Campus Library Extended Hours During Mid-Term Preparation",
                category: "Facility",
                date: "2026-09-17",
                priority: "Normal",
                author: "Chief Librarian",
                department: "Central Library",
                description: "The Central Reference Library and digital learning wing will remain open 24/7 beginning October 1 through October 25.",
                attachment: "Library_Rules_Extended.pdf"
            },
            {
                id: "NOT-205",
                title: "National Technical Symposium Call for Papers & Project Posters",
                category: "Technical",
                date: "2026-09-15",
                priority: "Medium",
                author: "R&D Research Committee",
                department: "Computer Science & Engineering",
                description: "Undergraduate researchers are encouraged to submit 4-page IEEE formatted manuscripts or project posters.",
                attachment: "Call_For_Papers_Template.docx"
            },
            {
                id: "NOT-206",
                title: "Holiday Announcement: Gandhi Jayanti & University Recess",
                category: "Holiday",
                date: "2026-09-14",
                priority: "Normal",
                author: "Registrar General",
                department: "General Administration",
                description: "University academic blocks, administrative desks, and regular classes shall remain suspended on October 2, 2026.",
                attachment: null
            },
            {
                id: "NOT-207",
                title: "Compulsory 75% Attendance Compliance Rule Reminder",
                category: "Academic",
                date: "2026-09-12",
                priority: "High",
                author: "Dean Academic Affairs",
                department: "Academic Audit Cell",
                description: "Students with aggregate attendance below 75% will be debarred from sitting for the end-term evaluations.",
                attachment: "Attendance_Policy_Handbook.pdf"
            }
        ],
        faqs: [
            {
                id: "FAQ-301",
                question: "How do I view my enrolled courses and study materials?",
                answer: "Once logged in to your account, click on Courses in the top navigation or your dashboard overview. You will find syllabus outlines, lecture notes, professor contact details, and course progress indicators.",
                category: "Academics",
                helpfulCount: 84
            },
            {
                id: "FAQ-302",
                question: "How does attendance calculation and threshold warning work?",
                answer: "Attendance is updated daily by your professors. The portal calculates your overall percentage as well as individual subject breakdowns. If attendance dips below 75%, an automated warning alerts you.",
                category: "Attendance",
                helpfulCount: 92
            },
            {
                id: "FAQ-303",
                question: "What should I do if I forget my login password?",
                answer: "Navigate to the Support & Contact page to submit a password reset request to the department admin, or verify your university student email credentials with the registrar office.",
                category: "Account",
                helpfulCount: 65
            },
            {
                id: "FAQ-304",
                question: "When are semester grades and final marks published?",
                answer: "Final exam grades are evaluated and published within 7-10 working days following final paper submissions. You can view your GPA and letter grade breakdown under Results.",
                category: "Examinations",
                helpfulCount: 78
            },
            {
                id: "FAQ-305",
                question: "How can I submit pending assignments and verify submission status?",
                answer: "Go to the Assignments module. Each listed task displays the due date, title, and current badge (Pending or Submitted). Click Action on any pending row to complete your submission.",
                category: "Academics",
                helpfulCount: 59
            },
            {
                id: "FAQ-306",
                question: "How do I apply for official merit or fee waiver scholarships?",
                answer: "Merit scholarships are announced every semester on the Campus Notice Board. Eligible students with CGPA 8.5+ can download the application form and submit to Student Welfare.",
                category: "Portal",
                helpfulCount: 51
            }
        ]
    };

    // --- DOM ELEMENT REFERENCES ---
    var elements = {
        tabs: document.querySelectorAll(".hub-tab-btn"),
        searchInput: document.getElementById("hubSearchInput"),
        clearSearchBtn: document.getElementById("clearSearchBtn"),
        categoryFilter: document.getElementById("hubCategoryFilter"),
        secondaryFilter: document.getElementById("hubSecondaryFilter"),
        secondaryFilterWrap: document.getElementById("secondaryFilterWrap"),
        secondaryFilterLabel: document.getElementById("secondaryFilterLabel"),
        sortBy: document.getElementById("hubSortBy"),
        resetBtn: document.getElementById("hubResetBtn"),
        resultsCount: document.getElementById("hubResultsCount"),
        pageSizeSelect: document.getElementById("hubPageSize"),
        dataContainer: document.getElementById("hubDataContainer"),
        paginationNav: document.getElementById("hubPaginationNav"),
        sourceBadgeText: document.getElementById("sourceBadgeText"),
        toast: document.getElementById("hubToast"),
        modal: document.getElementById("hubDetailModal"),
        modalTitle: document.getElementById("hubModalTitle"),
        modalBody: document.getElementById("hubModalBody"),
        modalFooter: document.getElementById("hubModalFooter")
    };

    // --- TOAST NOTIFICATION HELPER ---
    function showToast(message) {
        if (!elements.toast) return;
        elements.toast.textContent = message;
        elements.toast.classList.add("show");
        setTimeout(function () {
            elements.toast.classList.remove("show");
        }, 3200);
    }

    // --- FETCH API DATA LOADER ---
    async function fetchDataset(type) {
        // If already cached in memory, return cached
        if (state.datasets[type] && state.datasets[type].length > 0) {
            return state.datasets[type];
        }

        renderLoading();

        var jsonUrl = DATA_BASE_PATH + type + ".json";
        try {
            var response = await fetch(jsonUrl, { cache: "no-cache" });
            if (!response.ok) {
                throw new Error("HTTP error " + response.status + ": " + response.statusText);
            }
            var data = await response.json();
            state.datasets[type] = data;
            updateDataSourceIndicator(true, "Fetch API: " + type + ".json");
            return data;
        } catch (error) {
            console.warn("Fetch API fell back to in-memory data (" + error.message + ")");
            state.datasets[type] = FALLBACK_DATA[type] || [];
            updateDataSourceIndicator(false, "Local Store (" + type + ")");
            return state.datasets[type];
        }
    }

    function updateDataSourceIndicator(isLive, label) {
        if (!elements.sourceBadgeText) return;
        elements.sourceBadgeText.textContent = isLive ? "Live JSON via Fetch API" : label;
    }

    // --- DYNAMIC FILTER DROPDOWNS POPULATION ---
    function updateFilterOptions() {
        var currentData = state.datasets[state.activeTab] || [];

        // 1. Populate Category dropdown
        if (elements.categoryFilter) {
            var categories = new Set();
            currentData.forEach(function (item) {
                if (item.category) categories.add(item.category);
            });

            var catHtml = '<option value="all">All Categories</option>';
            Array.from(categories).sort().forEach(function (cat) {
                var selected = state.categoryFilter === cat ? " selected" : "";
                catHtml += '<option value="' + cat + '"' + selected + '>' + cat + '</option>';
            });
            elements.categoryFilter.innerHTML = catHtml;
        }

        // 2. Populate Secondary dropdown (Status for Events, Priority for Notices, Semester for Students)
        if (elements.secondaryFilter && elements.secondaryFilterWrap) {
            if (state.activeTab === "events") {
                elements.secondaryFilterWrap.style.display = "flex";
                if (elements.secondaryFilterLabel) elements.secondaryFilterLabel.textContent = "Status:";
                var statuses = ["Upcoming", "Ongoing", "Completed"];
                var statHtml = '<option value="all">All Statuses</option>';
                statuses.forEach(function (st) {
                    var selected = state.secondaryFilter === st ? " selected" : "";
                    statHtml += '<option value="' + st + '"' + selected + '>' + st + '</option>';
                });
                elements.secondaryFilter.innerHTML = statHtml;
            } else if (state.activeTab === "notices") {
                elements.secondaryFilterWrap.style.display = "flex";
                if (elements.secondaryFilterLabel) elements.secondaryFilterLabel.textContent = "Priority:";
                var priorities = ["High", "Medium", "Normal"];
                var priHtml = '<option value="all">All Priorities</option>';
                priorities.forEach(function (pr) {
                    var selected = state.secondaryFilter === pr ? " selected" : "";
                    priHtml += '<option value="' + pr + '"' + selected + '>' + pr + '</option>';
                });
                elements.secondaryFilter.innerHTML = priHtml;
            } else if (state.activeTab === "students") {
                elements.secondaryFilterWrap.style.display = "flex";
                if (elements.secondaryFilterLabel) elements.secondaryFilterLabel.textContent = "Semester:";
                var semHtml = '<option value="all">All Semesters</option>';
                [1, 3, 5, 7].forEach(function (sem) {
                    var selected = state.secondaryFilter === String(sem) ? " selected" : "";
                    semHtml += '<option value="' + sem + '"' + selected + '>Semester ' + sem + '</option>';
                });
                elements.secondaryFilter.innerHTML = semHtml;
            } else {
                elements.secondaryFilterWrap.style.display = "none";
            }
        }

        // 3. Update Sort options based on active tab
        if (elements.sortBy) {
            var sortHtml = '<option value="default">Default Order</option>';
            if (state.activeTab === "events") {
                sortHtml += '<option value="date-asc">Date: Soonest First</option>';
                sortHtml += '<option value="date-desc">Date: Latest First</option>';
                sortHtml += '<option value="title-asc">Title: A to Z</option>';
                sortHtml += '<option value="seats-desc">Most Available Seats</option>';
            } else if (state.activeTab === "students") {
                sortHtml += '<option value="name-asc">Name: A to Z</option>';
                sortHtml += '<option value="cgpa-desc">CGPA: Highest First</option>';
                sortHtml += '<option value="cgpa-asc">CGPA: Lowest First</option>';
                sortHtml += '<option value="attendance-desc">Attendance: Highest</option>';
                sortHtml += '<option value="sem-asc">Semester: Low to High</option>';
            } else if (state.activeTab === "notices") {
                sortHtml += '<option value="date-desc">Date: Newest First</option>';
                sortHtml += '<option value="priority-high">Priority: High First</option>';
                sortHtml += '<option value="title-asc">Title: A to Z</option>';
            } else if (state.activeTab === "faqs") {
                sortHtml += '<option value="helpful-desc">Most Helpful First</option>';
                sortHtml += '<option value="question-asc">Question: A to Z</option>';
            }
            elements.sortBy.innerHTML = sortHtml;
            elements.sortBy.value = state.sortBy;
        }
    }

    // --- SEARCH, FILTER & SORTING ENGINE ---
    function processData() {
        var rawData = state.datasets[state.activeTab] || [];
        var query = state.searchQuery.trim().toLowerCase();

        // 1. Filter by Search Query
        var filtered = rawData.filter(function (item) {
            if (!query) return true;

            if (state.activeTab === "events") {
                var title = (item.title || "").toLowerCase();
                var desc = (item.description || "").toLowerCase();
                var venue = (item.venue || "").toLowerCase();
                var dept = (item.department || "").toLowerCase();
                var cat = (item.category || "").toLowerCase();
                var tags = Array.isArray(item.tags) ? item.tags.join(" ").toLowerCase() : "";
                return title.includes(query) || desc.includes(query) || venue.includes(query) || dept.includes(query) || cat.includes(query) || tags.includes(query);
            }

            if (state.activeTab === "students") {
                var name = (item.name || "").toLowerCase();
                var enroll = (item.enrollment || "").toLowerCase();
                var dept = (item.department || "").toLowerCase();
                var email = (item.email || "").toLowerCase();
                var skills = Array.isArray(item.skills) ? item.skills.join(" ").toLowerCase() : "";
                return name.includes(query) || enroll.includes(query) || dept.includes(query) || email.includes(query) || skills.includes(query);
            }

            if (state.activeTab === "notices") {
                var title = (item.title || "").toLowerCase();
                var desc = (item.description || "").toLowerCase();
                var author = (item.author || "").toLowerCase();
                var dept = (item.department || "").toLowerCase();
                var cat = (item.category || "").toLowerCase();
                return title.includes(query) || desc.includes(query) || author.includes(query) || dept.includes(query) || cat.includes(query);
            }

            if (state.activeTab === "faqs") {
                var question = (item.question || "").toLowerCase();
                var answer = (item.answer || "").toLowerCase();
                var cat = (item.category || "").toLowerCase();
                return question.includes(query) || answer.includes(query) || cat.includes(query);
            }

            return true;
        });

        // 2. Filter by Category
        if (state.categoryFilter !== "all") {
            filtered = filtered.filter(function (item) {
                return item.category === state.categoryFilter;
            });
        }

        // 3. Filter by Secondary Attribute (Status / Priority / Semester)
        if (state.secondaryFilter !== "all") {
            filtered = filtered.filter(function (item) {
                if (state.activeTab === "events") {
                    return item.status === state.secondaryFilter;
                }
                if (state.activeTab === "notices") {
                    return item.priority === state.secondaryFilter;
                }
                if (state.activeTab === "students") {
                    return String(item.semester) === String(state.secondaryFilter);
                }
                return true;
            });
        }

        // 4. Sorting
        filtered.sort(function (a, b) {
            switch (state.sortBy) {
                // Events Sorting
                case "date-asc":
                    return new Date(a.date) - new Date(b.date);
                case "date-desc":
                    return new Date(b.date) - new Date(a.date);
                case "title-asc":
                    return (a.title || "").localeCompare(b.title || "");
                case "seats-desc":
                    return (b.seats - (b.registered || 0)) - (a.seats - (a.registered || 0));

                // Students Sorting
                case "name-asc":
                    return (a.name || "").localeCompare(b.name || "");
                case "cgpa-desc":
                    return (b.cgpa || 0) - (a.cgpa || 0);
                case "cgpa-asc":
                    return (a.cgpa || 0) - (b.cgpa || 0);
                case "attendance-desc":
                    return (b.attendance || 0) - (a.attendance || 0);
                case "sem-asc":
                    return (a.semester || 0) - (b.semester || 0);

                // Notices Sorting
                case "priority-high":
                    var rank = { "High": 3, "Medium": 2, "Normal": 1 };
                    return (rank[b.priority] || 0) - (rank[a.priority] || 0);

                // FAQs Sorting
                case "helpful-desc":
                    return (b.helpfulCount || 0) - (a.helpfulCount || 0);
                case "question-asc":
                    return (a.question || "").localeCompare(b.question || "");

                default:
                    return 0;
            }
        });

        state.filteredItems = filtered;
    }

    // --- PAGINATION & SLICE RENDERING ---
    function renderPagination() {
        var total = state.filteredItems.length;
        var totalPages = Math.ceil(total / state.itemsPerPage) || 1;

        if (state.currentPage > totalPages) state.currentPage = totalPages;
        if (state.currentPage < 1) state.currentPage = 1;

        // Update Summary Text
        if (elements.resultsCount) {
            var startIdx = total === 0 ? 0 : (state.currentPage - 1) * state.itemsPerPage + 1;
            var endIdx = Math.min(state.currentPage * state.itemsPerPage, total);
            elements.resultsCount.innerHTML = "Showing <strong>" + startIdx + "–" + endIdx + "</strong> of <strong>" + total + "</strong> items";
        }

        // Render Pagination Buttons
        if (elements.paginationNav) {
            if (totalPages <= 1) {
                elements.paginationNav.innerHTML = "";
                return;
            }

            var html = "";
            // Prev Button
            var prevDisabled = state.currentPage === 1 ? " disabled" : "";
            html += '<button class="page-btn" data-page="' + (state.currentPage - 1) + '"' + prevDisabled + ' aria-label="Previous Page">&lsaquo; Prev</button>';

            // Numbered Buttons
            for (var p = 1; p <= totalPages; p++) {
                var activeClass = p === state.currentPage ? " active" : "";
                html += '<button class="page-btn' + activeClass + '" data-page="' + p + '">' + p + '</button>';
            }

            // Next Button
            var nextDisabled = state.currentPage === totalPages ? " disabled" : "";
            html += '<button class="page-btn" data-page="' + (state.currentPage + 1) + '"' + nextDisabled + ' aria-label="Next Page">Next &rsaquo;</button>';

            elements.paginationNav.innerHTML = html;

            // Bind click events on page buttons
            elements.paginationNav.querySelectorAll(".page-btn").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var targetPage = parseInt(this.getAttribute("data-page"), 10);
                    if (!isNaN(targetPage) && targetPage >= 1 && targetPage <= totalPages && targetPage !== state.currentPage) {
                        state.currentPage = targetPage;
                        renderCurrentView();
                        // Smooth scroll back to top of container
                        elements.dataContainer.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                });
            });
        }
    }

    // --- DYNAMIC RENDERING OF CARDS ---
    function renderCurrentView() {
        if (!elements.dataContainer) return;

        processData();
        renderPagination();

        var total = state.filteredItems.length;

        // Empty state check
        if (total === 0) {
            elements.dataContainer.innerHTML =
                '<div class="hub-state-box">' +
                '  <div class="hub-state-icon">🔍</div>' +
                '  <h3>No Matching Results Found</h3>' +
                '  <p>No records matched your search keyword or selected filter criteria. Try resetting filters or searching with different terms.</p>' +
                '  <button class="btn btn-primary" id="hubEmptyResetBtn">Reset All Filters</button>' +
                '</div>';

            var emptyReset = document.getElementById("hubEmptyResetBtn");
            if (emptyReset) {
                emptyReset.addEventListener("click", resetFilters);
            }
            return;
        }

        var start = (state.currentPage - 1) * state.itemsPerPage;
        var end = start + state.itemsPerPage;
        var pageItems = state.filteredItems.slice(start, end);

        if (state.activeTab === "events") {
            renderEvents(pageItems);
        } else if (state.activeTab === "students") {
            renderStudents(pageItems);
        } else if (state.activeTab === "notices") {
            renderNotices(pageItems);
        } else if (state.activeTab === "faqs") {
            renderFaqs(pageItems);
        }
    }

    // 1. Render Campus Events
    function renderEvents(items) {
        var html = '<div class="hub-grid">';
        items.forEach(function (evt) {
            var registered = evt.registered || 0;
            var seats = evt.seats || 100;
            var pct = Math.min(100, Math.round((registered / seats) * 100));
            var statusClass = (evt.status || "upcoming").toLowerCase();
            var fillClass = pct > 85 ? " high" : "";

            var formattedDate = "";
            try {
                var d = new Date(evt.date);
                formattedDate = d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
            } catch (e) {
                formattedDate = evt.date;
            }

            var imageHtml = evt.image ?
                '<div class="event-card-header" style="background-image: url(\'' + evt.image + '\');">' :
                '<div class="event-card-header-fallback">🎯';

            html +=
                '<article class="event-card">' +
                imageHtml +
                '  <div class="event-card-overlay"></div>' +
                '  <div class="event-card-badges">' +
                '    <span class="event-cat-badge">' + (evt.category || "General") + '</span>' +
                '    <span class="event-status-badge ' + statusClass + '">' + (evt.status || "Upcoming") + '</span>' +
                '  </div>' +
                '</div>' +
                '<div class="event-card-body">' +
                '  <h3 class="event-card-title">' + evt.title + '</h3>' +
                '  <div class="event-meta-list">' +
                '    <div class="event-meta-item"><span class="event-meta-icon">📅</span> ' + formattedDate + ' &bull; ' + (evt.time || "TBA") + '</div>' +
                '    <div class="event-meta-item"><span class="event-meta-icon">📍</span> ' + (evt.venue || "Campus Hall") + '</div>' +
                '    <div class="event-meta-item"><span class="event-meta-icon">🏛️</span> ' + (evt.department || "Academic Council") + '</div>' +
                '  </div>' +
                '  <p class="event-card-desc">' + evt.description + '</p>' +
                '  <div class="event-capacity-bar-wrap">' +
                '    <div class="event-capacity-info">' +
                '      <span>Seats Reserved: ' + registered + '/' + seats + '</span>' +
                '      <span>' + pct + '%</span>' +
                '    </div>' +
                '    <div class="event-capacity-track">' +
                '      <div class="event-capacity-fill' + fillClass + '" style="width: ' + pct + '%;"></div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="event-card-footer">' +
                '    <button class="btn-card-action btn-primary-action" data-action="view-event" data-id="' + evt.id + '">View Details & Register</button>' +
                '  </div>' +
                '</div>' +
                '</article>';
        });
        html += '</div>';
        elements.dataContainer.innerHTML = html;

        // Bind Detail Buttons
        elements.dataContainer.querySelectorAll('[data-action="view-event"]').forEach(function (btn) {
            btn.addEventListener("click", function () {
                var evtId = this.getAttribute("data-id");
                openEventModal(evtId);
            });
        });
    }

    // 2. Render Student Profiles
    function renderStudents(items) {
        var html = '<div class="hub-grid">';
        items.forEach(function (stu) {
            var skillsHtml = "";
            if (Array.isArray(stu.skills)) {
                skillsHtml = stu.skills.map(function (s) {
                    return '<span class="skill-tag">' + s + '</span>';
                }).join(" ");
            }

            html +=
                '<article class="student-card">' +
                '  <div class="student-card-header">' +
                '    <div class="student-avatar">' + (stu.avatar || "ST") + '</div>' +
                '    <div class="student-header-info">' +
                '      <h3>' + stu.name + '</h3>' +
                '      <div class="student-enrollment">' + stu.enrollment + '</div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="student-stats-row">' +
                '    <div class="student-stat-item">' +
                '      <span>CGPA</span>' +
                '      <strong class="highlight">' + (stu.cgpa ? stu.cgpa.toFixed(1) : "—") + '</strong>' +
                '    </div>' +
                '    <div class="student-stat-item">' +
                '      <span>Attendance</span>' +
                '      <strong>' + (stu.attendance || 0) + '%</strong>' +
                '    </div>' +
                '    <div class="student-stat-item">' +
                '      <span>Semester</span>' +
                '      <strong>Sem ' + stu.semester + '</strong>' +
                '    </div>' +
                '  </div>' +
                '  <div class="student-details-list">' +
                '    <div><strong>Dept:</strong> ' + stu.department + '</div>' +
                '    <div><strong>Email:</strong> ' + stu.email + '</div>' +
                '  </div>' +
                '  <div class="student-skills-wrap">' +
                '    <div class="student-skills-title">Core Skills</div>' +
                '    <div class="skills-tags">' + skillsHtml + '</div>' +
                '  </div>' +
                '  <div class="event-card-footer">' +
                '    <button class="btn-card-action btn-primary-action" data-action="view-student" data-id="' + stu.id + '">View Profile Card</button>' +
                '  </div>' +
                '</article>';
        });
        html += '</div>';
        elements.dataContainer.innerHTML = html;

        // Bind Detail Buttons
        elements.dataContainer.querySelectorAll('[data-action="view-student"]').forEach(function (btn) {
            btn.addEventListener("click", function () {
                var stuId = this.getAttribute("data-id");
                openStudentModal(stuId);
            });
        });
    }

    // 3. Render Campus Notices
    function renderNotices(items) {
        var html = '<div class="notices-list">';
        items.forEach(function (not) {
            var priClass = (not.priority || "normal").toLowerCase();
            var d = new Date(not.date);
            var month = d.toLocaleDateString("en-US", { month: "short" });
            var day = d.getDate();

            var attachmentHtml = not.attachment ?
                '<button class="notice-attachment-btn" data-action="download-attachment" data-file="' + not.attachment + '">' +
                '  <span>📄 Download ' + not.attachment + '</span>' +
                '</button>' : '';

            html +=
                '<article class="notice-item-card">' +
                '  <div class="notice-date-box">' +
                '    <span class="notice-month">' + month + '</span>' +
                '    <span class="notice-day">' + (day < 10 ? "0" + day : day) + '</span>' +
                '  </div>' +
                '  <div class="notice-body">' +
                '    <div class="notice-top-row">' +
                '      <span class="notice-priority-badge ' + priClass + '">' + (not.priority || "Normal") + ' Priority</span>' +
                '      <span class="event-cat-badge" style="background-color: var(--bg-subtle); color: var(--text-secondary);">' + not.category + '</span>' +
                '      <span class="notice-author-tag">By ' + (not.author || "Administration") + '</span>' +
                '    </div>' +
                '    <h3 class="notice-title">' + not.title + '</h3>' +
                '    <p class="notice-desc">' + not.description + '</p>' +
                attachmentHtml +
                '  </div>' +
                '</article>';
        });
        html += '</div>';
        elements.dataContainer.innerHTML = html;

        // Bind attachment click
        elements.dataContainer.querySelectorAll('[data-action="download-attachment"]').forEach(function (btn) {
            btn.addEventListener("click", function () {
                var filename = this.getAttribute("data-file");
                showToast("Simulated Download: " + filename);
            });
        });
    }

    // 4. Render Dynamic FAQs (Accordion)
    function renderFaqs(items) {
        var html = '<div class="faq-dynamic-list">';
        items.forEach(function (faq, idx) {
            var activeClass = idx === 0 ? " active" : "";
            var expanded = idx === 0 ? "true" : "false";

            html +=
                '<div class="faq-item' + activeClass + '">' +
                '  <button class="faq-trigger" aria-expanded="' + expanded + '" type="button">' +
                '    <span class="faq-question-text">' +
                '      <span class="faq-badge">' + faq.category + '</span>' +
                '      ' + faq.question +
                '    </span>' +
                '    <span class="faq-icon" aria-hidden="true">' +
                '      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
                '    </span>' +
                '  </button>' +
                '  <div class="faq-panel">' +
                '    <div class="faq-content">' +
                '      <div class="faq-content-inner">' +
                '        <p>' + faq.answer + '</p>' +
                '        <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted);">' +
                '          <span>' + (faq.helpfulCount ? "👍 " + faq.helpfulCount + " students found this helpful" : "") + '</span>' +
                '          <button class="btn btn-light" style="padding: 4px 10px; font-size: 12px;" data-action="vote-helpful" data-id="' + faq.id + '">Helpful</button>' +
                '        </div>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>';
        });
        html += '</div>';
        elements.dataContainer.innerHTML = html;

        // Bind Accordion Trigger Events
        elements.dataContainer.querySelectorAll(".faq-trigger").forEach(function (trigger) {
            trigger.addEventListener("click", function () {
                var item = this.closest(".faq-item");
                var isActive = item.classList.contains("active");

                // Close other items
                elements.dataContainer.querySelectorAll(".faq-item").forEach(function (other) {
                    if (other !== item) {
                        other.classList.remove("active");
                        var oTrig = other.querySelector(".faq-trigger");
                        if (oTrig) oTrig.setAttribute("aria-expanded", "false");
                    }
                });

                if (isActive) {
                    item.classList.remove("active");
                    this.setAttribute("aria-expanded", "false");
                } else {
                    item.classList.add("active");
                    this.setAttribute("aria-expanded", "true");
                }
            });
        });

        // Helpful Vote Button
        elements.dataContainer.querySelectorAll('[data-action="vote-helpful"]').forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                e.stopPropagation();
                this.textContent = "✓ Thank you!";
                this.disabled = true;
                showToast("Feedback recorded. Thank you!");
            });
        });
    }

    // --- SKELETON LOADING STATE ---
    function renderLoading() {
        if (!elements.dataContainer) return;
        var html = '<div class="hub-grid">';
        for (var i = 0; i < 6; i++) {
            html +=
                '<div class="hub-skeleton-card">' +
                '  <div class="skeleton-line tall"></div>' +
                '  <div class="skeleton-line short"></div>' +
                '  <div class="skeleton-line medium"></div>' +
                '  <div class="skeleton-line"></div>' +
                '</div>';
        }
        html += '</div>';
        elements.dataContainer.innerHTML = html;
    }

    // --- MODAL SYSTEM FOR DETAILS ---
    function openEventModal(eventId) {
        var items = state.datasets.events || [];
        var evt = items.find(function (e) { return e.id === eventId; });
        if (!evt || !elements.modal) return;

        elements.modalTitle.innerHTML = '<span class="event-cat-badge">' + evt.category + '</span> ' + evt.title;
        elements.modalBody.innerHTML =
            '<p style="margin-bottom: 16px; font-size: 15px; color: var(--text-secondary);">' + evt.description + '</p>' +
            '<div style="background-color: var(--bg-body); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px;">' +
            '  <dl>' +
            '    <div class="modal-detail-row"><dt>Date & Time</dt><dd>' + evt.date + ' (' + evt.time + ')</dd></div>' +
            '    <div class="modal-detail-row"><dt>Campus Venue</dt><dd>' + evt.venue + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Hosting Department</dt><dd>' + evt.department + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Organizing Body</dt><dd>' + evt.organizer + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Total Capacity</dt><dd>' + evt.seats + ' Seats (' + (evt.seats - evt.registered) + ' remaining)</dd></div>' +
            '  </dl>' +
            '</div>';

        elements.modalFooter.innerHTML =
            '<button class="btn btn-light" data-modal-close type="button">Close</button>' +
            '<button class="btn btn-primary" id="hubRegisterSubmitBtn" type="button">Reserve My Seat</button>';

        elements.modal.classList.add("active");
        document.body.classList.add("modal-open");

        var regBtn = document.getElementById("hubRegisterSubmitBtn");
        if (regBtn) {
            regBtn.addEventListener("click", function () {
                evt.registered = (evt.registered || 0) + 1;
                regBtn.textContent = "✓ Seat Reserved";
                regBtn.disabled = true;
                showToast("Success! Seat confirmed for " + evt.title);
                setTimeout(function () {
                    closeHubModal();
                    renderCurrentView();
                }, 1200);
            });
        }
    }

    function openStudentModal(studentId) {
        var items = state.datasets.students || [];
        var stu = items.find(function (s) { return s.id === studentId; });
        if (!stu || !elements.modal) return;

        var skillsHtml = Array.isArray(stu.skills) ? stu.skills.join(", ") : "General";

        elements.modalTitle.innerHTML = 'Student Profile: ' + stu.name;
        elements.modalBody.innerHTML =
            '<div style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px;">' +
            '  <div class="student-avatar" style="width: 60px; height: 60px; font-size: 22px;">' + stu.avatar + '</div>' +
            '  <div>' +
            '    <h3 style="font-size: 19px; color: var(--text-primary);">' + stu.name + '</h3>' +
            '    <span style="font-family: monospace; color: var(--brand-primary); font-weight: 600;">' + stu.enrollment + '</span>' +
            '  </div>' +
            '</div>' +
            '<div style="background-color: var(--bg-body); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px;">' +
            '  <dl>' +
            '    <div class="modal-detail-row"><dt>Department</dt><dd>' + stu.department + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Current Semester</dt><dd>Semester ' + stu.semester + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Current CGPA</dt><dd><strong style="color: var(--brand-primary);">' + stu.cgpa + ' / 10.0</strong></dd></div>' +
            '    <div class="modal-detail-row"><dt>Attendance Record</dt><dd>' + stu.attendance + '%</dd></div>' +
            '    <div class="modal-detail-row"><dt>Student Email</dt><dd>' + stu.email + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Contact Phone</dt><dd>' + stu.phone + '</dd></div>' +
            '    <div class="modal-detail-row"><dt>Technical Skills</dt><dd>' + skillsHtml + '</dd></div>' +
            '  </dl>' +
            '</div>' +
            (stu.interests ? '<p style="font-size: 13px; color: var(--text-secondary);"><strong>Academic Focus:</strong> ' + stu.interests + '</p>' : '');

        elements.modalFooter.innerHTML =
            '<button class="btn btn-light" data-modal-close type="button">Close</button>' +
            '<button class="btn btn-primary" id="hubCopyEmailBtn" type="button">Copy Student Email</button>';

        elements.modal.classList.add("active");
        document.body.classList.add("modal-open");

        var copyBtn = document.getElementById("hubCopyEmailBtn");
        if (copyBtn) {
            copyBtn.addEventListener("click", function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(stu.email);
                }
                showToast("Copied: " + stu.email);
            });
        }
    }

    function closeHubModal() {
        if (elements.modal) {
            elements.modal.classList.remove("active");
            document.body.classList.remove("modal-open");
        }
    }

    // --- RESET FILTERS ---
    function resetFilters() {
        state.searchQuery = "";
        state.categoryFilter = "all";
        state.secondaryFilter = "all";
        state.sortBy = "default";
        state.currentPage = 1;

        if (elements.searchInput) {
            elements.searchInput.value = "";
        }
        if (elements.clearSearchBtn) {
            elements.clearSearchBtn.classList.remove("visible");
        }
        if (elements.categoryFilter) {
            elements.categoryFilter.value = "all";
        }
        if (elements.secondaryFilter) {
            elements.secondaryFilter.value = "all";
        }
        if (elements.sortBy) {
            elements.sortBy.value = "default";
        }

        renderCurrentView();
        showToast("Filters reset to default");
    }

    // --- TAB SWITCHER ---
    async function switchTab(newTab) {
        if (state.activeTab === newTab && state.datasets[newTab]) return;

        state.activeTab = newTab;
        state.currentPage = 1;
        state.categoryFilter = "all";
        state.secondaryFilter = "all";
        state.sortBy = "default";

        // Update active tab buttons
        elements.tabs.forEach(function (tab) {
            if (tab.getAttribute("data-tab") === newTab) {
                tab.classList.add("active");
            } else {
                tab.classList.remove("active");
            }
        });

        await fetchDataset(newTab);
        updateFilterOptions();
        renderCurrentView();
    }

    // --- HOMEPAGE DYNAMIC FAQ ENHANCEMENT (If on index.html) ---
    async function enhanceHomepageFaqs() {
        var homepageFaqContainer = document.querySelector(".faq-section .faq-list");
        var homepageSearch = document.getElementById("homepageFaqSearch");
        if (!homepageFaqContainer) return;

        try {
            var res = await fetch("data/faqs.json");
            var faqs = await res.json();

            function renderHpFaqs(list) {
                if (list.length === 0) {
                    homepageFaqContainer.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 30px;">No FAQs match your search.</p>';
                    return;
                }
                var html = "";
                list.forEach(function (faq, idx) {
                    var active = idx === 0 ? " active" : "";
                    var expanded = idx === 0 ? "true" : "false";
                    html +=
                        '<div class="faq-item' + active + '">' +
                        '  <button class="faq-trigger" aria-expanded="' + expanded + '" type="button">' +
                        '    <span class="faq-question-text">' +
                        '      <span class="faq-badge">' + faq.category + '</span>' +
                        '      ' + faq.question +
                        '    </span>' +
                        '    <span class="faq-icon" aria-hidden="true">' +
                        '      <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
                        '    </span>' +
                        '  </button>' +
                        '  <div class="faq-panel">' +
                        '    <div class="faq-content">' +
                        '      <div class="faq-content-inner">' + faq.answer + '</div>' +
                        '    </div>' +
                        '  </div>' +
                        '</div>';
                });
                homepageFaqContainer.innerHTML = html;

                // Re-bind accordion clicks
                if (window.StudentHub && window.StudentHub.initAccordion) {
                    window.StudentHub.initAccordion();
                } else {
                    homepageFaqContainer.querySelectorAll(".faq-trigger").forEach(function (trig) {
                        trig.addEventListener("click", function () {
                            var item = this.closest(".faq-item");
                            item.classList.toggle("active");
                            this.setAttribute("aria-expanded", item.classList.contains("active") ? "true" : "false");
                        });
                    });
                }
            }

            renderHpFaqs(faqs);

            if (homepageSearch) {
                homepageSearch.addEventListener("input", function () {
                    var q = this.value.toLowerCase().trim();
                    var filtered = faqs.filter(function (f) {
                        return f.question.toLowerCase().includes(q) || f.answer.toLowerCase().includes(q) || f.category.toLowerCase().includes(q);
                    });
                    renderHpFaqs(filtered);
                });
            }
        } catch (e) {
            console.log("Static FAQ retained for index page fallback");
        }
    }

    // --- INITIALIZATION ---
    async function init() {
        // If on the events/hub page:
        if (elements.dataContainer) {
            // Tab clicks
            elements.tabs.forEach(function (tab) {
                tab.addEventListener("click", function () {
                    var target = this.getAttribute("data-tab");
                    if (target) switchTab(target);
                });
            });

            // Live Search Input (Real-time with debounce)
            if (elements.searchInput) {
                var searchDebounceTimer = null;
                elements.searchInput.addEventListener("input", function () {
                    var val = this.value;
                    state.searchQuery = val;
                    state.currentPage = 1;

                    if (elements.clearSearchBtn) {
                        if (val.length > 0) {
                            elements.clearSearchBtn.classList.add("visible");
                        } else {
                            elements.clearSearchBtn.classList.remove("visible");
                        }
                    }

                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function () {
                        renderCurrentView();
                    }, 200);
                });
            }

            // Clear search button
            if (elements.clearSearchBtn) {
                elements.clearSearchBtn.addEventListener("click", function () {
                    elements.searchInput.value = "";
                    elements.clearSearchBtn.classList.remove("visible");
                    state.searchQuery = "";
                    state.currentPage = 1;
                    renderCurrentView();
                });
            }

            // Category filter change
            if (elements.categoryFilter) {
                elements.categoryFilter.addEventListener("change", function () {
                    state.categoryFilter = this.value;
                    state.currentPage = 1;
                    renderCurrentView();
                });
            }

            // Secondary filter change
            if (elements.secondaryFilter) {
                elements.secondaryFilter.addEventListener("change", function () {
                    state.secondaryFilter = this.value;
                    state.currentPage = 1;
                    renderCurrentView();
                });
            }

            // Sort change
            if (elements.sortBy) {
                elements.sortBy.addEventListener("change", function () {
                    state.sortBy = this.value;
                    state.currentPage = 1;
                    renderCurrentView();
                });
            }

            // Page Size change
            if (elements.pageSizeSelect) {
                elements.pageSizeSelect.addEventListener("change", function () {
                    state.itemsPerPage = parseInt(this.value, 10) || 6;
                    state.currentPage = 1;
                    renderCurrentView();
                });
            }

            // Reset button
            if (elements.resetBtn) {
                elements.resetBtn.addEventListener("click", resetFilters);
            }

            // Modal close triggers
            if (elements.modal) {
                elements.modal.addEventListener("click", function (e) {
                    if (e.target === elements.modal || e.target.closest("[data-modal-close]")) {
                        closeHubModal();
                    }
                });
            }

            // Load initial dataset (events)
            await fetchDataset(state.activeTab);
            updateFilterOptions();
            renderCurrentView();
        }

        // If on index.html, enhance FAQs
        enhanceHomepageFaqs();
    }

    // Run on DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    // Expose for external invocation or console debugging
    window.StudentHubData = {
        fetchDataset: fetchDataset,
        switchTab: switchTab,
        resetFilters: resetFilters,
        state: state
    };
})();
