# Student Hub Website Overview

Student Hub is a simple student portal website created with HTML, CSS, and JavaScript. The website gives students one place to access common academic information such as courses, assignments, attendance, results, profile details, and support contact information.

The project is built as a static website, so it can be opened directly in a browser without a backend server.

## Website Purpose

The main purpose of Student Hub is to show a complete student portal flow:

- A visitor opens the home page.
- The visitor can register or log in.
- After login, the student reaches the dashboard.
- From the dashboard, the student can open academic pages like courses, assignments, attendance, results, and profile.
- The student can also contact support or log out.

## Main Pages

### Home Page

File: `index.html`

The home page is the starting point of the website. It introduces the Student Hub portal and provides quick actions for login and registration.

Main sections:

- Header with logo and navigation.
- Hero section with the website message.
- Login and Create Account buttons.
- Small student dashboard preview.
- Portal statistics such as courses, portal access, and student focus.
- Call-to-action section for login or sign up.
- Footer with copyright information.

CSS file:

- `css/index.css`

### Login Page

File: `Pages/login.html`

The login page allows an existing user to enter account details and access the dashboard.

Main features:

- Email input.
- Password input.
- Role selection for Student, Teacher, or Admin.
- Remember me checkbox.
- Help link to the contact page.
- Link to registration page for new students.
- Quote box that changes using JavaScript.

JavaScript behavior:

- The form checks that email and password are filled.
- When submitted successfully, it stores login status in `localStorage`.
- After login, the user is redirected to `dashboard.html`.

CSS file:

- `css/login.css`

### Registration Page

File: `Pages/register.html`

The registration page allows a new user to create a Student Hub account.

Main features:

- Full name input.
- Email input.
- Password input.
- Role selection.
- Course dropdown shown when the Student role is selected.
- Terms checkbox.
- Link back to the login page.
- Quote box that changes using JavaScript.

JavaScript behavior:

- The form checks that required fields are filled.
- When submitted successfully, it shows a success message.
- The user is redirected to the login page.
- The course dropdown appears only for the Student role.

CSS file:

- `css/register.css`

### Student Dashboard

File: `Pages/dashboard.html`

The dashboard is the main page after login. It gives the student a quick academic summary and links to other portal modules.

Main sections:

- Page heading with welcome message.
- Summary cards for courses, attendance, assignments, and result.
- Today schedule panel.
- Portal modules panel with links to course info, assignments, attendance, results, and contact support.
- Logout button in the header.

JavaScript behavior:

- This page is protected.
- If the user is not logged in, JavaScript redirects them to the login page.
- The logout button clears login data from `localStorage`.

CSS file:

- `css/dashboard.css`

### Courses Page

File: `Pages/courses.html`

The courses page displays academic course information and progress.

Main content:

- Web Development course card.
- Database Management course card.
- Computer Networks course card.
- Each course card includes a short description and progress percentage.

JavaScript behavior:

- This page is protected and requires login.

CSS file:

- `css/courses.css`

### Assignment Page

File: `Pages/assignment.html`

The assignment page shows assignment details in a table format.

Main content:

- Subject name.
- Assignment title.
- Due date.
- Submission status.
- Action link such as Submit or View.

Status badges:

- Pending assignments use a warning badge.
- Submitted assignments use a success badge.

JavaScript behavior:

- This page is protected and requires login.

CSS file:

- `css/assignment.css`

### Attendance Page

File: `Pages/attendance.html`

The attendance page shows subject-wise attendance records.

Main content:

- Subject name.
- Total classes.
- Present count.
- Absent count.
- Attendance percentage.

Status badges:

- Good attendance uses a success badge.
- Lower attendance uses a warning badge.

JavaScript behavior:

- This page is protected and requires login.

CSS file:

- `css/attendance.css`

### Results Page

File: `Pages/result.html`

The results page shows academic performance details.

Main sections:

- CGPA summary card.
- Rank summary card.
- Pass status summary card.
- Result table with subject, marks, grade, and status.

JavaScript behavior:

- This page is protected and requires login.

CSS file:

- `css/result.css`

### Profile Page

File: `Pages/profile.html`

The profile page displays student personal and academic information.

Main sections:

- Profile card with student initials, name, semester, and contact button.
- Details panel with email, enrollment number, course, department, and phone number.

JavaScript behavior:

- This page is protected and requires login.

CSS file:

- `css/profile.css`

### Contact Page

File: `Pages/contact.html`

The contact page allows users to send a support message and view support details.

Main sections:

- Contact form with name, email, and message fields.
- Support details card with email, phone, office, and support time.

CSS file:

- `css/contact.css`

## Navigation Flow

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

## JavaScript Overview

File: `js/script.js`

The JavaScript file controls simple website behavior:

- Checks whether protected pages require login.
- Redirects users to the login page if they are not logged in.
- Handles login form submission.
- Saves login status in `localStorage`.
- Handles registration form submission.
- Shows or hides the course dropdown based on selected role.
- Clears login data when the user logs out.
- Displays random education quotes on login and registration pages.
- Changes quotes automatically every few seconds.

## CSS Organization

The CSS is split into common and page-specific files.

Common stylesheet:

- `css/style.css`

Page-specific stylesheets:

- `css/index.css`
- `css/login.css`
- `css/register.css`
- `css/dashboard.css`
- `css/courses.css`
- `css/assignment.css`
- `css/attendance.css`
- `css/result.css`
- `css/profile.css`
- `css/contact.css`

Each HTML page loads `style.css` first, then its own page CSS file.

## Folder Structure

```text
student HUB project
|-- index.html
|-- README.md
|-- sitemap.md
|-- css
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
|-- js
|   `-- script.js
|-- img
`-- Pages
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

## How To Run

1. Open the project folder in a code editor.
2. Open `index.html` in a browser.
3. Click Login or Sign Up to continue.
4. After login, use the dashboard links to visit the student pages.

## Technologies Used

- HTML for page structure.
- CSS for layout, colors, spacing, responsive design, and page styling.
- JavaScript for login flow, registration behavior, protected pages, logout, and rotating quotes.

## Project Summary

Student Hub is a complete front-end student portal demo. It includes a landing page, authentication pages, a student dashboard, academic information pages, a profile page, and a contact page. The website uses separate CSS files for each page and a shared common stylesheet for reusable design elements.
