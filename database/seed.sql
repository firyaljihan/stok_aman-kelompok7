INSERT IGNORE INTO users (username, password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT IGNORE INTO categories (name) VALUES
('Bahan Baku'),
('Aksesoris'),
('Produk Jadi');

INSERT IGNORE INTO suppliers (name, e-commerce, link) VALUES
('PT Liontin Cinta', 'Shopee', 'https://s.shopee.co.id/8pnD4Eemgr'),
('PT Hanan.Daily', 'Shopee', 'https://s.shopee.co.id/LoE483yBH');

INSERT IGNORE INTO products (sku, name, category_id, supplier_id, min_stock) VALUES
('SKU-28112620', 'Cat Plushie', 1, 1, 5),
('SKU-27112620', 'Ribbon Chain', 2, 1, 5);
