# 🍎 FruitKart - Flipkart-Style Fruit Shopping Web Application

A full-stack, responsive **Flipkart-style Fruit Shopping App** built with **PHP (OOP / PDO)**, **MySQL**, **Tailwind CSS**, and **JavaScript**, running natively on XAMPP Apache & MySQL.

---

## 🌟 Demo Credentials

| Role | Email | Password | Quick Action |
|---|---|---|---|
| **Store Admin** | `admin@fruitkart.com` | `admin123` | Full access to Dashboard, Catalog, Orders, Inventory & Analytics |
| **Customer** | `user@example.com` | `user123` | Pre-configured with saved address and past orders |

> 💡 **Quick 1-Click Login:** On the login screen, single-click buttons are provided to autofill customer or admin credentials instantly!

---

## 🚀 Application URLs (Local XAMPP)

- **Customer Storefront:** [http://localhost/Fruit%20Shopping%20App/](http://localhost/Fruit%20Shopping%20App/)
- **Catalog & Search:** [http://localhost/Fruit%20Shopping%20App/products.php](http://localhost/Fruit%20Shopping%20App/products.php)
- **Shopping Cart:** [http://localhost/Fruit%20Shopping%20App/cart.php](http://localhost/Fruit%20Shopping%20App/cart.php)
- **User Orders & Tracking:** [http://localhost/Fruit%20Shopping%20App/my-orders.php](http://localhost/Fruit%20Shopping%20App/my-orders.php)
- **Admin Control Center:** [http://localhost/Fruit%20Shopping%20App/admin/login.php](http://localhost/Fruit%20Shopping%20App/admin/login.php)

---

## 📱 Complete App Screens Implemented

### 👤 User Side
1. **Home / Splash Screen (`index.php`):** Flipkart hero banner, Category circular icons, "Deals of the Day" with live countdown timer, Farm Fresh arrivals, Exotic superfoods section.
2. **Catalog & Search (`products.php`):** Multi-filter sidebar (categories, price range, ratings, discount %), sorting (price, rating, newest), responsive fruit cards.
3. **Product Details (`product-details.php`):** Left image gallery, wishlist heart, Flipkart Add to Cart & Buy Now buttons, nutrition facts table, shelf life, origin orchards, certified customer reviews with "Write a Review" form, similar fruits carousel.
4. **Shopping Cart (`cart.php`):** Flipkart split layout, delivery location banner, quantity increment/decrement (+ / -) controls, Save for later, Remove, Price Details summary box, coupon apply drawer.
5. **My Wishlist (`wishlist.php`):** Saved favorites with 1-click "Move to Cart" and delete.
6. **Checkout (`checkout.php`):** Account verification, Saved delivery addresses selector, "Add New Address" form, delivery time slot preference (2-Hour Express, Morning, Evening).
7. **Payment (`payment.php`):** Cash on Delivery (COD), UPI with simulated live QR code & apps (GPay, PhonePe, Paytm), Credit/Debit Card, Net Banking.
8. **Order Success (`order-success.php`):** Animated confirmation, order number, delivery slot, quick links to live tracking and invoice.
9. **My Orders (`my-orders.php`):** Flipkart-style order history with status pills, order totals, item summaries.
10. **Order Details & Invoice (`order-details.php`):** Tax invoice view, printable receipt (`window.print()`), seller licensing, itemized pricing breakdown.
11. **Live Order Tracking (`order-tracking.php`):** 5-stage visual progress tracker (Placed &rarr; Confirmed &rarr; Packed &rarr; Dispatched &rarr; Out for Delivery &rarr; Delivered), delivery OTP, cold-chain driver details.
12. **Customer Reviews:** Verified buyer reviews system integrated on product pages.
13. **User Auth (`login.php`, `signup.php`, `forgot-password.php`):** Clean Flipkart blue-branded modal/page with 1-click demo logins.
14. **User Profile & Addresses (`profile.php`):** Account settings, multiple delivery address manager, password update.
15. **Mobile Bottom Navigation:** Fixed sticky navigation bar for smartphone viewports.

---

### 🛡️ Admin Side
1. **Admin Login (`admin/login.php`):** Secure admin portal authentication.
2. **Dashboard (`admin/index.php`):** Key metrics (Total Revenue, Orders, Fruits Catalog, Customers), low stock warning cards, recent orders table, category breakdown.
3. **Products Management (`admin/products.php`):** Search, category filter, active/inactive toggle, edit and delete actions.
4. **Add Product (`admin/product-add.php`):** Full form with image URL, title, category, pricing, unit, stock, nutrition info, origin, deal/featured badges.
5. **Edit Product (`admin/product-edit.php`):** Update existing fruit pricing and details.
6. **Categories Management (`admin/categories.php`):** Create and manage fruit categories with thumbnail images.
7. **Orders Management (`admin/orders.php`):** Filter orders by status, quick inline status updater, live links to delivery tracker.
8. **Admin Order Details (`admin/order-details.php`):** Full customer snapshot, packing slip print, status and payment updater with custom tracking notes.
9. **Inventory & Stock (`admin/inventory.php`):** Real-time stock health monitoring, low-stock warnings (&le; 25), quick inline stock updater.
10. **Coupons & Promo Codes (`admin/coupons.php`):** Create flat or percentage discount promo codes (`FRESH50`, `FRUIT20`, etc.).
11. **Customers Directory (`admin/users.php`):** Buyer list with total orders placed and lifetime spend.
12. **Sales Reports & Analytics (`admin/reports.php`):** Net sales, discounts given, fruit units sold, payment methods breakdown.
13. **Store Settings (`admin/settings.php`):** Configurable delivery fee, free delivery threshold, central warehouse address.

---

## 🗄️ Database Architecture (`fruit_shopping_db`)

- `users`: User and admin authentication, roles, contact info.
- `categories`: Fruit groupings (Daily Essentials, Exotic, Citrus, Melons, Berries, Organic).
- `products`: Product catalog with MRP, selling price, stock, ratings, nutrition facts, origins, discount percentages.
- `cart`: Shopping cart items with quantity and session-to-user automatic migration.
- `wishlist`: Customer wishlist items.
- `addresses`: Multi-address delivery directory with Home/Work labels and default flags.
- `coupons`: Active discount coupons with minimum cart thresholds and expiry dates.
- `orders`: Master orders record with delivery slots, address snapshots, payment modes, and fulfillment statuses.
- `order_items`: Line-item details of ordered fruits.
- `order_tracking`: Chronological timeline logs for order tracking.
- `reviews`: Customer reviews and star ratings.
- `settings`: Global site configuration key-value storage.

---

## 💡 Promo Coupons to Test

- `FRESH50` - Flat ₹50 OFF on orders above ₹299.
- `FRUIT20` - 20% OFF (up to ₹100) on orders above ₹399.
- `WELCOME10` - 10% OFF on orders above ₹199.
