<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

/*=========================================================
    PROCESS SALE
==========================================================*/

if(isset($_POST['action']) && $_POST['action'] == 'process_sale'){
    $cashier_id  = 1; // replace with $_SESSION['user_id'] once login is done
    $items       = json_decode($_POST['items'], true);
    $total       = (float)$_POST['total'];
    $payment     = (float)$_POST['payment'];
    $change      = $payment - $total;

    if($payment < $total){
        echo 'insufficient';
        exit();
    }

    if(empty($items)){
        echo 'empty';
        exit();
    }

    // Insert into sales
    $saleQuery = mysqli_query($conn,"
        INSERT INTO sales (cashier_id, total_amount, payment, change_amount, status)
        VALUES ($cashier_id, $total, $payment, $change, 'Completed')
    ");

    if(!$saleQuery){
        echo 'error: ' . mysqli_error($conn);
        exit();
    }

    $sale_id = mysqli_insert_id($conn);

    // Insert sale items + deduct inventory
    foreach($items as $item){
        $product_id = (int)$item['product_id'];
        $quantity   = (int)$item['quantity'];
        $price      = (float)$item['price'];
        $subtotal   = $price * $quantity;

        mysqli_query($conn,"
            INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal)
            VALUES ($sale_id, $product_id, $quantity, $price, $subtotal)
        ");

        // Deduct from inventory
        mysqli_query($conn,"
            UPDATE inventory SET quantity = GREATEST(0, quantity - $quantity)
            WHERE product_id = $product_id
        ");

        // Auto set product status based on stock
        mysqli_query($conn,"
            UPDATE products SET status = 
                CASE WHEN (SELECT quantity FROM inventory WHERE product_id = $product_id) = 0 
                THEN 'Unavailable' ELSE 'Available' END
            WHERE product_id = $product_id
        ");
    }
    logAction($conn, $cashier_id, 'Create', 'sales', $sale_id,
        "Processed sale #$sale_id — Total: ₱$total");
    echo 'success:' . $sale_id . ':' . $change;
    exit();
}

/*=========================================================
    FETCH PRODUCTS
==========================================================*/

$products = mysqli_query($conn,"
    SELECT
        p.product_id,
        p.product_name,
        p.selling_price,
        p.image,
        c.category_name,
        IFNULL(i.quantity, 0) AS stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.status = 'Available'
    ORDER BY p.product_name ASC
");

$productList = [];
while($row = mysqli_fetch_assoc($products)){
    $productList[] = $row;
}

// Get unique categories for filter
$categoryFilter = mysqli_query($conn,"
    SELECT DISTINCT c.category_id, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.status = 'Available'
    ORDER BY c.category_name ASC
");

?>

<style>

.cashier-wrap {
    display: flex;
    gap: 16px;
    height: calc(100vh - 110px);
}

/* LEFT - Product Grid */
.product-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}

.product-panel-header {
    padding: 14px 16px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.product-grid {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    align-content: start;
}

.product-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    border: 2px solid transparent;
    transition: .2s;
    user-select: none;
}

.product-item:hover {
    border-color: #198754;
    background: #f0faf4;
}

.product-item.out-of-stock {
    opacity: .5;
    cursor: not-allowed;
}

.product-item .prod-icon {
    font-size: 32px;
    margin-bottom: 6px;
}

.product-item .prod-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    line-height: 1.3;
}

.product-item .prod-price {
    font-size: 13px;
    color: #198754;
    font-weight: 700;
}

.product-item .prod-stock {
    font-size: 11px;
    color: #6c757d;
}

/* RIGHT - Cart / Order */
.cart-panel {
    width: 320px;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}

.cart-header {
    padding: 16px;
    background: #1E5631;
    color: white;
    font-weight: 700;
    font-size: 16px;
}

.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 8px;
}

.cart-item-name {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
}

.cart-item-price {
    font-size: 12px;
    color: #198754;
    font-weight: 700;
    white-space: nowrap;
}

.qty-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
}

.qty-btn:hover { background: #dee2e6; }

.qty-display {
    min-width: 24px;
    text-align: center;
    font-weight: 700;
    font-size: 14px;
}

.cart-footer {
    padding: 14px 16px;
    border-top: 1px solid #e9ecef;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.total-label { font-size: 13px; color: #6c757d; }

.total-amount {
    font-size: 22px;
    font-weight: 800;
    color: #198754;
}

.empty-cart {
    text-align: center;
    padding: 40px 20px;
    color: #adb5bd;
}

.empty-cart i { font-size: 48px; margin-bottom: 10px; }

</style>

<div class="cashier-wrap">

    <!-- LEFT: PRODUCTS -->
    <div class="product-panel">

        <div class="product-panel-header">
            <input type="text" class="form-control form-control-sm"
                   id="productSearch" placeholder="🔍 Search product..."
                   oninput="filterProducts()" style="max-width:200px">

            <select class="form-select form-select-sm" id="categoryFilter"
                    onchange="filterProducts()" style="max-width:180px">
                <option value="">All Categories</option>
                <?php while($cat = mysqli_fetch_assoc($categoryFilter)){ ?>
                <option value="<?= $cat['category_id']; ?>">
                    <?= htmlspecialchars($cat['category_name']); ?>
                </option>
                <?php } ?>
            </select>

            <span class="text-muted ms-auto" style="font-size:13px">
                Click a product to add to cart
            </span>
        </div>

        <div class="product-grid" id="productGrid">
            <?php foreach($productList as $p){ ?>
            <div class="product-item <?= $p['stock'] <= 0 ? 'out-of-stock' : ''; ?>"
                 data-id="<?= $p['product_id']; ?>"
                 data-name="<?= htmlspecialchars($p['product_name']); ?>"
                 data-price="<?= $p['selling_price']; ?>"
                 data-stock="<?= $p['stock']; ?>"
                 data-category="<?= $p['category_id'] ?? ''; ?>"
                 onclick="addToCart(this)">
                <div class="prod-icon">🛍️</div>
                <div class="prod-name"><?= htmlspecialchars($p['product_name']); ?></div>
                <div class="prod-price">₱<?= number_format($p['selling_price'], 2); ?></div>
                <div class="prod-stock">
                    <?= $p['stock'] > 0 ? 'Stock: ' . $p['stock'] : 'Out of Stock'; ?>
                </div>
            </div>
            <?php } ?>
        </div>

    </div>

    <!-- RIGHT: CART -->
    <div class="cart-panel">

        <div class="cart-header">
            <i class="bi bi-cart3 me-2"></i>Current Order
            <button class="btn btn-sm btn-outline-light float-end"
                    onclick="clearCart()" title="Clear Cart">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="cart-items" id="cartItems">
            <div class="empty-cart" id="emptyCart">
                <i class="bi bi-cart-x d-block"></i>
                No items yet
            </div>
        </div>

        <div class="cart-footer">

            <div class="total-row">
                <span class="total-label">Items</span>
                <span id="totalItems" class="fw-semibold">0</span>
            </div>

            <div class="total-row mb-3">
                <span class="total-label" style="font-size:15px;font-weight:600;">Total</span>
                <span class="total-amount">₱<span id="totalAmount">0.00</span></span>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:13px;">
                    Cash Payment (₱)
                </label>
                <input type="number" class="form-control" id="paymentInput"
                       placeholder="0.00" min="0" step="0.01"
                       oninput="updateChange()">
            </div>

            <div class="total-row mb-3">
                <span class="total-label">Change</span>
                <span id="changeAmount" class="fw-bold text-success">₱0.00</span>
            </div>

            <!-- Quick Cash Buttons -->
            <div class="d-flex gap-1 flex-wrap mb-3">
                <?php foreach([20,50,100,200,500,1000] as $bill){ ?>
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="setPayment(<?= $bill; ?>)">
                    ₱<?= $bill; ?>
                </button>
                <?php } ?>
            </div>

            <button class="btn btn-success w-100 fw-bold"
                    style="height:48px;font-size:16px;"
                    onclick="processSale()">
                <i class="bi bi-check-circle me-2"></i>Process Sale
            </button>

        </div>

    </div>

</div>

<!--=========================================================
    RECEIPT MODAL
==========================================================-->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptBody" style="font-family:monospace;font-size:13px;">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button class="btn btn-success btn-sm" onclick="newTransaction()">
                    <i class="bi bi-plus me-1"></i>New Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    JAVASCRIPT
==========================================================-->
<script>

let cart = [];

/*====================================================
    ADD TO CART
====================================================*/
function addToCart(el){
    if(el.classList.contains('out-of-stock')) return;

    const id    = el.dataset.id;
    const name  = el.dataset.name;
    const price = parseFloat(el.dataset.price);
    const stock = parseInt(el.dataset.stock);

    const existing = cart.find(i => i.product_id == id);

    if(existing){
        if(existing.quantity >= stock){
            Swal.fire('Stock Limit','No more stock available for this product.','warning');
            return;
        }
        existing.quantity++;
        existing.subtotal = existing.quantity * existing.price;
    } else {
        cart.push({
            product_id: id,
            name:       name,
            price:      price,
            quantity:   1,
            subtotal:   price,
            stock:      stock
        });
    }

    renderCart();
}

/*====================================================
    RENDER CART
====================================================*/
function renderCart(){
    const container = $("#cartItems");
    container.find(".cart-item").remove();

    if(cart.length == 0){
        $("#emptyCart").show();
        $("#totalItems").text(0);
        $("#totalAmount").text("0.00");
        $("#changeAmount").text("₱0.00");
        return;
    }

    $("#emptyCart").hide();

    let total = 0;
    let totalItems = 0;

    cart.forEach((item, index) => {
        total += item.subtotal;
        totalItems += item.quantity;

        const html = `
        <div class="cart-item">
            <div style="flex:1">
                <div class="cart-item-name">${item.name}</div>
                <div style="font-size:11px;color:#6c757d;">
                    ₱${item.price.toFixed(2)} each
                </div>
            </div>
            <button class="qty-btn" onclick="changeQty(${index}, -1)">−</button>
            <span class="qty-display">${item.quantity}</span>
            <button class="qty-btn" onclick="changeQty(${index}, 1)">+</button>
            <div class="cart-item-price ms-1">₱${item.subtotal.toFixed(2)}</div>
            <button class="qty-btn ms-1" style="background:#fee2e2;color:#dc3545;"
                    onclick="removeItem(${index})">×</button>
        </div>`;

        container.append(html);
    });

    $("#totalItems").text(totalItems);
    $("#totalAmount").text(total.toFixed(2));
    updateChange();
}

/*====================================================
    CHANGE QUANTITY
====================================================*/
function changeQty(index, delta){
    cart[index].quantity += delta;

    if(cart[index].quantity <= 0){
        cart.splice(index, 1);
    } else if(cart[index].quantity > cart[index].stock){
        cart[index].quantity = cart[index].stock;
        Swal.fire('Stock Limit','Maximum stock reached.','warning');
    } else {
        cart[index].subtotal = cart[index].quantity * cart[index].price;
    }

    renderCart();
}

/*====================================================
    REMOVE ITEM
====================================================*/
function removeItem(index){
    cart.splice(index, 1);
    renderCart();
}

/*====================================================
    CLEAR CART
====================================================*/
function clearCart(){
    if(cart.length == 0) return;
    Swal.fire({
        title: 'Clear Cart?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Clear'
    }).then(r => { if(r.isConfirmed){ cart = []; renderCart(); } });
}

/*====================================================
    PAYMENT & CHANGE
====================================================*/
function setPayment(amount){
    const total = parseFloat($("#totalAmount").text()) || 0;
    const current = parseFloat($("#paymentInput").val()) || 0;
    $("#paymentInput").val((current + amount).toFixed(2));
    updateChange();
}

function updateChange(){
    const total   = parseFloat($("#totalAmount").text()) || 0;
    const payment = parseFloat($("#paymentInput").val()) || 0;
    const change  = payment - total;
    $("#changeAmount")
        .text("₱" + (change >= 0 ? change.toFixed(2) : "0.00"))
        .css("color", change >= 0 ? "#198754" : "#dc3545");
}

/*====================================================
    PRODUCT FILTER
====================================================*/
function filterProducts(){
    const search   = $("#productSearch").val().toLowerCase();
    const category = $("#categoryFilter").val();

    $(".product-item").each(function(){
        const name = $(this).data("name").toLowerCase();
        const cat  = String($(this).data("category"));
        const matchName = name.includes(search);
        const matchCat  = !category || cat === String(category);s
        $(this).toggle(matchName && matchCat);
    });
}

/*====================================================
    PROCESS SALE
====================================================*/
function processSale(){
    if(cart.length == 0){
        Swal.fire('Empty Cart','Please add products first.','warning');
        return;
    }

    const total   = parseFloat($("#totalAmount").text());
    const payment = parseFloat($("#paymentInput").val()) || 0;

    if(payment <= 0){
        Swal.fire('No Payment','Please enter the cash payment amount.','warning');
        return;
    }

    if(payment < total){
        Swal.fire('Insufficient Payment',
            `Payment ₱${payment.toFixed(2)} is less than total ₱${total.toFixed(2)}.`,
            'warning');
        return;
    }

    Swal.fire({
        title: 'Confirm Sale?',
        html: `Total: <strong>₱${total.toFixed(2)}</strong><br>Payment: <strong>₱${payment.toFixed(2)}</strong>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Process Sale'
    }).then(result => {
        if(!result.isConfirmed) return;

        $.post('cashier.php', {
            action:  'process_sale',
            items:   JSON.stringify(cart),
            total:   total,
            payment: payment
        }, function(response){
            if(response.startsWith('success:')){
                const parts    = response.split(':');
                const sale_id  = parts[1];
                const change   = parseFloat(parts[2]);
                showReceipt(sale_id, total, payment, change);
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

/*====================================================
    SHOW RECEIPT
====================================================*/
function showReceipt(sale_id, total, payment, change){
    const now  = new Date();
    const date = now.toLocaleDateString('en-PH', {
        year:'numeric', month:'long', day:'numeric'
    });
    const time = now.toLocaleTimeString();

    let itemsHtml = '';
    cart.forEach(item => {
        itemsHtml += `
        <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
            <span>${item.name} x${item.quantity}</span>
            <span>₱${item.subtotal.toFixed(2)}</span>
        </div>`;
    });

    $("#receiptBody").html(`
        <div style="text-align:center;margin-bottom:10px;">
            <strong style="font-size:16px;">🏪 Sari-Sari Store</strong><br>
            <small>${date}</small><br>
            <small>${time}</small><br>
            <small>Sale #${sale_id}</small>
        </div>
        <hr style="border-style:dashed;">
        ${itemsHtml}
        <hr style="border-style:dashed;">
        <div style="display:flex;justify-content:space-between;">
            <strong>TOTAL</strong><strong>₱${total.toFixed(2)}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <span>Cash</span><span>₱${payment.toFixed(2)}</span>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <span>Change</span><span>₱${change.toFixed(2)}</span>
        </div>
        <hr style="border-style:dashed;">
        <div style="text-align:center;font-size:12px;">
            Thank you for your purchase! 😊
        </div>
    `);

    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

/*====================================================
    NEW TRANSACTION
====================================================*/
function newTransaction(){
    cart = [];
    renderCart();
    $("#paymentInput").val('');
    $("#changeAmount").text("₱0.00");
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

</script>