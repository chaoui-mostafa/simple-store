<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

class CartController {
    private PDO $conn;
    private string $session_id;

    // Accept optional PDO connection
    public function __construct(?PDO $conn = null) {
        if ($conn instanceof PDO) {
            $this->conn = $conn;
        } else {
            $db = new Database();
            $this->conn = $db->connect();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->session_id = session_id();
    }

    // Add item to cart
    public function addToCart(int $product_id, int $quantity = 1): bool {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM cart 
                WHERE user_session = :session_id AND product_id = :product_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $existingItem = $stmt->fetch();

            if ($existingItem) {
                $stmt = $this->conn->prepare("
                    UPDATE cart 
                    SET quantity = quantity + :quantity 
                    WHERE user_session = :session_id AND product_id = :product_id
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO cart (user_session, product_id, quantity) 
                    VALUES (:session_id, :product_id, :quantity)
                ");
            }

            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Product added to cart successfully!";
                return true;
            } else {
                $_SESSION['error'] = "Failed to add product to cart.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("CartController:addToCart - " . $e->getMessage());
            $_SESSION['error'] = "Failed to add product to cart. Please try again.";
            return false;
        }
    }

    // Get all cart items
    public function getCartItems(): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.*, p.name, p.price, p.image, p.quantity as stock_quantity 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.user_session = :session_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("CartController:getCartItems - " . $e->getMessage());
            return [];
        }
    }

    // Update cart item quantity
    public function updateCartItem(int $product_id, int $quantity): bool {
        try {
            if ($quantity <= 0) {
                return $this->removeFromCart($product_id);
            }

            $stmt = $this->conn->prepare("
                UPDATE cart 
                SET quantity = :quantity 
                WHERE user_session = :session_id AND product_id = :product_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Cart updated successfully!";
                return true;
            } else {
                $_SESSION['error'] = "Failed to update cart.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("CartController:updateCartItem - " . $e->getMessage());
            $_SESSION['error'] = "Failed to update cart. Please try again.";
            return false;
        }
    }

    // Remove item from cart
    public function removeFromCart(int $product_id): bool {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM cart 
                WHERE user_session = :session_id AND product_id = :product_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Item removed from cart.";
                return true;
            } else {
                $_SESSION['error'] = "Failed to remove item.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("CartController:removeFromCart - " . $e->getMessage());
            $_SESSION['error'] = "Failed to remove item. Please try again.";
            return false;
        }
    }

    // Clear all cart
    public function clearCart(): bool {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM cart 
                WHERE user_session = :session_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Cart cleared successfully.";
                return true;
            } else {
                $_SESSION['error'] = "Failed to clear cart.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("CartController:clearCart - " . $e->getMessage());
            $_SESSION['error'] = "Failed to clear cart. Please try again.";
            return false;
        }
    }

    // Get total price
    public function getCartTotal(): float {
        $items = $this->getCartItems();
        $total = 0.0;
        foreach ($items as $item) {
            $total += ((float)$item['price']) * ((int)$item['quantity']);
        }
        return (float)$total;
    }

    // Get total items count
    public function getCartCount(): int {
        try {
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(quantity),0) as total 
                FROM cart 
                WHERE user_session = :session_id
            ");
            $stmt->bindParam(':session_id', $this->session_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("CartController:getCartCount - " . $e->getMessage());
            return 0;
        }
    }
}
