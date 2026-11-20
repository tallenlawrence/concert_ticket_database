CREATE SCHEMA ConcertTicketDB;
USE ConcertTicketDB;


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
booking_id INT NOT NULL,
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





-- Auto Upgrades 
DELIMITER $$ 
CREATE TRIGGER auto_upgrade
AFTER INSERT ON Ticket
FOR EACH ROW
BEGIN
      DECLARE c_customer_if INT;
      DECLARE t_total INT;
 
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
END $$
DELIMITER ;


-- Check if payment is valid
DELIMITER $$
CREATE TRIGGER payment_validation
BEFORE INSERT ON Booking
FOR EACH ROW
BEGIN
	IF NEW.purchase_date is NULL THEN
		SET NEW.purchase_date = NOW();
	END IF;

	IF NEW.payment_method NOT IN('Card', 'CashApp', 'Paypal', 'Apple Pay') THEN 
		SIGNAL SQLSTATE '45000'
		SET MESSAGE_TEXT = 'Payment not supported.';
	END IF;
END$$
DELIMITER ;

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
GROUP BY  c.customer_id, c.name, rt.tier_name, rt.threshold;

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