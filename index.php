<?php
ob_start(); // Start output buffering

session_start();
$host = 'localhost';
$dbname = 'noire_luxe';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $result = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER status");
    }
    
    $result = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_address'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_address TEXT DEFAULT NULL AFTER payment_method");
    }
    
    $result = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'accessory' AFTER description");
        
        $pdo->exec("UPDATE products SET category = 'tshirt' WHERE name LIKE '%Tee%' OR name LIKE '%T-Shirt%'");
        $pdo->exec("UPDATE products SET category = 'hoodie' WHERE name LIKE '%Hoodie%'");
        $pdo->exec("UPDATE products SET category = 'pants' WHERE name LIKE '%Pants%' OR name LIKE '%Cargo%'");
    }
    
    $result = $pdo->query("SHOW TABLES LIKE 'ratings'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            rating INT NOT NULL,
            comment TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (product_id) REFERENCES products(id),
            UNIQUE KEY (user_product_rating (user_id, product_id))
        )");
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$login_error = '';
$register_error = '';
$success_message = '';
$contact_success = '';
$checkout_success = '';
$rating_error = '';
$review_error = '';
$rating_success = '';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_created_at'] = $user['created_at'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "Invalid credentials";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute([$name, $email, $password]);
        $success_message = "Registration successful! Please login.";
    } catch(PDOException $e) {
        $register_error = "Email already exists";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $size = isset($_POST['size']) ? $_POST['size'] : 'M';
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        $cart_key = $product_id . '_' . $size;
        
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'size' => $size
            ];
        }
        
        header('Location: ?page=shop&success=1');
        exit;
    }
}
if (isset($_GET['remove_cart'])) {
    $cart_key = $_GET['remove_cart'];
    unset($_SESSION['cart'][$cart_key]);
    header('Location: ?page=cart');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cart_key = $_POST['cart_key'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0 && isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
    }
    header('Location: ?page=cart');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact'])) {
    $name = $_POST['contact_name'];
    $email = $_POST['contact_email'];
    $message = $_POST['contact_message'];
    
    $stmt = $pdo->prepare("INSERT INTO contacts (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $message]);
    $contact_success = "Message sent successfully!";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_checkout'])) {
    $_SESSION['payment_method'] = $_POST['payment_method'];
    
    $_SESSION['delivery'] = [
        'name' => $_POST['delivery_name'],
        'phone' => $_POST['delivery_phone'],
        'address' => $_POST['delivery_address'],
        'city' => $_POST['delivery_city'],
        'postal' => $_POST['delivery_postal']
    ];
    
    header('Location: ?page=review');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?page=login');
        exit;
    }
    
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO ratings (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?, comment = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $comment, $rating, $comment]);
        $rating_success = "Rating submitted successfully!";
    } catch (PDOException $e) {
        $rating_error = "Error submitting rating.";
    }
    
    header('Location: ?page=product&id=' . $product_id);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && !empty($_SESSION['cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?page=login');
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ?page=login');
        exit;
    }
    
    if (!isset($_SESSION['payment_method']) || !isset($_SESSION['delivery'])) {
        header('Location: ?page=cart');
        exit;
    }
    
    $payment_method = $_SESSION['payment_method'];
    $delivery = $_SESSION['delivery'];
    $delivery_address = $delivery['address'] . ', ' . $delivery['city'] . ' ' . $delivery['postal'] . ', Phone: ' . $delivery['phone'];
    $total = 0;
    
    // Get sizes mapping
    $sizes = [];
    $stmt_sizes = $pdo->query("SELECT id, name FROM sizes");
    while ($row = $stmt_sizes->fetch()) {
        $sizes[$row['name']] = $row['id'];
    }
    
    // Prepare order items data
    $order_items_data = [];
    
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        $subtotal = $product['price'] * $item['quantity'];
        $total += $subtotal;
        
        $order_items_data[] = [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'size' => $item['size'],
            'price' => $product['price']
        ];
    }
    
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status, payment_method, delivery_address, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())");
    $stmt->execute([$user_id, $total, $payment_method, $delivery_address]);
    $order_id = $pdo->lastInsertId();
    
    // Insert order items with size_id instead of size
    foreach ($order_items_data as $item_data) {
        $size_id = isset($sizes[$item_data['size']]) ? $sizes[$item_data['size']] : 1; // fallback to id 1 if not found
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, size_id, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $item_data['product_id'], $item_data['quantity'], $size_id, $item_data['price']]);
    }
    
    $_SESSION['cart'] = [];
    unset($_SESSION['payment_method']);
    unset($_SESSION['delivery']);
    $checkout_success = "Order placed successfully! Order #$order_id";
    header('Location: ?page=profile&order_success=1');
    exit;
}
$page = isset($_GET['page']) ? $_GET['page'] : 'shop'; // Changed default to 'shop'
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && $_SESSION['user_role'] === 'admin';
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['quantity'];
}
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}
function getProductCategoryInfo($product) {
    $category = isset($product['category']) ? $product['category'] : 'accessory';
    
    $categoryClass = '';
    $categoryText = '';
    $categoryIcon = '';
    
    switch($category) {
        case 'tshirt':
            $categoryClass = 'category-tshirt';
            $categoryText = 'T-Shirt';
            $categoryIcon = 'fas fa-tshirt';
            break;
        case 'hoodie':
            $categoryClass = 'category-hoodie';
            $categoryText = 'Hoodie';
            $categoryIcon = 'fas fa-tshirt';
            break;
        case 'pants':
            $categoryClass = 'category-pants';
            $categoryText = 'Pants';
            $categoryIcon = 'fas fa-socks';
            break;
        default:
            $categoryClass = 'category-accessory';
            $categoryText = 'Accessory';
            $categoryIcon = 'fas fa-gem';
    }
    
    return [
        'class' => $categoryClass,
        'text' => $categoryText,
        'icon' => $categoryIcon
    ];
}

// Enhanced settings page functions
function renderSettingsPage($page, $isLoggedIn) {
    if (!$isLoggedIn) {
        header('Location: ?page=login');
        exit;
    }
    
    $settings_pages = [
        'settings' => [
            'title' => 'Settings',
            'icon' => 'fas fa-cog',
            'description' => 'Manage your account preferences and settings'
        ],
        'account_settings' => [
            'title' => 'Account Settings',
            'icon' => 'fas fa-user-cog',
            'description' => 'Update your profile information and security settings'
        ],
        'notification_settings' => [
            'title' => 'Notification Preferences',
            'icon' => 'fas fa-bell',
            'description' => 'Configure how you receive notifications'
        ],
        'address_settings' => [
            'title' => 'Address Book',
            'icon' => 'fas fa-map-marker-alt',
            'description' => 'Manage your shipping and billing addresses'
        ],
        'privacy_settings' => [
            'title' => 'Privacy & Security',
            'icon' => 'fas fa-shield-alt',
            'description' => 'Control your privacy settings and security options'
        ],
        'about' => [
            'title' => 'About Us',
            'icon' => 'fas fa-info-circle',
            'description' => 'Learn more about Noiré Luxe'
        ],
        'contact' => [
            'title' => 'Contact Us',
            'icon' => 'fas fa-envelope',
            'description' => 'Get in touch with our team'
        ]
    ];
    
    $current_page = $settings_pages[$page] ?? $settings_pages['settings'];
    ?>
    <section style="padding: 3rem 0;">        
        <div class="settings-container">
            <div class="settings-sidebar">
                <div class="settings-card">
                    <h4><i class="fas fa-user"></i> Account Summary</h4>
                    <div class="account-info">
                        <div class="account-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="account-details">
                            <h5><?= htmlspecialchars($_SESSION['user_name']) ?></h5>
                            <p><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                            <small>Member since <?= date('M j, Y', strtotime($_SESSION['user_created_at'])) ?></small>
                        </div>
                    </div>
                </div>

                <div class="settings-nav">
                    <h3><i class="fas fa-cog"></i> Settings</h3>
                    <ul class="settings-menu">
                        <li>
                            <a href="?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">
                                <i class="fas fa-home"></i> Overview
                            </a>
                        </li>
                        <li>
                            <a href="?page=account_settings" class="<?= $page === 'account_settings' ? 'active' : '' ?>">
                                <i class="fas fa-user"></i> Account
                            </a>
                        </li>
                        <li>
                            <a href="?page=notification_settings" class="<?= $page === 'notification_settings' ? 'active' : '' ?>">
                                <i class="fas fa-bell"></i> Notifications
                            </a>
                        </li>
                        <li>
                            <a href="?page=address_settings" class="<?= $page === 'address_settings' ? 'active' : '' ?>">
                                <i class="fas fa-map-marker-alt"></i> Addresses
                            </a>
                        </li>
                        <li>
                            <a href="?page=privacy_settings" class="<?= $page === 'privacy_settings' ? 'active' : '' ?>">
                                <i class="fas fa-shield-alt"></i> Privacy
                            </a>
                        </li>
                        <li>
                            <a href="?page=about&settings=1" class="<?= $page === 'about' ? 'active' : '' ?>">
                                <i class="fas fa-info-circle"></i> About
                            </a>
                        </li>
                        <li>
                            <a href="?page=contact&settings=1" class="<?= $page === 'contact' ? 'active' : '' ?>">
                                <i class="fas fa-envelope"></i> Contact
                            </a>
                        </li>
                    </ul>
                    <ul class="quick-links">
                        <li><a href="?page=profile"><i class="fas fa-user"></i> My Profile</a></li>
                        <li><a href="?page=orders"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                        <li><a href="?page=cart"><i class="fas fa-shopping-cart"></i> Shopping Cart</a></li>
                        <li><a href="?page=reviews"><i class="fas fa-star"></i> My Reviews</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="settings-content">
                <?php
                switch ($page) {
                    case 'settings':
                        renderSettingsOverview();
                        break;
                    case 'account_settings':
                        renderAccountSettings();
                        break;
                    case 'notification_settings':
                        renderNotificationSettings();
                        break;
                    case 'address_settings':
                        renderAddressSettings();
                        break;
                    case 'privacy_settings':
                        renderPrivacySettings();
                        break;
                    case 'about':
                        renderSettingsAbout();
                        break;
                    case 'contact':
                        renderSettingsContact();
                        break;
                    default:
                        renderSettingsOverview();
                }
                ?>
            </div>
        </div>
    </section>
    <?php
}

