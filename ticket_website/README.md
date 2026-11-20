# ConcertTicketDB Manager

A local web interface for the ConcertTicketDB MySQL database.

## Setup
1. Start **Apache** and **MySQL** in XAMPP.
2. Run the SQL file once to create the database.
3. Put `index.php` and `db.php` in `C:\xampp\htdocs\ticket_website\`.
4. Open `http://localhost/ticket_website/index.php`.


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

*Claude used for assistance with developing PHP files*
