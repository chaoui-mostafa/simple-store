<!-- Desktop Footer -->
<footer class="desktop-footer">
    <div class="footer-section">
        <div class="footer-column">
            <h3>Monster Store</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Votre destination unique pour les dernières tendances mode et produits de qualité.</p>
        </div>
        
        <div class="footer-column">
            <h3>Liens Rapides</h3>
            <ul>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">À propos</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Contactez-nous</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">FAQ</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Conditions générales</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Politique de confidentialité</a></li>
            </ul>
        </div>
        
        <div class="footer-column">
            <h3>Service Client</h3>
            <ul>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Suivi de commande</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Retours et échanges</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Informations de livraison</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Guide des tailles</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white">Centre d'aide</a></li>
            </ul>
        </div>
        
        <div class="footer-column">
            <h3>Restez Connecté</h3>
            <ul>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white"><i class="fab fa-facebook mr-2"></i> Facebook</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white"><i class="fab fa-instagram mr-2"></i> Instagram</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white"><i class="fab fa-twitter mr-2"></i> Twitter</a></li>
                <li><a href="#" class="dark:text-gray-400 dark:hover:text-white"><i class="fab fa-pinterest mr-2"></i> Pinterest</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer-features">
        <div class="feature-item">
            <i class="fas fa-shipping-fast feature-icon"></i>
            <span class="dark:text-gray-400">Livraison gratuite partout au Maroc</span>
        </div>
        <div class="feature-item">
            <i class="fas fa-shield-alt feature-icon"></i>
            <span class="dark:text-gray-400">Paiement sécurisé</span>
        </div>
        <div class="feature-item">
            <i class="fas fa-undo feature-icon"></i>
            <span class="dark:text-gray-400">Politique de retour de 7 jours</span>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p class="dark:text-gray-400">&copy; 2025 Monster Store. Tous droits réservés.</p>
    </div>
</footer>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav md:hidden">
    <a href="index.php" class="nav-item active">
        <i class="fas fa-home nav-icon"></i>
        <span>Accueil</span>
    </a>
    <a href="#" class="nav-item" id="mobile-search-btn">
        <i class="fas fa-search nav-icon"></i>
        <span>Recherche</span>
    </a>
    <a href="cart.php" class="nav-item relative">
        <i class="fas fa-shopping-cart nav-icon"></i>
        <?php if ($cartCount > 0): ?>
        <span class="absolute -top-1 right-3 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs"><?php echo $cartCount; ?></span>
        <?php endif; ?>
        <span>Panier</span>
    </a>
    <a href="<?php echo isset($_SESSION['user_id']) ? 'profile.php' : 'login.php'; ?>" class="nav-item">
        <i class="fas fa-user nav-icon"></i>
        <span>Compte</span>
    </a>
</nav>