<?php
// require_once '../config/db.php';
require_once 'ProductController.php';
require_once 'OrderController.php';
require_once 'CartController.php';

class AdminController {
    private $conn;
    private $productController;
    private $orderController;
    private $cartController;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
        $this->productController = new ProductController($this->conn);
        $this->orderController = new OrderController($this->conn);
        $this->cartController = new CartController($this->conn);
    }

    // Admin login
    public function login($username, $password) {
        $username = htmlspecialchars(trim($username));

        try {
            $stmt = $this->conn->prepare("SELECT * FROM admins WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                return true;
            } else {
                $_SESSION['error'] = 'Nom d\'utilisateur ou mot de passe invalide.';
            }
        } catch(PDOException $e) {
            error_log("Error during login: " . $e->getMessage());
            $_SESSION['error'] = 'Échec de la connexion. Veuillez réessayer plus tard.';
        }

        return false;
    }

    // Get all admins
    public function getAllAdmins() {
        try {
            $stmt = $this->conn->query("SELECT id, username, created_at FROM admins ORDER BY id DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching admins: " . $e->getMessage());
            return [];
        }
    }

    // Create new admin
    public function createAdmin($username, $password) {
        $username = htmlspecialchars(trim($username));

        // Check if username already exists
        if ($this->adminExists($username)) {
            $_SESSION['error'] = 'Ce nom d\'utilisateur existe déjà.';
            return false;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->conn->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password_hash', $password_hash);

            if ($stmt->execute()) {
                $_SESSION['success'] = 'Administrateur créé avec succès.';
                return true;
            }
        } catch(PDOException $e) {
            error_log("Error creating admin: " . $e->getMessage());
            $_SESSION['error'] = 'Échec de la création de l\'administrateur. Veuillez réessayer plus tard.';
        }

        return false;
    }

    // Check if admin exists
    private function adminExists($username) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Error checking admin existence: " . $e->getMessage());
            return false;
        }
    }

    // Delete admin
    public function deleteAdmin($id) {
        // Prevent deleting the main admin (ID 1) or currently logged in admin
        if ($id == 1 || $id == ($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['error'] = 'Impossible de supprimer ce compte administrateur.';
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM admins WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success'] = 'Administrateur supprimé avec succès.';
                return true;
            }
        } catch(PDOException $e) {
            error_log("Error deleting admin: " . $e->getMessage());
            $_SESSION['error'] = 'Échec de la suppression de l\'administrateur. Veuillez réessayer plus tard.';
        }

        return false;
    }

    // Change admin password
    public function changePassword($id, $currentPassword, $newPassword) {
        try {
            $stmt = $this->conn->prepare("SELECT password_hash FROM admins WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
                $_SESSION['error'] = 'Le mot de passe actuel est incorrect.';
                return false;
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE admins SET password_hash = :password_hash WHERE id = :id");
            $stmt->bindParam(':password_hash', $newPasswordHash);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success'] = 'Mot de passe mis à jour avec succès.';
                return true;
            }
        } catch(PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            $_SESSION['error'] = 'Échec de la modification du mot de passe. Veuillez réessayer plus tard.';
        }

        return false;
    }

    // --- PRODUCT MANAGEMENT ---
    public function getAllProducts() {
        return $this->productController->getAllProducts();
    }

    public function addProduct($data, $files) {
        return $this->productController->addProduct($data, $files);
    }

    public function updateProduct($id, $data, $files) {
        return $this->productController->updateProduct($id, $data, $files);
    }

    public function deleteProduct($id) {
        return $this->productController->deleteProduct($id);
    }

    // --- PRODUCT IMAGES MANAGEMENT ---
    public function getProductImages($productId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id DESC");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching product images: " . $e->getMessage());
            return [];
        }
    }

    public function addProductImages($productId, $files) {
        if (!isset($files['product_images'])) {
            $_SESSION['error'] = "Aucune image sélectionnée.";
            return false;
        }

        // CHEMIN CORRIGÉ : C:\laragon\www\test\simple store\assets\images\product_images\
        $uploadDir = __DIR__ . '/../../assets/images/product_images/';

        // Création du dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedCount = 0;
        
        foreach ($files['product_images']['tmp_name'] as $key => $tmpName) {
            if ($files['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($files['product_images']['name'][$key]);
                $safeName = preg_replace("/[^A-Za-z0-9_\-\.]/", "_", $originalName);
                $fileName = uniqid('prod_') . '_' . $safeName;
                $uploadFile = $uploadDir . $fileName;

                // Vérifier le type MIME
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($tmpName);

                if (in_array($fileType, $allowedTypes)) {
                    // Vérifier la taille du fichier (max 2MB)
                    if ($files['product_images']['size'][$key] > 2097152) {
                        $_SESSION['error'] = "L'image " . $originalName . " est trop volumineuse. Taille max: 2MB";
                        continue;
                    }

                    if (move_uploaded_file($tmpName, $uploadFile)) {
                        // Enregistrer dans la base
                        $stmt = $this->conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                        $stmt->execute([$productId, $fileName]);
                        $uploadedCount++;
                    }
                }
            }
        }

        if ($uploadedCount > 0) {
            $_SESSION['success'] = $uploadedCount . " image(s) ajoutée(s) avec succès !";
            return true;
        } else {
            $_SESSION['error'] = "Aucune image n'a pu être téléchargée.";
            return false;
        }
    }

    public function deleteProductImage($imageId) {
        try {
            $stmt = $this->conn->prepare("SELECT image_path FROM product_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();

            if ($image) {
                // CHEMIN CORRIGÉ : C:\laragon\www\test\simple store\assets\images\product_images\
                $filePath = __DIR__ . '/../../assets/images/product_images/' . $image['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $stmt = $this->conn->prepare("DELETE FROM product_images WHERE id = ?");
                $stmt->execute([$imageId]);

                $_SESSION['success'] = "Image supprimée avec succès !";
                return true;
            } else {
                $_SESSION['error'] = "Image non trouvée.";
                return false;
            }
        } catch(PDOException $e) {
            error_log("Error deleting product image: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de la suppression de l'image.";
            return false;
        }
    }

    // --- ORDER MANAGEMENT ---
    public function getAllOrders() {
        return $this->orderController->getAllOrders();
    }

    public function getOrderBySession($sessionId) {
        return $this->orderController->getUserOrders($sessionId);
    }

    // --- CART MANAGEMENT ---
    public function getCartItems() {
        return $this->cartController->getCartItems();
    }

    public function clearCart() {
        return $this->cartController->clearCart();
    }

    // --- UTILITY ---
    public function logout() {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_id']);
        session_regenerate_id(true);
    }

    // Check if admin is logged in
    public function isLoggedIn() {
        return !empty($_SESSION['admin_logged_in']);
    }
   
    public function getGalleryImages() {
        // Retourner les images de la galerie depuis la base de données ou le dossier d'images
        return [
            'img1.jpg',
            'img2.jpg',
            'img3.jpg',
        ];
    }
}
?>