# Heal2Rise Book Project

## 1. Core Business Logic Implementation

### A. Secure Registration & Privacy Protection
* **Files:** `user/register.php`, `ngo/register.php`
* **Implementation:** 
  * **Security:** Forms are protected against Cross-Site Request Forgery (CSRF) using `verifyCSRFToken()`. Passwords are encrypted using PHP's secure hashing algorithms before being stored.
  * **Privacy Consent:** A mandatory `privacy_consent` field must be checked to proceed.
  * **State Management:** By default (defined in `database/schema.sql`), new accounts are created with `is_verified = 0` and `verification_status = 'pending'`. Active support cannot be requested until admin approval.

### B. Admin Verification Flow
* **Files:** `admin/verify-user.php`, `admin/verify-ngo.php`
* **Implementation:** 
  * Admins review registrations and submit a secure POST request to approve or reject.
  * An `UPDATE` SQL query changes the `verification_status` and `is_verified` flags accordingly.
  * An automated notification is inserted into the `notifications` table to alert the user/NGO of their account status in real-time.

### C. NGO Connection & Case Assignment (Auto-Matching)
* **Files:** `includes/functions.php` (specifically `createAndAssignCase()`)
* **Implementation:** 
  * **1. Matching the NGO:** The `findSuitableNGO()` function queries the `ngos` table to find verified, active organizations matching the user's specific issue (e.g., 'depression'). It strictly checks capacity (`current_cases < capacity`) and prioritizes NGOs in the user's city.
  * **2. Assigning the Team Member:** The `findAvailableTeamMember()` function finds staff within the chosen NGO. It performs smart routing (e.g., prioritizing psychiatrists for mental health issues) and ensures the member hasn't hit their `max_cases` limit.
  * **3. State Updates:** A new record is created in the `cases` table. The logic then increments the `current_cases` for the NGO and `cases_assigned` for the team member to manage workload distribution accurately.

---

## 2. How to Explain the Code in Your Viva

When the supervisor asks how the application works, guide them through the data flow:

1. **Start at Registration:** Open `user/register.php`. Explain the initial data capture, emphasizing the security sanitization and the default "pending" state.
2. **Move to Verification:** Open `admin/verify-user.php`. Explain how state is updated in the database and how the feedback loop (notifications system) works.
3. **Deep Dive into the Star Feature:** Open `includes/functions.php` and locate `createAndAssignCase()`.
   * **Highlight the Constraints:** Explain that your code actively prevents overwhelming any single NGO by strictly checking `current_cases < capacity`.
   * **Highlight the Algorithm:** Explain the `ORDER BY city` and `current_cases ASC` logic. Describe this as your "load-balancing and proximity routing algorithm."
4. **Use Key Terminology:** Regularly use terms like *Data Sanitization*, *State Updates*, *Constraints*, and *Dynamic SQL Queries*.

---

## 3. Database Architecture: `schema.sql` vs `migration.sql`

A common viva question is why both files exist. Here is the technical justification:

### The Foundation: `schema.sql`
* **Purpose:** This is the complete blueprint used to build the database from scratch (the `CREATE TABLE` statements).
* **Usage:** Run only once during initial project setup.
* **Limitation:** Running this again would require dropping existing tables, resulting in total data loss.

### The Version Control: `migration.sql`
* **Purpose:** This file contains incremental updates (`ALTER TABLE`, `ADD COLUMN`) made to the database as project requirements evolve.
* **Usage:** Run whenever a structural change is needed during development without losing existing test data.
* **The Analogy:** If `schema.sql` is the blueprint to build a house from scratch, `migration.sql` is the renovation order to add a new room without tearing the whole house down.

*Understanding this distinction proves to supervisors that you comprehend agile development, database version control, and safe production-environment practices.*