# ConcertTicketDB Manager

A local web interface for the ConcertTicketDB MySQL database.

## Setup
1. Install **MySQL Server** and **MySQL Workbench**, and make sure the MySQL service is running.
2. In Workbench, connect as `root`, open `ConcertTicketDB.sql` (File > Open SQL Script) and run the whole script (lightning bolt). Re-run it any time you want to reset the data.
3. Edit `db.php` and set `$pass` to your MySQL root password.
4. Serve the site (PHP with the `mysqli` extension is required). Either:
   - **XAMPP, Apache only:** copy `index.php` and `db.php` into `C:\xampp\htdocs\my_site\`, start only **Apache** (leave XAMPP's MySQL stopped so it doesn't clash on port 3306), and open `http://localhost/my_site/index.php`; or
   - **PHP built-in server:** in the folder with the files run `php -S localhost:8000`, then open `http://localhost:8000/index.php`.


## Roles (sidebar, bottom)
Everyone starts as **Staff**. To become **Manager**, type the manager password in the sidebar box and click *Switch to Manager*. *Switch to Staff* returns to limited access.

Super secret manager password: sup3raw3som3manag3r

| | Staff | Manager |
|---|---|---|
| Tables | View Reward_Tier, Venue, Concert, Performer, Concert_Performer; view/add/edit Customer, Booking, Ticket. No Employee table. | All tables |
| Delete records | No | Yes |
| Create/drop tables, views, triggers | No | Yes |
| Saved queries | Run only | Run, add, edit, delete, custom SQL |
| Views | Open only | Open, create, edit, drop |
| Triggers page | Hidden | Yes |

Staff connects to MySQL as `concert_staff` (created at the bottom of the `.sql` file), so the database enforces these limits too. **Re-import the `.sql` file once** to create that user. Change permissions in two places: `$STAFF_TABLES` in `index.php` and the `GRANT` lines in the `.sql` file.

## Sidebar
- **Home**: Saved Queries, then Saved Views, then a table dropdown with add-record form and its rows.
- **Tables**: list of all tables. Open one to add, edit or delete records. Create a table by pasting a `CREATE TABLE` statement, or drop one.
- **Queries**: list of saved queries. Run, edit or delete them, or save a new one. "Run only" runs SQL without saving it.
- **Views**: list of views. Open, edit, drop or create one (name plus a `SELECT`).
- **Triggers**: list of triggers. Edit, drop or create one (paste a full `CREATE TRIGGER`, no `DELIMITER` needed).

## Tips
- Leave a field blank to store NULL. `*` marks primary keys.
- Deletes may fail if another table references the record. Delete the child records first.
- Saved queries are stored in an auto-created `Saved_Query` table.
- Procedures and functions run as saved queries. Edit the ID in the SQL to change it.
