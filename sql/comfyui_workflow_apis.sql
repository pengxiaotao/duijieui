CREATE TABLE comfyui_workflow_apis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL
);