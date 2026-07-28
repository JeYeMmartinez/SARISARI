<?php
session_start();

$prefix = '';
if (basename(dirname(__FILE__)) == 'View' || basename(dirname(__FILE__)) == 'view') {
    $prefix = '../';
}

require_once __DIR__ . '/' . $prefix . 'Model/database.php';
require_once __DIR__ . '/' . $prefix . 'Model/logger.php';

// Create blocked_registrations table if it doesn't exist
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS blocked_registrations (
        gmail VARCHAR(255) PRIMARY KEY,
        blocked_until DATETIME NOT NULL
    )
");

$customerLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Customer';

// Resolve Table Names Dynamically
$tableProducts = 'products';
$tableUsers = 'users';
$tableCategories = 'categories';
$tableSales = 'sales';
$tableSaleItems = 'sale_items';
$tableInventory = 'inventory';
$tableOrders = 'orders';
$tableOrderItems = 'order_items';

if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'product'")) {
    if (mysqli_num_rows($res) > 0)
        $tableProducts = 'product';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'user'")) {
    if (mysqli_num_rows($res) > 0)
        $tableUsers = 'user';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'category'")) {
    if (mysqli_num_rows($res) > 0)
        $tableCategories = 'category';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'sale'")) {
    if (mysqli_num_rows($res) > 0)
        $tableSales = 'sale';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'sale_item'")) {
    if (mysqli_num_rows($res) > 0)
        $tableSaleItems = 'sale_item';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'order'")) {
    if (mysqli_num_rows($res) > 0)
        $tableOrders = 'order';
}
if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'order_item'")) {
    if (mysqli_num_rows($res) > 0)
        $tableOrderItems = 'order_item';
}

// Check and seed categories if empty
$catCheck = mysqli_query($conn, "SELECT * FROM $tableCategories");
if ($catCheck && mysqli_num_rows($catCheck) == 0) {
    mysqli_query($conn, "INSERT INTO $tableCategories (category_id, category_name, description) VALUES
    (1, 'Food & Groceries', '(Canned goods, instant noodles, rice, condiments, oil)'),
    (2, 'Beverages', '(Soft drinks, water, coffee sachets, powdered juice, liquor)'),
    (3, 'Snacks & Sweets', '(Chips, biscuits, candies, chocolates, bakery items)'),
    (4, 'Personal Care', '(Shampoo, soap, toothpaste, sanitary napkins)'),
    (5, 'Household Supplies', '(Detergents, dishwashing liquid, bleach, matches, candles)'),
    (6, 'Digital Services & Loading', '(Prepaid load, GCash/Maya cash-in, gaming pins)'),
    (7, 'Tobacco & Alcohol', '(Cigarettes, lighters, beer, hard drinks)'),
    (8, 'Miscellaneous / Others', '(School supplies, rags, pet food)')");
}

// Check and seed products (Coke 1.5L and Liquid Detergent) if empty to match the visual screenshots
$cokeCheck = mysqli_query($conn, "SELECT * FROM $tableProducts WHERE product_name = 'Coke 1.5L'");
if ($cokeCheck && mysqli_num_rows($cokeCheck) == 0) {
    // Find Beverages category
    $catQuery = mysqli_query($conn, "SELECT category_id FROM $tableCategories WHERE category_name LIKE '%Beverage%' LIMIT 1");
    $catId = 2;
    if ($catQuery && $row = mysqli_fetch_assoc($catQuery)) {
        $catId = $row['category_id'];
    }
    // Insert Coke 1.5L
    mysqli_query($conn, "INSERT INTO $tableProducts (category_id, product_name, barcode, description, selling_price, cost_price, status, added_by) VALUES ($catId, 'Coke 1.5L', 'coke15l', '1.5 Liters of Coca-Cola', 65.25, 50.00, 'Available', 1)");
    $newProdId = mysqli_insert_id($conn);
    // Add inventory
    mysqli_query($conn, "INSERT INTO $tableInventory (product_id, quantity, minimum_stock, maximum_Stock, aisle) VALUES ($newProdId, 50, 5, 100, 'Aisle 1')");
}

$detCheck = mysqli_query($conn, "SELECT * FROM $tableProducts WHERE product_name = 'Liquid Detergent'");
if ($detCheck && mysqli_num_rows($detCheck) == 0) {
    // Find Household category
    $catQuery = mysqli_query($conn, "SELECT category_id FROM $tableCategories WHERE category_name LIKE '%Household%' OR category_name LIKE '%Other%' LIMIT 1");
    $catId = 5;
    if ($catQuery && $row = mysqli_fetch_assoc($catQuery)) {
        $catId = $row['category_id'];
    }
    // Insert Liquid Detergent
    mysqli_query($conn, "INSERT INTO $tableProducts (category_id, product_name, barcode, description, selling_price, cost_price, status, added_by) VALUES ($catId, 'Liquid Detergent', 'liqdet', 'Premium liquid detergent', 35.00, 25.00, 'Available', 1)");
    $newProdId = mysqli_insert_id($conn);
    // Add inventory
    mysqli_query($conn, "INSERT INTO $tableInventory (product_id, quantity, minimum_stock, maximum_Stock, aisle) VALUES ($newProdId, 30, 5, 100, 'Aisle 2')");
}

$oishiCheck = mysqli_query($conn, "SELECT * FROM $tableProducts WHERE product_name = 'Oishi Potato Chips'");
if ($oishiCheck && mysqli_num_rows($oishiCheck) == 0) {
    // Find Snacks category
    $catQuery = mysqli_query($conn, "SELECT category_id FROM $tableCategories WHERE category_name LIKE '%Snacks%' LIMIT 1");
    $catId = 3;
    if ($catQuery && $row = mysqli_fetch_assoc($catQuery)) {
        $catId = $row['category_id'];
    }
    // Insert Oishi Potato Chips
    mysqli_query($conn, "INSERT INTO $tableProducts (category_id, product_name, barcode, description, selling_price, cost_price, image, status, added_by) VALUES ($catId, 'Oishi Potato Chips', 'oishichips', 'Oishi Potato Chips 60g', 29.50, 20.00, 'oishi_potato_chips.png', 'Available', 1)");
    $newProdId = mysqli_insert_id($conn);
    // Add inventory
    mysqli_query($conn, "INSERT INTO $tableInventory (product_id, quantity, minimum_stock, maximum_Stock, aisle) VALUES ($newProdId, 45, 5, 100, 'Aisle 3')");
}


// Helper to send OTP verification email using PHPMailer
function sendOTPEmail($gmail, $otp, $prefix)
{
    require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/Exception.php';
    require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Configurable Gmail Credentials (USER can edit these)
        $mail->Username = 'edonnarao06@gmail.com';
        $mail->Password = 'pqda kqsx qnxo pqsp'; // Put Google App Password here

        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('edonnarao06@gmail.com', 'OCart!');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Verification Code - OCart!';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #4C7A5C;'>OCart!</h2>
                <p>Hello,</p>
                <p>Thank you for registering at OCart!. To complete your registration, please enter the following verification code (OTP):</p>
                <div style='font-size: 24px; font-weight: bold; background: #f4f6f5; padding: 15px; text-align: center; border-radius: 5px; color: #1E5631; letter-spacing: 5px;'>
                    $otp
                </div>
                <p style='margin-top: 20px; font-size: 12px; color: #888;'>This code is valid for 10 minutes. If you did not request this code, please ignore this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    }
}

