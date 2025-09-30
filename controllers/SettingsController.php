<?php
class SettingsController {
    private $pdo;
    private $host;
    private $dbname;
    private $username;
    private $password;

    public function __construct() {
        // Charger les variables d'environnement depuis .env
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }

        $this->host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $this->dbname = $_ENV['DB_NAME'] ?? 'store2026';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';

        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Récupère tous les paramètres de la boutique
     */
    public function getStoreSettings() {
        $settings = [];
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM store_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            // Retourne des valeurs par défaut si la table n'existe pas
            $settings = [
                'store_name' => 'Monster Store',
                'store_email' => 'contact@monsterstore.com',
                'store_phone' => '+33 1 23 45 67 89',
                'store_address' => '123 Rue du Commerce, Paris, France',
                'store_currency' => 'EUR',
                'store_language' => 'fr',
                'store_timezone' => 'Europe/Paris',
                'store_description' => 'Votre boutique en ligne préférée',
                'store_keywords' => 'boutique, ecommerce, produits',
                'store_maintenance' => '0',
                'store_theme' => 'default'
            ];
        }
        return $settings;
    }

    /**
     * Met à jour les paramètres de la boutique
     */
    public function updateStoreSettings($settings) {
        try {
            // Créer la table si elle n'existe pas
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS store_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(255) UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            foreach ($settings as $key => $value) {
                $stmt = $this->pdo->prepare("INSERT INTO store_settings (setting_key, setting_value) 
                                          VALUES (?, ?) 
                                          ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            return [
                'success' => true,
                'message' => 'Paramètres mis à jour avec succès !'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change le mot de passe de l'administrateur
     */
    public function changeAdminPassword($current_password, $new_password, $confirm_password) {
        // Vérifier l'utilisateur actuel
        $stmt = $this->pdo->prepare("SELECT password_hash FROM admins WHERE username = ?");
        $stmt->execute([$_SESSION['admin_username'] ?? 'admin']);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($current_password, $admin['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Mot de passe actuel incorrect.'
            ];
        }

        if ($new_password !== $confirm_password) {
            return [
                'success' => false,
                'message' => 'Les nouveaux mots de passe ne correspondent pas.'
            ];
        }

        if (strlen($new_password) < 8) {
            return [
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
            ];
        }

        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
        
        if ($stmt->execute([$new_password_hash, $_SESSION['admin_username'] ?? 'admin'])) {
            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès !'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe.'
            ];
        }
    }

    /**
     * Récupère les informations de l'administrateur
     */
    public function getAdminInfo() {
        $stmt = $this->pdo->prepare("SELECT username, created_at FROM admins WHERE username = ?");
        $stmt->execute([$_SESSION['admin_username'] ?? 'admin']);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques pour la sidebar
     */
    public function getSidebarStats() {
        try {
            // Produits
            $stmt_products = $this->pdo->prepare("SELECT COUNT(*) as total FROM products");
            $stmt_products->execute();
            $products_count = $stmt_products->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Commandes en attente
            $stmt_orders = $this->pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
            $stmt_orders->execute();
            $orders_count = $stmt_orders->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'products_count' => $products_count,
                'orders_count' => $orders_count
            ];
        } catch(PDOException $e) {
            return [
                'products_count' => 0,
                'orders_count' => 0
            ];
        }
    }

    /**
     * Gère la sauvegarde de la base de données
     */
    public function backupDatabase() {
        try {
            // Créer le dossier backups s'il n'existe pas
            $backupDir = __DIR__ . '/../../backups/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $backupFileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = $backupDir . $backupFileName;
            
            // Récupérer toutes les tables
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            $backupContent = "-- Monster Store Database Backup\n";
            $backupContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Database: {$this->dbname}\n\n";
            
            foreach ($tables as $table) {
                // Structure de la table
                $createTable = $this->pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $backupContent .= "\n\n-- Structure de la table `$table`\n";
                $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
                $backupContent .= $createTable['Create Table'] . ";\n\n";
                
                // Données de la table
                $rows = $this->pdo->query("SELECT * FROM `$table`");
                $rowCount = $rows->rowCount();
                
                if ($rowCount > 0) {
                    $backupContent .= "-- Données de la table `$table` ($rowCount lignes)\n";
                    
                    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                        $columns = array_map(function($value) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $this->pdo->quote($value);
                        }, array_values($row));
                        
                        $backupContent .= "INSERT INTO `$table` VALUES (" . implode(', ', $columns) . ");\n";
                    }
                }
            }
            
            if (file_put_contents($backupPath, $backupContent)) {
                return [
                    'success' => true,
                    'message' => "Sauvegarde créée avec succès : $backupFileName",
                    'file' => $backupFileName
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de l\'écriture du fichier de sauvegarde'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Active/désactive le mode maintenance
     */
    public function toggleMaintenanceMode($enable) {
        $maintenance_value = $enable ? '1' : '0';
        $result = $this->updateStoreSettings(['store_maintenance' => $maintenance_value]);
        
        if ($result['success']) {
            // Créer ou supprimer le fichier de maintenance
            if ($enable) {
                $this->createMaintenanceFile();
            } else {
                $this->removeMaintenanceFile();
            }
            
            $status = $enable ? 'activé' : 'désactivé';
            return [
                'success' => true,
                'message' => "Mode maintenance {$status} avec succès"
            ];
        }
        
        return $result;
    }

    /**
     * Crée le fichier de maintenance
     */
    private function createMaintenanceFile() {
        $maintenanceContent = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Monster Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Inter', 'Arial', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }
        
        .container {
            background: rgba(255,255,255,0.1);
            padding: 40px 30px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .icon { 
            font-size: 4rem; 
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        
        h1 { 
            font-size: 2.2rem; 
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        p { 
            font-size: 1.1rem; 
            margin-bottom: 25px; 
            opacity: 0.9;
        }
        
        .info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            p {
                font-size: 1rem;
            }
            
            .icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Maintenance en cours</h1>
        <p>Notre boutique est actuellement en maintenance pour améliorer votre expérience.</p>
        <p>Nous serons de retour très bientôt !</p>
        <div class="info">
            <strong>Monster Store</strong><br>
            Merci de votre patience et de votre compréhension.
        </div>
    </div>
</body>
</html>
HTML;
        
        $maintenanceFile = __DIR__ . '/../../.maintenance';
        if (file_put_contents($maintenanceFile, $maintenanceContent)) {
            // Ajouter des logs
            $this->logSystem("Mode maintenance activé");
            return true;
        }
        return false;
    }

    /**
     * Supprime le fichier de maintenance
     */
    private function removeMaintenanceFile() {
        $maintenanceFile = __DIR__ . '/../../.maintenance';
        if (file_exists($maintenanceFile)) {
            if (unlink($maintenanceFile)) {
                $this->logSystem("Mode maintenance désactivé");
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si le mode maintenance est activé
     */
    public function isMaintenanceMode() {
        $settings = $this->getStoreSettings();
        return ($settings['store_maintenance'] ?? '0') === '1';
    }

    /**
     * Nettoie le cache
     */
    public function clearCache() {
        try {
            // Supprimer les fichiers de cache
            $cache_dirs = [
                __DIR__ . '/../../cache/',
                __DIR__ . '/../../tmp/',
            ];
            
            $cleaned_files = 0;
            
            foreach ($cache_dirs as $cache_dir) {
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                            $cleaned_files++;
                        }
                    }
                }
            }
            
            // Vider le cache de session si nécessaire
            if (isset($_SESSION['cache'])) {
                unset($_SESSION['cache']);
            }
            
            // Nettoyer le cache OPcache si activé
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            $this->logSystem("Cache nettoyé - $cleaned_files fichiers supprimés");
            
            return [
                'success' => true,
                'message' => "Cache nettoyé avec succès ($cleaned_files fichiers supprimés)"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du nettoyage du cache : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les logs système
     */
    public function getSystemLogs($limit = 100) {
        $log_file = __DIR__ . '/../../logs/system.log';
        $logs = [];
        
        // Créer le dossier logs s'il n'existe pas
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        if (file_exists($log_file)) {
            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                $logs = array_slice(array_reverse($lines), 0, $limit);
            }
        }
        
        return $logs;
    }

    /**
     * Ajoute une entrée dans les logs système
     */
    private function logSystem($message) {
        $log_file = __DIR__ . '/../../logs/system.log';
        $log_dir = dirname($log_file);
        
        // Créer le dossier logs s'il n'existe pas
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $username = $_SESSION['admin_username'] ?? 'system';
        $log_entry = "[$timestamp] [$username] $message\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Gère les téléchargements de fichiers de configuration
     */
    public function downloadConfig($type) {
        switch ($type) {
            case 'settings':
                $settings = $this->getStoreSettings();
                $filename = 'store_settings_' . date('Y-m-d') . '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($settings, JSON_PRETTY_PRINT);
                exit;
                
            case 'database_schema':
                $schema = $this->getDatabaseSchema();
                $filename = 'database_schema_' . date('Y-m-d') . '.sql';
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $schema;
                exit;

            case 'system_logs':
                $logs = $this->getSystemLogs(1000);
                $filename = 'system_logs_' . date('Y-m-d') . '.log';
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo implode("\n", $logs);
                exit;
        }
    }

    /**
     * Récupère le schéma de la base de données
     */
    private function getDatabaseSchema() {
        $tables = ['admins', 'products', 'orders', 'order_items', 'cart', 'cart_items', 'product_images', 'store_settings'];
        $schema = "-- Monster Store Database Schema\n";
        $schema .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $schema .= "-- Table: $table\n";
                    $schema .= $result['Create Table'] . ";\n\n";
                }
            } catch (Exception $e) {
                $schema .= "-- Table $table: non trouvée\n\n";
            }
        }
        
        return $schema;
    }

    /**
     * Récupère les informations système
     */
    public function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
            'database_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'max_memory' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'server_time' => date('Y-m-d H:i:s'),
            'maintenance_mode' => $this->isMaintenanceMode() ? 'Activé' : 'Désactivé'
        ];
    }
}

// Vérification de maintenance pour les pages publiques
function checkMaintenanceMode() {
    $maintenanceFile = __DIR__ . '/../.maintenance';
    
    if (file_exists($maintenanceFile)) {
        // Vérifier si l'utilisateur est un admin
        session_start();
        $isAdmin = isset($_SESSION['admin_username']);
        
        // Vérifier les IPs autorisées
        $allowedIPs = ['127.0.0.1', '::1'];
        
        if (!$isAdmin && !in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
            // Afficher la page de maintenance
            readfile($maintenanceFile);
            exit;
        }
    }
}

// Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_start();
    $settingsController = new SettingsController();
    $response = [];
    
    switch ($_POST['action']) {
        case 'update_store_info':
            $store_settings = [
                'store_name' => $_POST['store_name'],
                'store_email' => $_POST['store_email'],
                'store_phone' => $_POST['store_phone'],
                'store_address' => $_POST['store_address'],
                'store_currency' => $_POST['store_currency'] ?? 'EUR',
                'store_language' => $_POST['store_language'] ?? 'fr',
                'store_timezone' => $_POST['store_timezone'] ?? 'Europe/Paris'
            ];
            
            $response = $settingsController->updateStoreSettings($store_settings);
            break;
            
        case 'change_password':
            $response = $settingsController->changeAdminPassword(
                $_POST['current_password'],
                $_POST['new_password'],
                $_POST['confirm_password']
            );
            break;
            
        case 'backup_database':
            $response = $settingsController->backupDatabase();
            break;
            
        case 'toggle_maintenance':
            $enable = $_POST['maintenance_mode'] === '1';
            $response = $settingsController->toggleMaintenanceMode($enable);
            break;
            
        case 'clear_cache':
            $response = $settingsController->clearCache();
            break;
    }
    
    // Rediriger avec le message de réponse
    if (isset($response['success'])) {
        $_SESSION['message'] = [
            'type' => $response['success'] ? 'success' : 'error',
            'text' => $response['message']
        ];
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement des requêtes GET pour les téléchargements
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    session_start();
    $settingsController = new SettingsController();
    $settingsController->downloadConfig($_GET['download']);
}
?>