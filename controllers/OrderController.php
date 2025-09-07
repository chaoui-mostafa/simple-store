<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/ProductController.php'; // CORRECTED PATH

class OrderController {
    private PDO $conn;

    public function __construct(?PDO $conn = null) {
        if ($conn) {
            $this->conn = $conn;
        } else {
            $db = new Database();
            $this->conn = $db->connect();
        }
    }

    // توليد order_code بحال ord-0002536
    private function generateOrderCode($id): string {
        return 'ord-' . str_pad($id, 7, '0', STR_PAD_LEFT);
    }

    // ✅ Create new order
    public function createOrder(array $data): array {
        try {
            $product_id        = (int) filter_var($data['product_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
            $quantity          = max(1, (int) filter_var($data['quantity'] ?? 1, FILTER_SANITIZE_NUMBER_INT));
            $customer_name     = trim($data['customer_name'] ?? '');
            $customer_email    = filter_var(trim($data['customer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $customer_phone    = trim($data['customer_phone'] ?? '');
            $customer_whatsapp = trim($data['customer_whatsapp'] ?? '');
            $customer_city     = trim($data['customer_city'] ?? '');
            $customer_state    = trim($data['customer_state'] ?? '');
            $customer_zipcode  = trim($data['customer_zipcode'] ?? '');
            $customer_country  = trim($data['customer_country'] ?? '');
            $customer_address  = trim($data['customer_address'] ?? '');
            $customer_notes    = trim($data['customer_notes'] ?? '');

            // Validate required fields (allow 0 for zipcode, allow phone/whatsapp to be optional)
            $required = [$customer_name, $customer_city, $customer_country, $customer_address];
            foreach ($required as $field) {
                if ($field === '') {
                    return ['success' => false, 'message' => 'Please fill in all required fields.'];
                }
            }
            // if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            //     return ['success' => false, 'message' => 'Please provide a valid email address.'];
            // }

            $productController = new ProductController($this->conn);
            $this->conn->beginTransaction();

            // Check product availability
            $product = $productController->getProductById($product_id);
            if (!$product) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Selected product not found.'];
            }
            if ((int)$product['quantity'] < $quantity) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Insufficient stock for ' . $product['name'] . '.'];
            }

            // Insert order (بدون order_code مؤقتاً)
            $stmt = $this->conn->prepare("
                INSERT INTO orders 
                (session_id, product_id, quantity, customer_name, customer_email, customer_phone, customer_whatsapp, 
                 customer_city, customer_state, customer_zipcode, customer_country, customer_address, customer_notes) 
                VALUES 
                (:session_id, :product_id, :quantity, :customer_name, :customer_email, :customer_phone, :customer_whatsapp, 
                 :customer_city, :customer_state, :customer_zipcode, :customer_country, :customer_address, :customer_notes)
            ");
            $session_id = session_id();
            $stmt->execute([
                ':session_id' => $session_id,
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':customer_name' => $customer_name,
                ':customer_email' => $customer_email,
                ':customer_phone' => $customer_phone,
                ':customer_whatsapp' => $customer_whatsapp,
                ':customer_city' => $customer_city,
                ':customer_state' => $customer_state,
                ':customer_zipcode' => $customer_zipcode,
                ':customer_country' => $customer_country,
                ':customer_address' => $customer_address,
                ':customer_notes' => $customer_notes
            ]);

            // Get last insert ID
            $orderId = (int) $this->conn->lastInsertId();

            // Generate order_code
            $orderCode = $this->generateOrderCode($orderId);

            // Update order_code
            $update = $this->conn->prepare("UPDATE orders SET order_code = :order_code WHERE id = :id");
            $update->execute([
                ':order_code' => $orderCode,
                ':id' => $orderId
            ]);

            // Update stock
            if (!$productController->updateProductQuantity($product_id, $quantity)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Failed to update product inventory.'];
            }

            $this->conn->commit();

            // ✅ Save order in session for success.php
            $_SESSION['order_success'] = true;
            $_SESSION['order_id'] = $orderId;
            $_SESSION['order_code'] = $orderCode;

            return ['success' => true, 'order_id' => $orderId, 'order_code' => $orderCode];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("OrderController:createOrder Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }

    // Get single order by ID
    public function getOrder($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT o.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching order: " . $e->getMessage());
            return null;
        }
    }

    // ✅ Get order by order code
    public function getOrderByCode($orderCode) {
        try {
            $stmt = $this->conn->prepare("
                SELECT o.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.order_code = :order_code
            ");
            $stmt->bindParam(':order_code', $orderCode);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching order by code: " . $e->getMessage());
            return null;
        }
    }

    // Update order
    public function updateOrder($id, $data) {
        try {
            $customer_name = htmlspecialchars(strip_tags($data['customer_name']));
            $customer_email = htmlspecialchars(strip_tags($data['customer_email']));
            $customer_phone = htmlspecialchars(strip_tags($data['customer_phone']));
            $customer_address = htmlspecialchars(strip_tags($data['customer_address']));
            $customer_notes = htmlspecialchars(strip_tags($data['customer_notes']));

            $query = "UPDATE orders 
                      SET customer_name = :customer_name, 
                          customer_email = :customer_email, 
                          customer_phone = :customer_phone,
                          customer_address = :customer_address, 
                          customer_notes = :customer_notes
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':customer_name' => $customer_name,
                ':customer_email' => $customer_email,
                ':customer_phone' => $customer_phone,
                ':customer_address' => $customer_address,
                ':customer_notes' => $customer_notes,
                ':id' => $id
            ]);

            $_SESSION['success'] = 'Order updated successfully.';
            return true;
        } catch(PDOException $e) {
            error_log("Error updating order: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to update order. Please try again later.';
            return false;
        }
    }

    // Delete order
    public function deleteOrder($id) {
        try {
            $query = "DELETE FROM orders WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                $_SESSION['success'] = 'Order deleted successfully.';
                return true;
            }
        } catch(PDOException $e) {
            error_log("Error deleting order: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete order. Please try again later.';
        }
        return false;
    }

    // Get orders for current user session
    public function getUserOrders($sessionId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT o.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.session_id = :session_id
                ORDER BY o.created_at DESC
            ");
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching user orders: " . $e->getMessage());
            return [];
        }
    }

    // ✅ Update order status (⚡ this fixes your error)
    public function updateOrderStatus($orderId, $status) {
        $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    }

    // Ensure status column exists in orders table
    public function ensureStatusColumnExists() {
        try {
            // Check if status column exists
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM orders LIKE 'status'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // Add status column if it doesn't exist
                $alter = $this->conn->prepare("ALTER TABLE orders ADD status VARCHAR(20) DEFAULT 'pending'");
                $alter->execute();
                error_log("Added status column to orders table");
            }
        } catch(PDOException $e) {
            error_log("Error ensuring status column exists: " . $e->getMessage());
        }
    }

    // Get order items for a specific order
    public function getOrderItems($orderId) {
        try {
            // Since your database doesn't have an order_items table yet,
            // we'll get the product info from the orders table joined with products
            $stmt = $this->conn->prepare("
                SELECT 
                    o.id as order_id,
                    o.quantity,
                    o.product_id,
                    p.name AS product_name, 
                    p.price AS product_price, 
                    p.image AS product_image,
                    (o.quantity * p.price) as item_total
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.id = :order_id
            ");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Format as array to match the expected structure
            return $result ? [$result] : [];
        } catch(PDOException $e) {
            error_log("Error fetching order items: " . $e->getMessage());
            return [];
        }
    }

    // Get user information
    public function getUserInfo($userId) {
        try {
            // You'll need to create a users table or use the orders table to get the latest info
            $stmt = $this->conn->prepare("
                SELECT 
                    customer_name as name,
                    customer_email as email,
                    customer_phone as phone,
                    customer_address as address,
                    customer_city as city,
                    customer_state as state,
                    customer_zipcode as zipcode,
                    customer_country as country
                FROM orders 
                WHERE session_id = :session_id 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bindParam(':session_id', $_SESSION['session_id']);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : [];
        } catch(PDOException $e) {
            error_log("Error fetching user info: " . $e->getMessage());
            return [];
        }
    }

    // Update user information
    public function updateUserInfo($userId, $name, $email, $phone, $address, $city, $state, $zipcode, $country) {
        try {
            // In a real application, you would update a users table
            // For now, we'll update the session information
            $_SESSION['user_info'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zipcode' => $zipcode,
                'country' => $country
            ];
            
            return true;
        } catch(PDOException $e) {
            error_log("Error updating user info: " . $e->getMessage());
            return false;
        }
    }

    // Get user orders with pagination support
    public function getUserOrdersWithPagination($sessionId, $perPage = 5, $offset = 0) {
        try {
            // Get orders
            $stmt = $this->conn->prepare("
                SELECT o.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.session_id = :session_id
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $this->conn->prepare("
                SELECT COUNT(*) as total 
                FROM orders 
                WHERE session_id = :session_id
            ");
            $countStmt->bindParam(':session_id', $sessionId);
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'orders' => $orders,
                'total' => $total
            ];
        } catch(PDOException $e) {
            error_log("Error fetching user orders: " . $e->getMessage());
            return ['orders' => [], 'total' => 0];
        }
    }

    // Get order details for AJAX request
    public function getOrderDetails($orderId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT o.*, p.name AS product_name, p.price AS product_price, p.image AS product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.id = :id
            ");
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Get order items (in your current structure, each order has one product)
                $order['items'] = [
                    [
                        'product_id' => $order['product_id'],
                        'product_name' => $order['product_name'],
                        'product_price' => $order['product_price'],
                        'product_image' => $order['product_image'],
                        'quantity' => $order['quantity']
                    ]
                ];
            }
        
            return $order;
        } catch(PDOException $e) {
            error_log("Error fetching order details: " . $e->getMessage());
            return null;
        }
    }

    // Get all orders with filters
    public function getAllOrders($phoneFilter = '', $minOrdersFilter = '', $codeFilter = '', $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT o.*, p.name as product_name, p.price as product_price, p.image as product_image 
                FROM orders o 
                LEFT JOIN products p ON o.product_id = p.id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($phoneFilter)) {
            $sql .= " AND o.customer_phone LIKE ?";
            $params[] = "%$phoneFilter%";
        }
        
        if (!empty($codeFilter)) {
            $sql .= " AND o.order_code LIKE ?";
            $params[] = "%$codeFilter%";
        }
        
        // For min_orders filter, we need to find customers with at least X orders
        if (!empty($minOrdersFilter)) {
            $sql .= " AND o.customer_phone IN (
                        SELECT customer_phone 
                        FROM orders 
                        GROUP BY customer_phone 
                        HAVING COUNT(*) >= ?
                    )";
            $params[] = $minOrdersFilter;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SQL Error in getAllOrders: " . $e->getMessage());
            error_log("SQL Query: " . $sql);
            return [];
        }
    }

    // Count total orders for pagination
    public function countAllOrders($phoneFilter = '', $minOrdersFilter = '', $codeFilter = '') {
        $sql = "SELECT COUNT(*) as total 
                FROM orders o 
                WHERE 1=1";
        $params = [];
        
        if (!empty($phoneFilter)) {
            $sql .= " AND o.customer_phone LIKE ?";
            $params[] = "%$phoneFilter%";
        }
        
        if (!empty($codeFilter)) {
            $sql .= " AND o.order_code LIKE ?";
            $params[] = "%$codeFilter%";
        }
        
        // For min_orders filter, we need to find customers with at least X orders
        if (!empty($minOrdersFilter)) {
            $sql .= " AND o.customer_phone IN (
                        SELECT customer_phone 
                        FROM orders 
                        GROUP BY customer_phone 
                        HAVING COUNT(*) >= ?
                    )";
            $params[] = $minOrdersFilter;
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("SQL Error in countAllOrders: " . $e->getMessage());
            error_log("SQL Query: " . $sql);
            return 0;
        }
    }
    /**
     * Update customer information for a specific order.
     *
     * @param int $orderId
     * @param array $customerData
     * @return bool
     */
    public function updateCustomerInfo($orderId, $customerData) {
        $sql = "UPDATE orders SET 
            customer_name = :customer_name,
            customer_email = :customer_email,
            customer_phone = :customer_phone,
            customer_whatsapp = :customer_whatsapp,
            customer_address = :customer_address,
            customer_city = :customer_city,
            customer_state = :customer_state,
            customer_zipcode = :customer_zipcode,
            customer_country = :customer_country,
            customer_notes = :customer_notes
            WHERE id = :order_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':customer_name' => $customerData['customer_name'],
            ':customer_email' => $customerData['customer_email'],
            ':customer_phone' => $customerData['customer_phone'],
            ':customer_whatsapp' => $customerData['customer_whatsapp'],
            ':customer_address' => $customerData['customer_address'],
            ':customer_city' => $customerData['customer_city'],
            ':customer_state' => $customerData['customer_state'],
            ':customer_zipcode' => $customerData['customer_zipcode'],
            ':customer_country' => $customerData['customer_country'],
            ':customer_notes' => $customerData['customer_notes'],
            ':order_id' => $orderId
        ]);
    }
}