function renderSettingsOverview() {
    ?>
    <div class="settings-grid">
        <div class="settings-card">
            <div class="settings-card-icon">
                <i class="fas fa-user-cog"></i>
            </div>
            <h3>Account Settings</h3>
            <p>Manage your profile information, security, and preferences.</p>
            <a href="?page=account_settings" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Manage Account
            </a>
        </div>
        
        <div class="settings-card">
            <div class="settings-card-icon">
                <i class="fas fa-bell"></i>
            </div>
            <h3>Notification Preferences</h3>
            <p>Configure how you want to receive notifications.</p>
            <a href="?page=notification_settings" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Manage Notifications
            </a>
        </div>
        
        <div class="settings-card">
            <div class="settings-card-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3>Address Book</h3>
            <p>Manage your shipping and billing addresses.</p>
            <a href="?page=address_settings" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Manage Addresses
            </a>
        </div>
        
        <div class="settings-card">
            <div class="settings-card-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3>Privacy & Security</h3>
            <p>Control your privacy settings and security options.</p>
            <a href="?page=privacy_settings" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Manage Privacy
            </a>
        </div>
    </div>
    <?php
}

function renderAccountSettings() {
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-user"></i> Profile Information</h3>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" value="<?= htmlspecialchars($_SESSION['user_email']) ?>" readonly>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Member Since</label>
                <input type="text" value="<?= date('F j, Y', strtotime($_SESSION['user_created_at'])) ?>" readonly>
            </div>
        </form>
    </div>
    
    <div class="settings-form">
        <h3><i class="fas fa-lock"></i> Account Security</h3>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password">
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password">
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Password
            </button>
        </form>
    </div>
    <?php
}

function renderNotificationSettings() {
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
        <form method="POST">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    <span class="checkmark"></span>
                    Email Notifications
                </label>
                <p class="help-text">Receive order updates and promotions via email</p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    <span class="checkmark"></span>
                    SMS Notifications
                </label>
                <p class="help-text">Get important alerts via text message</p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox">
                    <span class="checkmark"></span>
                    Promotional Offers
                </label>
                <p class="help-text">Receive special offers and discounts</p>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Preferences
            </button>
        </form>
    </div>
    <?php
}

function renderAddressSettings() {
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-map-marker-alt"></i> Default Address</h3>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" value="<?= htmlspecialchars($_SESSION['user_name']) ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" placeholder="Enter phone number">
            </div>
            <div class="form-group">
                <label><i class="fas fa-home"></i> Address</label>
                <textarea rows="3" placeholder="Enter your address"></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-city"></i> City</label>
                <input type="text" placeholder="Enter city">
            </div>
            <div class="form-group">
                <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                <input type="text" placeholder="Enter postal code">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Address
            </button>
        </form>
    </div>
    <?php
}

function renderPrivacySettings() {
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-shield-alt"></i> Privacy Settings</h3>
        <form method="POST">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    <span class="checkmark"></span>
                    Allow others to see my profile
                </label>
                <p class="help-text">Make your profile visible to other users</p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox">
                    <span class="checkmark"></span>
                    Show my activity status
                </label>
                <p class="help-text">Display when you're active on the site</p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    <span class="checkmark"></span>
                    Allow personalized recommendations
                </label>
                <p class="help-text">Get product suggestions based on your preferences</p>
            </div>
            <div class="form-group">
                <label><i class="fas fa-database"></i> Data Sharing Preferences</label>
                <select>
                    <option>Share only necessary data</option>
                    <option>Share all data for better experience</option>
                    <option>Don't share any data</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Privacy Settings
            </button>
        </form>
    </div>
    <?php
}

