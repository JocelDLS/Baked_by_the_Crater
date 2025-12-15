<?php 
// Set variables for the header before including it
$page_title = 'Baked by the Crater | Home';
$active_page = 'homepage'; 
include 'header.php'; 
?>
        <section class="hero-section">
            <div class="container hero-content">
                <div class="hero-text">
                    <h1>Artisan Breads & Pastries</h1>
                    <p>Experience the warmth and flavor of freshly baked goods, crafted with passion right here in the Crater.</p>
                    <div class="hero-ctas">
                        <a href="shop.html" class="btn btn-primary">Shop Now</a>
                        <a href="bestsellers.html" class="btn-tertiary">View Best Sellers</a>
                    </div>
                </div>
                <div class="hero-image-placeholder">
                    </div>
            </div>
        </section>

        <section class="featured-products container">
            <h2>Our Signature Bakes</h2>
            <div class="product-grid">
                <div class="product-card">
                    <div class="product-image"></div>
                    <h3>Crater Sourdough</h3>
                    <p class="price"><?php echo format_currency(7.99); ?></p> 
                    <a href="shop.html#sourdough" class="btn-small">Add to Cart</a>
                </div>
                <div class="product-card">
                    <div class="product-image"></div>
                    <h3>Flaky Croissants</h3>
                    <p class="price"><?php echo format_currency(3.50); ?></p> 
                    <a href="shop.html#croissant" class="btn-small">Add to Cart</a>
                </div>
                <div class="product-card">
                    <div class="product-image"></div>
                    <h3>French Baguette</h3>
                    <p class="price"><?php echo format_currency(4.25); ?></p> 
                    <a href="shop.html#baguette" class="btn-small">Add to Cart</a>
                </div>
                <div class="product-card">
                    <div class="product-image"></div>
                    <h3>Challah Bread</h3>
                    <p class="price"><?php echo format_currency(6.50); ?></p> 
                    <a href="shop.html#challah" class="btn-small">Add to Cart</a>
                </div>
            </div>
        </section>
        
        <section class="usp-section container">
            <div class="usp-grid">
                <div class="usp-item">
                    <i class="fas fa-seedling"></i>
                    <h3>Natural Ingredients</h3>
                    <p>We use only locally sourced, natural ingredients with no artificial preservatives.</p>
                </div>
                <div class="usp-item">
                    <i class="fas fa-hand-holding-box"></i>
                    <h3>Hand-Crafted Daily</h3>
                    <p>Every loaf and pastry is mixed, shaped, and baked by hand with passion.</p>
                </div>
                <div class="usp-item">
                    <i class="fas fa-truck-fast"></i>
                    <h3>Fresh Delivery</h3>
                    <p>Delivered fresh to your door within hours of coming out of the oven.</p>
                </div>
            </div>
        </section>

        <section class="testimonials container">
            <h2>What Our Customers Say</h2>
            <div class="testimonial-box">
                <p>"The sourdough here is the best I've ever had. Perfectly tangy crust and airy interior. It feels like a real treat every time!"</p>
                <footer>— Jenna P., Local Food Critic</footer>
            </div>
        </section>

        <section class="newsletter-signup">
            <div class="container">
                <h3>Get Exclusive Deals!</h3>
                <p>Join our newsletter for 10% off your first order and updates on our seasonal bakes.</p>
                <form class="newsletter-form">
                    <input type="email" placeholder="Enter your email address" required>
                    <button type="submit" class="btn-primary">Subscribe</button>
                </form>
            </div>
        </section>

<?php include 'footer.php'; ?>