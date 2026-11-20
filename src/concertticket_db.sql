CREATE SCHEMA IF NOT EXISTS ConcertTicketDB;
USE ConcertTicketDB;

DROP VIEW IF EXISTS tier_summary;
DROP VIEW IF EXISTS loyalty;
DROP PROCEDURE IF EXISTS Get_Customer_Bookings;
DROP FUNCTION IF EXISTS Get_Concert_Revenue;

DROP TABLE IF EXISTS Employee;
DROP TABLE IF EXISTS Concert_Performer;
DROP TABLE IF EXISTS Performer;
DROP TABLE IF EXISTS Ticket;
DROP TABLE IF EXISTS Booking;
DROP TABLE IF EXISTS Customer;
DROP TABLE IF EXISTS Concert;
DROP TABLE IF EXISTS Venue;
DROP TABLE IF EXISTS Reward_Tier;

CREATE TABLE Reward_Tier (
tier_id INT PRIMARY KEY,
tier_name VARCHAR(100) NOT NULL,
threshold INT,
perk VARCHAR(250));

CREATE TABLE Venue (
venue_id INT PRIMARY KEY,
venue_name VARCHAR(100),
state VARCHAR(100),
city VARCHAR(100),
capacity INT,
fixed_cost DECIMAL(12, 2)
);

CREATE TABLE Concert (
concert_id INT PRIMARY KEY,
concert_date DATE NOT NULL,
start_time TIME NOT NULL,
sold_out BOOLEAN DEFAULT FALSE,
venue_id INT NOT NULL,
FOREIGN KEY (venue_id) REFERENCES Venue(venue_id)
);



CREATE TABLE Customer (
customer_id INT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
age INT,
sex VARCHAR(100),
contact_number VARCHAR(100),
tier_id INT NOT NULL,
FOREIGN KEY (tier_id) REFERENCES Reward_Tier(tier_id)
);


CREATE TABLE Booking (
booking_id INT PRIMARY KEY,
booking_number INT NOT NULL UNIQUE,
purchase_date TIMESTAMP,
payment_method VARCHAR(100),
customer_id INT NOT NULL,
FOREIGN KEY (customer_id) REFERENCES Customer(customer_id));

CREATE TABLE Ticket (
ticket_id INT PRIMARY KEY,
seat_type VARCHAR(100) NOT NULL,
seat_num VARCHAR(100) NOT NULL,
price DECIMAL(10, 2) NOT NULL,
ticket_status VARCHAR(100) NOT NULL,
booking_id INT,
concert_id INT NOT NULL,
FOREIGN KEY (booking_id) REFERENCES Booking(booking_id),
FOREIGN KEY (concert_id) REFERENCES Concert(concert_id),
UNIQUE (concert_id, seat_num));



CREATE TABLE Performer (
performer_id INT PRIMARY KEY,
performer_name VARCHAR(100) NOT NULL,
genre VARCHAR(100)
);

-- Junction table for M:N relationship of concert and performer
CREATE TABLE Concert_Performer ( 
	concert_id INT,
    performer_id INT,
    PRIMARY KEY (concert_id, performer_id),
    FOREIGN KEY (concert_id) REFERENCES Concert(concert_id),
    FOREIGN KEY (performer_id) REFERENCES Performer(performer_id));

CREATE TABLE Employee (
employee_id INT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
role VARCHAR(100) NOT NULL,
pay_per_concert DECIMAL(10, 2) NOT NULL,
venue_id INT NOT NULL,
FOREIGN KEY (venue_id) REFERENCES Venue(venue_id));



INSERT INTO Reward_Tier VALUES
(101, 'Base', 0, 'base tier'),
(102, 'Show Supporter', 5, '5% every ticket purchase'),
(103, 'Crowd Commoner', 10, '10% every ticket purchase'),
(104, 'Pit Professional', 25, '15% every ticket purchase'),
(105, 'Basshead', 50, '20% every ticket purchase and one yearly free VIP upgrade');

INSERT INTO Venue VALUES
(20001, 'Salt Shed (Indoors)', 'Illinois', 'Chicago', 3600, 33020.10),
(20002, 'The United Center' , 'Illinois', 'Chicago', 23500, 113243.50),
(20003, 'Red Rocks Ampitheatre' , 'Colorado', 'Denver', 9525, 83542.40),
(20004, 'Madison Square Garden' , 'New York ', 'New York City', 22000, 107458.75),
(20005, 'Gabes', 'Iowa' , 'Iowa City', 400, 2543.43);

