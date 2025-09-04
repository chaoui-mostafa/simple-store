<?php
require_once __DIR__ . '/../config/db.php';

class ProductController {
    private $conn;

    public function __construct($pdo) {
        $this->conn = $pdo;
    }

    // Get all products
    public function getAllProducts() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM products ORDER BY id DESC");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les images pour chaque produit
            foreach ($products as &$product) {
                $product['additional_images'] = $this->getProductImages($product['id']);
            }
            
            return $products;
        } catch (PDOException $e) {
            error_log("Error getting products: " . $e->getMessage());
            return [];
        }
    }

    // Get single product by ID
    public function getProductById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $product['additional_images'] = $this->getProductImages($id);
            }
            
            return $product;
        } catch (PDOException $e) {
            error_log("Error getting product: " . $e->getMessage());
            return false;
        }
    }

    // Add new product
    public function addProduct($data, $files) {
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = filter_var($data['price'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $quantity = filter_var($data['quantity'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        // Validate image
        $imageError = $this->validateImage($files['image'] ?? null);
        if ($imageError !== true) {
            $_SESSION['error'] = $imageError;
            return false;
        }

        // Upload image
        $imageName = $this->uploadImage($files['image'] ?? null);
        if (!$imageName) {
            $_SESSION['error'] = 'Failed to upload image. Please try again.';
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO products (name, description, price, quantity, image) 
                VALUES (:name, :description, :price, :quantity, :image)
            ");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':quantity' => $quantity,
                ':image' => $imageName
            ]);
            
            $productId = $this->conn->lastInsertId();
            
            // Traitement des images supplémentaires si fournies
            if (isset($files['additional_images']) && !empty($files['additional_images']['name'][0])) {
                $this->uploadAdditionalImages($productId, $files['additional_images']);
            }
            
            $_SESSION['success'] = 'Product added successfully.';
            return true;
        } catch (PDOException $e) {
            error_log("Error adding product: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to add product. Please try again later.';
            return false;
        }
    }

    // Update product
    public function updateProduct($id, $data, $files) {
        $product = $this->getProductById($id);
        if (!$product) { 
            $_SESSION['error'] = 'Product not found'; 
            return false; 
        }

        $name = trim($data['name'] ?? $product['name']);
        $description = trim($data['description'] ?? $product['description']);
        $price = filter_var($data['price'] ?? $product['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $quantity = filter_var($data['quantity'] ?? $product['quantity'], FILTER_SANITIZE_NUMBER_INT);

        $imageName = $product['image']; // keep current image if none uploaded
        if (!empty($files['image']['name'] ?? '')) {
            $imageError = $this->validateImage($files['image']);
            if ($imageError !== true) { 
                $_SESSION['error'] = $imageError; 
                return false; 
            }
            $imageName = $this->uploadImage($files['image']);
            if (!$imageName) { 
                $_SESSION['error'] = 'Failed to upload image. Please try again.'; 
                return false; 
            }
            // Remove old image safely
            if (!empty($product['image'])) {
                $oldImage = $this->getImagePath($product['image'], false);
                if (file_exists($oldImage)) @unlink($oldImage);
            }
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE products 
                SET name=:name, description=:description, price=:price, quantity=:quantity, image=:image 
                WHERE id=:id
            ");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':quantity' => $quantity,
                ':image' => $imageName,
                ':id' => $id
            ]);
            
            $_SESSION['success'] = 'Product updated successfully.';
            return true;
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to update product. Please try again later.';
            return false;
        }
    }

    // Update product quantity after order
    public function updateProductQuantity($product_id, $quantity) {
        try {
            $sql = "SELECT quantity FROM products WHERE id = :product_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':product_id' => $product_id]);
            $product = $stmt->fetch();
            if (!$product || (int)$product['quantity'] < $quantity) return false;

            $stmt = $this->conn->prepare("UPDATE products SET quantity = quantity - :quantity WHERE id = :product_id");
            $stmt->execute([
                ':quantity' => $quantity,
                ':product_id' => $product_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating product quantity: " . $e->getMessage());
            return false;
        }
    }

    // Delete product
    public function deleteProduct($id) {
        $product = $this->getProductById($id);
        if (!$product) { 
            $_SESSION['error'] = 'Product not found'; 
            return false; 
        }

        try {
            // Vérifier s'il y a des commandes liées à ce produit
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE product_id = :id");
            $stmt->execute([':id' => $id]);
            $orderCount = (int)$stmt->fetchColumn();

            if ($orderCount > 0) {
                $_SESSION['error'] = 'Cannot delete product. There are existing orders for this product.';
                return false;
            }

            // Supprimer l'image principale
            if (!empty($product['image'])) {
                $imgPath = $this->getImagePath($product['image'], false);
                if (file_exists($imgPath)) {
                    if (!unlink($imgPath)) {
                        error_log("Failed to delete main image: " . $imgPath);
                    }
                }
            }

            // Supprimer les images supplémentaires
            $additionalImages = $this->getProductImages($id);
            foreach ($additionalImages as $image) {
                $imgPath = $this->getImagePath($image['image_path'], true);
                if (file_exists($imgPath)) {
                    if (!unlink($imgPath)) {
                        error_log("Failed to delete additional image: " . $imgPath);
                    }
                }
                
                // Supprimer l'entrée de la base de données
                $stmt = $this->conn->prepare("DELETE FROM product_images WHERE id = :image_id");
                $stmt->execute([':image_id' => $image['id']]);
            }

            // Supprimer le produit de la base de données
            $stmt = $this->conn->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = 'Product deleted successfully.';
                return true;
            } else {
                $_SESSION['error'] = 'Product not found or already deleted.';
                return false;
            }
        } catch (PDOException $e) {
            error_log("Error deleting product: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete product. Please try again later.';
            return false;
        }
    }

    // Validate image
    private function validateImage($image) {
        if (!$image || ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'Error uploading file.';
        if (($image['size'] ?? 0) > 2 * 1024 * 1024) return 'File too large. Max size is 2MB.';
        $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $image['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime,$allowedTypes,true)) return 'Only JPG, PNG, GIF and WEBP files are allowed.';
        return true;
    }

    // Upload image
    private function uploadImage($image) {
        if (!$image) return false;
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $fileName = uniqid('prod_', true) . '.' . $ext;
        $dest = $this->getImagePath($fileName, false);
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
        return move_uploaded_file($image['tmp_name'], $dest) ? $fileName : false;
    }

    // Upload additional images
    private function uploadAdditionalImages($productId, $files) {
        $uploadedCount = 0;
        
        foreach ($files['tmp_name'] as $key => $tmpName) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($files['name'][$key]);
                $safeName = preg_replace("/[^A-Za-z0-9_\-\.]/", "_", $originalName);
                $fileName = uniqid('prod_') . '_' . $safeName;
                $uploadFile = $this->getImagePath($fileName, true);

                // Vérifier le type MIME
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($tmpName);

                if (in_array($fileType, $allowedTypes)) {
                    // Vérifier la taille du fichier (max 2MB)
                    if ($files['size'][$key] > 2097152) {
                        continue; // Passer à l'image suivante
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
            $_SESSION['success'] .= ' ' . $uploadedCount . ' additional image(s) added.';
        }
    }

    // Search products
    public function searchProducts($keyword) {
        $sql = "SELECT * FROM products WHERE name LIKE :keyword OR description LIKE :keyword";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get product images
    public function getProductImages($productId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM product_images WHERE product_id = :product_id ORDER BY created_at DESC");
            $stmt->execute([':product_id' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting product images: " . $e->getMessage());
            return [];
        }
    }
    
    // Get image URL for display (web accessible)
    public function getImageUrl($imagePath, $isAdditional = false) {
        // Use relative path that works on both local and server environments
        if ($isAdditional) {
            return '/../assets/images/product_images/' . $imagePath;
        } else {
            return '/../assets/images/pro' . $imagePath;
        }
    }
    
    // Get image path for file operations (server file system)
    public function getImagePath($imagePath, $isAdditional = false) {
        // Use __DIR__ to get absolute path that works on any server
        if ($isAdditional) {
            return __DIR__ . '/../assets/images/product_images/' . $imagePath;
        } else {
            return __DIR__ . '/../assets/images/' . $imagePath;
        }
    }
}
?>