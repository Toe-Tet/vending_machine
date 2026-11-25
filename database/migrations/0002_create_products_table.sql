CREATE TABLE products (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name               VARCHAR(100) NOT NULL,
    price              DECIMAL(10,3) NOT NULL,
    quantity_available INT UNSIGNED NOT NULL,
    archived_at        DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT products_price_positive CHECK (price > 0),
    CONSTRAINT products_quantity_max CHECK (quantity_available <= 1000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