INSERT INTO Concert VALUES
(30039, '2026-10-03', '18:30:00', False, 20001),
(30040, '2026-12-03', '19:30:00', False, 20001),
(30041, '2026-12-05', '20:00:00', False, 20005),
(30042, '2027-02-08', '20:30:00', False, 20004),
(30043, '2027-02-10', '19:45:00', False, 20002),
(30044, '2027-03-15', '20:00:00', False, 20001);

INSERT INTO Customer VALUES
(50041, 'Alex Johnson', 24, 'Male', '8154043782', 101),
(50042, 'Taylor Smith', 28, 'Female', '4043785657', 103),
(50043, 'Jordan Williams', 22, 'Male', '4152222956', 102),
(50044, 'Morgan Davis', 31, 'Female', '3133334044', 101),
(50055, 'Casey Brown', 26, 'Male', '2222222222', 104);

INSERT INTO Booking VALUES
(44000, 3002, '2026-10-03 08:23:31', 'Visa', 50041),
(44001, 3003, '2026-10-23 12:43:12', 'MasterCard', 50042),
(44002, 3004, '2026-10-24 14:21:52', 'Visa', 50044),
(44003, 3005, '2026-10-28 07:14:30', 'Apple Pay', 50043),
(44004, 3006, '2026-11-03 08:57:31', 'Visa', 50042);

INSERT INTO Ticket VALUES
(62031, 'Regular', 'G3502', 145.25, 'Used', 44000, 30039),
(62032, 'VIP', 'A320', 125.35, 'Active', 44001, 30043),
(62033, 'Regular', 'B2402', 45.45, 'Active', 44002, 30044),
(62034, 'Regular', 'B2403', 45.45, 'Active', 44002, 30044),
(62035, 'VIP', 'A300', 345.25, 'Active', 44003, 30040),
(62036, 'Floor', 'F001', 25.34, 'Active', 44004, 30041),
(62037, 'Floor', 'F002', 25.34, 'Active', NULL, 30041);

INSERT INTO Performer VALUES
(80023, 'Clarion', 'Shoegaze'),
(80012, 'The Weeknd', 'Pop'),
(80014, 'Tyler, the Creator', 'Hip Hop'),
(80019, 'Balu Brigada', 'Indie'),
(80022, 'Justice', 'Electronic'),
(80024, 'Magdalena Bay', 'Indie');

INSERT INTO Concert_Performer VALUES
(30041, 80023),
(30043, 80012),
(30043, 80024),
(30039, 80022),
(30042, 80014),
(30040, 80019),
(30044, 80023);

INSERT INTO Employee VALUES
(10306, 'James Wilson', 'Security', 300.40, 20003),
(10307, 'Michael Brown', 'Production Manager', 843.35, 20001),
(10308, 'David Miller', 'Production Crew', 256.32, 20002),
(10309, 'Chris Anderson', 'Security', 210.40, 20004),
(10310, 'Daniel Taylor', 'Production Crew', 48.03, 20005);


-- Auto Upgrades 
DELIMITER $$ 
CREATE TRIGGER auto_upgrade
AFTER INSERT ON Ticket
FOR EACH ROW
BEGIN
      DECLARE c_customer_id INT;
      DECLARE t_total INT;

      IF NEW.booking_id IS NOT NULL THEN
 
          SELECT customer_id INTO c_customer_id FROM
          Booking WHERE booking_id = NEW.booking_id;

          SELECT COUNT(*) INTO t_total
                 FROM Ticket t
                 JOIN Booking b ON b.booking_id = t.booking_id
                 WHERE b.customer_id = c_customer_id;

          UPDATE Customer
                 SET tier_id = (SELECT tier_id FROM Reward_Tier 
                                WHERE threshold = (SELECT MAX(threshold) FROM Reward_Tier WHERE threshold <= t_total)) 
                                WHERE customer_id = c_customer_id;
      END IF;
END $$
DELIMITER ;


