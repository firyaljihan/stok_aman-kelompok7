INSERT IGNORE INTO users (username, password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT IGNORE INTO categories (name) VALUES
('Minuman'),
('Makanan Ringan');

INSERT IGNORE INTO suppliers (name, phone, address) VALUES
('CV Sumber Berkah', '081234567890', 'Jl. Raya Gudang No. 12');

INSERT IGNORE INTO products (sku, name, category_id, supplier_id, min_stock) VALUES
('SKU-001', 'Air Mineral Botol 600ml', 1, 1, 5),
('SKU-002', 'Keripik Singkong Original', 2, 1, 5);