function renderSettingsAbout() {
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-info-circle"></i> About Noiré Luxe</h3>
        <div class="about-content">
            <div class="about-image">
                <img src="https://images.unsplash.com/photo-1543466835-00a7fe2fe683?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="About Noiré Luxe">
            </div>
            <div class="about-text">
                <p>Noiré Luxe is a premium streetwear brand founded in 2020 with a mission to create high-quality, stylish apparel that empowers Filipinos to express their unique identity.</p>
                <p>Our collections feature meticulously crafted t-shirts, hoodies, and pants made from premium materials that combine comfort with cutting-edge design tailored for the Philippine climate.</p>
                <p>At Noiré Luxe, we believe fashion is more than just clothing—it's a statement of self-expression and confidence for the modern Filipino.</p>
                
                <div class="about-stats">
                    <div class="stat-item">
                        <i class="fas fa-tshirt"></i>
                        <div class="stat-number">50+</div>
                        <p>Unique Designs</p>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="stat-number">15+</div>
                        <p>Philippine Cities</p>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-users"></i>
                        <div class="stat-number">10K+</div>
                        <p>Satisfied Customers</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderSettingsContact() {
    global $contact_success;
    ?>
    <div class="settings-form">
        <h3><i class="fas fa-envelope"></i> Contact Us</h3>
        
        <?php if (!empty($contact_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $contact_success ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Name</label>
                <input type="text" name="contact_name" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="contact_email" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-comment"></i> Message</label>
                <textarea name="contact_message" rows="5" required></textarea>
            </div>
            <button type="submit" name="contact" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-paper-plane"></i> Send Message
            </button>
        </form>
        
        <div class="contact-info" style="margin-top: 2rem;">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-text">
                    <h3>Our Location</h3>
                    <p>123 BGC Street<br>Bonifacio Global City, Taguig<br>1634 Metro Manila</p>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="info-text">
                    <h3>Call Us</h3>
                    <p>+63 (2) 8123 4567<br>+63 (917) 890 1234</p>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-text">
                    <h3>Email Us</h3>
                    <p>support@noireluxe.com.ph<br>info@noireluxe.com.ph</p>
                </div>
            </div>
            
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-tiktok"></i></a>
                <a href="#"><i class="fab fa-viber"></i></a>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noiré Luxe - Premium Streetwear & Apparel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1a1a;
            --secondary: #2d1b3d;
            --accent: #8a2be2;
            --light-accent: #da70d6;
            --text: #ffffff;
            --light-text: rgba(255, 255, 255, 0.8);
            --card-bg: rgba(255, 255, 255, 0.05);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0d0d0d 0%, #1a0f1f 50%, #0d0d0d 100%);
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(15px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid rgba(218, 112, 214, 0.2);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }
        
        .logo {
            font-size: 2.2rem;
            font-weight: bold;
            background: linear-gradient(45deg, #8a2be2, #da70d6, #dda0dd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(138, 43, 226, 0.5);
        }
        
        .search-bar {
            flex: 1;
            max-width: 400px;
            margin: 0 20px;
        }

        .search-bar form {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 5px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .search-bar form:focus-within {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(138, 43, 226, 0.5);
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
        }

        .search-bar input {
            flex: 1;
            background: none;
            border: none;
            color: white;
            padding: 8px 10px;
            outline: none;
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-bar button {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .search-bar button:hover {
            color: #da70d6;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 0.8rem 1.2rem;
            border-radius: 30px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links a:hover {
            background: rgba(138, 43, 226, 0.2);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn {
            padding: 0.8rem 1.8rem;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #8a2be2, #da70d6);
            color: white;
            box-shadow: 0 4px 15px rgba(138, 43, 226, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .btn-secondary {
            background: transparent;
            color: #8a2be2;
            border: 2px solid #8a2be2;
        }
        
        .btn-secondary:hover {
            background: #8a2be2;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .btn-add-cart {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-add-cart:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: rgba(0, 0, 0, 0.95);
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 10px;
            margin-top: 10px;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(218, 112, 214, 0.2);
        }
        
        .dropdown-content a {
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            border-radius: 0;
            margin: 0;
        }
        
        .dropdown-content a:hover {
            background-color: rgba(138, 43, 226, 0.2);
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .main {
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }
        
        .hero {
            padding: 5rem 0;
            text-align: center;
            background: radial-gradient(circle at center, rgba(138, 43, 226, 0.1) 0%, transparent 70%);
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://images.unsplash.com/photo-1515886658914-4577d2bb0e43?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80');
            background-size: cover;
            background-position: center;
            opacity: 0.2;
            z-index: -1;
        }
        
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--text), var(--light-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 3rem 0;
        }
        
        .product-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(138, 43, 226, 0.2);
        }
        
        .product-image {
            height: 300px;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2c2c2c, #404040);
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-tshirt {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        
        .category-hoodie {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
        }
        
        .category-pants {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }
        
        .category-accessory {
            background: linear-gradient(45deg, #f39c12, #d35400);
        }
        
        .product-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-title {
            size: 0.1rem;
            margin-bottom: 0.5rem;
            color: var(--light-accent);
        }
        
        .product-category {
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 15;
            font-weight: 600;
            margin: 0.5rem 0;
            color: var(--text);
        }
        
        .product-description {
            color: var(--light-text);
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .rating {
            color: gold;
        }
        
        .collections {
            padding: 3rem 0;
            text-align: center;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, var(--text), var(--light-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }
        
        .collection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .collection-card {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            height: 250px;
            transition: all 0.3s ease;
        }
        
        .collection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(138, 43, 226, 0.3);
        }
        
        .collection-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .collection-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            transition: all 0.3s ease;
        }
        
        .collection-card:hover .collection-overlay {
            padding-bottom: 2rem;
        }
        
        .collection-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .about {
            padding: 3rem 0;
            background: rgba(0,0,0,0.2);
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        
        .about-image {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .about-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--light-accent);
        }
        
        .about-text p {
            margin-bottom: 1.5rem;
            color: var(--light-text);
        }
        
        .about-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: 15px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .contact {
            padding: 3rem 0;
        }
        
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        
        .contact-form {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .form-container {
            display: flex;
            flex-direction: column;
            max-width: 500px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 2.5rem;
        }
        
        .form-containers {
            display: flex;
            flex-direction: column;
            max-width: 1000px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 2.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #da70d6;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .contact-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .info-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .info-icon {
            font-size: 1.5rem;
            color: var(--accent);
            margin-right: 1rem;
            width: 50px;
            height: 50px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .info-text h3 {
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
            color: var(--light-accent);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            color: var(--text);
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--accent);
            transform: translateY(-3px);
        }
        
        .dashboard {
            padding: 2rem 0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(138, 43, 226, 0.2);
        }
        
        .stat-card i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab.active:hover, .tab:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }
        
        .tab {
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 15px;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab.active {
            background: #8a2be2;
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.4);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2c2c2c, #404040);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a2be2;
            font-size: 2rem;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-info h4 {
            color: #da70d6;
            margin-bottom: 0.5rem;
        }
        
        .cart-item-info p {
            margin: 0.2rem 0;
        }
        
        .cart-total {
            text-align: right;
            font-size: 1.5rem;
            font-weight: bold;
            color: #8a2be2;
            padding: 1rem;
            background: rgba(138, 43, 226, 0.1);
            border-radius: 15px;
            margin-top: 1rem;
        }
        
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-top: 1rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option:hover {
            background: rgba(138, 43, 226, 0.2);
        }
        
        .payment-option input[type="radio"] {
            margin-right: 1rem;
            width: 18px;
            height: 18px;
            accent-color: #8a2be2;
        }
        
        .payment-option i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: #8a2be2;
            width: 30px;
            text-align: center;
        }
        
        .payment-option.selected {
            background: rgba(138, 43, 226, 0.3);
            border: 1px solid #8a2be2;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .checkout-column {
            display: flex;
            flex-direction: column;
        }
        
        .order-summary {
            background: rgba(138, 43, 226, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.2rem;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(218, 112, 214, 0.3);
            color: #da70d6;
        }
        
        .checkout-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .checkout-buttons .btn {
            flex: 1;
            max-width: 250px;
        }
        
        .table {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table th {
            background: rgba(138, 43, 226, 0.2);
            color: #da70d6;
            font-weight: 600;
        }
        
        .table tr:hover {
            background: rgba(138, 43, 226, 0.1);
        }
        
        .order-details {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .order-details h3 {
            color: #da70d6;
            margin-bottom: 1rem;
        }
        
        .rating-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .rating-form h4 {
            color: #da70d6;
            margin-bottom: 1rem;
        }
        
        .star-rating {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .star-rating i {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .star-rating i:hover,
        .star-rating i.active {
            color: gold;
        }
        
        .product-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        .product-image-container {
            background: var(--card-bg);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image-placeholder {
            font-size: 8rem;
            color: #8a2be2;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
        }
        
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--light-accent);
        }
        
        .product-price {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text);
        }
        
        .product-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--light-text);
            margin-bottom: 2rem;
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(138, 43, 226, 0.1);
            border-radius: 15px;
        }
        
        .rating-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .rating-stars {
            color: gold;
            font-size: 1.5rem;
        }
        
        .rating-count {
            color: var(--light-text);
        }
        
        .star-rating-input {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .star-rating-input i {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .star-rating-input i:hover,
        .star-rating-input i.active {
            color: gold;
        }
        
        .reviews-section {
            margin-top: 3rem;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .reviews-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--light-accent);
        }
        
        .review-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(138, 43, 226, 0.2);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .review-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .review-avatar {
            width: 40px;
            height: 40px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a2be2;
        }
        
        .review-user-info h4 {
            margin: 0;
            color: #da70d6;
            font-size: 1.1rem;
        }
        
        .review-user-info p {
            margin: 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .review-rating {
            color: gold;
            font-size: 1.2rem;
        }
        
        .review-body {
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .no-reviews {
            text-align: center;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }
        
        .no-reviews i {
            font-size: 3rem;
            color: #8a2be2;
            margin-bottom: 1rem;
        }
        
        .no-reviews h3 {
            margin-bottom: 1rem;
            color: #da70d6;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .settings-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(138, 43, 226, 0.2);
        }
        
        .settings-section h3 {
            color: #da70d6;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .settings-section p {
            color: var(--light-text);
            margin-bottom: 1.5rem;
        }
        
        .settings-section .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer {
            background: rgba(0, 0, 0, 0.9);
            padding: 3rem 0 1rem;
            margin-top: 5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            color: var(--light-accent);
            margin-bottom: 1rem;
            font-family: 'Playfair Display', serif;
        }
        
        .footer-section p,
        .footer-section a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            line-height: 1.6;
        }
        
        .footer-section a:hover {
            color: var(--accent);
        }
        
        .category-filter {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .category-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .category-btn:hover {
            background: rgba(138, 43, 226, 0.2);
            transform: translateY(-3px);
        }
        
        .category-btn.active {
            background: #8a2be2;
            border-color: #8a2be2;
        }
        
        /* Settings Page Styles */
        .settings-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .settings-description {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .settings-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .settings-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .settings-nav {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-nav h3 {
            color: #da70d6;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .settings-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .settings-menu li {
            margin-bottom: 0.5rem;
        }
        
        .settings-menu a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .settings-menu a:hover {
            background: rgba(138, 43, 226, 0.2);
            color: #fff;
            transform: translateX(5px);
        }
        
        .settings-menu a.active {
            background: rgba(138, 43, 226, 0.3);
            color: #da70d6;
            border-left: 3px solid #da70d6;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-card h4 {
            color: #da70d6;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .account-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .account-avatar {
            width: 50px;
            height: 50px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a2be2;
            font-size: 1.2rem;
        }
        
        .account-details h5 {
            margin: 0;
            color: #fff;
            font-size: 1rem;
        }
        
        .account-details p {
            margin: 0.2rem 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .account-details small {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .settings-content {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: fit-content;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(138, 43, 226, 0.2);
            background: rgba(138, 43, 226, 0.1);
        }
        
        .settings-card-icon {
            width: 60px;
            height: 60px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: #8a2be2;
            font-size: 1.5rem;
        }
        
        .settings-card h3 {
            color: #da70d6;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .settings-card p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .settings-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-form h3 {
            color: #da70d6;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #8a2be2;
        }
        
        .help-text {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            margin-top: 0.3rem;
            margin-left: 2rem;
        }
        
        .settings-activity {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-activity h3 {
            color: #da70d6;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: rgba(138, 43, 226, 0.1);
            transform: translateX(5px);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(138, 43, 226, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a2be2;
        }
        
        .activity-details h4 {
            margin: 0;
            color: #fff;
            font-size: 1rem;
        }
        
        .activity-details p {
            margin: 0.2rem 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
        }
        
        .activity-time {
            margin-left: auto;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
        }
        
        .quick-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .quick-links li {
            margin-bottom: 0.8rem;
        }
        
        .quick-links a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .quick-links a:hover {
            background: rgba(138, 43, 226, 0.2);
            color: #fff;
            transform: translateX(5px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a1a, #2d1b3d);
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(138, 43, 226, 0.3);
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }
        
        .modal-header {
            background: rgba(138, 43, 226, 0.2);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #da70d6;
            font-size: 1.5rem;
        }
        
        .close {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .modal-footer .btn {
            padding: 0.6rem 1.5rem;
        }
        
        /* Confirmation Modal Styles */
        .confirm-modal .modal-content {
            max-width: 400px;
        }
        
        .confirm-modal .modal-body {
            text-align: center;
            padding: 2rem;
        }
        
        .confirm-modal .modal-body i {
            font-size: 3rem;
            color: #ff4757;
            margin-bottom: 1rem;
        }
        
        .confirm-modal .modal-body h3 {
            margin-bottom: 1rem;
            color: #ffffff;
        }
        
        .confirm-modal .modal-body p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1.5rem;
        }
        
        .confirm-modal .modal-footer {
            justify-content: center;
            gap: 1rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .about-content {
                grid-template-columns: 1fr;
            }
            
            .contact-container {
                grid-template-columns: 1fr;
            }
            
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .checkout-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .checkout-buttons .btn {
                max-width: 100%;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .product-container {
                grid-template-columns: 1fr;
            }
            
            .product-image-container {
                height: 300px;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .rating-form {
                padding: 1.5rem;
            }
            
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                position: static;
            }
            
            .settings-nav {
                margin-bottom: 1rem;
            }
            
            .settings-menu {
                display: flex;
                overflow-x: auto;
                gap: 0.5rem;
                padding-bottom: 0.5rem;
            }
            
            .settings-menu li {
                margin-bottom: 0;
                flex-shrink: 0;
            }
            
            .settings-menu a {
                white-space: nowrap;
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .account-info {
                flex-direction: column;
                text-align: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .animate-glow {
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(138, 43, 226, 0.3); }
            to { box-shadow: 0 0 30px rgba(138, 43, 226, 0.6); }
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #f44336;
        }
        
        .login-btn {
            background: linear-gradient(45deg, #8a2be2, #da70d6);
            padding: 8px 20px !important;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
        }

        .user-dropdown {
            position: relative;
        }

        .user-menu-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 10px;
            padding: 10px 0;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(138, 43, 226, 0.2);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .user-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: rgba(138, 43, 226, 0.2);
            color: #da70d6;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 10px;
            padding: 15px 20px;
            min-width: 300px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.7s forwards;
            opacity: 0;
            transform: translateX(100%);
        }

        @keyframes slideInRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        .toast-success {
            border-left-color: #2ecc71;
        }

        .toast-error {
            border-left-color: #e74c3c;
        }

        .toast-info {
            border-left-color: #3498db;
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast-icon {
            font-size: 1.2rem;
        }

        .toast-success .toast-icon {
            color: #2ecc71;
        }

        .toast-error .toast-icon {
            color: #e74c3c;
        }

        .toast-info .toast-icon {
            color: #3498db;
        }

        .toast-message {
            color: white;
            font-weight: 500;
        }

        .toast-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .toast-close:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div id="toast-container" class="toast-container"></div>
    
    <div id="authModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="authModalTitle">Login / Register</h2>
                <span class="close" onclick="closeAuthModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="tabs" style="justify-content: center;">
                    <button class="tab active" onclick="showAuthTab('login-tab')">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <button class="tab" onclick="showAuthTab('register-tab')">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </div>
                
                <div id="login-tab" class="tab-content active">
                    <?php if (!empty($login_error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $login_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                </div>
                
                 <div id="register-tab" class="tab-content">
                    <?php if (!empty($register_error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $register_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div id="logoutModal" class="modal confirm-modal">
        <div class="modal-content">
            <div class="modal-body">
                <i class="fas fa-sign-out-alt"></i>
                <h3>Confirm Logout</h3>
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeLogoutModal()">Cancel</button>
                <a href="?logout=1" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </div>
    
    <header class="header">
        <div class="container">
            <nav class="nav">
                <div class="logo">
                    <i class="fas fa-tshirt"></i> Noiré Luxe
                </div>
                
                <div class="search-bar">
                    <form method="GET" action="?page=shop">
                        <input type="hidden" name="page" value="shop">
                        <input type="text" name="search" placeholder="Search products...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <ul class="nav-links">
                    <li><a href="?page=shop"><i class="fas fa-tshirt"></i> Shop</a></li>
                    <li>
                        <a href="?page=cart" style="position: relative;">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?= $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="?page=settings"><i class="fas fa-cog"></i> Settings</a></li>
                    <?php else: ?>
                        <li><a href="#" onclick="openAuthModal()" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main">
        <div class="container">
            <?php
            if ($page === 'settings' || $page === 'account_settings' || $page === 'notification_settings' || 
                $page === 'address_settings' || $page === 'privacy_settings' || $page === 'about' || $page === 'contact') {
                renderSettingsPage($page, $isLoggedIn);
            }
            else {
                switch ($page) {
                    case 'shop':
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-tshirt"></i> Shop Our Collection
                    </h2>
                    
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Item added to cart successfully!
                    </div>
                    <?php endif; ?>
                    
                    <div class="category-filter">
                        <button class="category-btn <?= !isset($_GET['category']) ? 'active' : '' ?>" onclick="window.location.href='?page=shop'">
                            <i class="fas fa-th"></i> All Products
                        </button>
                        <button class="category-btn <?= isset($_GET['category']) && $_GET['category'] === 'tshirt' ? 'active' : '' ?>" onclick="window.location.href='?page=shop&category=tshirt'">
                            <i class="fas fa-tshirt"></i> T-Shirts
                        </button>
                        <button class="category-btn <?= isset($_GET['category']) && $_GET['category'] === 'hoodie' ? 'active' : '' ?>" onclick="window.location.href='?page=shop&category=hoodie'">
                            <i class="fas fa-tshirt"></i> Hoodies
                        </button>
                        <button class="category-btn <?= isset($_GET['category']) && $_GET['category'] === 'pants' ? 'active' : '' ?>" onclick="window.location.href='?page=shop&category=pants'">
                            <i class="fas fa-socks"></i> Pants
                        </button>
                    </div>
                    
                    <div class="products">
                        <?php
                        // Handle search functionality
                        $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
                        
                        // Build the SQL query based on search and category
                        $sql = "SELECT * FROM products";
                        $params = [];
                        
                        if (!empty($search_query) || isset($_GET['category'])) {
                            $sql .= " WHERE";
                            
                            if (!empty($search_query)) {
                                $sql .= " (name LIKE ? OR description LIKE ?)";
                                $params[] = "%{$search_query}%";
                                $params[] = "%{$search_query}%";
                            }
                            
                            if (isset($_GET['category']) && in_array($_GET['category'], ['tshirt', 'hoodie', 'pants'])) {
                                if (!empty($search_query)) $sql .= " AND";
                                $sql .= " category = ?";
                                $params[] = $_GET['category'];
                            }
                        }
                        
                        $sql .= " ORDER BY created_at DESC";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        while ($product = $stmt->fetch()):
                            $stmt_rating = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM ratings WHERE product_id = ?");
                            $stmt_rating->execute([$product['id']]);
                            $rating_data = $stmt_rating->fetch();
                            $avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
                            
                            $categoryInfo = getProductCategoryInfo($product);
                        ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php else: ?>
                                    <i class="<?= $categoryInfo['icon'] ?>" style="font-size: 4rem; color: #8a2be2;"></i>
                                <?php endif; ?>
                                <?php if ($product['featured']): ?>
                                <div class="product-badge">Featured</div>
                                <?php endif; ?>
                                <div class="product-badge <?= $categoryInfo['class'] ?>" style="top: 1rem; left: 1rem; right: auto;">
                                    <?= $categoryInfo['text'] ?>
                                </div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="product-price"><?= formatPrice($product['price']) ?></p>
                                <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                                <div class="product-meta">
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= round($avg_rating)): ?>
                                                <i class="fas fa-star"></i>
                                            <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                                <i class="fas fa-star-half-alt"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span>(<?= $avg_rating > 0 ? $avg_rating : 'New' ?>)</span>
                                    </div>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <?php if (in_array($categoryInfo['text'], ['T-Shirt', 'Hoodie', 'Pants'])): ?>
                                        <select name="size" style="width: 100%; padding: 0.5rem; border-radius: 10px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); margin-bottom: 0.5rem;">
                                            <option value="XS">XS</option>
                                            <option value="S">S</option>
                                            <option value="M" selected>M</option>
                                            <option value="L">L</option>
                                            <option value="XL">XL</option>
                                            <option value="XXL">XXL</option>
                                        </select>
                                        <?php endif; ?>
                                        <button type="submit" name="add_to_cart" class="btn btn-add-cart">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php
                    break;
                    case 'product':
                        $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch();
                        if (!$product) {
                            die("Product not found");
                        }
                        $stmt = $pdo->prepare("
                            SELECT r.*, u.name as user_name 
                            FROM ratings r
                            JOIN users u ON r.user_id = u.id
                            WHERE r.product_id = ?
                            ORDER BY r.created_at DESC
                        ");
                        $stmt->execute([$product_id]);
                        $ratings = $stmt->fetchAll();
                        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                        $rating_stats = $stmt->fetch();
                        $avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
                        $total_ratings = $rating_stats['total_ratings'];
                        $user_rating = null;
                        if ($isLoggedIn) {
                            $stmt = $pdo->prepare("SELECT * FROM ratings WHERE user_id = ? AND product_id = ?");
                            $stmt->execute([$_SESSION['user_id'], $product_id]);
                            $user_rating = $stmt->fetch();
                        }
                        
                        $categoryInfo = getProductCategoryInfo($product);
            ?>
                <section style="padding: 3rem 0;">
                    <div class="product-container">
                        <div class="product-image-container">
                            <?php if (!empty($product['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                            <?php else: ?>
                                <i class="<?= $categoryInfo['icon'] ?> product-image-placeholder"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category">
                                <span class="product-badge <?= $categoryInfo['class'] ?>"><?= $categoryInfo['text'] ?></span>
                            </div>
                            <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-price"><?= formatPrice($product['price']) ?></div>
                            
                            <div class="rating-summary">
                                <div class="rating-number"><?= $avg_rating ?></div>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= round($avg_rating)): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-count">(<?= $total_ratings ?> reviews)</div>
                            </div>
                            
                            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <?php if (in_array($categoryInfo['text'], ['T-Shirt', 'Hoodie', 'Pants'])): ?>
                                <select name="size" style="width: 100%; padding: 0.5rem; border-radius: 10px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); margin-bottom: 0.5rem;">
                                    <option value="XS">XS</option>
                                    <option value="S">S</option>
                                    <option value="M" selected>M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                </select>
                                <?php endif; ?>
                                <button type="submit" name="add_to_cart" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                    <div class="rating-form">
                        <h3><i class="fas fa-star"></i> Rate This Product</h3>
                        
                        <?php if (!empty($rating_success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= $rating_success ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($rating_error)): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i> <?= $rating_error ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$user_rating): ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Your Rating</label>
                                <div class="star-rating-input" id="starRating">
                                    <i class="far fa-star" data-rating="1"></i>
                                    <i class="far fa-star" data-rating="2"></i>
                                    <i class="far fa-star" data-rating="3"></i>
                                    <i class="far fa-star" data-rating="4"></i>
                                    <i class="far fa-star" data-rating="5"></i>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Your Review (Optional)</label>
                                <textarea name="comment" placeholder="Share your experience with this product..."></textarea>
                            </div>
                            
                            <button type="submit" name="submit_rating" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Review
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-success">
                            You have already rated this product: <?= $user_rating['rating'] ?> stars
                            <?php if ($user_rating['comment']): ?>
                            <br><strong>Your review:</strong> <?= htmlspecialchars($user_rating['comment']) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="rating-form">
                        <h3><i class="fas fa-star"></i> Rate This Product</h3>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> You must be <a href="#" onclick="openAuthModal()">logged in</a> to rate products.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="reviews-section">
                        <div class="reviews-header">
                            <h2><i class="fas fa-comments"></i> Customer Reviews</h2>
                            <span><?= count($ratings) ?> reviews</span>
                        </div>
                        
                        <?php if (count($ratings) > 0): ?>
                            <?php foreach ($ratings as $rating): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-user">
                                        <div class="review-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="review-user-info">
                                            <h4><?= htmlspecialchars($rating['user_name']) ?></h4>
                                            <p><?= date('F j, Y', strtotime($rating['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $rating['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-body">
                                    <?php if (!empty($rating['comment'])): ?>
                                        <p><?= htmlspecialchars($rating['comment']) ?></p>
                                    <?php else: ?>
                                        <p style="color: rgba(255, 255, 255, 0.6); font-style: italic;">No comment provided</p>
                                    <?php endif; ?>
                                </div>
                                <div class="review-footer">
                                    <span>Verified Purchase</span>
                                    <span><?= $rating['rating'] ?>/5 stars</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-reviews">
                                <i class="fas fa-comments"></i>
                                <h3>No Reviews Yet</h3>
                                <p>Be the first to share your experience with this product!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <script>
                    const stars = document.querySelectorAll('.star-rating-input i');
                    const ratingValue = document.getElementById('ratingValue');
                    
                    stars.forEach(star => {
                        star.addEventListener('click', function() {
                            const rating = this.getAttribute('data-rating');
                            ratingValue.value = rating;
                            
                            stars.forEach((s, index) => {
                                if (index < rating) {
                                    s.classList.remove('far');
                                    s.classList.add('fas');
                                } else {
                                    s.classList.remove('fas');
                                    s.classList.add('far');
                                }
                            });
                        });
                        
                        star.addEventListener('mouseover', function() {
                            const rating = this.getAttribute('data-rating');
                            
                            stars.forEach((s, index) => {
                                if (index < rating) {
                                    s.classList.add('active');
                                } else {
                                    s.classList.remove('active');
                                }
                            });
                        });
                    });
                    
                    document.querySelector('.star-rating-input').addEventListener('mouseleave', function() {
                        const currentRating = ratingValue.value;
                        
                        stars.forEach((s, index) => {
                            if (index < currentRating) {
                                s.classList.remove('far');
                                s.classList.add('fas');
                            } else {
                                s.classList.remove('fas');
                                s.classList.add('far');
                            }
                            s.classList.remove('active');
                        });
                    });
                </script>
            <?php
                    break;
                    case 'cart':
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i> Shopping Cart
                    </h2>
                    
                    <?php if (!empty($checkout_success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $checkout_success ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($_SESSION['cart'])): ?>
                        <div style="text-align: center; padding: 3rem;">
                            <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #8a2be2; margin-bottom: 1rem;"></i>
                            <h3>Your cart is empty</h3>
                            <p style="margin-bottom: 2rem;">Add some amazing products to get started!</p>
                            <a href="?page=shop" class="btn btn-primary">
                                <i class="fas fa-shopping-bag"></i> Continue Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="max-width: 800px; margin: 0 auto;">
                            <?php
                            $total = 0;
                            foreach ($_SESSION['cart'] as $cart_key => $item):
                                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                                $stmt->execute([$item['product_id']]);
                                $product = $stmt->fetch();
                                $subtotal = $product['price'] * $item['quantity'];
                                $total += $subtotal;
                                
                                $categoryInfo = getProductCategoryInfo($product);
                            ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                                    <?php else: ?>
                                        <i class="<?= $categoryInfo['icon'] ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="cart-item-info">
                                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                                    <p>Price: <?= formatPrice($product['price']) ?></p>
                                    <?php if (in_array($categoryInfo['text'], ['T-Shirt', 'Hoodie', 'Pants'])): ?>
                                        <p>Size: <?= $item['size'] ?></p>
                                    <?php endif; ?>
                                    <p>Quantity: 
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="cart_key" value="<?= $cart_key ?>">
                                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" onchange="this.form.submit()" style="width: 60px; padding: 0.3rem; border-radius: 5px; margin: 0 0.5rem;">
                                            <input type="hidden" name="update_cart" value="1">
                                        </form>
                                    </p>
                                    <p><strong>Subtotal: <?= formatPrice($subtotal) ?></strong></p>
                                </div>
                                <div>
                                    <a href="?remove_cart=<?= $cart_key ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                                        <i class="fas fa-trash"></i> Remove
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="cart-total">
                                <h3>Total: <?= formatPrice($total) ?></h3>
                            </div>
                            
                            <div style="text-align: center; margin-top: 2rem;">
                                <a href="?page=shop" class="btn btn-secondary" style="margin-right: 1rem;">
                                    <i class="fas fa-arrow-left"></i> Continue Shopping
                                </a>
                                <?php if ($isLoggedIn): ?>
                                    <a href="?page=checkout" class="btn btn-primary">
                                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="openAuthModal()" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Login to Checkout
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php
                    break;
                    case 'checkout':
                        if (!$isLoggedIn) {
                            header('Location: ?page=login');
                            exit;
                        }
                        
                        if (empty($_SESSION['cart'])) {
                            header('Location: ?page=cart');
                            exit;
                        }
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Checkout Details
                    </h2>
                    
                    <div class="form-containers">
                        <form method="POST">
                            <div class="checkout-grid">
                                <div class="checkout-column">
                                    <h3 style="color: #da70d6; margin-bottom: 1.5rem;">
                                        <i class="fas fa-truck"></i> Delivery Information
                                    </h3>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Full Name</label>
                                        <input type="text" name="delivery_name" required value="<?= htmlspecialchars($_SESSION['user_name']) ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-phone"></i> Phone Number</label>
                                        <input type="tel" name="delivery_phone" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-home"></i> Address</label>
                                        <textarea name="delivery_address" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-city"></i> City</label>
                                        <input type="text" name="delivery_city" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                                        <input type="text" name="delivery_postal" required>
                                    </div>
                                </div>
                                
                                <div class="checkout-column">
                                    <h3 style="color: #da70d6; margin-bottom: 1.5rem;">
                                        <i class="fas fa-credit-card"></i> Payment Method
                                    </h3>
                                    
                                    <div class="form-group">
                                        <label>Select Payment Method</label>
                                        <div class="payment-options">
                                            <label class="payment-option">
                                                <input type="radio" name="payment_method" value="cod" required>
                                                <i class="fas fa-money-bill-wave"></i> Cash on Delivery
                                            </label>
                                            <label class="payment-option">
                                                <input type="radio" name="payment_method" value="ewallet" required>
                                                <i class="fas fa-wallet"></i> E-Wallet (GCash, PayMaya)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="order-summary">
                                        <h4 style="color: #da70d6; margin-bottom: 1rem;">
                                            <i class="fas fa-shopping-bag"></i> Order Summary
                                        </h4>
                                        <?php
                                        $total = 0;
                                        foreach ($_SESSION['cart'] as $cart_key => $item):
                                            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                                            $stmt->execute([$item['product_id']]);
                                            $product = $stmt->fetch();
                                            $subtotal = $product['price'] * $item['quantity'];
                                            $total += $subtotal;
                                        ?>
                                        <div class="summary-item">
                                            <span><?= htmlspecialchars($product['name']) ?> (<?= $item['quantity'] ?>)</span>
                                            <span><?= formatPrice($subtotal) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="summary-total">
                                            <span>Total:</span>
                                            <span><?= formatPrice($total) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="checkout-buttons">
                                <a href="?page=cart" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Cart
                                </a>
                                <button type="submit" name="save_checkout" class="btn btn-primary">
                                    CheckOut! <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php
                    break;
                    case 'review':
                        if (!$isLoggedIn) {
                            header('Location: ?page=login');
                            exit;
                        }
                        
                        if (empty($_SESSION['cart']) || !isset($_SESSION['payment_method']) || !isset($_SESSION['delivery'])) {
                            header('Location: ?page=cart');
                            exit;
                        }
                        
                        $total = 0;
                        foreach ($_SESSION['cart'] as $item) {
                            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                            $stmt->execute([$item['product_id']]);
                            $product = $stmt->fetch();
                            $total += $product['price'] * $item['quantity'];
                        }
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Review Order
                    </h2>
                    
                    <div style="max-width: 800px; margin: 0 auto;">
                        <h3>Order Items</h3>
                        <?php foreach ($_SESSION['cart'] as $cart_key => $item): 
                            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                            $stmt->execute([$item['product_id']]);
                            $product = $stmt->fetch();
                            $subtotal = $product['price'] * $item['quantity'];
                            
                            $categoryInfo = getProductCategoryInfo($product);
                        ?>
                        <div class="cart-item">
                            <div class="cart-item-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                                <?php else: ?>
                                    <i class="<?= $categoryInfo['icon'] ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-info">
                                <h4><?= htmlspecialchars($product['name']) ?></h4>
                                <p>Price: <?= formatPrice($product['price']) ?></p>
                                <?php if (in_array($categoryInfo['text'], ['T-Shirt', 'Hoodie', 'Pants'])): ?>
                                    <p>Size: <?= $item['size'] ?></p>
                                <?php endif; ?>
                                <p>Quantity: <?= $item['quantity'] ?></p>
                                <p><strong>Subtotal: <?= formatPrice($subtotal) ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-total">
                            <h3>Total: <?= formatPrice($total) ?></h3>
                        </div>
                        
                        <h3>Payment Method</h3>
                        <div class="payment-method">
                            <?php
                            if ($_SESSION['payment_method'] == 'cod') {
                                echo '<i class="fas fa-money-bill-wave"></i> Cash on Delivery';
                            } elseif ($_SESSION['payment_method'] == 'card') {
                                echo '<i class="fas fa-credit-card"></i> Credit/Debit Card';
                            } elseif ($_SESSION['payment_method'] == 'bank') {
                                echo '<i class="fas fa-university"></i> Bank Transfer';
                            } elseif ($_SESSION['payment_method'] == 'ewallet') {
                                echo '<i class="fas fa-wallet"></i> E-Wallet (GCash, PayMaya)';
                            }
                            ?>
                        </div>
                        
                        <h3>Delivery Address</h3>
                        <div class="delivery-address">
                            <p><strong><?= htmlspecialchars($_SESSION['delivery']['name']) ?></strong></p>
                            <p><?= htmlspecialchars($_SESSION['delivery']['address']) ?></p>
                            <p><?= htmlspecialchars($_SESSION['delivery']['city']) ?>, <?= htmlspecialchars($_SESSION['delivery']['postal']) ?></p>
                            <p>Phone: <?= htmlspecialchars($_SESSION['delivery']['phone']) ?></p>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <a href="?page=checkout" class="btn btn-secondary" style="margin-right: 1rem;">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="place_order" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Place Order
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            <?php
                    break;
                    case 'collections':
            ?>
                <section class="collections">
                    <h2 class="section-title">
                        <i class="fas fa-layer-group"></i> Our Collections
                    </h2>
                    
                    <div class="collection-grid">
                        <div class="collection-card">
                            <img src="https://images.unsplash.com/photo-1507604849428-6a386fc832d2?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="Urban Collection" class="collection-image">
                            <div class="collection-overlay">
                                <h3 class="collection-title">Urban Collection</h3>
                                <p>Streetwear essentials for the modern Filipino city dweller</p>
                            </div>
                        </div>
                        
                        <div class="collection-card">
                            <img src="https://images.unsplash.com/photo-1549294471-7a6665d6bd0a?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="Athleisure Collection" class="collection-image">
                            <div class="collection-overlay">
                                <h3 class="collection-title">Athleisure Collection</h3>
                                <p>Comfort meets style for active Filipino lifestyles</p>
                            </div>
                        </div>
                        
                        <div class="collection-card">
                            <img src="https://images.unsplash.com/photo-1507604849428-6a386fc832d2?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="Vintage Collection" class="collection-image">
                            <div class="collection-overlay">
                                <h3 class="collection-title">Vintage Collection</h3>
                                <p>Retro-inspired pieces with modern quality for Filipino fashion</p>
                            </div>
                        </div>
                        
                        <div class="collection-card">
                            <img src="https://images.unsplash.com/photo-1507604849428-6a386fc832d2?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="Premium Collection" class="collection-image">
                            <div class="collection-overlay">
                                <h3 class="collection-title">Premium Collection</h3>
                                <p>Luxury fabrics and exclusive designs for the Filipino market</p>
                            </div>
                        </div>
                    </div>
                </section>
            <?php
                    break;
                    case 'about':
                        if ($isLoggedIn && isset($_GET['settings'])) {
                            renderSettingsPage('about', $isLoggedIn);
                        } else {
            ?>
                <section class="about">
                    <div class="about-content">
                        <div class="about-image">
                            <img src="https://images.unsplash.com/photo-1543466835-00a7fe2fe683?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1770&q=80" alt="About Noiré Luxe">
                        </div>
                        <div class="about-text">
                            <h2><i class="fas fa-info-circle"></i> About Noiré Luxe</h2>
                            <p>Noiré Luxe is a premium streetwear brand founded in 2020 with a mission to create high-quality, stylish apparel that empowers Filipinos to express their unique identity.</p>
                            <p>Our collections feature meticulously crafted t-shirts, hoodies, and pants made from premium materials that combine comfort with cutting-edge design tailored for the Philippine climate.</p>
                            <p>At Noiré Luxe, we believe fashion is more than just clothing—it's a statement of self-expression and confidence for the modern Filipino.</p>
                            
                            <div class="about-stats">
                                <div class="stat-item">
                                    <i class="fas fa-tshirt"></i>
                                    <div class="stat-number">50+</div>
                                    <p>Unique Designs</p>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="stat-number">15+</div>
                                    <p>Philippine Cities</p>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <div class="stat-number">10K+</div>
                                    <p>Satisfied Customers</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php
                        }
                    break;
                    case 'contact':
                        if ($isLoggedIn && isset($_GET['settings'])) {
                            renderSettingsPage('contact', $isLoggedIn);
                        } else {
            ?>
                <section class="contact">
                    <h2 class="section-title">
                        <i class="fas fa-envelope"></i> Contact Us
                    </h2>
                    
                    <?php if (!empty($contact_success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $contact_success ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="contact-container">
                        <div class="contact-form">
                            <form method="POST">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Name</label>
                                    <input type="text" name="contact_name" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="contact_email" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-comment"></i> Message</label>
                                    <textarea name="contact_message" rows="5" required></textarea>
                                </div>
                                <button type="submit" name="contact" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </form>
                        </div>
                        
                        <div class="contact-info">
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-text">
                                    <h3>Our Location</h3>
                                    <p>123 BGC Street<br>Bonifacio Global City, Taguig<br>1634 Metro Manila</p>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="info-text">
                                    <h3>Call Us</h3>
                                    <p>+63 (2) 8123 4567<br>+63 (917) 890 1234</p>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-text">
                                    <h3>Email Us</h3>
                                    <p>support@noireluxe.com.ph<br>info@noireluxe.com.ph</p>
                                </div>
                            </div>
                            
                            <div class="social-links">
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fab fa-tiktok"></i></a>
                                <a href="#"><i class="fab fa-viber"></i></a>
                            </div>
                        </div>
                    </div>
                </section>
            <?php
                        }
                    break;
                    case 'profile':
                        if (!$isLoggedIn) {
                            header('Location: ?page=login');
                            exit;
                        }
                        
                        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $orders = $stmt->fetchAll();
                        
                        $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        $contacts_count = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
                        $orders_count = count($orders);
                        
                        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile-info';
            ?>
                <section class="dashboard">
                    <div class="dashboard-header">
                        <h2><i class="fas fa-user"></i> My Profile</h2>
                        <span>Welcome, <?= $_SESSION['user_name'] ?>!</span>
                    </div>
                    
                    <div class="tabs">
                        <button class="tab <?= $active_tab === 'profile-info' ? 'active' : '' ?>" onclick="showTab('profile-info-tab')">
                            <i class="fas fa-user"></i> Profile Info
                        </button>
                        <button class="tab <?= $active_tab === 'orders' ? 'active' : '' ?>" onclick="showTab('orders-tab')">
                            <i class="fas fa-shopping-bag"></i> My Orders
                        </button>
                        <button class="tab <?= $active_tab === 'dashboard' ? 'active' : '' ?>" onclick="showTab('dashboard-tab')">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </button>
                    </div>
                    
                    <div id="profile-info-tab" class="tab-content <?= $active_tab === 'profile-info' ? 'active' : '' ?>">
                        <div class="form-container">
                            <h3>Profile Information</h3>
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?= htmlspecialchars($_SESSION['user_email']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Member Since</label>
                                <input type="text" value="<?= date('F j, Y', strtotime($_SESSION['user_created_at'])) ?>" readonly>
                            </div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="?page=account_settings" class="btn btn-primary">
                                    <i class="fas fa-cog"></i> Account Settings
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div id="orders-tab" class="tab-content <?= $active_tab === 'orders' ? 'active' : '' ?>">
                        <h3>Order History</h3>
                        <?php if (empty($orders)): ?>
                            <p>You haven't placed any orders yet.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?= $order['id'] ?></td>
                                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                        <td><?= formatPrice($order['total']) ?></td>
                                        <td>
                                            <?php
                                            if ($order['payment_method'] == 'cod') {
                                                echo '<i class="fas fa-money-bill-wave"></i> Cash on Delivery';
                                            } elseif ($order['payment_method'] == 'card') {
                                                echo '<i class="fas fa-credit-card"></i> Credit/Debit Card';
                                            } elseif ($order['payment_method'] == 'bank') {
                                                echo '<i class="fas fa-university"></i> Bank Transfer';
                                            } elseif ($order['payment_method'] == 'ewallet') {
                                                echo '<i class="fas fa-wallet"></i> E-Wallet';
                                            } else {
                                                echo 'Not specified';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span style="background: <?= $order['status'] === 'completed' ? '#27ae60' : ($order['status'] === 'pending' ? '#f39c12' : '#8a2be2') ?>; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?page=order_details&id=<?= $order['id'] ?>" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <div id="dashboard-tab" class="tab-content <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
                        <?php if ($isAdmin): ?>
                            <h3>Admin Dashboard</h3>
                            <div class="stats">
                                <div class="stat-card">
                                    <i class="fas fa-tshirt"></i>
                                    <div class="stat-number"><?= $users_count ?></div>
                                    <p>Total Users</p>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-envelope"></i>
                                    <div class="stat-number"><?= $contacts_count ?></div>
                                    <p>Messages</p>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-shopping-bag"></i>
                                    <div class="stat-number"><?= $orders_count ?></div>
                                    <p>Orders</p>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-star"></i>
                                    <div class="stat-number">4.9</div>
                                    <p>Rating</p>
                                </div>
                            </div>
                            
                            <div class="tabs">
                                <button class="tab active" onclick="showTab('users-tab')">
                                    <i class="fas fa-users"></i> Users
                                </button>
                                <button class="tab" onclick="showTab('messages-tab')">
                                    <i class="fas fa-envelope"></i> Messages
                                </button>
                            </div>
                            
                            <div id="users-tab" class="tab-content active">
                                <h3>User Management</h3>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
                                        while ($user = $stmt->fetch()):
                                        ?>
                                        <tr>
                                            <td><i class="fas fa-user"></i> <?= htmlspecialchars($user['name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <span style="background: <?= $user['role'] === 'admin' ? '#8a2be2' : 'rgba(255,255,255,0.1)' ?>; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                                    <i class="fas fa-<?= $user['role'] === 'admin' ? 'crown' : 'user' ?>"></i> <?= ucfirst($user['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="messages-tab" class="tab-content">
                                <h3>Contact Messages</h3>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Message</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 20");
                                        while ($contact = $stmt->fetch()):
                                        ?>
                                        <tr>
                                            <td><i class="fas fa-user"></i> <?= htmlspecialchars($contact['name']) ?></td>
                                            <td><?= htmlspecialchars($contact['email']) ?></td>
                                            <td><?= substr(htmlspecialchars($contact['message']), 0, 100) ?>...</td>
                                            <td><?= date('M j, Y', strtotime($contact['created_at'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="card" style="background: var(--card-bg); border-radius: 15px; padding: 2rem; margin-bottom: 2rem;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--accent); margin-bottom: 1rem;"></i>
                                <h3 style="color: var(--light-accent); margin-bottom: 1rem;">User Dashboard</h3>
                                <p style="color: var(--light-text); margin-bottom: 1.5rem;">Welcome to your personal dashboard. Here you can manage your account and view your activity.</p>
                                <div style="margin-top: 1rem;">
                                    <a href="?page=settings" class="btn btn-primary">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                </div>
                            </div>
                            
                            <h3 style="color: var(--light-accent); margin-bottom: 1.5rem;">Recent Orders</h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $has_orders = false;
                                    
                                    while ($order = $stmt->fetch()):
                                        $has_orders = true;
                                    ?>
                                    <tr>
                                        <td>#<?= $order['id'] ?></td>
                                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                        <td><?= formatPrice($order['total']) ?></td>
                                        <td>
                                            <?php
                                            if ($order['payment_method'] == 'cod') {
                                                echo '<i class="fas fa-money-bill-wave"></i> Cash on Delivery';
                                            } elseif ($order['payment_method'] == 'card') {
                                                echo '<i class="fas fa-credit-card"></i> Credit/Debit Card';
                                            } elseif ($order['payment_method'] == 'bank') {
                                                echo '<i class="fas fa-university"></i> Bank Transfer';
                                            } elseif ($order['payment_method'] == 'ewallet') {
                                                echo '<i class="fas fa-wallet"></i> E-Wallet';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span style="background: <?= $order['status'] === 'completed' ? '#27ae60' : ($order['status'] === 'pending' ? '#f39c12' : '#8a2be2') ?>; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if (!$has_orders): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 1.5rem;">You haven't placed any orders yet.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>
            <?php
                    break;
                    case 'order_details':
                        if (!$isLoggedIn) {
                            header('Location: ?page=login');
                            exit;
                        }
                        
                        $order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                        
                        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
                        $stmt->execute([$order_id, $_SESSION['user_id']]);
                        $order = $stmt->fetch();
                        
                        if (!$order) {
                            header('Location: ?page=profile');
                            exit;
                        }
                        
                        $stmt = $pdo->prepare("
                            SELECT oi.*, p.name, p.image, p.category, s.name as size_name 
                            FROM order_items oi 
                            JOIN products p ON oi.product_id = p.id 
                            LEFT JOIN sizes s ON oi.size_id = s.id
                            WHERE oi.order_id = ?
                        ");
                        $stmt->execute([$order_id]);
                        $order_items = $stmt->fetchAll();
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-receipt"></i> Order Details #<?= $order_id ?>
                    </h2>
                    
                    <div style="max-width: 800px; margin: 0 auto;">
                        <div class="order-details">
                            <h3>Order Information</h3>
                            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                            <p><strong>Status:</strong> 
                                <span style="background: <?= $order['status'] === 'completed' ? '#27ae60' : ($order['status'] === 'pending' ? '#f39c12' : '#8a2be2') ?>; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </p>
                            <p><strong>Payment Method:</strong> 
                                <?php
                                if ($order['payment_method'] == 'cod') {
                                    echo 'Cash on Delivery';
                                } elseif ($order['payment_method'] == 'card') {
                                    echo 'Credit/Debit Card';
                                } elseif ($order['payment_method'] == 'bank') {
                                    echo 'Bank Transfer';
                                } elseif ($order['payment_method'] == 'ewallet') {
                                    echo 'E-Wallet (GCash, PayMaya)';
                                }
                                ?>
                            </p>
                            <p><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                            <p><strong>Total:</strong> <?= formatPrice($order['total']) ?></p>
                        </div>
                        
                        <h3>Order Items</h3>
                        <?php foreach ($order_items as $item): 
                            $categoryInfo = getProductCategoryInfo($item);
                        ?>
                        <div class="cart-item">
                            <div class="cart-item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                                <?php else: ?>
                                    <i class="<?= $categoryInfo['icon'] ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-info">
                                <h4><?= htmlspecialchars($item['name']) ?></h4>
                                <p>Quantity: <?= $item['quantity'] ?></p>
                                <?php if ($item['size_name']): ?>
                                    <p>Size: <?= htmlspecialchars($item['size_name']) ?></p>
                                <?php endif; ?>
                                <p>Price: <?= formatPrice($item['price']) ?></p>
                            </div>
                        </div>
                        
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM ratings WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $item['product_id']]);
                        $rating = $stmt->fetch();
                        
                        if (!$rating && $order['status'] == 'completed'):
                        ?>
                        <div class="rating-form">
                            <h4>Rate this product</h4>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <div class="form-group">
                                    <label>Rating</label>
                                    <select name="rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="5">5 Stars</option>
                                        <option value="4">4 Stars</option>
                                        <option value="3">3 Stars</option>
                                        <option value="2">2 Stars</option>
                                        <option value="1">1 Star</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Comment (Optional)</label>
                                    <textarea name="comment" rows="3"></textarea>
                                </div>
                                <button type="submit" name="submit_rating" class="btn btn-primary">Submit Rating</button>
                            </form>
                        </div>
                        <?php elseif ($rating): ?>
                        <div class="alert alert-success">
                            You rated this product: <?= $rating['rating'] ?> stars
                            <?php if ($rating['comment']): ?>
                            <br>Comment: <?= htmlspecialchars($rating['comment']) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php
                    break;
                    case 'dashboard':
                        if (!$isLoggedIn) {
                            header('Location: ?page=login');
                            exit;
                        }
                        header('Location: ?page=profile&tab=dashboard');
                        exit;
                    case 'reviews':
            ?>
                <section style="padding: 3rem 0;">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i> Customer Reviews
                    </h2>
                    
                    <?php if (!empty($review_error)): ?>
                    <div class="alert alert-error alert-fixed">
                        <div class="alert-content">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>Error</strong>
                                <p><?= $review_error ?></p>
                            </div>
                            <button class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="reviews-container">
                        <?php
                        $stmt = $pdo->query("
                            SELECT r.*, u.name as user_name, p.name as product_name, p.image as product_image, p.category as product_category
                            FROM ratings r
                            JOIN users u ON r.user_id = u.id
                            JOIN products p ON r.product_id = p.id
                            ORDER BY r.created_at DESC
                        ");
                        $reviews = $stmt->fetchAll();
                        
                        if (count($reviews) > 0):
                            foreach ($reviews as $review):
                                $categoryInfo = getProductCategoryInfo($review);
                        ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-product">
                                    <?php if (!empty($review['product_image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($review['product_image']) ?>" alt="<?= htmlspecialchars($review['product_name']) ?>">
                                    <?php else: ?>
                                        <div class="product-placeholder"><i class="<?= $categoryInfo['icon'] ?>"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <h4><?= htmlspecialchars($review['product_name']) ?></h4>
                                        <p>by <?= htmlspecialchars($review['user_name']) ?></p>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-body">
                                <?php if (!empty($review['comment'])): ?>
                                    <p><?= htmlspecialchars($review['comment']) ?></p>
                                <?php else: ?>
                                    <p class="no-comment">No comment provided</p>
                                <?php endif; ?>
                            </div>
                            <div class="review-footer">
                                <span><?= date('F j, Y', strtotime($review['created_at'])) ?></span>
                                <a href="?page=product&id=<?= $review['product_id'] ?>" class="btn btn-sm">View Product</a>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="no-reviews">
                            <i class="fas fa-comments"></i>
                            <h3>No Reviews Yet</h3>
                            <p>Be the first to share your experience with our products!</p>
                            <a href="?page=shop" class="btn btn-primary">Browse Products</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php
                    break;
                    default:
                        header('Location: ?page=shop');
                        exit;
                }
            }
            ?>
        </div>
    </main>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-gem"></i> Noiré Luxe</h3>
                    <p>Premium streetwear & apparel for the modern Filipino. Elevate your style with our exclusive collection designed for the Philippine climate.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                        <a href="#"><i class="fab fa-viber"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3><i class="fas fa-tshirt"></i> Shop</h3>
                    <p><a href="?page=shop&category=tshirt">T-Shirts</a></p>
                    <p><a href="?page=shop&category=hoodie">Hoodies</a></p>
                    <p><a href="?page=shop&category=pants">Pants</a></p>
                    <p><a href="?page=collections">Collections</a></p>
                </div>
                <div class="footer-section">
                    <h3><i class="fas fa-link"></i> Quick Links</h3>
                    <p><a href="?page=shop">Shop</a></p>
                    <p><a href="?page=about">About</a></p>
                    <p><a href="?page=contact">Contact</a></p>
                    <p><a href="#" onclick="openAuthModal()">Account</a></p>
                </div>
                <div class="footer-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Find Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Cagayan</p>
                    <p><i class="fas fa-map-marker-alt"></i> Ilocos</p>
                    <p><i class="fas fa-map-marker-alt"></i> Bagiuo</p>
                    <p><i class="fas fa-phone"></i> +6375 249 4148</p>
                </div>
            </div>
            <div style="text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <p>&copy; 2025 Noiré Luxe Philippines. All rights reserved. | Made with <i class="fas fa-heart" style="color: #8a2be2;"></i> for Filipinos</p>
            </div>
        </div>
    </footer>
    <script>
        function showTab(tabId) {
            const parent = event.target.closest('.tabs').parentNode;
            
            const tabContents = parent.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            const tabs = parent.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            
            event.target.classList.add('active');
            
            const tabName = tabId.replace('-tab', '');
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        function showAuthTab(tabId) {
            const parent = document.getElementById('authModal');
            
            const tabContents = parent.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            const tabs = parent.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            
            event.target.classList.add('active');
        }
        
        function openAuthModal() {
            document.getElementById('authModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAuthModal() {
            document.getElementById('authModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function confirmLogout() {
            document.getElementById('logoutModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const authModal = document.getElementById('authModal');
            const logoutModal = document.getElementById('logoutModal');
            
            if (event.target == authModal) {
                authModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            if (event.target == logoutModal) {
                logoutModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            const cards = document.querySelectorAll('.product-card, .collection-card, .stat-card, .info-card, .review-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
            
            const heroTitle = document.querySelector('.hero h1');
            if (heroTitle) {
                const text = heroTitle.innerHTML;
                heroTitle.innerHTML = '';
                let i = 0;
                const typeWriter = () => {
                    if (i < text.length) {
                        heroTitle.innerHTML += text.charAt(i);
                        i++;
                        setTimeout(typeWriter, 50);
                    }
                };
                setTimeout(typeWriter, 500);
            }
        });
        
        function createParticles() {
            const particles = document.createElement('div');
            particles.style.position = 'fixed';
            particles.style.top = '0';
            particles.style.left = '0';
            particles.style.width = '100%';
            particles.style.height = '100%';
            particles.style.pointerEvents = 'none';
            particles.style.zIndex = '-1';
            document.body.appendChild(particles);
            
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '2px';
                particle.style.height = '2px';
                particle.style.background = 'rgba(138, 43, 226, 0.5)';
                particle.style.borderRadius = '50%';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animation = `float ${Math.random() * 10 + 5}s linear infinite`;
                particles.appendChild(particle);
            }
        }
        
        createParticles();
        
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = form.querySelectorAll('input[required], textarea[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = '#f44336';
                        isValid = false;
                    } else {
                        input.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
        
        const paymentOptions = document.querySelectorAll('.payment-option');
        paymentOptions.forEach(option => {
            option.addEventListener('click', function() {
                paymentOptions.forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                this.classList.add('selected');
                
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            });
        });
        
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container');
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon = '';
            if (type === 'success') icon = 'fa-check-circle';
            else if (type === 'error') icon = 'fa-exclamation-circle';
            else if (type === 'info') icon = 'fa-info-circle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="toast-icon fas ${icon}"></i>
                    <span class="toast-message">${message}</span>
                </div>
                <button class="toast-close">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000); // Changed from 3000ms to 5000ms (5 seconds)
            
            // Close button functionality
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        }

        // Convert existing alerts to toasts
        document.addEventListener('DOMContentLoaded', function() {
            // Convert existing alerts to toasts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const message = alert.textContent.trim();
                let type = 'info';
                
                if (alert.classList.contains('alert-success')) type = 'success';
                else if (alert.classList.contains('alert-error')) type = 'error';
                
                showToast(message, type);
                alert.remove();
            });
            
            // Show any PHP messages as toasts
            <?php if (!empty($success_message)): ?>
                showToast('<?= $success_message ?>', 'success');
            <?php endif; ?>
            
            <?php if (!empty($login_error)): ?>
                showToast('<?= $login_error ?>', 'error');
            <?php endif; ?>
            
            <?php if (!empty($register_error)): ?>
                showToast('<?= $register_error ?>', 'error');
            <?php endif; ?>
            
            <?php if (!empty($contact_success)): ?>
                showToast('<?= $contact_success ?>', 'success');
            <?php endif; ?>
            
            <?php if (!empty($checkout_success)): ?>
                showToast('<?= $checkout_success ?>', 'success');
            <?php endif; ?>
            
            <?php if (!empty($rating_error)): ?>
                showToast('<?= $rating_error ?>', 'error');
            <?php endif; ?>
            
            <?php if (!empty($rating_success)): ?>
                showToast('<?= $rating_success ?>', 'success');
            <?php endif; ?>
            
            <?php if (!empty($review_error)): ?>
                showToast('<?= $review_error ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
ob_end_flush(); // End output buffering and send the buffered content
?>