-- Check if payment is valid
DELIMITER $$
CREATE TRIGGER payment_validation
BEFORE INSERT ON Booking
FOR EACH ROW
BEGIN
    IF NEW.purchase_date IS NULL THEN
        SET NEW.purchase_date = NOW();
    END IF;

    IF NEW.payment_method NOT IN('Card', 'CashApp', 'Paypal', 'Apple Pay', 'Visa', 'MasterCard') THEN 
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Payment not supported.';
    END IF;
END$$
DELIMITER ;


-- Trigger - set concert sold_out to true if all tickets become unavailable
DROP TRIGGER IF EXISTS update_ticket;
DELIMITER $$

CREATE TRIGGER update_ticket
AFTER UPDATE ON Ticket
FOR EACH ROW
BEGIN
    IF(SELECT COUNT(booking_id) = COUNT(*) FROM Ticket WHERE concert_id = NEW.concert_id)
    THEN
        UPDATE Concert
            SET sold_out = TRUE WHERE concert_id = NEW.concert_id;
    END IF;
END$$
DELIMITER ;


-- testing trigger

SELECT * FROM Concert WHERE concert_id = 30041;
UPDATE Ticket SET booking_id = 44004 WHERE ticket_id = 62037;
SELECT * FROM Concert WHERE concert_id = 30041;


-- Check Loyalty Status
CREATE VIEW loyalty AS
SELECT c.customer_id,
       c.name,
       COUNT(t.ticket_id) AS total_tick_bought,
       rt.tier_name AS current_tier,
       rt.threshold AS current_threshold
FROM Customer c
JOIN Reward_Tier rt ON rt.tier_id = c.tier_id
LEFT JOIN Booking b ON b.customer_id = c.customer_id
LEFT JOIN Ticket t ON t.booking_id = b.booking_id
GROUP BY c.customer_id, c.name, rt.tier_name, rt.threshold;


-- Tier Summary

CREATE VIEW tier_summary AS
SELECT rt.tier_id,
       rt.tier_name,
       rt.threshold,
       COUNT(DISTINCT c.customer_id) AS num_customers,
       COUNT(t.ticket_id) AS total_tickets
FROM Reward_Tier rt
JOIN Customer c ON c.tier_id = rt.tier_id
JOIN Booking b ON b.customer_id = c.customer_id
JOIN Ticket t ON t.booking_id = b.booking_id
GROUP BY rt.tier_id, rt.tier_name, rt.threshold
ORDER BY rt.threshold;


-- Query 1 - show all upcoming shows at Gabe's - aggregate and join
SELECT 
    c.concert_id, 
    c.concert_date, 
    c.start_time, 
    GROUP_CONCAT(p.performer_name SEPARATOR ', ') AS performers
FROM Concert c
LEFT JOIN Concert_Performer o ON c.concert_id = o.concert_id
LEFT JOIN Performer p ON o.performer_id = p.performer_id
WHERE c.venue_id = 20005
AND c.concert_date > CURRENT_DATE
GROUP BY c.concert_id, c.concert_date, c.start_time;

-- Query 2 - show all upcoming shows Tyler, the Creator is performing at - join

SELECT c.concert_id, c.concert_date,
v.venue_name, v.state, v.city
FROM Concert c
JOIN Concert_Performer p
ON c.concert_id = p.concert_id
JOIN Venue v
ON v.venue_id = c.venue_id
WHERE p.performer_id = 80014
AND c.concert_date > CURRENT_DATE;

-- Query 3 - calculate the total of a booking 44002 - aggregate
SELECT COUNT(*) AS ticket_count, SUM(t.price) AS total_cost
FROM Ticket t
WHERE t.booking_id = 44002;


-- Query 4 - calculate ticket sales and revenue for each concert - join and aggregate
SELECT
    c.concert_id,
    c.concert_date,
    COUNT(t.ticket_id) AS tickets_sold,
    SUM(t.price) AS total_revenue
FROM Concert c
JOIN Ticket t
ON c.concert_id = t.concert_id
GROUP BY c.concert_id, c.concert_date;



-- Query 5 - Show customers who purchased more than the average number of tickets
-- Uses loyalty view and a subquery

SELECT
    customer_id,
    name,
    total_tick_bought,
    current_tier
