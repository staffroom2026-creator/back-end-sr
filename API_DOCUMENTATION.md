# StaffRoom API Documentation

## Overview
StaffRoom is a teaching job marketplace backend API. The API is built using Node.js, Express, and MySQL.

**Base URL:** `/api`
**Health Check:** `GET /health`

## Authentication
Authentication is required for most endpoints using a Bearer token (JWT). Include the token in the `Authorization` header:
`Authorization: Bearer <your_jwt_token>`

---

### Auth Endpoints

#### Register
* **URL:** `/auth/register`
* **Method:** `POST`
* **Body Parameters:**
  * `full_name` (string)
  * `email` (string)
  * `phone` (string)
  * `password` (string)
  * `role` (string) - e.g., "teacher", "school", "admin"
* **Success Response:** 201 Created with User object and Token.

#### Login
* **URL:** `/auth/login`
* **Method:** `POST`
* **Body Parameters:**
  * `email` (string)
  * `password` (string)
* **Success Response:** 200 OK with User object and Token.

---

### Jobs Endpoints

#### Get All Jobs
* **URL:** `/jobs`
* **Method:** `GET`
* **Query Parameters:** `subject`, `state` (optional)
* **Success Response:** 200 OK with array of Job objects.

#### Get Job by ID
* **URL:** `/jobs/:id`
* **Method:** `GET`
* **Success Response:** 200 OK with Job object.

#### Create Job
* **URL:** `/jobs`
* **Method:** `POST`
* **Auth:** Required (Role: `school`)
* **Success Response:** 201 Created.

#### Update Job
* **URL:** `/jobs/:id`
* **Method:** `PUT`
* **Auth:** Required (Role: `school`)
* **Success Response:** 200 OK.

#### Delete Job
* **URL:** `/jobs/:id`
* **Method:** `DELETE`
* **Auth:** Required (Role: `school`)
* **Success Response:** 204 No Content.

---

### Applications Endpoints

#### Apply for Job
* **URL:** `/applications/apply/:jobId`
* **Method:** `POST`
* **Auth:** Required (Role: `teacher`)
* **Success Response:** 201 Created.

#### Get My Applications
* **URL:** `/applications/my-applications`
* **Method:** `GET`
* **Auth:** Required (Role: `teacher`)
* **Success Response:** 200 OK with array of Applications.

#### Get Job Applications
* **URL:** `/applications/job/:jobId`
* **Method:** `GET`
* **Auth:** Required (Role: `school`)
* **Success Response:** 200 OK with array of Applications for the job.

#### Update Application Status
* **URL:** `/applications/:id/status`
* **Method:** `PATCH`
* **Auth:** Required (Role: `school`)
* **Body Parameters:** `status` (string)
* **Success Response:** 200 OK.

---

### Profile Endpoints

#### Get Current User Profile
* **URL:** `/profiles/me`
* **Method:** `GET`
* **Auth:** Required
* **Success Response:** 200 OK with User profile.

#### Update Teacher Profile
* **URL:** `/profiles/teacher`
* **Method:** `PUT`
* **Auth:** Required (Role: `teacher`)
* **Success Response:** 200 OK.

#### Update School Profile
* **URL:** `/profiles/school`
* **Method:** `PUT`
* **Auth:** Required (Role: `school`)
* **Success Response:** 200 OK.

#### Upload CV
* **URL:** `/profiles/upload-cv`
* **Method:** `POST`
* **Auth:** Required (Role: `teacher`)
* **Body:** `multipart/form-data` with `cv` file.

#### Upload Logo
* **URL:** `/profiles/upload-logo`
* **Method:** `POST`
* **Auth:** Required (Role: `school`)
* **Body:** `multipart/form-data` with `logo` file.

---

### Features Endpoints

#### Save Job
* **URL:** `/features/saved-jobs/:jobId`
* **Method:** `POST`
* **Auth:** Required

#### Get Saved Jobs
* **URL:** `/features/saved-jobs`
* **Method:** `GET`
* **Auth:** Required

#### Unsave Job
* **URL:** `/features/saved-jobs/:jobId`
* **Method:** `DELETE`
* **Auth:** Required

#### Get Notifications
* **URL:** `/features/notifications`
* **Method:** `GET`
* **Auth:** Required

#### Mark Notification as Read
* **URL:** `/features/notifications/:id/read`
* **Method:** `PATCH`
* **Auth:** Required

---

### Admin Endpoints

#### Get Stats
* **URL:** `/admin/stats`
* **Method:** `GET`
* **Auth:** Required (Role: `admin`)

#### Get Pending Verifications
* **URL:** `/admin/verifications`
* **Method:** `GET`
* **Auth:** Required (Role: `admin`)

#### Verify School
* **URL:** `/admin/verifications/:id`
* **Method:** `PATCH`
* **Auth:** Required (Role: `admin`)
