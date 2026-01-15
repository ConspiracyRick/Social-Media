CREATE DATABASE IF NOT EXISTS myspace_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE myspace_clone;

-- users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100),
  bio TEXT,
  profile_pic VARCHAR(255),
  bg_image VARCHAR(255),
  custom_css TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- friend requests (status: 0=pending,1=accepted,2=rejected)
CREATE TABLE friend_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  receiver_id INT NOT NULL,
  status TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (requester_id, receiver_id),
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- statuses (profile posts)
CREATE TABLE statuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  profile_user_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (profile_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- comments on statuses
CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- basic messages (inbox)
CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  subject VARCHAR(200),
  body TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- sample index user (optional)
INSERT INTO users (username,email,password_hash,display_name,bio) VALUES
('ricky','ricky@example.com', '$2y$10$abcdefghijklmnopqrstuv', 'Ricky', 'Welcome to RetroSpace!');