FROM loyalty
WHERE total_tick_bought > (
    SELECT AVG(total_tick_bought)
    FROM loyalty
);




-- PROCEDURE: Get Customer Bookings
-- Example:
-- CALL Get_Customer_Bookings(50042);
DELIMITER //

CREATE PROCEDURE Get_Customer_Bookings(IN customerID INT)
BEGIN

    SELECT
        Customer.customer_id,
        Customer.name,
        Booking.booking_number,
        Booking.purchase_date,
        Ticket.ticket_id,
        Ticket.seat_type,
        Ticket.price,
        Ticket.ticket_status
    FROM Customer
    JOIN Booking ON Customer.customer_id = Booking.customer_id
    JOIN Ticket ON Booking.booking_id = Ticket.booking_id
    WHERE Customer.customer_id = customerID;

END //

DELIMITER ;



-- FUNCTION: Get Concert Revenue
-- Example:
-- SELECT Get_Concert_Revenue(30043) AS total_revenue;
DELIMITER //

CREATE FUNCTION Get_Concert_Revenue(concertID INT)
RETURNS DECIMAL(10, 2)
READS SQL DATA
BEGIN

    DECLARE totalRevenue DECIMAL(10, 2);

    SELECT SUM(price)
    INTO totalRevenue
    FROM Ticket
    WHERE concert_id = concertID;

    RETURN totalRevenue;

END //

DELIMITER ;





-- =====================================================================
-- ACCESS CONTROL: STAFF and MANAGER roles
-- The website picks the role from the sidebar (manager needs a password in
-- index.php). Staff then connects to MySQL as concert_staff, so the database
-- itself enforces the limits. Manager uses the normal db.php (root) login.
-- Keep this in sync with $STAFF_TABLES / $STAFF_DB at the top of index.php.
-- =====================================================================

-- Saved_Query is created by the website; create it here too so staff can be
-- granted access to it. (Not dropped above, so saved queries survive re-imports.)
CREATE TABLE IF NOT EXISTS Saved_Query (
    query_id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    sql_text TEXT NOT NULL
);

-- Recreate users on every import so permission changes always apply
DROP USER IF EXISTS 'concert_staff'@'localhost';
DROP USER IF EXISTS 'concert_manager'@'localhost';

CREATE USER 'concert_staff'@'localhost'   IDENTIFIED BY 'staff_pass_123';
CREATE USER 'concert_manager'@'localhost' IDENTIFIED BY 'manager_pass_123';

-- MANAGER: everything in this database (including Employee pay data,
-- creating/dropping tables, views, triggers and routines)
GRANT ALL PRIVILEGES ON ConcertTicketDB.* TO 'concert_manager'@'localhost';

-- STAFF, read only: reference data
GRANT SELECT ON ConcertTicketDB.Reward_Tier        TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.Venue              TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.Concert            TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.Performer          TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.Concert_Performer  TO 'concert_staff'@'localhost';

-- STAFF, read + add + edit (no DELETE): day-to-day sales tables
GRANT SELECT, INSERT, UPDATE ON ConcertTicketDB.Customer TO 'concert_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON ConcertTicketDB.Booking  TO 'concert_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON ConcertTicketDB.Ticket   TO 'concert_staff'@'localhost';

-- STAFF: may open the views and run saved queries / routines
GRANT SELECT ON ConcertTicketDB.loyalty        TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.tier_summary   TO 'concert_staff'@'localhost';
GRANT SELECT ON ConcertTicketDB.Saved_Query    TO 'concert_staff'@'localhost';
GRANT EXECUTE ON PROCEDURE ConcertTicketDB.Get_Customer_Bookings TO 'concert_staff'@'localhost';
GRANT EXECUTE ON FUNCTION  ConcertTicketDB.Get_Concert_Revenue   TO 'concert_staff'@'localhost';

-- STAFF gets NO access to: Employee (pay data), and no DELETE, CREATE, DROP,
-- ALTER or TRIGGER privileges anywhere.

FLUSH PRIVILEGES;


-- SELECT * FROM Reward_Tier;
-- SELECT * FROM Venue;
-- SELECT * FROM Concert;
-- SELECT * FROM Customer;
-- SELECT * FROM Booking;
-- SELECT * FROM Ticket;
-- SELECT * FROM Employee;
