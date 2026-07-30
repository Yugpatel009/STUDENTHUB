# Student Hub - Website Details

## 1. Project Name

**Student Hub**

Student Hub is a front-end student portal website made with HTML, CSS, and JavaScript. It is designed for students to access academic information such as dashboard details, courses, assignments, attendance, results, profile information, and contact support.

## 2. Website Overview

Student Hub works like a simple academic portal. A visitor starts from the home page, then logs in or creates an account. After login, the user can open the dashboard and access different academic modules.

The project is static, so it does not need a database or backend server. Login status is handled in the browser using `localStorage`.

## 3. Role Requirements

| Role | Requirement | Access Details |
| --- | --- | --- |
| Visitor | No login required | Can open Home, Login, Register, and Contact pages. |
| Student | Login required | Can open Dashboard, Courses, Assignments, Attendance, Results, and Profile pages. |
| Teacher | Login required | Can use the same protected portal pages in this front-end demo. |
| Admin | Login required | Can use the same protected portal pages in this front-end demo. |

Note: This is a front-end demo project. Student, Teacher, and Admin roles are available in the form, but the protected pages currently use one common login check.

## 4. Modules And Page Details

### Module 1: Home Module

**Page:** `index.html`

**CSS Files:**

- `css/style.css`
- `css/index.css`

**Role Requirement:** Public access

**Details:**

- Shows the Student Hub landing page.
- Contains the website logo and navigation.
- Provides Login and Sign Up buttons.
- Shows a short introduction about the portal.
- Displays dashboard preview cards.
- Shows quick statistics such as courses, portal access, and student focus.
- Includes a call-to-action section and footer.

### Module 2: Login Module

**Page:** `Pages/login.html`

**CSS Files:**

- `css/style.css`
- `css/login.css`

**JavaScript File:** `js/script.js`

**Role Requirement:** Public access

**Details:**

- Allows users to enter email and password.
- Allows users to select role: Student, Teacher, or Admin.
- Includes a Remember Me checkbox.
- Includes a Need Help link.
- Includes a link for new users to create an account.
- Shows rotating education quotes.
- On successful login, stores login status in `localStorage`.
- Redirects the user to the dashboard page.

### Module 3: Registration Module

**Page:** `Pages/register.html`

**CSS Files:**

- `css/style.css`
- `css/register.css`

**JavaScript File:** `js/script.js`

**Role Requirement:** Public access

**Details:**

- Allows a new user to enter full name, email, and password.
- Allows role selection.
- Shows course dropdown when Student role is selected.
- Includes terms checkbox.
- Shows rotating education quotes.
- Shows a success message after registration.
- Redirects user to the login page after registration.

### Module 4: Dashboard Module

**Page:** `Pages/dashboard.html`

**CSS Files:**

- `css/style.css`
- `css/dashboard.css`

**JavaScript File:** `js/script.js`

**Role Requirement:** Login required

**Details:**

- Shows welcome message.
- Displays summary cards for courses, attendance, assignments, and result.
- Shows today's schedule.
- Provides quick links to portal modules.
- Includes Logout button.
- Redirects unauthenticated users to the login page.

### Module 5: Courses Module

**Page:** `Pages/courses.html`

**CSS Files:**

- `css/style.css`
- `css/courses.css`

**Role Requirement:** Login required

**Details:**

- Shows course cards.
- Displays course names and short descriptions.
- Shows progress percentage for each course.
- Current courses include Web Development, Database Management, and Computer Networks.

### Module 6: Assignment Module

**Page:** `Pages/assignment.html`

**CSS Files:**

- `css/style.css`
- `css/assignment.css`

**Role Requirement:** Login required

**Details:**

- Shows assignment information in table format.
- Displays subject name, assignment title, due date, status, and action.
- Uses badges for Pending and Submitted status.
- Allows users to see whether work is pending or submitted.

### Module 7: Attendance Module

**Page:** `Pages/attendance.html`

**CSS Files:**

- `css/style.css`
- `css/attendance.css`

**Role Requirement:** Login required

**Details:**

- Shows subject-wise attendance.
- Displays total classes, present count, absent count, and percentage.
- Uses success and warning badges for attendance percentage.
- Helps students track attendance performance.

### Module 8: Results Module

**Page:** `Pages/result.html`

**CSS Files:**

- `css/style.css`
- `css/result.css`

**Role Requirement:** Login required

