<?php
// pos/index.php

// STEP 1: Perform all PHP logic BEFORE any HTML is outputted.
require_once __DIR__ . '/../config.php';

if (!check_permission('POS', 'view')) {
    // We can't use the fancy header here because no HTML has been sent yet.
    die('You do not have permission to access the POS system.');
}

$page_title = "Point of Sale - BizManager";
$user_id = $_SESSION['user_id'];

// CRITICAL FIX: The location_id might not be set. Check for it.
if (!isset($_SESSION['location_id']) || empty($_SESSION['location_id'])) {
    // Can't use the header yet, so die with a clean message.
     die('<div style="font-family: sans-serif; text-align: center; padding: 50px;"><h2>Location Not Set</h2><p>Your user account is not assigned to a specific location. Please contact an administrator to have your profile updated.</p><a href="../dashboard.php">Back to Dashboard</a></div>');
}
$location_id = $_SESSION['location_id'];

// --- SESSION CHECK ---
// Check for an active session for this user, if not, redirect to start a new one.
$active_session_stmt = $conn->prepare("SELECT id FROM scs_pos_sessions WHERE user_id = ? AND status = 'Active'");
$active_session_stmt->bind_param("i", $user_id);
$active_session_stmt->execute();
$active_session_result = $active_session_stmt->get_result();

if ($active_session_result->num_rows == 0) {
    // This redirect will now work because no HTML has been sent.
    header("Location: start-session.php");
    exit();
} else {
    $session_data = $active_session_result->fetch_assoc();
    $_SESSION['pos_session_id'] = $session_data['id'];
}
$active_session_stmt->close();

