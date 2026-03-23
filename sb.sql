DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(20),
  password VARCHAR(20), 
  firstname VARCHAR(20),
  surname VARCHAR(20),
  email VARCHAR(255),
  role INT DEFAULT 0 
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS medical_data;
CREATE TABLE medical_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  blood_type VARCHAR(5),
  allergies TEXT,
  conditions TEXT,
  medications TEXT
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  subject VARCHAR(255),
  message TEXT
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user VARCHAR(128), 
  amount_cents INT,
  product VARCHAR(50)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS payment_details;
CREATE TABLE payment_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  card_number VARCHAR(16), 
  ccv INT,
  name VARCHAR(255),
  expire_month INT,
  expire_year INT
) ENGINE=MyISAM DEFAULT CHARSET=latin1;