**Details:**

- Shows CGPA, rank, and pass status cards.
- Displays result table with subject, marks, grade, and status.
- Uses success badges for passed subjects.
- Helps students review academic performance.

### Module 9: Profile Module

**Page:** `Pages/profile.html`

**CSS Files:**

- `css/style.css`
- `css/profile.css`

**Role Requirement:** Login required

**Details:**

- Shows student profile card.
- Displays student initials, name, and semester.
- Shows email, enrollment number, course, department, and phone number.
- Includes a Contact Office button.

### Module 10: Contact Module

**Page:** `Pages/contact.html`

**CSS Files:**

- `css/style.css`
- `css/contact.css`

**Role Requirement:** Public access

**Details:**

- Shows contact form with name, email, and message fields.
- Shows support email, phone number, office address, and working time.
- Helps visitors or students contact the support team.

## 5. Folder Structure

```text
student HUB project/
|-- index.html
|-- README.md
|-- sitemap.md
|-- css/
|   |-- style.css
|   |-- index.css
|   |-- login.css
|   |-- register.css
|   |-- dashboard.css
|   |-- courses.css
|   |-- assignment.css
|   |-- attendance.css
|   |-- result.css
|   |-- profile.css
|   `-- contact.css
|-- js/
|   `-- script.js
|-- img/
|   |-- logo.png
|   |-- Logo1.png
|   |-- dashboard.png
|   |-- Courses.png
|   |-- Assignment.png
|   |-- Attendance.png
|   |-- Result.png
|   |-- Profile.png
|   |-- Login.png
|   |-- Sign-up.png
|   `-- Contact Us.png
`-- Pages/
    |-- login.html
    |-- register.html
    |-- dashboard.html
    |-- courses.html
    |-- assignment.html
    |-- attendance.html
    |-- result.html
    |-- profile.html
    `-- contact.html
```

## 6. File Details

| File / Folder | Details |
| --- | --- |
| `index.html` | Main home page of the website. |
| `Pages/` | Contains all inner website pages. |
| `css/style.css` | Common CSS for global design, header, buttons, forms, panels, footer, and responsive styles. |
| `css/index.css` | Home page specific CSS. |
| `css/login.css` | Login page specific CSS. |
| `css/register.css` | Registration page specific CSS. |
| `css/dashboard.css` | Dashboard page specific CSS. |
| `css/courses.css` | Courses page specific CSS. |
| `css/assignment.css` | Assignment page specific CSS. |
| `css/attendance.css` | Attendance page specific CSS. |
| `css/result.css` | Result page specific CSS. |
| `css/profile.css` | Profile page specific CSS. |
| `css/contact.css` | Contact page specific CSS. |
| `js/script.js` | Handles login, registration, protected page check, logout, role-based course dropdown, and rotating quotes. |
| `img/` | Stores website images and icons. |
| `sitemap.md` | Shows website page navigation structure. |
| `README.md` | Contains project overview and documentation. |

## 7. Website Navigation Flow

```text
Home Page
|-- Login Page
|   `-- Dashboard
|       |-- Courses
|       |-- Assignments
|       |-- Attendance
|       |-- Results
|       |-- Profile
|       `-- Contact
|-- Registration Page
`-- Contact Page
```

## 8. JavaScript Details

The `js/script.js` file provides the dynamic behavior of the website.

Main functions:

- Checks login status before opening protected pages.
- Redirects unauthenticated users to the login page.
- Handles login form submission.
- Stores `isLoggedIn` value in browser `localStorage`.
- Handles registration form submission.
- Shows course dropdown for Student role during registration.
- Clears login data when Logout is clicked.
- Displays random quotes on login and registration pages.
- Changes quotes automatically every 3 seconds.

## 9. How To Run

1. Open the project folder.
2. Open `index.html` in any modern web browser.
3. Click Login or Sign Up.
4. After login, open Dashboard and use the module links.
5. Click Logout to end the session.

## 10. Technologies Used

- HTML5 for website structure.
- CSS3 for styling and responsive layout.
- JavaScript for interactivity and login flow.
- Browser `localStorage` for simple login session handling.

## 11. Project Summary

Student Hub is a complete front-end student portal demo. It includes public pages, authentication pages, protected student pages, separated CSS files for each page, and JavaScript-based login behavior. The project is suitable for an HTML, CSS, and JavaScript semester assignment or academic web development project.
