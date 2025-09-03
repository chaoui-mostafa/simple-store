<?php
session_start();
require_once '../config/db.php';

$searchQuery = '';
$searchResults = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;
$totalResults = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    
    if (!empty($searchQuery)) {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM products 
                WHERE name LIKE :search 
                   OR description LIKE :search 
                   OR price LIKE :search
            ");
            
            $searchParam = "%$searchQuery%";
            $countStmt->bindParam(':search', $searchParam);
            $countStmt->execute();
            $totalResults = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results
            $stmt = $conn->prepare("
                SELECT * FROM products 
                WHERE name LIKE :search 
                   OR description LIKE :search 
                   OR category LIKE :search
                ORDER BY 
                    CASE 
                        WHEN name LIKE :exact THEN 1
                        WHEN name LIKE :start THEN 2
                        ELSE 3
                    END,
                    created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $exactParam = "$searchQuery%";
            $startParam = "%$searchQuery%";
            
            $stmt->bindParam(':search', $searchParam);
            $stmt->bindParam(':exact', $exactParam);
            $stmt->bindParam(':start', $startParam);
            $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            $_SESSION['error'] = "Une erreur s'est produite lors de la recherche.";
        }
    }
}

$totalPages = ceil($totalResults / $perPage);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - Monster Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
         <?php include '../assets/part/header.php'  ?>

    
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                <?php if (!empty($searchQuery)): ?>
                    Résultats pour "<?php echo htmlspecialchars($searchQuery); ?>"
                <?php else: ?>
                    Recherche de produits
                <?php endif; ?>
            </h1>
            
            <?php if (empty($searchQuery)): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                    <i class="fas fa-search text-blue-500 text-4xl mb-4"></i>
                    <h2 class="text-xl font-semibold text-blue-800 mb-2">Que recherchez-vous ?</h2>
                    <p class="text-blue-600">Utilisez la barre de recherche pour trouver des produits</p>
                </div>
            <?php elseif (empty($searchResults)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                    <h2 class="text-xl font-semibold text-yellow-800 mb-2">Aucun résultat trouvé</h2>
                    <p class="text-yellow-600">Aucun produit ne correspond à votre recherche "<?php echo htmlspecialchars($searchQuery); ?>"</p>
                    <div class="mt-4">
                        <p class="text-sm text-yellow-700 mb-2">Suggestions :</p>
                        <ul class="text-sm text-yellow-600 space-y-1">
                            <li>• Vérifiez l'orthographe des mots</li>
                            <li>• Utilisez des termes plus généraux</li>
                            <li>• Essayez d'autres mots-clés</li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-600 mb-6"><?php echo $totalResults; ?> résultat(s) trouvé(s)</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($searchResults as $product): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <?php if (!empty($product['image'])): ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="w-full h-48 object-cover">
                            <?php else: ?>
                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-image text-gray-400 text-3xl"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-blue-600"><?php echo number_format($product['price'], 2); ?> DH</span>
                                    <?php if ($product['quantity'] > 0): ?>
                                    <span class="text-sm text-green-600 bg-green-100 px-2 py-1 rounded-full">
                                        <i class="fas fa-check-circle mr-1"></i>En stock
                                    </span>
                                    <?php else: ?>
                                    <span class="text-sm text-red-600 bg-red-100 px-2 py-1 rounded-full">
                                        <i class="fas fa-times-circle mr-1"></i>Rupture
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-center mt-8">
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-2"></i>Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>" class="px-4 py-2 border border-gray-300 rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                                Suivant<i class="fas fa-chevron-right ml-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
   
</body>
</html>