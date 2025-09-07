 <!-- Floating Cart Button (Mobile) -->
        <div class="floating-cart-btn" onclick="window.location.href='cart.php'">
            <i class="fas fa-shopping-cart"></i>
            <?php if ($cartCount > 0): ?>
                <span class="floating-cart-badge"><?php echo $cartCount; ?></span>
            <?php endif; ?>
        </div>
        <!-- Floating WhatsApp Button -->
<div class="floating-whatsapp-btn" 
     onclick="window.open('https://wa.me/212600000000?text=Salam, bghit n3rf 3la Monster Store 😊', '_blank')">
    <i class="fab fa-whatsapp"></i>
</div>

        <style>
               /* Modern Design Elements */
        .floating-cart-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
            z-index: 90;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .floating-cart-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5);
        }
        
        .floating-cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--error-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        </style>
        <style>
/* Floating WhatsApp Button */
.floating-whatsapp-btn {
    position: fixed;
    bottom: 20px; /* تحت الكارت */
    right: 20px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
    z-index: 90;
    cursor: pointer;
    transition: all 0.3s ease;
     bottom: 170px; /
}

.floating-whatsapp-btn:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 12px 30px rgba(18, 140, 126, 0.5);
}

.floating-whatsapp-btn i {
    font-size: 28px;
}
</style>