// ==========================================
//   API REQUESTS ROUTING (POST ACTIONS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    header('Content-Type: application/json');

    // API: LOGIN
    if ($action === 'login') {
        $gmail = mysqli_real_escape_string($conn, trim($_POST['gmail']));
        $password = $_POST['password'];

        if (empty($gmail) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields.']);
            exit();
        }

        $query = mysqli_query($conn, "SELECT * FROM $tableUsers WHERE gmail = '$gmail' AND status = 'Active'");
        if ($query && mysqli_num_rows($query) === 1) {
            $user = mysqli_fetch_assoc($query);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['gmail'] = $user['gmail'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Update last login
                mysqli_query($conn, "UPDATE $tableUsers SET last_login = NOW() WHERE user_id = {$user['user_id']}");

                logAction($conn, $user['user_id'], 'Login', $tableUsers, $user['user_id'], "{$user['full_name']} logged in");

                echo json_encode([
                    'status' => 'success',
                    'user' => [
                        'user_id' => $user['user_id'],
                        'full_name' => $user['full_name'],
                        'gmail' => $user['gmail']
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gmail account not found or account is inactive.']);
        }
        exit();
    }

    // API: REGISTER
    if ($action === 'register') {
        $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
        $gmail = mysqli_real_escape_string($conn, trim($_POST['gmail']));
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($full_name) || empty($gmail) || empty($password) || empty($confirm_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields.']);
            exit();
        }

        if ($password !== $confirm_password) {
            echo json_encode(['status' => 'error', 'message' => 'Passwords mismatched! Please check you password.']);
            exit();
        }

        if (strlen($password) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
            exit();
        }

        // Clean up expired blocks
        mysqli_query($conn, "DELETE FROM blocked_registrations WHERE blocked_until <= NOW()");

        // Check if Gmail is currently blocked
        $block_check = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(blocked_until) - UNIX_TIMESTAMP(NOW()) AS remaining_seconds FROM blocked_registrations WHERE gmail = '$gmail' AND blocked_until > NOW()");
        if ($block_check && mysqli_num_rows($block_check) > 0) {
            $block_row = mysqli_fetch_assoc($block_check);
            $remaining = ceil($block_row['remaining_seconds'] / 60);
            echo json_encode([
                'status' => 'error',
                'message' => "This Gmail account is temporarily blocked for $remaining more minute(s) due to too many failed OTP attempts."
            ]);
            exit();
        }

        // Check if gmail exists
        $check = mysqli_query($conn, "SELECT user_id FROM $tableUsers WHERE gmail = '$gmail'");
        if ($check && mysqli_num_rows($check) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Gmail account is already registered.']);
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $otp = rand(100000, 999999);

        // Store registration info in session and flush immediately
        $_SESSION['pending_register'] = [
            'full_name' => $full_name,
            'gmail'     => $gmail,
            'password'  => $hashed_password,
            'otp'       => (string)$otp,
            'time'      => time(),
            'attempts'  => 0
        ];
        // Force session save BEFORE sending email (PHPMailer can delay response)
        session_write_close();

        try {
            sendOTPEmail($gmail, $otp, $prefix);
            echo json_encode([
                'status' => 'otp_sent',
                'message' => 'An OTP verification code has been sent to ' . htmlspecialchars($gmail) . '. Please check your inbox or spam folder.'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to send OTP verification email. Please check SMTP configuration. Error: ' . htmlspecialchars($e->getMessage())
            ]);
        }
        exit();
    }

    // API: VERIFY OTP
    if ($action === 'verify_otp') {
        // Reopen session — it was closed after register to force-save
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();

        $entered_otp = trim($_POST['otp']);

        if (!isset($_SESSION['pending_register'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please register again.']);
            exit();
        }

        $pending = $_SESSION['pending_register'];

        // Check 10-minute expiry
        if (time() - $pending['time'] > 600) {
            unset($_SESSION['pending_register']);
            echo json_encode(['status' => 'error', 'message' => 'OTP has expired (10 minutes). Please register again.']);
            exit();
        }

        $gmail = mysqli_real_escape_string($conn, $pending['gmail']);

        // Check if Gmail is currently blocked
        $block_check = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(blocked_until) - UNIX_TIMESTAMP(NOW()) AS remaining_seconds FROM blocked_registrations WHERE gmail = '$gmail' AND blocked_until > NOW()");
        if ($block_check && mysqli_num_rows($block_check) > 0) {
            unset($_SESSION['pending_register']);
            echo json_encode(['status' => 'error', 'message' => 'This Gmail account is temporarily blocked. Please try again later.']);
            exit();
        }

        if ((string)$entered_otp !== (string)$pending['otp']) {
            $_SESSION['pending_register']['attempts'] = ($_SESSION['pending_register']['attempts'] ?? 0) + 1;
            $attempts = $_SESSION['pending_register']['attempts'];

            if ($attempts >= 3) {
                // Block the email for 30 minutes
                mysqli_query($conn, "
                    INSERT INTO blocked_registrations (gmail, blocked_until)
                    VALUES ('$gmail', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                    ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                ");
                unset($_SESSION['pending_register']);
                echo json_encode([
                    'status' => 'blocked',
                    'message' => 'Too many failed OTP attempts. This Gmail account has been blocked for 30 minutes.'
                ]);
                exit();
            } else {
                $remaining = 3 - $attempts;
                echo json_encode([
                    'status' => 'error',
                    'message' => "Incorrect verification code. You have $remaining attempt(s) remaining."
                ]);
                exit();
            }
        }

        $full_name = mysqli_real_escape_string($conn, $pending['full_name']);
        $gmail = mysqli_real_escape_string($conn, $pending['gmail']);
        $password = mysqli_real_escape_string($conn, $pending['password']);

        // Final database insertion
        $insert = mysqli_query($conn, "
            INSERT INTO $tableUsers (gmail, password, full_name, role, status)
            VALUES ('$gmail', '$password', '$full_name', 'Customer', 'Active')
        ");

        if ($insert) {
            // Delete block record on successful registration (if any)
            mysqli_query($conn, "DELETE FROM blocked_registrations WHERE gmail = '$gmail'");

            $new_user_id = mysqli_insert_id($conn);

            // Log safely — skip if FK constraint fails
            try {
                mysqli_query($conn, "SET foreign_key_checks = 0");
                logAction($conn, $new_user_id, 'Create', $tableUsers, $new_user_id, "Registered & Verified new customer: $full_name");
                mysqli_query($conn, "SET foreign_key_checks = 1");
            } catch(Throwable $e) {
                mysqli_query($conn, "SET foreign_key_checks = 1");
                // Logging failed silently — registration still succeeds
            }

            // Auto-login after registration
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['gmail'] = $gmail;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = 'Customer';

            unset($_SESSION['pending_register']);

            echo json_encode([
                'status' => 'success',
                'user' => [
                    'user_id' => $new_user_id,
                    'full_name' => $full_name,
                    'gmail' => $gmail
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration database insert failed.']);
        }
        exit();
    }

    // API: FORGOT PASSWORD — send OTP to gmail
    if ($action === 'forgot_password') {
        $gmail = mysqli_real_escape_string($conn, trim($_POST['gmail']));

        if (empty($gmail)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter your Gmail account.']);
            exit();
        }

        $check = mysqli_query($conn, "SELECT user_id FROM $tableUsers WHERE gmail = '$gmail' AND status = 'Active'");
        if (!$check || mysqli_num_rows($check) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'No active account found with that Gmail address.']);
            exit();
        }

        $otp = rand(100000, 999999);
        $_SESSION['pending_reset'] = [
            'gmail' => $gmail,
            'otp' => $otp,
            'time' => time()
        ];

        // Reuse sendOTPEmail with reset-specific body
        try {
            require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/Exception.php';
            require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/' . $prefix . 'Assets/PHPMailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'edonnarao06@gmail.com';
            $mail->Password = 'pqda kqsx qnxo pqsp';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

            $mail->setFrom('edonnarao06@gmail.com', ' OCart!');
            $mail->addAddress($gmail);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code - OCart!';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                    <h2 style='color: #4C7A5C;'>OCart!</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset the password for your account. Enter the following 6-digit code to proceed:</p>
                    <div style='font-size: 26px; font-weight: bold; background: #f4f6f5; padding: 16px; text-align: center; border-radius: 5px; color: #1E5631; letter-spacing: 6px;'>
                        $otp
                    </div>
                    <p style='margin-top: 18px; font-size: 12px; color: #888;'>This code is valid for 10 minutes. If you did not request a password reset, please ignore this email.</p>
                </div>
            ";
            $mail->send();
            echo json_encode(['status' => 'otp_sent', 'message' => 'A password reset code has been sent to ' . htmlspecialchars($gmail) . '. Please check your inbox or spam folder.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send reset email. Error: ' . htmlspecialchars($e->getMessage())]);
        }
        exit();
    }

    // API: RESET PASSWORD — verify OTP and save new password
    if ($action === 'reset_password') {
        $entered_otp = trim($_POST['otp']);
        $new_password = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (!isset($_SESSION['pending_reset'])) {
            echo json_encode(['status' => 'error', 'message' => 'No reset request found. Please start the forgot password process again.']);
            exit();
        }

        $pending = $_SESSION['pending_reset'];

        // Expire after 10 minutes
        if (time() - $pending['time'] > 600) {
            unset($_SESSION['pending_reset']);
            echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new reset code.']);
            exit();
        }

        if ($entered_otp != $pending['otp']) {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect OTP code. Please try again.']);
            exit();
        }

        if (empty($new_password) || strlen($new_password) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
            exit();
        }

        if ($new_password !== $confirm_pass) {
            echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
            exit();
        }

        $gmail = mysqli_real_escape_string($conn, $pending['gmail']);
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $update = mysqli_query($conn, "UPDATE $tableUsers SET password = '$hashed' WHERE gmail = '$gmail'");
        if ($update && mysqli_affected_rows($conn) > 0) {
            unset($_SESSION['pending_reset']);
            echo json_encode(['status' => 'success', 'message' => 'Password reset successfully! You can now log in with your new password.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']);
        }
        exit();
    }

    // API: CHECKOUT
    if ($action === 'checkout') {
        if (!$customerLoggedIn) {
            echo json_encode(['status' => 'error', 'message' => 'Please log in to complete checkout.']);
            exit();
        }

        $user_id   = $_SESSION['user_id'];
        $cart_data = isset($_POST['cart']) ? json_decode($_POST['cart'], true) : [];

        if (empty($cart_data)) {
            echo json_encode(['status' => 'error', 'message' => 'Your cart is empty.']);
            exit();
        }

        $subtotal = 0;
        foreach ($cart_data as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }
        $tax   = round($subtotal * 0.12, 2);
        $total = round($subtotal + $tax, 2);

        mysqli_begin_transaction($conn);
        try {
            // Validate stock first before touching anything
            foreach ($cart_data as $item) {
                $product_id = (int) $item['id'];
                $quantity   = (int) $item['quantity'];
                $stock_row  = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT quantity FROM $tableInventory WHERE product_id = $product_id"
                ));
                if (!$stock_row) {
                    throw new Exception("Inventory record not found for '" . $item['name'] . "'.");
                }
                if ((int)$stock_row['quantity'] < $quantity) {
                    throw new Exception("'" . $item['name'] . "' only has " . $stock_row['quantity'] . " left in stock.");
                }
            }

            // Insert into orders table as Pending
            $order_insert = mysqli_query($conn, "
                INSERT INTO $tableOrders (cashier_id, subtotal, tax, total, status)
                VALUES ($user_id, $subtotal, $tax, $total, 'Pending')
            ");
            if (!$order_insert) throw new Exception("Failed to create order: " . mysqli_error($conn));
            $order_id = mysqli_insert_id($conn);

            // Process each item — insert order_items AND deduct inventory immediately
            foreach ($cart_data as $item) {
                $product_id    = (int) $item['id'];
                $product_name  = mysqli_real_escape_string($conn, $item['name']);
                $quantity      = (int) $item['quantity'];
                $price         = (float) $item['price'];
                $item_subtotal = round($price * $quantity, 2);

                // Insert order item
                $item_insert = mysqli_query($conn, "
                    INSERT INTO $tableOrderItems (order_id, product_id, product_name, quantity, selling_price, subtotal)
                    VALUES ($order_id, $product_id, '$product_name', $quantity, $price, $item_subtotal)
                ");
                if (!$item_insert) throw new Exception("Failed to save item: " . mysqli_error($conn));

                // Deduct inventory immediately (reservation)
                mysqli_query($conn, "
                    UPDATE $tableInventory
                    SET quantity = GREATEST(0, quantity - $quantity)
                    WHERE product_id = $product_id
                ");

                // Update product status based on remaining stock
                mysqli_query($conn, "
                    UPDATE $tableProducts SET status =
                        CASE WHEN (SELECT quantity FROM $tableInventory WHERE product_id = $product_id) = 0
                        THEN 'Unavailable' ELSE 'Available' END
                    WHERE product_id = $product_id
                ");
            }

            logAction($conn, $user_id, 'Checkout', $tableOrders, $order_id,
                "Order #$order_id placed — Total: ₱" . number_format($total, 2) . " (inventory reserved)");

            // Notify admin
            $custName = mysqli_real_escape_string($conn, $_SESSION['full_name']);
            mysqli_query($conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('New Order', 'Order #$order_id from $custName — ₱" . number_format($total, 2) . " (inventory reserved)', 'Approval', 0)
            ");

            mysqli_commit($conn);
            echo json_encode([
                'status'   => 'success',
                'message'  => 'Your order has been placed! Inventory has been reserved. Please wait for approval.',
                'order_id' => $order_id,
                'total'    => $total
            ]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }
    // API: GET ORDER HISTORY
    if ($action === 'get_order_history') {
        if (!$customerLoggedIn) {
            echo json_encode(['status' => 'error', 'message' => 'Please log in.']);
            exit();
        }
        $user_id = (int)$_SESSION['user_id'];
        $orders_q = mysqli_query($conn, "
            SELECT o.order_id, o.subtotal, o.tax, o.total, o.status, o.created_at
            FROM $tableOrders o
            WHERE o.cashier_id = $user_id
            ORDER BY o.created_at DESC
            LIMIT 20
        ");
        $orders = [];
        while ($ord = mysqli_fetch_assoc($orders_q)) {
            $oid = (int)$ord['order_id'];
            $items_q = mysqli_query($conn, "
                SELECT product_name, quantity, selling_price, subtotal
                FROM $tableOrderItems
                WHERE order_id = $oid
            ");
            $items = [];
            while ($it = mysqli_fetch_assoc($items_q)) {
                $items[] = $it;
            }
            $ord['items'] = $items;
            $orders[] = $ord;
        }
        echo json_encode(['status' => 'success', 'orders' => $orders]);
        exit();
    }
}

// API: LOGOUT
if (isset($_GET['logout'])) {
    // Capture before destroying session
    $logout_user_id   = $_SESSION['user_id']   ?? 0;
    $logout_full_name = $_SESSION['full_name']  ?? 'Unknown';

    if($logout_user_id > 0){
        logAction($conn, $logout_user_id, 'Logout', $tableUsers, $logout_user_id, "$logout_full_name logged out");
    }

    // Fully destroy the session
    session_unset();
    session_destroy();
    session_write_close();
    // Prevent browser cache from restoring the logged-in page
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// Fetch categories from DB
$categories_res = mysqli_query($conn, "SELECT * FROM $tableCategories ORDER BY category_id ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($categories_res)) {
    $categories[] = $row;
}

// Fetch products from DB
$products_res = mysqli_query($conn, "
    SELECT p.product_id, p.product_name, p.selling_price, p.category_id,
           p.image, c.category_name, IFNULL(i.quantity, 0) AS stock
    FROM $tableProducts p
    LEFT JOIN $tableCategories c ON p.category_id = c.category_id
    LEFT JOIN $tableInventory i ON p.product_id = i.product_id
    WHERE p.status = 'Available' AND p.deleted_at IS NULL
    ORDER BY p.product_name ASC
");
$products = [];
while ($row = mysqli_fetch_assoc($products_res)) {
    $products[] = $row;
}


// Helper function to render high-fidelity custom SVG vectors inline
function getProductSVG($name, $categoryName = '')
{
    $name = strtolower($name);
    $categoryName = strtolower($categoryName);

    if (strpos($name, 'coke') !== false || strpos($name, 'cola') !== false) {
        return '
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 200" width="80" height="135">
            <path d="M 45,15 H 55 V 30 C 55,30 58,40 62,50 C 66,60 65,75 65,75 L 67,110 C 67,110 70,120 70,140 C 70,160 68,185 60,195 C 55,200 45,200 40,195 C 32,185 30,160 30,140 C 30,120 33,110 33,110 L 35,75 C 35,75 34,60 38,50 C 42,40 45,30 45,30 Z" fill="#C62828" />
            <rect x="43" y="5" width="14" height="10" rx="2" fill="#E53935" />
            <rect x="42" y="12" width="16" height="3" fill="#B71C1C" />
            <path d="M 33,90 H 67 V 125 H 33 Z" fill="#D32F2F" />
            <path d="M 35,110 Q 50,98 65,110" stroke="#FFFFFF" stroke-width="3.5" fill="none" />
            <path d="M 35,115 Q 50,105 65,117" stroke="#FFFFFF" stroke-width="1.2" fill="none" />
            <text x="50" y="121" font-family="\'Brush Script MT\', cursive, sans-serif" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle" transform="rotate(-10 50 121)">Coke</text>
        </svg>';
    }

    if (strpos($name, 'detergent') !== false || strpos($name, 'soap') !== false || strpos($name, 'wash') !== false) {
        return '
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 200" width="80" height="135">
            <path d="M 35,40 H 65 V 60 Q 75,70 75,90 V 170 Q 75,190 55,190 H 45 Q 25,190 25,170 V 90 Q 25,70 35,40 Z" fill="#81C784" />
            <path d="M 25,100 H 16 Q 10,100 10,110 V 150 Q 10,160 16,160 H 25 Z" fill="#81C784" />
            <path d="M 25,110 H 20 V 150 H 25 Z" fill="#4CAF50" opacity="0.3" />
            <rect x="43" y="20" width="14" height="20" fill="#4CAF50" />
            <rect x="38" y="10" width="24" height="10" rx="3" fill="#2E7D32" />
            <path d="M 29,95 H 71 V 145 H 29 Z" fill="#FFF" rx="5" />
            <path d="M 29,95 Q 50,85 71,95 L 71,115 Q 50,105 29,115 Z" fill="#FFD54F" />
            <text x="50" y="110" font-family="sans-serif" font-size="9" font-weight="bold" fill="#E65100" text-anchor="middle">SUPER</text>
            <text x="50" y="132" font-family="sans-serif" font-size="14" font-weight="bold" fill="#1B5E20" text-anchor="middle">Wash</text>
        </svg>';
    }

    if (strpos($categoryName, 'food') !== false || strpos($categoryName, 'grocer') !== false || strpos($name, 'beef') !== false || strpos($name, 'tuna') !== false) {
        return '
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 150" width="70" height="110">
            <ellipse cx="50" cy="20" rx="25" ry="10" fill="#CFD8DC" stroke="#78909C" stroke-width="1.5" />
            <path d="M 25,20 V 100 A 25,10 rx 0 0 0 75,100 V 20 Z" fill="#ECEFF1" stroke="#78909C" stroke-width="1.5" />
            <ellipse cx="50" cy="100" rx="25" ry="10" fill="#B0BEC5" stroke="#78909C" stroke-width="1.5" />
            <path d="M 25,35 H 75 V 85 H 25 Z" fill="#E53935" />
            <rect x="25" y="45" width="50" height="5" fill="#FFEB3B" />
            <text x="50" y="65" font-family="sans-serif" font-size="9" font-weight="bold" fill="#FFFFFF" text-anchor="middle">DELUXE</text>
            <text x="50" y="78" font-family="sans-serif" font-size="7" fill="#FFFFFF" text-anchor="middle">Food Item</text>
        </svg>';
    }

    if (strpos($categoryName, 'personal') !== false || strpos($name, 'rexona') !== false || strpos($name, 'shampoo') !== false) {
        return '
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 150" width="70" height="110">
            <path d="M 35,45 C 35,45 35,25 50,25 C 65,25 65,45 65,45 V 130 C 65,135 60,140 50,140 C 40,140 35,135 35,130 Z" fill="#EEEEEE" stroke="#B0BEC5" stroke-width="1.5" />
            <rect x="42" y="10" width="16" height="15" fill="#0D47A1" rx="2" />
            <rect x="45" y="5" width="10" height="5" fill="#1976D2" rx="1" />
            <path d="M 35,60 H 65 V 105 H 35 Z" fill="#1E88E5" />
            <text x="50" y="85" font-family="sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">FRESH</text>
            <text x="50" y="98" font-family="sans-serif" font-size="7" fill="#FFFFFF" text-anchor="middle">Body Care</text>
        </svg>';
    }

    return '
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 120" width="70" height="100">
        <path d="M 50,15 L 85,32 L 85,78 L 50,95 L 15,78 L 15,32 Z" fill="#FFE082" stroke="#FFB300" stroke-width="1.5" />
        <path d="M 50,15 L 15,32 L 50,50 L 85,32 Z" fill="#FFD54F" stroke="#FFB300" stroke-width="1.5" />
        <path d="M 50,50 V 95" stroke="#FFB300" stroke-width="1.5" />
    </svg>';
}

function getWelcomeBannerSVG()
{
    return '
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 350" width="100%" height="100%">
        <circle cx="200" cy="220" r="120" fill="#FFFFFF" opacity="0.12" />
        <path d="M 120,180 C 120,150 160,110 190,110 C 220,110 240,140 240,170 C 240,170 240,210 240,220 C 240,220 120,220 120,220 Z" fill="#F4B459" stroke="#E09638" stroke-width="2" />
        <path d="M 155,140 L 180,170" stroke="#D37E28" stroke-width="4" stroke-linecap="round" />
        <path d="M 175,130 L 205,165" stroke="#D37E28" stroke-width="4" stroke-linecap="round" />
        <path d="M 195,125 L 225,160" stroke="#D37E28" stroke-width="4" stroke-linecap="round" />
        <path d="M 255,160 H 295 V 230 H 255 Z" fill="#FFB74D" stroke="#E65100" stroke-width="2" />
        <path d="M 262,160 L 268,125 H 282 L 288,160 Z" fill="#FFE082" stroke="#E65100" stroke-width="2" />
        <rect x="268" y="112" width="14" height="13" rx="2" fill="#E53935" stroke="#B71C1C" stroke-width="1.5" />
        <rect x="255" y="175" width="40" height="30" fill="#FFF" />
        <circle cx="275" cy="190" r="8" fill="#FFA726" />
        <path d="M 140,210 V 260 C 140,260 140,270 165,270 C 190,270 190,260 190,260 V 210 Z" fill="#ECEFF1" stroke="#90A4AE" stroke-width="2" />
        <ellipse cx="165" cy="210" rx="25" ry="8" fill="#CFD8DC" stroke="#90A4AE" stroke-width="2" />
        <rect x="140" y="222" width="50" height="28" fill="#1E88E5" />
        <circle cx="165" cy="236" r="6" fill="#FFEB3B" />
        <circle cx="215" cy="245" r="22" fill="#E53935" stroke="#C62828" stroke-width="2" />
        <path d="M 215,223 C 215,223 218,220 220,222 C 222,224 218,227 218,227 Z" fill="#4CAF50" />
        <circle cx="245" cy="250" r="18" fill="#E53935" stroke="#C62828" stroke-width="2" />
        <path d="M 110,210 L 130,320 L 290,320 L 310,210 Z" fill="#D7CCC8" stroke="#8D6E63" stroke-width="3" />
        <path d="M 110,210 L 180,240 L 220,240 L 310,210" fill="none" stroke="#A1887F" stroke-width="2" />
        <path d="M 180,240 L 190,320" stroke="#A1887F" stroke-width="1.5" stroke-dasharray="3,3" />
        <path d="M 220,240 L 230,320" stroke="#A1887F" stroke-width="1.5" stroke-dasharray="3,3" />
    </svg>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCart! — Sari-Sari Store</title>

    <!-- Local Assets Setup -->
    <link rel="stylesheet" href="<?= $prefix; ?>Assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $prefix; ?>Assets/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= $prefix; ?>Assets/datatables.min.css">
    <link rel="stylesheet" href="<?= $prefix; ?>Assets/animate.min.css">

    <!-- Bootstrap Icons via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary-green: #2563eb;
            --dark-green: #1d4ed8;
            --brand-green: #0f172a;
            --bg-light: #f8fafc;
            --card-gray: #ffffff;
            --text-dark: #334155;
            
            --primary-gradient: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            --accent-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            --navy-dark: #0f172a;
            --blue-accent: #2563eb;
            --emerald-accent: #10b981;
            --card-hover-transform: translateY(-4px);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* HEADER */
        header {
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 80px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 500;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #ffffff !important;
        }

        .header-logo svg {
            color: #ffffff !important;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff !important;
        }

        /* SEARCH BAR */
        .search-container {
            position: relative;
            width: 45%;
            max-width: 500px;
        }

        .search-input {
            width: 100%;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 10px 20px 10px 45px;
            font-size: 15px;
            background-color: rgba(255, 255, 255, 0.07);
            color: #ffffff;
            transition: all 0.2s ease;
        }

        .search-input::placeholder {
            color: #94a3b8;
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
            border-color: var(--primary-green);
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
        }


        /* CART & PROFILE BUTTONS */
        .right-actions {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .cart-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #ffffff !important;
            font-size: 16px;
            user-select: none;
            text-decoration: none;
            position: relative;
            transition: opacity 0.2s ease;
        }

        .cart-btn:hover {
            opacity: 0.8;
            color: #ffffff !important;
        }

        .cart-icon {
            font-size: 24px;
        }

        .btn-login-register {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 9px 22px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-login-register:hover {
            opacity: 0.9;
            color: white;
        }

        .btn-login-register:active {
            transform: scale(0.97);
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e293b 100%) !important;
            border-radius: 0;
            color: white;
            padding: 70px 0;
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .banner-inner {
            width: 100%;
            padding: 0 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 2;
            position: relative;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .banner-content {
            flex: 1;
            max-width: 50%;
            z-index: 2;
        }

        .banner-subtitle {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            color: #38bdf8;
        }

        .banner-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .banner-desc {
            font-size: 18px;
            color: #94a3b8;
            margin-bottom: 30px;
            font-weight: 400;
        }

        .btn-shop-now {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 32px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }

        .btn-shop-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
            background: var(--accent-gradient);
            color: white;
        }

        .banner-illustration {
            width: 300px;
            height: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2;
        }

        /* MAIN CONTENT AREA */
        .store-container {
            width: 100%;
            padding: 0 80px 50px 80px;
            gap: 40px;
        }

        /* SIDEBAR CATEGORIES */
        .store-sidebar {
            width: 260px;
            flex-shrink: 0;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #0f172a;
        }

        .category-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            background: #FFFFFF;
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }

        .category-menu li {
            margin-bottom: 6px;
        }

        .category-menu li:last-child {
            margin-bottom: 0;
        }

        .category-item {
            display: block;
            padding: 12px 20px;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .category-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .category-item.active {
            background: var(--accent-gradient);
            color: #FFFFFF;
        }

        /* PRODUCT GRID */
        .store-content {
            flex-grow: 1;
        }

        .section-title {
            font-weight: 700;
            font-size: 22px;
            color: #222;
            margin-bottom: 20px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .product-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            text-align: left;
            position: relative;
            z-index: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 220px;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border-color: #cbd5e1;
        }

        .product-card-top {
            display: flex;
            gap: 15px;
            width: 100%;
            align-items: center;
        }

        .product-image-container {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 8px;
        }

        .product-image-container svg {
            max-height: 100%;
            max-width: 100%;
        }

        .product-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
        }

        .dept-tag {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2563eb;
            background: #eff6ff;
            padding: 0.25rem 0.5rem;
            border-radius: 30px;
            display: inline-block;
            margin-bottom: 0.25rem;
            width: fit-content;
        }

        .product-title {
            font-weight: 700;
            font-size: 14.5px;
            color: #0f172a;
            margin-bottom: 3px;
            line-height: 1.2;
        }

        .product-price {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .stock-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            background: #ecfdf5;
            color: #059669;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            width: fit-content;
        }

        .stock-badge.out {
            background: #fef2f2;
            color: #dc2626;
        }

        .product-card-bottom {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            margin-top: 10px;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
        }

        .qty-selector {
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            overflow: hidden;
            height: 32px;
        }

        .qty-btn {
            background: none;
            border: none;
            width: 26px;
            height: 100%;
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .qty-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .qty-input {
            width: 22px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            user-select: none;
        }

        .add-to-cart-btn-new {
            flex-grow: 1;
            height: 32px;
            border-radius: 8px;
            background: var(--accent-gradient);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .add-to-cart-btn-new:hover {
            opacity: 0.95;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }

        /* Banner Features style */
        .banner-features {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .feature-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .feature-title {
            font-weight: 700;
            font-size: 13px;
            color: #ffffff;
            line-height: 1.2;
        }
        
        .feature-sub {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.2;
            text-align: left;
        }

        /* CUSTOM MODAL OVERLAYS */
        .modal-backdrop-custom {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
        }

        .modal-custom {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            width: 420px;
            max-width: 90%;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 1001;
            display: none;
            padding: 35px 30px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-custom.active {
            transform: translate(-50%, -50%) scale(1);
        }

        .close-btn {
            position: absolute;
            top: 18px;
            right: 22px;
            background: none;
            border: none;
            font-size: 22px;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }

        .close-btn:hover {
            color: #0f172a;
        }

        .icon-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background: #eff6ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle svg path,
        .icon-circle svg rect,
        .icon-circle svg circle {
            stroke: #2563eb !important;
        }

        .modal-title {
            font-weight: 700;
            font-size: 20px;
            color: #0f172a;
        }

        .modal-text {
            font-size: 15px;
            line-height: 1.5;
            color: #64748b !important;
        }

        .btn-login-modal {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .btn-login-modal:hover {
            opacity: 0.9;
            color: white;
        }

        .btn-register-modal {
            background-color: #FFFFFF;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .btn-register-modal:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }

        .cancel-link {
            color: #777;
            font-size: 14.5px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .cancel-link:hover {
            color: #333;
            text-decoration: underline;
        }

        .custom-input {
            border-radius: 8px;
            border: 1px solid #D9DFDB;
            padding: 11px 15px;
            font-size: 14.5px;
            background-color: #FFFFFF;
            transition: all 0.2s ease;
        }

        .custom-input:focus {
            box-shadow: 0 0 0 3px rgba(76, 122, 92, 0.15);
            border-color: var(--primary-green);
        }

        .forgot-link {
            font-size: 13px;
            color: #777;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }

        /* Terms Checkbox */
        .terms-check-wrap {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            text-align: left;
            margin-bottom: 14px;
            font-size: 12.5px;
            color: #555;
            line-height: 1.5;
        }

        .terms-check-wrap input[type='checkbox'] {
            margin-top: 2px;
            flex-shrink: 0;
            accent-color: #4C7A5C;
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .terms-check-wrap a {
            color: #2C5E43;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .terms-check-wrap a:hover {
            text-decoration: underline;
        }

        /* Terms Text Modal */
        #termsModal {
            max-height: 80vh;
            overflow-y: auto;
            text-align: left;
        }

        #termsModal .terms-body {
            font-size: 13px;
            color: #444;
            line-height: 1.75;
            margin-top: 12px;
            max-height: 340px;
            overflow-y: auto;
            padding-right: 6px;
        }

        #termsModal .terms-body p {
            margin-bottom: 12px;
        }

        .footer-text {
            font-size: 14px;
            color: #666;
            margin-top: 15px;
        }

        .register-link {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link:hover {
            text-decoration: underline;
            color: var(--dark-green);
        }

        /* CART DROPDOWN */
        .cart-dropdown {
            position: absolute;
            top: 70px;
            right: 40px;
            width: 380px;
            background: #FFFFFF;
            border-radius: 28px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
            border: 1px solid #EAECEB;
            z-index: 999;
            display: none;
            padding: 24px;
            flex-direction: column;
        }

        .cart-dropdown.active {
            display: flex;
        }

        .cart-items-list {
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 5px;
        }

        .cart-item-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #F0F2F1;
        }

        .cart-item-row:last-child {
            border-bottom: none;
        }

        .cart-item-thumb {
            width: 50px;
            height: 50px;
            background: var(--card-gray);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .cart-item-thumb svg {
            max-width: 100%;
            max-height: 100%;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 2px;
        }

        .cart-item-price {
            font-weight: 700;
            font-size: 14px;
            color: #333;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            background: #EFF1F0;
            border-radius: 6px;
            padding: 3px 8px;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 600;
            user-select: none;
        }

        .qty-btn {
            cursor: pointer;
            font-weight: 800;
            color: #555;
            padding: 0 4px;
        }

        .qty-btn:hover {
            color: #000;
        }

        .cart-item-delete {
            color: #DC3545;
            cursor: pointer;
            font-size: 18px;
            margin-left: 5px;
            transition: transform 0.2s ease;
        }

        .cart-item-delete:hover {
            transform: scale(1.15);
        }

        .cart-summary {
            border-top: 1.5px solid #F0F2F1;
            padding-top: 15px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .summary-label {
            font-weight: 600;
            font-size: 15px;
            color: #333;
        }

        .summary-value {
            font-weight: 700;
            font-size: 17px;
            color: #333;
        }

        .text-end {
            text-align: right;
        }

        .summary-subtext {
            font-size: 12px;
            color: #888;
        }

        .cart-actions {
            display: flex;
            gap: 10px;
        }

        .btn-view-cart {
            flex: 1;
            background: #FFFFFF;
            border: 1px solid #D9DFDB;
            color: #333;
            border-radius: 10px;
            font-weight: 600;
            padding: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-view-cart:hover {
            background: #F8F9FA;
            border-color: #C1C9C4;
        }

        .btn-checkout-cart {
            flex: 1;
            background: var(--primary-green);
            border: none;
            color: #FFFFFF;
            border-radius: 10px;
            font-weight: 600;
            padding: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-checkout-cart:hover {
            background: var(--dark-green);
        }

        /* DROPDOWN SCROLLBAR */
        .cart-items-list::-webkit-scrollbar {
            width: 5px;
        }

        .cart-items-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .cart-items-list::-webkit-scrollbar-thumb {
            background: #d1d1d1;
            border-radius: 10px;
        }

        /* RESPONSIVE LAYOUT OVERRIDES */
        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .welcome-banner {
                padding: 20px 0;
                border-radius: 12px;
            }
            .banner-inner {
                flex-direction: column;
                align-items: flex-start;
                padding: 0 20px;
                gap: 20px;
            }

            .banner-illustration { display: none; }
            .banner-content { max-width: 100%; }

            .store-container {
                flex-direction: column;
                padding: 0 12px 30px 12px;
            }

            .store-sidebar {
                width: 100%;
                margin-bottom: 16px;
            }

            header {
                padding: 10px 14px;
                gap: 10px;
            }

            .search-container {
                width: 100%;
                max-width: none;
                order: 3;
                flex: 1 1 100%;
            }

            /* Stack header on mobile */
            header {
                flex-wrap: wrap;
            }

            .right-actions { gap: 12px; }

            .btn-login-register {
                padding: 7px 14px;
                font-size: 13px;
            }

            .logo-text { display: none; }

            /* Product grid smaller on tablet portrait */
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .product-card { padding: 10px; }

            .product-title { font-size: 12px; }

            .product-price { font-size: 13px; }

            /* Cart dropdown full width on mobile */
            .cart-dropdown {
                width: 100vw !important;
                right: -14px !important;
                border-radius: 0 0 16px 16px;
            }

            /* Category chips scroll */
            .category-chips {
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 4px;
            }
        }

        @media (max-width: 576px) {
            .product-grid {
                grid-template-columns: repeat(1, 1fr);
            }

            .welcome-banner h2 { font-size: 20px; }
            .welcome-banner p  { font-size: 13px; }
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="d-flex justify-content-between align-items-center">
        <!-- Logo -->
        <a href="index.php" class="header-logo">
            <img src="<?= $prefix; ?>EXTRA/ocart_logo.png" alt="OCart! Logo" width="48" height="48">
            <span class="logo-text">OCart!</span>
        </a>

        <!-- Search Bar -->
        <div class="search-container">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search groceries, brands, items...">
        </div>

        <!-- Right actions -->
        <div class="right-actions">
            <!-- Careers Button -->
            <a href="jobs.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1">
                <i class="bi bi-briefcase"></i> Careers
            </a>

            <!-- Cart Button -->
            <a href="javascript:void(0)" class="cart-btn" onclick="toggleCartDropdown()">
                <i class="bi bi-cart3 cart-icon"></i>
                <span class="cart-text">Cart (<span id="cartCount">0</span>)</span>
            </a>

            <!-- Cart Dropdown Panel -->
            <div class="cart-dropdown" id="cartDropdown">
                <div class="cart-items-list" id="cartItemsList">
                    <!-- Loaded dynamically via JS -->
                </div>
                <div class="cart-summary">
                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value" id="cartSubtotal">P 0.00</span>
                    </div>
                    <div class="text-end">
                        <span class="summary-subtext">12% VAT included</span>
                    </div>
                </div>
                <div class="cart-actions">
                    <button class="btn-view-cart" onclick="handleViewCart()">View Cart</button>
                    <button class="btn-checkout-cart" onclick="handleCheckout()">Checkout</button>
                </div>
            </div>

            <!-- Profile Dropdown or Login Button -->
            <?php if ($customerLoggedIn): ?>
                <div class="dropdown">
                    <div class="profile-dropdown-toggle d-flex align-items-center gap-2 text-white" id="profileDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="cursor:pointer; font-weight:600;">
                        <i class="bi bi-person-circle text-white" style="font-size:24px;"></i>
                        <span><?= htmlspecialchars($_SESSION['full_name']); ?></span>
                        <i class="bi bi-chevron-down" style="font-size:12px; color:#94a3b8;"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown"
                        style="border-radius:14px; overflow:hidden;">
                        <li><a class="dropdown-item py-2 px-3" href="javascript:void(0)" onclick="showOrderHistory()"><i class="bi bi-receipt me-2 text-success"></i>Order History</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 px-3" href="javascript:void(0)" onclick="confirmLogout()"><i
                                    class="bi bi-box-arrow-right me-2 text-danger"></i>Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <button class="btn-login-register" onclick="showLoginRequiredModal()">Login or Register</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- WELCOME BANNER (GUEST VIEW ONLY) -->
    <?php if (!$customerLoggedIn): ?>
        <section class="welcome-banner">
            <div class="banner-inner">
                <div class="banner-content">
                    <div class="banner-subtitle">Welcome to</div>
                    <h1 class="banner-title">OCart!</h1>
                    <p class="banner-desc">Fresh groceries at your fingertips.</p>
                    <button class="btn-shop-now" onclick="showLoginRequiredModal()">Shop Now</button>
                    
                    <!-- Feature Pills -->
                    <div class="banner-features">
                        <div class="feature-pill">
                            <i class="bi bi-cart text-warning fs-5"></i>
                            <div>
                                <div class="feature-title">Easy to navigate</div>
                                <div class="feature-sub">Pick Items Easily</div>
                            </div>
                        </div>
                        <div class="feature-pill">
                            <i class="bi bi-tags text-warning fs-5"></i>
                            <div>
                                <div class="feature-title">Best Prices</div>
                                <div class="feature-sub">Affordable everyday</div>
                            </div>
                        </div>
                        <div class="feature-pill">
                            <i class="bi bi-box-seam text-warning fs-5"></i>
                            <div>
                                <div class="feature-title">Wide Selection</div>
                                <div class="feature-sub">All your needs in one place</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="banner-illustration">
                    <img src="<?= $prefix; ?>EXTRA/anek.png" alt="Banner Illustration"
                        style="max-height: 380px; width: auto; object-fit: contain;">
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- MAIN BODY CONTAINER -->
    <main class="store-container d-flex">
        <!-- Sidebar Categories (Logged-in view only) -->
        <?php if ($customerLoggedIn): ?>
            <aside class="store-sidebar animate__animated animate__fadeInLeft">
                <h3 class="sidebar-title" style="margin-top: 20px;">Categories</h3>
                <ul class="category-menu">
                    <li>
                        <a href="javascript:void(0)" class="category-item active" data-category-id="all">
                            All Categories
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="javascript:void(0)" class="category-item" data-category-id="<?= $cat['category_id']; ?>">
                                <?= htmlspecialchars($cat['category_name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        <?php endif; ?>

        <!-- Product Grid List -->
        <section class="store-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 id="productSectionTitle" class="section-title mb-0" style="margin-top: 20px;">Popular Items</h3>
                <a href="javascript:void(0)" class="text-decoration-none fw-semibold" style="font-size: 14px; color: #2563eb;">View all</a>
            </div>

            <div class="product-grid" id="productGrid">
                <?php if (empty($products)): ?>
                    <div class="text-center text-muted py-5 w-100">
                        <i class="bi bi-box-seam" style="font-size: 40px;"></i>
                        <p class="mt-2">No available items at this moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                        <div class="product-card" data-category-id="<?= $prod['category_id']; ?>">
                            <div class="product-card-top" onclick="addToCart(<?= htmlspecialchars(json_encode([
                                  'id'    => $prod['product_id'],
                                  'name'  => $prod['product_name'],
                                  'price' => (float) $prod['selling_price'],
                                  'stock' => (int) $prod['stock']
                              ])); ?>)">
                                <div class="product-image-container">
                                    <?php if(!empty($prod['image'])): ?>
                                        <img src="<?= $prefix; ?>View/uploads/products/<?= htmlspecialchars($prod['image']); ?>"
                                             alt="<?= htmlspecialchars($prod['product_name']); ?>"
                                             style="width:100%;height:100%;object-fit:cover;border-radius:10px;"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                        <div style="display:none;width:100%;height:100%;">
                                            <?= getProductSVG($prod['product_name'], $prod['category_name'] ?? ''); ?>
                                        </div>
                                    <?php else: ?>
                                        <?= getProductSVG($prod['product_name'], $prod['category_name'] ?? ''); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <span class="dept-tag"><?= htmlspecialchars($prod['category_name'] ?? 'Groceries'); ?></span>
                                    <div class="product-title"><?= htmlspecialchars($prod['product_name']); ?></div>
                                    <div class="product-price">₱<?= number_format($prod['selling_price'], 2); ?></div>
                                    <div class="stock-badge <?= ($prod['stock'] <= 0) ? 'out' : ''; ?>">
                                        <i class="bi bi-dot" style="font-size: 1.4rem; line-height: 1; vertical-align: middle; margin-right: -2px;"></i>
                                        <?= ($prod['stock'] <= 0) ? 'Out of stock' : 'In stock'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="product-card-bottom">
                                <div class="qty-selector">
                                    <button class="qty-btn minus" onclick="decreaseQty(<?= $prod['product_id']; ?>, event)">-</button>
                                    <span class="qty-input" id="qty_<?= $prod['product_id']; ?>">1</span>
                                    <button class="qty-btn plus" onclick="increaseQty(<?= $prod['product_id']; ?>, <?= $prod['stock']; ?>, event)">+</button>
                                </div>
                                <button class="add-to-cart-btn-new" onclick="addWithQty(<?= $prod['product_id']; ?>, '<?= htmlspecialchars($prod['product_name']); ?>', <?= (float)$prod['selling_price']; ?>, <?= (int)$prod['stock']; ?>, event)">
                                    <i class="bi bi-cart3 me-1"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- CUSTOM MODAL BACKDROP -->
    <div class="modal-backdrop-custom" id="modalBackdrop"></div>

    <!-- DIALOG: LOGIN REQUIRED -->
    <div class="modal-custom" id="loginRequiredModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="45" height="45">
                    <rect x="30" y="44" width="40" height="34" rx="5" fill="none" stroke="#2C5E43" stroke-width="4.5" />
                    <path d="M 40,44 V 32 A 10,10 0 0,1 60,32 V 44" fill="none" stroke="#2C5E43" stroke-width="4.5"
                        stroke-linecap="round" />
                    <circle cx="50" cy="58" r="4.5" fill="#2C5E43" />
                    <path d="M 50,62.5 V 69.5" stroke="#2C5E43" stroke-width="3" stroke-linecap="round" />
                </svg>
            </div>
        </div>
        <h4 class="modal-title">Login Required!</h4>
        <p class="modal-text text-muted my-3">Please login to your account to add item<br>to ur cart</p>

        <button class="btn-login-modal w-100 py-2.5 mb-2.5" onclick="showLoginModal()">Login</button>
        <button class="btn-register-modal w-100 py-2.5 mb-3" onclick="showRegisterModal()">Register</button>
        <div>
            <a href="javascript:void(0)" class="cancel-link" onclick="closeAllModals()">Cancel</a>
        </div>
    </div>

    <!-- DIALOG: LOGIN FORM -->
    <div class="modal-custom" id="loginFormModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="45" height="45">
                    <rect x="30" y="44" width="40" height="34" rx="5" fill="none" stroke="#2C5E43" stroke-width="4.5" />
                    <path d="M 40,44 V 32 A 10,10 0 0,1 60,32 V 44" fill="none" stroke="#2C5E43" stroke-width="4.5"
                        stroke-linecap="round" />
                    <circle cx="50" cy="58" r="4.5" fill="#2C5E43" />
                    <path d="M 50,62.5 V 69.5" stroke="#2C5E43" stroke-width="3" stroke-linecap="round" />
                </svg>
            </div>
        </div>
        <p class="modal-text text-muted my-3">Please login to your account to add item<br>to ur cart</p>

        <form id="loginForm" onsubmit="handleLoginSubmit(event)">
            <div class="mb-3">
                <input type="text" name="gmail" class="form-control custom-input" placeholder="Gmail Account" required
                    id="loginUsername" pattern="[a-zA-Z0-9@_.\-]+"
                    title="Only letters, numbers, and @ _ - . are allowed"
                    oninput="this.value = this.value.replace(/[^a-zA-Z0-9@_.\-]/g, '')">
            </div>
            <div class="mb-2">
                <input type="password" name="password" class="form-control custom-input" placeholder="Password" required
                    id="loginPassword">
            </div>
            <div class="text-end mb-3">
                <a href="javascript:void(0)" class="forgot-link" onclick="handleForgotPassword(event)">Forgot
                    Password?</a>
            </div>
            <div class="terms-check-wrap">
                <input type="checkbox" id="loginTermsCheck" onchange="toggleLoginBtn()">
                <label for="loginTermsCheck">I have read and agree to the
                    <a href="javascript:void(0)" onclick="showTermsModal()">Terms and Conditions</a>
                </label>
            </div>
            <button type="submit" class="btn-login-modal w-100 py-2.5 mb-3" id="loginSubmitBtn" disabled>Login</button>
        </form>

        <div class="footer-text">
            Dont have an acc? <a href="javascript:void(0)" class="register-link" onclick="showRegisterModal()">Register
                here</a>
        </div>
    </div>

    <!-- DIALOG: REGISTER FORM -->
    <div class="modal-custom" id="registerFormModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="45" height="45" fill="none"
                    stroke="#2C5E43" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="8.5" cy="7" r="4" />
                    <line x1="20" y1="8" x2="20" y2="14" />
                    <line x1="23" y1="11" x2="17" y2="11" />
                </svg>
            </div>
        </div>
        <h4 class="modal-title">Create Account</h4>
        <p class="modal-text text-muted my-2">Please fill in details to start shopping</p>

        <form id="registerForm" onsubmit="handleRegisterSubmit(event)">
            <div class="mb-3">
                <input type="text" name="full_name" class="form-control custom-input" placeholder="Full Name" required
                    id="regFullName" pattern="[a-zA-Z\s]+" title="Full name must contain letters only"
                    oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
            </div>
            <div class="mb-3">
                <input type="text" name="gmail" class="form-control custom-input" placeholder="Gmail Account" required
                    id="regUsername" pattern="[a-zA-Z0-9@_.\-]+" title="Only letters, numbers, and @ _ - . are allowed"
                    oninput="this.value = this.value.replace(/[^a-zA-Z0-9@_.\-]/g, '')">
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control custom-input" placeholder="Password" required
                    id="regPassword" minlength="8" title="Password must be at least 8 characters long">
            </div>
            <div class="mb-3">
                <input type="password" name="confirm_password" class="form-control custom-input" placeholder="Confirm Password" required
                    id="regConfirmPassword" minlength="8" title="Please confirm your password">
            </div>
            <div class="terms-check-wrap">
                <input type="checkbox" id="regTermsCheck" onchange="toggleRegBtn()">
                <label for="regTermsCheck">I have read and agree to the
                    <a href="javascript:void(0)" onclick="showTermsModal()">Terms and Conditions</a>
                </label>
            </div>
            <button type="submit" class="btn-login-modal w-100 py-2.5 mb-3" id="regSubmitBtn" disabled>Register</button>
        </form>

        <div class="footer-text">
            Already have an acc? <a href="javascript:void(0)" class="register-link" onclick="showLoginModal()">Login
                here</a>
        </div>
    </div>

    <!-- DIALOG: OTP VERIFICATION FORM -->
    <div class="modal-custom" id="otpFormModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="45" height="45" fill="none"
                    stroke="#2C5E43" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>
        </div>
        <h4 class="modal-title">Enter OTP Code</h4>
        <p class="modal-text text-muted my-2">Please enter the 6-digit OTP code sent to your Gmail</p>

        <form id="otpForm" onsubmit="handleOtpSubmit(event)">
            <div class="mb-3">
                <input type="text" name="otp" class="form-control custom-input text-center" placeholder="6-Digit OTP"
                    required id="otpCode" maxlength="6" pattern="\d{6}" title="OTP must be exactly 6 digits"
                    style="font-size: 20px; letter-spacing: 5px; font-weight: bold;"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>
            <button type="submit" class="btn-login-modal w-100 py-2.5 mb-3">Verify &amp; Register</button>
        </form>
    </div>

    <!-- DIALOG: FORGOT PASSWORD — Enter Gmail -->
    <div class="modal-custom" id="forgotPasswordModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="45" height="45" fill="none"
                    stroke="#2C5E43" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <path d="M2 7l10 7 10-7" />
                </svg>
            </div>
        </div>
        <h4 class="modal-title">Forgot Password</h4>
        <p class="modal-text text-muted my-2">Enter your Gmail account and we'll send you a reset code</p>
        <form id="forgotPasswordForm" onsubmit="handleForgotPasswordSubmit(event)">
            <div class="mb-3">
                <input type="text" name="gmail" class="form-control custom-input" placeholder="Gmail Account" required
                    id="forgotGmail" pattern="[a-zA-Z0-9@_.\-]+" title="Only letters, numbers, and @ _ - . are allowed"
                    oninput="this.value = this.value.replace(/[^a-zA-Z0-9@_.\-]/g, '')">
            </div>
            <button type="submit" class="btn-login-modal w-100 py-2.5 mb-3" id="forgotSubmitBtn">Send Reset
                Code</button>
        </form>
        <div class="footer-text">
            Remember your password? <a href="javascript:void(0)" class="register-link" onclick="showLoginModal()">Login
                here</a>
        </div>
    </div>

    <!-- DIALOG: RESET PASSWORD — Enter OTP + New Password -->
    <div class="modal-custom" id="resetPasswordModal">
        <button class="close-btn" onclick="closeAllModals()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="45" height="45" fill="none"
                    stroke="#2C5E43" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
            </div>
        </div>
        <h4 class="modal-title">Reset Password</h4>
        <p class="modal-text text-muted my-2">Enter the OTP code sent to your Gmail and your new password</p>
        <form id="resetPasswordForm" onsubmit="handleResetPasswordSubmit(event)">
            <div class="mb-3">
                <input type="text" name="otp" class="form-control custom-input text-center" placeholder="6-Digit OTP"
                    required id="resetOtpCode" maxlength="6" pattern="\d{6}" title="OTP must be exactly 6 digits"
                    style="font-size: 18px; letter-spacing: 4px; font-weight: bold;"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>
            <div class="mb-3">
                <input type="password" name="new_password" class="form-control custom-input" placeholder="New Password"
                    required id="resetNewPass" minlength="8" title="Password must be at least 8 characters long">
            </div>
            <div class="mb-3">
                <input type="password" name="confirm_password" class="form-control custom-input"
                    placeholder="Confirm New Password" required id="resetConfirmPass" minlength="8"
                    title="Password must be at least 8 characters long">
            </div>
            <button type="submit" class="btn-login-modal w-100 py-2.5 mb-3">Reset Password</button>
        </form>
    </div>
    <div class="modal-custom" id="termsModal">
        <button class="close-btn" onclick="closeTermsModal()">&times;</button>
        <h4 class="modal-title" style="margin-bottom:6px;">Terms and Conditions</h4>
        <div class="terms-body">
            <p>By using this system, you acknowledge and agree that it is designed solely for order processing and
                reservation purposes. The checkout feature allows customers to reserve and organize their purchases
                before visiting the store, making the ordering process faster and more convenient. This system is
                intended to improve order management, reduce waiting times, and assist the store in preparing customer
                orders efficiently.</p>
            <p>Please note that this system does not provide delivery services. All orders placed through the checkout
                must be claimed at the physical store during its operating hours. Customers are responsible for visiting
                the store to pick up the items they have reserved. Placing an order through the system does not
                guarantee delivery, and failure to claim the order in person may result in its cancellation.</p>
            <p>Each checkout transaction is valid for one (1) day only and will automatically expire once the store
                closes on the same day the order was placed. Orders that have expired will no longer be processed and
                must be submitted again if the customer still wishes to purchase the items. By proceeding with the
                checkout process, you confirm that you have read, understood, and agreed to these terms and conditions.
            </p>
        </div>
        <button class="btn-login-modal w-100 mt-3" onclick="closeTermsModal()">I Understand</button>
    </div>

    <!-- DIALOG: CHECKOUT VIEW MODAL -->
    <div class="modal-custom" id="checkoutModal" style="width:520px;max-width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <button class="close-btn" onclick="closeCheckoutModal()">&times;</button>
        <h4 class="modal-title mb-1">Your Order Summary</h4>
        <p class="modal-text text-muted mb-3" style="font-size:13px;">Review your items before placing the order</p>
        <div id="checkoutItemsList" style="max-height:260px;overflow-y:auto;margin-bottom:18px;padding-right:4px;"></div>
        <div style="border-top:1.5px solid #E0E4E2;padding-top:14px;margin-bottom:18px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="font-weight:600;color:#555;">Subtotal</span>
                <span style="font-weight:700;" id="coSubtotal">₱0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="font-weight:600;color:#555;">VAT (12%)</span>
                <span style="font-weight:700;" id="coVat">₱0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #E0E4E2;">
                <span style="font-weight:800;font-size:17px;">Total</span>
                <span style="font-weight:800;font-size:17px;color:var(--dark-green);" id="coTotal">₱0.00</span>
            </div>
        </div>
        <div style="background:#FFF8E1;border-radius:10px;padding:10px 14px;font-size:12.5px;color:#7B6000;margin-bottom:18px;text-align:left;line-height:1.6;">
            <i class="bi bi-info-circle me-1"></i>
            Orders must be <strong>claimed in person</strong> at the store. No delivery. Order expires at closing time.
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-view-cart" style="flex:1;" onclick="closeCheckoutModal()">Continue Shopping</button>
            <button class="btn-checkout-cart" style="flex:1;" id="placeOrderBtn" onclick="submitCheckout()">
                <i class="bi bi-bag-check me-1"></i> Place Order
            </button>
        </div>
    </div>

    <!-- DIALOG: ORDER HISTORY MODAL -->
    <div class="modal-custom" id="orderHistoryModal" style="width:560px;max-width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <button class="close-btn" onclick="closeOrderHistoryModal()">&times;</button>
        <div class="icon-container">
            <div class="icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="#2C5E43" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
        </div>
        <h4 class="modal-title mb-1">Order History</h4>
        <p class="modal-text text-muted mb-3" style="font-size:13px;">Your past and pending orders</p>
        <div id="orderHistoryContent" style="max-height:420px;overflow-y:auto;padding-right:4px;">
            <div class="text-center text-muted py-4">
                <i class="bi bi-hourglass-split" style="font-size:28px;"></i>
                <p class="mt-2" style="font-size:13px;">Loading orders...</p>
            </div>
        </div>
    </div>

    <!-- LOCAL LIBS IMPORT -->
    <script src="<?= $prefix; ?>Assets/jquery-3.7.1.min.js"></script>
    <script src="<?= $prefix; ?>Assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $prefix; ?>Assets/sweetalert2.all.min.js"></script>
    <script src="<?= $prefix; ?>Assets/datatables.min.js"></script>

    <script>
        // Logged-in state indicator
        const isLoggedIn = <?php echo $customerLoggedIn ? 'true' : 'false'; ?>;

        // DB products with their stocks
        const dbProducts = <?php echo json_encode($products); ?>;

        // Load cart from LocalStorage
        let cart = [];
        try {
            let rawCart = JSON.parse(localStorage.getItem('sarisari_cart')) || [];
            let uniqueCart = {};
            rawCart.forEach(function (item) {
                let id = parseInt(item.id);
                if (isNaN(id)) return;
                let dbProd = dbProducts.find(p => parseInt(p.product_id) === id);
                let stock = dbProd ? parseInt(dbProd.stock) : (item.stock !== undefined ? parseInt(item.stock) : 9999);
                
                if (uniqueCart[id]) {
                    uniqueCart[id].quantity += parseInt(item.quantity);
                } else {
                    uniqueCart[id] = {
                        id: id,
                        name: item.name,
                        price: parseFloat(item.price),
                        quantity: parseInt(item.quantity),
                        stock: stock
                    };
                }
            });
            cart = Object.values(uniqueCart);
        } catch (e) {
            cart = [];
        }

        // Initialize state
        $(document).ready(function () {
            // Remove lingering cart cache if logged out
            if (!isLoggedIn) {
                localStorage.removeItem('sarisari_cart');
                cart = [];
            }
            updateCartCount();
            renderCart();

            // Real-time product search filter
            $('#searchInput').on('keyup', function () {
                let value = $(this).val().toLowerCase();
                $('#productGrid .product-card').filter(function () {
                    $(this).toggle($(this).find('.product-title').text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Sidebar category filter selector
            $('.category-item').on('click', function (e) {
                e.preventDefault();
                $('.category-item').removeClass('active');
                $(this).addClass('active');

                let categoryId = $(this).data('category-id');

                if (categoryId === 'all') {
                    $('#productGrid .product-card').show();
                    $('#productSectionTitle').text('All Products');
                } else {
                    $('#productGrid .product-card').hide();
                    $('#productGrid .product-card[data-category-id="' + categoryId + '"]').show();
                    $('#productSectionTitle').text($(this).text().trim());
                }
            });
        });

        // Toggle Cart Dropdown View
        function toggleCartDropdown() {
            $('#cartDropdown').toggleClass('active');
        }

        // Close dropdown clicking outside (stop propagation inside dropdown)
        $('#cartDropdown').on('click', function (e) {
            e.stopPropagation();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.cart-btn').length && !$(e.target).closest('#cartDropdown').length) {
                $('#cartDropdown').removeClass('active');
            }
        });

        // Scroll to items for guest Shop Now button
        function scrollToItems() {
            $('html, body').animate({
                scrollTop: $("#productSectionTitle").offset().top - 20
            }, 500);
        }


        // Modal triggers
        function showLoginRequiredModal() {
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#loginRequiredModal').show().addClass('active');
        }

        // Show Login Form modal
        function showLoginModal() {
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#loginFormModal').show().addClass('active');
        }

        // Show Register Form modal
        function showRegisterModal() {
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#registerFormModal').show().addClass('active');
        }

        // Show OTP verification modal
        function showOtpModal() {
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#otpFormModal').show().addClass('active');
        }

        function closeAllModals() {
            $('.modal-custom').removeClass('active').hide();
            $('#modalBackdrop').fadeOut(150);
            // Reset checkboxes when closing modals
            $('#loginTermsCheck').prop('checked', false);
            $('#regTermsCheck').prop('checked', false);
            $('#loginSubmitBtn').prop('disabled', true);
            $('#regSubmitBtn').prop('disabled', true);
        }

        // Terms modal - shows on top without hiding the backdrop
        function showTermsModal() {
            $('#termsModal').show().addClass('active');
        }

        function closeTermsModal() {
            $('#termsModal').removeClass('active').hide();
        }

        // Enable/disable submit buttons based on checkbox state
        function toggleLoginBtn() {
            $('#loginSubmitBtn').prop('disabled', !$('#loginTermsCheck').is(':checked'));
        }

        function toggleRegBtn() {
            $('#regSubmitBtn').prop('disabled', !$('#regTermsCheck').is(':checked'));
        }

        // Backdrop click hides modals
        $('#modalBackdrop').on('click', closeAllModals);

        // Forgot password — open the Forgot Password modal
        function handleForgotPassword(e) {
            e.preventDefault();
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#forgotPasswordForm')[0].reset();
            $('#forgotPasswordModal').show().addClass('active');
        }

        function showForgotPasswordModal() {
            closeAllModals();
            $('#modalBackdrop').fadeIn(150);
            $('#forgotPasswordForm')[0].reset();
            $('#forgotPasswordModal').show().addClass('active');
        }

        // Submit Forgot Password - send OTP to gmail
        function handleForgotPasswordSubmit(e) {
            e.preventDefault();
            let gmail = $('#forgotGmail').val();
            const btn = $('#forgotSubmitBtn');
            btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'forgot_password', gmail: gmail },
                dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).text('Send Reset Code');
                    if (res.status === 'otp_sent') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reset Code Sent!',
                            text: res.message,
                            confirmButtonColor: '#4C7A5C'
                        }).then(() => {
                            closeAllModals();
                            $('#modalBackdrop').fadeIn(150);
                            $('#resetPasswordModal').show().addClass('active');
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4C7A5C' });
                    }
                },
                error: function () {
                    btn.prop('disabled', false).text('Send Reset Code');
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error. Please try again.', confirmButtonColor: '#4C7A5C' });
                }
            });
        }

        // Submit Reset Password - verify OTP and update password
        function handleResetPasswordSubmit(e) {
            e.preventDefault();
            let otp = $('#resetOtpCode').val();
            let newPass = $('#resetNewPass').val();
            let confPass = $('#resetConfirmPass').val();

            if (newPass.length < 8) {
                Swal.fire({ icon: 'error', title: 'Invalid Password', text: 'Password must be at least 8 characters.', confirmButtonColor: '#4C7A5C' });
                return;
            }

            if (newPass !== confPass) {
                Swal.fire({ icon: 'error', title: 'Mismatch', text: 'Passwords do not match.', confirmButtonColor: '#4C7A5C' });
                return;
            }

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'reset_password', otp: otp, new_password: newPass, confirm_password: confPass },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset!',
                            text: res.message,
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            closeAllModals();
                            showLoginModal();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4C7A5C' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error. Please try again.', confirmButtonColor: '#4C7A5C' });
                }
            });
        }

        // Add to Cart Quantity Helpers
        function decreaseQty(productId, event) {
            if (event) event.stopPropagation();
            let qtySpan = document.getElementById('qty_' + productId);
            if (qtySpan) {
                let currentVal = parseInt(qtySpan.textContent) || 1;
                if (currentVal > 1) {
                    qtySpan.textContent = currentVal - 1;
                }
            }
        }

        function increaseQty(productId, maxStock, event) {
            if (event) event.stopPropagation();
            let qtySpan = document.getElementById('qty_' + productId);
            if (qtySpan) {
                let currentVal = parseInt(qtySpan.textContent) || 1;
                if (currentVal < maxStock) {
                    qtySpan.textContent = currentVal + 1;
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Limit Reached',
                        text: 'Only ' + maxStock + ' units are available in stock.',
                        confirmButtonColor: '#2563eb'
                    });
                }
            }
        }

        function addWithQty(productId, name, price, stock, event) {
            if (event) event.stopPropagation();
            if (!isLoggedIn) {
                showLoginRequiredModal();
                return;
            }
            let qtySpan = document.getElementById('qty_' + productId);
            let qty = 1;
            if (qtySpan) {
                qty = parseInt(qtySpan.textContent) || 1;
            }
            
            let prodStock = getProductStockLimit(productId, stock);
            let existing = cart.find(item => parseInt(item.id) === parseInt(productId));
            let totalQtyToRequest = qty;
            if (existing) {
                totalQtyToRequest += existing.quantity;
            }
            
            if (totalQtyToRequest > prodStock) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Out of Stock',
                    text: 'Cannot add ' + qty + ' more. Stock limit of ' + prodStock + ' reached.',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }
            
            if (existing) {
                existing.quantity += qty;
            } else {
                cart.push({
                    id: parseInt(productId),
                    name: name,
                    price: parseFloat(price),
                    quantity: qty,
                    stock: prodStock
                });
            }
            
            if (qtySpan) {
                qtySpan.textContent = '1';
            }
            
            saveCart();
            renderCart();
            
            // Cart icon pulse micro-animation
            $('.cart-btn').addClass('animate__animated animate__pulse');
            setTimeout(() => $('.cart-btn').removeClass('animate__animated animate__pulse'), 500);
            
            Swal.fire({
                icon: 'success',
                title: 'Added to Cart',
                text: qty + ' x ' + name + ' added to your cart.',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Add to Cart
        // Helper to find actual stock limit of a product
        function getProductStockLimit(id, fallbackStock) {
            let found = dbProducts.find(p => parseInt(p.product_id) === parseInt(id));
            return found ? parseInt(found.stock) : (fallbackStock !== undefined ? parseInt(fallbackStock) : 9999);
        }

        function addToCart(prod) {
            if (!isLoggedIn) {
                showLoginRequiredModal();
                return;
            }

            let prodId = parseInt(prod.id);
            let prodStock = getProductStockLimit(prodId, prod.stock);

            let existing = cart.find(item => parseInt(item.id) === prodId);
            if (existing) {
                if (existing.quantity >= prodStock) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Out of Stock',
                        text: 'Cannot add more. Limit of ' + prodStock + ' reached.',
                        confirmButtonColor: '#4C7A5C'
                    });
                    return;
                }
                existing.quantity++;
            } else {
                if (prodStock <= 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Out of Stock',
                        text: 'This item is currently out of stock.',
                        confirmButtonColor: '#4C7A5C'
                    });
                    return;
                }
                cart.push({
                    id: parseInt(prod.id),
                    name: prod.name,
                    price: parseFloat(prod.price),
                    quantity: 1,
                    stock: prodStock
                });
            }

            saveCart();
            renderCart();

            // Cart icon pulse micro-animation
            $('.cart-btn').addClass('animate__animated animate__pulse');
            setTimeout(() => $('.cart-btn').removeClass('animate__animated animate__pulse'), 500);
        }

        // Save Cart to LocalStorage
        function saveCart() {
            localStorage.setItem('sarisari_cart', JSON.stringify(cart));
            updateCartCount();
        }

        // Update Qty badge
        function updateCartCount() {
            let totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);
            $('#cartCount').text(totalQty);
        }

        // Update item quantity
        function updateQty(id, change) {
            let targetId = parseInt(id);
            let item = cart.find(i => parseInt(i.id) === targetId);
            if (!item) return;

            let newQty = item.quantity + change;
            if (newQty <= 0) {
                removeItem(id);
                return;
            }

            let stockLimit = getProductStockLimit(id, item.stock);
            if (change > 0 && item.quantity >= stockLimit) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock Limit Reached',
                    text: 'Only ' + stockLimit + ' units available in stock.',
                    confirmButtonColor: '#4C7A5C'
                });
                return;
            }

            item.quantity = newQty;
            saveCart();
            renderCart();
        }

        // Remove item row
        function removeItem(id) {
            let targetId = parseInt(id);
            cart = cart.filter(i => parseInt(i.id) !== targetId);
            saveCart();
            renderCart();
        }

        // Render Dropdown Content
        function renderCart() {
            let list = $('#cartItemsList');
            list.empty();

            if (cart.length === 0) {
                list.html(`
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-cart-x" style="font-size:32px;"></i>
                        <p class="mt-2 mb-0" style="font-size:13px;">Your cart is empty</p>
                    </div>
                `);
                $('#cartSubtotal').text('P 0.00');
                return;
            }

            let subtotal = 0;
            cart.forEach(item => {
                let rowSum = item.price * item.quantity;
                subtotal += rowSum;

                list.append(`
                    <div class="cart-item-row">
                        <div class="cart-item-thumb">
                            ${getCartItemSVG(item.name)}
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">P ${rowSum.toFixed(2)}</div>
                        </div>
                        <div class="cart-item-controls">
                            <span class="qty-btn" onclick="event.stopPropagation(); updateQty(${item.id}, -1)">-</span>
                            <span>${item.quantity}</span>
                            <span class="qty-btn" onclick="event.stopPropagation(); updateQty(${item.id}, 1)">+</span>
                        </div>
                        <i class="bi bi-trash3 cart-item-delete" onclick="event.stopPropagation(); removeItem(${item.id})"></i>
                    </div>
                `);
            });

            $('#cartSubtotal').text('P ' + subtotal.toFixed(2));
        }

        // Helper: Simplified cart item thumbnails SVG
        function getCartItemSVG(name) {
            name = name.toLowerCase();
            if (name.includes('coke') || name.includes('cola')) {
                return '<svg viewBox="0 0 100 200" width="18" height="35"><path d="M 45,15 H 55 V 30 C 55,30 58,40 62,50 C 66,60 65,75 65,75 L 67,110 C 67,110 70,120 70,140 C 70,160 68,185 60,195 C 55,200 45,200 40,195 C 32,185 30,160 30,140 C 30,120 33,110 33,110 L 35,75 C 35,75 34,60 38,50 C 42,40 45,30 45,30 Z" fill="#C62828" /><rect x="43" y="5" width="14" height="10" rx="2" fill="#E53935" /><path d="M 33,90 H 67 V 125 H 33 Z" fill="#D32F2F" /></svg>';
            }
            if (name.includes('detergent') || name.includes('soap') || name.includes('wash')) {
                return '<svg viewBox="0 0 100 200" width="18" height="35"><path d="M 35,40 H 65 V 60 Q 75,70 75,90 V 170 Q 75,190 55,190 H 45 Q 25,190 25,170 V 90 Q 25,70 35,40 Z" fill="#81C784" /><rect x="42" y="20" width="16" height="20" fill="#4CAF50" /></svg>';
            }
            if (name.includes('beef') || name.includes('tuna') || name.includes('food')) {
                return '<svg viewBox="0 0 100 150" width="22" height="30"><ellipse cx="50" cy="20" rx="25" ry="10" fill="#CFD8DC" stroke="#78909C" stroke-width="1.5" /><path d="M 25,20 V 100 A 25,10 rx 0 0 0 75,100 V 20 Z" fill="#ECEFF1" stroke="#78909C" stroke-width="1.5" /><path d="M 25,35 H 75 V 85 H 25 Z" fill="#E53935" /></svg>';
            }
            if (name.includes('rexona') || name.includes('shampoo')) {
                return '<svg viewBox="0 0 100 150" width="18" height="25"><path d="M 35,45 C 35,45 35,25 50,25 C 65,25 65,45 65,45 V 130 C 65,135 60,140 50,140 Z" fill="#EEEEEE" stroke="#B0BEC5" stroke-width="1.5" /><path d="M 35,60 H 65 V 105 H 35 Z" fill="#1E88E5" /></svg>';
            }
            return '<svg viewBox="0 0 100 120" width="22" height="25"><path d="M 50,15 L 85,32 L 85,78 L 50,95 L 15,78 Z" fill="#FFE082" stroke="#FFB300" stroke-width="1.5" /></svg>';
        }

        // Form Submission - AJAX Register
        function handleRegisterSubmit(e) {
            e.preventDefault();
            let fullName = $('#regFullName').val();
            let gmail = $('#regUsername').val();
            let password = $('#regPassword').val();
            let confirmPassword = $('#regConfirmPassword').val();

            if (password.length < 8) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Password',
                    text: 'Password must be at least 8 characters.',
                    confirmButtonColor: '#4C7A5C'
                });
                return;
            }

            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Mismatch',
                    text: 'Passwords do not match.',
                    confirmButtonColor: '#4C7A5C'
                });
                return;
            }

            const submitBtn = $('#registerForm button[type="submit"]');
            const originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text('Sending OTP...');

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: {
                    action: 'register',
                    full_name: fullName,
                    gmail: gmail,
                    password: password,
                    confirm_password: confirmPassword
                },
                dataType: 'json',
                success: function (res) {
                    submitBtn.prop('disabled', false).text(originalText);
                    if (res.status === 'otp_sent') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Verification Needed',
                            text: res.message,
                            confirmButtonColor: '#4C7A5C'
                        }).then(() => {
                            showOtpModal();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Registration Failed',
                            text: res.message,
                            confirmButtonColor: '#4C7A5C'
                        });
                    }
                },
                error: function () {
                    submitBtn.prop('disabled', false).text(originalText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred during account creation. Try again.',
                        confirmButtonColor: '#4C7A5C'
                    });
                }
            });
        }

        // Verify registration OTP submission
        function handleOtpSubmit(e) {
            e.preventDefault();
            let otp = $('#otpCode').val().trim();
            const submitBtn = $('#otpForm button[type="submit"]');
            submitBtn.prop('disabled', true).text('Verifying...');

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'verify_otp', otp: otp },
                success: function (raw) {
                    submitBtn.prop('disabled', false).text('Verify & Register');
                    let res;
                    try {
                        // Strip any stray whitespace/output before JSON
                        const clean = raw.trim().replace(/^[^{[]*/, '');
                        res = JSON.parse(clean);
                    } catch(err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Unexpected server response. Raw: ' + raw.substring(0, 200), confirmButtonColor: '#4C7A5C' });
                        return;
                    }
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Verified & Registered!',
                            text: 'Welcome, ' + res.user.full_name + '!',
                            showConfirmButton: false,
                            timer: 1600
                        }).then(() => { location.reload(); });
                    } else if (res.status === 'blocked') {
                        closeAllModals();
                        Swal.fire({ icon: 'error', title: 'Blocked', text: res.message, confirmButtonColor: '#4C7A5C' });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Verification Failed', text: res.message, confirmButtonColor: '#4C7A5C' });
                    }
                },
                error: function (xhr) {
                    submitBtn.prop('disabled', false).text('Verify & Register');
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Server error ' + xhr.status + ': ' + xhr.responseText.substring(0, 200), confirmButtonColor: '#4C7A5C' });
                }
            });
        }

        // Form Submission - AJAX Login
        function handleLoginSubmit(e) {
            e.preventDefault();
            let gmail = $('#loginUsername').val();
            let password = $('#loginPassword').val();

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'login', gmail: gmail, password: password },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Welcome Back!',
                            text: 'Logged in as ' + res.user.full_name,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Login Failed', text: res.message, confirmButtonColor: '#4C7A5C' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error. Please check database connection.', confirmButtonColor: '#4C7A5C' });
                }
            });
        }

        // ── CHECKOUT MODAL ──────────────────────────────────────────
        function closeCheckoutModal() {
            $('#checkoutModal').removeClass('active').hide();
            $('#modalBackdrop').fadeOut(150);
        }

        function handleViewCart() {
            if (cart.length === 0) {
                Swal.fire({ icon: 'info', title: 'Cart is Empty', text: 'Add some items before viewing your cart.', confirmButtonColor: '#4C7A5C' });
                return;
            }
            $('#cartDropdown').removeClass('active');
            let itemsHtml = '';
            let subtotal = 0;
            cart.forEach(item => {
                let rowSum = item.price * item.quantity;
                subtotal += rowSum;
                itemsHtml += `
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F0F2F1;">
                        <div style="width:46px;height:46px;background:#EFF1F0;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            ${getCartItemSVG(item.name)}
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:14px;">${item.name}</div>
                            <div style="font-size:12px;color:#888;">₱${item.price.toFixed(2)} × ${item.quantity}</div>
                        </div>
                        <div style="font-weight:700;font-size:14px;color:#333;">₱${rowSum.toFixed(2)}</div>
                    </div>`;
            });
            let vat = subtotal * 0.12;
            let total = subtotal + vat;
            $('#checkoutItemsList').html(itemsHtml);
            $('#coSubtotal').text('₱' + subtotal.toFixed(2));
            $('#coVat').text('₱' + vat.toFixed(2));
            $('#coTotal').text('₱' + total.toFixed(2));
            $('#modalBackdrop').fadeIn(150);
            $('#checkoutModal').show().addClass('active');
        }

        function handleCheckout() { handleViewCart(); }

        function submitCheckout() {
            if (cart.length === 0) return;
            const btn = $('#placeOrderBtn');
            btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Placing Order...');
            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'checkout', cart: JSON.stringify(cart) },
                dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html('<i class="bi bi-bag-check me-1"></i> Place Order');
                    if (res.status === 'success') {
                        closeCheckoutModal();
                        Swal.fire({
                            icon: 'success',
                            title: 'Order Placed!',
                            html: `<p>${res.message}</p><p style="font-size:13px;color:#888;">Order #${res.order_id} · Total: <strong>₱${parseFloat(res.total).toFixed(2)}</strong></p>`,
                            confirmButtonColor: '#4C7A5C'
                        }).then(() => {
                            localStorage.removeItem('sarisari_cart');
                            cart = [];
                            location.reload();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Checkout Failed', text: res.message, confirmButtonColor: '#4C7A5C' });
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html('<i class="bi bi-bag-check me-1"></i> Place Order');
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to process checkout. Try again.', confirmButtonColor: '#4C7A5C' });
                }
            });
        }

        // ── ORDER HISTORY ────────────────────────────────────────────
        function closeOrderHistoryModal() {
            $('#orderHistoryModal').removeClass('active').hide();
            $('#modalBackdrop').fadeOut(150);
        }

        const statusColors = {
            'Pending':   { bg: '#FFF8E1', color: '#F57F17', border: '#FFE082' },
            'Approved':  { bg: '#E8F5E9', color: '#2E7D32', border: '#A5D6A7' },
            'Completed': { bg: '#E3F2FD', color: '#1565C0', border: '#90CAF9' },
            'Cancelled': { bg: '#FFEBEE', color: '#C62828', border: '#EF9A9A' },
        };

        function showOrderHistory() {
            $('#modalBackdrop').fadeIn(150);
            $('#orderHistoryModal').show().addClass('active');
            $('#orderHistoryContent').html(`
                <div class="text-center text-muted py-4">
                    <i class="bi bi-hourglass-split" style="font-size:28px;"></i>
                    <p class="mt-2" style="font-size:13px;">Loading orders...</p>
                </div>`);
            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { action: 'get_order_history' },
                dataType: 'json',
                success: function (res) {
                    if (res.status !== 'success' || res.orders.length === 0) {
                        $('#orderHistoryContent').html(`
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-bag-x" style="font-size:38px;color:#ccc;"></i>
                                <p class="mt-3" style="font-size:14px;">No orders yet.</p>
                                <p style="font-size:12px;color:#aaa;">Your checkout history will appear here.</p>
                            </div>`);
                        return;
                    }
                    let html = '';
                    res.orders.forEach(order => {
                        let sc = statusColors[order.status] || { bg: '#F5F5F5', color: '#555', border: '#ddd' };
                        let date = new Date(order.created_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
                        let itemsHtml = order.items.map(it =>
                            `<div style="display:flex;justify-content:space-between;font-size:12.5px;color:#555;padding:3px 0;">
                                <span>${it.product_name} × ${it.quantity}</span>
                                <span>₱${parseFloat(it.subtotal).toFixed(2)}</span>
                            </div>`
                        ).join('');
                        html += `
                        <div style="border:1px solid #E8EAE9;border-radius:16px;padding:16px 18px;margin-bottom:14px;background:#FAFBFA;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <div>
                                    <span style="font-weight:800;font-size:14px;">Order #${order.order_id}</span>
                                    <div style="font-size:11.5px;color:#888;margin-top:2px;">${date}</div>
                                </div>
                                <span style="background:${sc.bg};color:${sc.color};border:1px solid ${sc.border};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
                                    ${order.status}
                                </span>
                            </div>
                            <div style="border-top:1px solid #EAECEA;padding-top:10px;margin-bottom:10px;">
                                ${itemsHtml}
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:13px;color:#777;">
                                <span>VAT (12%): ₱${parseFloat(order.tax).toFixed(2)}</span>
                                <span style="font-weight:800;font-size:15px;color:var(--dark-green);">Total: ₱${parseFloat(order.total).toFixed(2)}</span>
                            </div>
                        </div>`;
                    });
                    $('#orderHistoryContent').html(html);
                },
                error: function () {
                    $('#orderHistoryContent').html(`<div class="text-center text-danger py-4">Failed to load orders. Try again.</div>`);
                }
            });
        }

        // Logout confirmation
        function confirmLogout() {
            Swal.fire({
                title: 'Log Out?',
                text: 'Are you sure you want to log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#C0392B',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Yes, Log Out',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    localStorage.removeItem('sarisari_cart');
                    window.location.replace(window.location.pathname + '?logout=1');
                }
            });
        }
    </script>
</body>

</html>