// --- DATA FETCHING for the Interface ---
// Fetch products with their stock levels at the user's current location
$products_stmt = $conn->prepare("
    SELECT p.id, p.product_name, p.sku, p.selling_price, COALESCE(i.quantity, 0) as stock
    FROM scs_products p
    LEFT JOIN scs_inventory i ON p.id = i.product_id AND i.location_id = ?
    ORDER BY p.product_name ASC
");
$products_stmt->bind_param("i", $location_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Fetch payment methods
$payment_methods_result = $conn->query("SELECT method_name FROM scs_payment_methods WHERE is_active = 1");

// STEP 2: Now that all logic is done, it's safe to include the header.
require_once __DIR__ . '/../templates/header.php';
?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col h-[calc(100vh-100px)]">
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white/50 p-4 rounded-xl shadow-inner flex flex-col">
            <div class="mb-4">
                <input type="text" id="product-search" placeholder="Search by product name or SKU..." class="form-input w-full p-3 rounded-lg shadow-sm">
            </div>
             <div id="product-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 overflow-y-auto h-full">
                <?php foreach ($products as $product): ?>
                    <button
                        class="product-item aspect-square flex flex-col items-center justify-center text-center p-2 rounded-lg shadow-sm transition-transform transform hover:scale-105"
                        data-id="<?php echo $product['id']; ?>"
                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                        data-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                        data-price="<?php echo $product['selling_price']; ?>"
                        data-stock="<?php echo $product['stock']; ?>"
                        style="background-color: <?php echo ($product['stock'] > 0) ? 'rgba(255, 255, 255, 0.8)' : 'rgba(230, 230, 230, 0.7)'; ?>;"
                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>

                        <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></span>
                        <span class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($product['selling_price'], 2)); ?></span>
                        <span class="text-xs <?php echo ($product['stock'] <= 0) ? 'text-red-500' : 'text-green-600'; ?> mt-1">(Stock: <?php echo $product['stock']; ?>)</span>
                    </button>
                <?php endforeach; ?>
                <div id="no-results" class="hidden col-span-full text-center text-gray-500 p-8">No products found.</div>
             </div>
        </div>

        <div class="lg:col-span-1 bg-white/60 p-4 rounded-xl flex flex-col">
             <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Current Sale</h2>
                <div class="flex space-x-2">
                    <a href="session-history.php" class="text-sm bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600">History</a>
                    <a href="close-session.php" class="text-sm bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600">Close Session</a>
                </div>
            </div>

            <div id="cart-items" class="flex-1 overflow-y-auto space-y-2">
                <p class="text-center text-gray-500 p-4">Cart is empty</p>
            </div>

            <div class="border-t border-gray-300/50 pt-4 mt-4 text-gray-800">
                <div class="flex justify-between font-semibold">
                    <span>Subtotal</span>
                    <span id="cart-subtotal">0.00</span>
                </div>
                 <div class="flex justify-between font-semibold mt-2">
                    <span>Tax (0%)</span>
                    <span id="cart-tax">0.00</span>
                </div>
                 <div class="flex justify-between font-bold text-2xl mt-4">
                    <span>Total</span>
                    <span id="cart-total">0.00</span>
                </div>
            </div>

            <button id="pay-button" class="mt-4 w-full bg-indigo-600 text-white font-bold py-4 rounded-lg text-lg hover:bg-indigo-700 disabled:bg-gray-400" disabled>
                Pay
            </button>
        </div>
    </div>
</div>


<div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Complete Payment</h3>
        <div class="text-center mb-6">
            <p class="text-gray-600">Total Amount Due</p>
            <p id="modal-total" class="text-4xl font-bold text-indigo-600">0.00</p>
        </div>
        <form id="payment-form">
            <div class="mb-4">
                <label for="payment-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                <select id="payment-method" name="payment_method" class="form-input mt-1 block w-full rounded-md p-3" required>
                    <?php while($method = $payment_methods_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($method['method_name']); ?>"><?php echo htmlspecialchars($method['method_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-6">
                 <label for="amount-tendered" class="block text-sm font-medium text-gray-700">Amount Tendered</label>
                 <input type="number" step="0.01" id="amount-tendered" name="amount_tendered" class="form-input mt-1 block w-full rounded-md p-3 text-lg" placeholder="0.00">
            </div>
             <div class="text-center mb-6">
                <p class="text-gray-600">Change Due</p>
                <p id="change-due" class="text-3xl font-bold text-green-600">0.00</p>
            </div>
            <div class="flex justify-between">
                <button type="button" id="cancel-payment" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" id="confirm-payment" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 disabled:bg-gray-400">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productItems = document.querySelectorAll('.product-item');
    const cartItemsContainer = document.getElementById('cart-items');
    const payButton = document.getElementById('pay-button');
    const paymentModal = document.getElementById('payment-modal');
    const cancelPaymentButton = document.getElementById('cancel-payment');
    const paymentForm = document.getElementById('payment-form');
    const amountTenderedInput = document.getElementById('amount-tendered');
    const productSearchInput = document.getElementById('product-search');
    const productGrid = document.getElementById('product-grid');
    const noResultsDiv = document.getElementById('no-results');
    
    let cart = {};

    function updateCartDisplay() {
        cartItemsContainer.innerHTML = '';
        let subtotal = 0;
        
        if (Object.keys(cart).length === 0) {
            cartItemsContainer.innerHTML = '<p class="text-center text-gray-500 p-4">Cart is empty</p>';
            payButton.disabled = true;
            updateTotals(0, 0);
            return;
        }

        for (const id in cart) {
            const item = cart[id];
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;

            const cartItemHTML = `
                <div class="cart-item flex items-center p-2 bg-white/80 rounded-lg" data-id="${id}">
                    <div class="flex-grow">
                        <p class="font-semibold text-sm">${item.name}</p>
                        <p class="text-xs text-gray-600">${item.quantity} x ${parseFloat(item.price).toFixed(2)}</p>
                    </div>
                    <div class="font-semibold">${itemTotal.toFixed(2)}</div>
                    <button class="remove-item-btn text-red-500 hover:text-red-700 ml-4">&times;</button>
                </div>
            `;
            cartItemsContainer.innerHTML += cartItemHTML;
        }
        
        const tax = 0;
        updateTotals(subtotal, tax);
        payButton.disabled = false;
    }

    function updateTotals(subtotal, tax) {
        const total = subtotal + tax;
        document.getElementById('cart-subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('cart-tax').textContent = tax.toFixed(2);
        document.getElementById('cart-total').textContent = total.toFixed(2);
        document.getElementById('modal-total').textContent = total.toFixed(2);
    }
    
    productItems.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            const stock = parseInt(this.dataset.stock);

            if (cart[id]) {
                if(cart[id].quantity < stock) {
                    cart[id].quantity++;
                } else {
                    alert('Maximum stock reached for this item.');
                }
            } else {
                cart[id] = { name, price, quantity: 1, stock };
            }
            updateCartDisplay();
        });
    });

    cartItemsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item-btn')) {
            const itemId = e.target.closest('.cart-item').dataset.id;
            delete cart[itemId];
            updateCartDisplay();
        }
    });
    
    payButton.addEventListener('click', () => {
        paymentModal.classList.remove('hidden');
        paymentModal.classList.add('flex');
        amountTenderedInput.focus();
        if(document.getElementById('payment-method').value !== 'Cash') {
             amountTenderedInput.value = document.getElementById('cart-total').textContent;
             amountTenderedInput.dispatchEvent(new Event('input'));
        }
    });

    cancelPaymentButton.addEventListener('click', () => {
        paymentModal.classList.add('hidden');
        paymentModal.classList.remove('flex');
    });

    amountTenderedInput.addEventListener('input', function() {
        const total = parseFloat(document.getElementById('cart-total').textContent);
        const tendered = parseFloat(this.value);
        const change = tendered - total;
        document.getElementById('change-due').textContent = (change >= 0) ? change.toFixed(2) : '0.00';
    });

    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const total = parseFloat(document.getElementById('cart-total').textContent);
        const tendered = parseFloat(amountTenderedInput.value || 0);

        if (tendered < total) {
            alert('Amount tendered is less than the total amount.');
            return;
        }

        const formData = {
            cart: cart,
            payment_method: document.getElementById('payment-method').value,
            amount_tendered: tendered,
            total_amount: total
        };

        fetch('process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.open('print-receipt.php?invoice_id=' + data.invoice_id, 'Print Receipt', 'width=400,height=600');
                
                alert('Sale completed successfully!');
                cart = {};
                updateCartDisplay();
                paymentModal.classList.add('hidden');
                paymentModal.classList.remove('flex');
                paymentForm.reset();
                
                location.reload(); 
                
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        });
    });

    productSearchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        let found = false;
        productItems.forEach(item => {
            const productName = item.dataset.name.toLowerCase();
            const productSku = item.dataset.sku.toLowerCase();
            if (productName.includes(searchTerm) || productSku.includes(searchTerm)) {
                item.style.display = 'flex';
                found = true;
            } else {
                item.style.display = 'none';
            }
        });
        noResultsDiv.style.display = found ? 'none' : 'block';
    });


    updateCartDisplay();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>